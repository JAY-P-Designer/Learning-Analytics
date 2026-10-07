<?php
// This file is part of Moodle - https://moodle.org/
// Copyright 2026 Joaquim Pascoal Mulima Junior.
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Explicit migration from the author's previous component.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics\local;

defined('MOODLE_INTERNAL') || die();

/** Copy settings and logos, and preserve the complete legacy permission matrix. */
final class legacy_migration {
    /** @var string Previous component, also used by an unrelated public plugin. */
    private const SOURCE = 'local_learning_analytics';
    /** @var string Current component. */
    private const TARGET = 'local_mulima_analytics';
    /** @var string[] Capability suffixes specific to this report suite. */
    private const CAPABILITIES = [
        'view', 'viewdashboard', 'viewaccesses', 'viewcoverage', 'viewrisk',
        'viewteachers', 'viewattendance', 'exportdata', 'manageattendance',
    ];
    /** @var string[] The only file areas owned by the previous report suite. */
    private const FILE_AREAS = ['logo_pdf', 'logo_excel'];

    /**
     * Read a preview without changing the installation.
     *
     * @return array Status and counts, without configuration values or personal data.
     */
    public static function summary(): array {
        global $CFG;

        if (get_config(self::TARGET, 'legacy_migration_completed')) {
            return ['status' => 'completed', 'counts' => self::completed_counts()];
        }
        if (!self::recognises_source() || !self::capabilities_registered(self::TARGET)) {
            return ['status' => 'unavailable', 'counts' => []];
        }
        $snapshot = self::snapshot();
        [, $menulinks] = self::rewrite_menu((string)($CFG->custommenuitems ?? ''), $CFG->wwwroot);
        return ['status' => 'ready', 'counts' => [
            'settingscopied' => count($snapshot['settings']),
            'settingskept' => $snapshot['settingskept'],
            'permissions' => count($snapshot['permissions']),
            'filescopied' => count($snapshot['files']),
            'fileskept' => $snapshot['fileskept'],
            'menulinks' => $menulinks,
        ]];
    }

    /**
     * Execute a single, authenticated transfer. The source is never deleted.
     *
     * This is deliberately separate from installation: all new capabilities
     * must already be registered before their defaults can be replaced safely.
     *
     * @return array Counts saved when the transfer completed.
     */
    public static function migrate(): array {
        global $CFG, $DB;

        require_login();
        require_capability('moodle/site:config', \context_system::instance());
        require_sesskey();

        $factory = \core\lock\lock_config::get_lock_factory(self::TARGET);
        $lock = $factory->get_lock('legacy_migration', 0);
        if (!$lock) {
            throw new \moodle_exception('migration_busy', self::TARGET);
        }
        try {
            if (get_config(self::TARGET, 'legacy_migration_completed')) {
                return self::completed_counts();
            }
            if (!self::recognises_source() || !self::capabilities_registered(self::TARGET)) {
                throw new \moodle_exception('migration_unavailable', self::TARGET);
            }
            $originalmenu = (string)($CFG->custommenuitems ?? '');
            $transaction = $DB->start_delegated_transaction();
            try {
                $snapshot = self::snapshot();
                foreach ($snapshot['settings'] as $setting) {
                    set_config($setting->name, $setting->value, self::TARGET);
                }

                // Remove new installation defaults, including grants absent in
                // the source. Copy every explicit allow/prevent/prohibit and
                // context override, rather than reapplying the role-selection UI.
                foreach (self::CAPABILITIES as $suffix) {
                    $DB->delete_records('role_capabilities', ['capability' => 'local/mulima_analytics:' . $suffix]);
                }
                foreach ($snapshot['permissions'] as $permission) {
                    $capability = str_replace('local/learning_analytics:', 'local/mulima_analytics:', $permission->capability);
                    if (!assign_capability($capability, (int)$permission->permission,
                            (int)$permission->roleid, (int)$permission->contextid, true)) {
                        throw new \moodle_exception('migration_failed', self::TARGET);
                    }
                }

                $fs = get_file_storage();
                foreach ($snapshot['files'] as $file) {
                    // Create directory records first, with their recorded owner;
                    // do not let a later image copy assign all parent directories
                    // to the image uploader or to the administrator transferring it.
                    if ($file->is_directory()) {
                        $created = $fs->create_directory($file->get_contextid(), self::TARGET, $file->get_filearea(),
                            $file->get_itemid(), $file->get_filepath(), $file->get_userid());
                    } else {
                        // The Files API copies source metadata and recalculates
                        // the path hash for the new component.
                        $created = $fs->create_file_from_storedfile(['component' => self::TARGET], $file);
                    }
                    if (!$created) {
                        throw new \moodle_exception('migration_failed', self::TARGET);
                    }
                }
                [$menu, $menulinks] = self::rewrite_menu((string)($CFG->custommenuitems ?? ''), $CFG->wwwroot);
                if ($menulinks) {
                    set_config('custommenuitems', $menu);
                }
                $counts = [
                    'settingscopied' => count($snapshot['settings']),
                    'settingskept' => $snapshot['settingskept'],
                    'permissions' => count($snapshot['permissions']),
                    'filescopied' => count($snapshot['files']),
                    'fileskept' => $snapshot['fileskept'],
                    'menulinks' => $menulinks,
                ];
                set_config('legacy_migration_result', json_encode($counts), self::TARGET);
                set_config('legacy_migration_completed', time(), self::TARGET);
                $transaction->allow_commit();
            } catch (\Throwable $error) {
                // Configuration/access caches are not part of the transaction.
                // Invalidate them even when Moodle rolls back the database.
                try {
                    $transaction->rollback($error);
                } finally {
                    $CFG->custommenuitems = $originalmenu;
                    \cache_helper::invalidate_by_definition('core', 'config');
                    accesslib_clear_all_caches(false);
                }
                throw $error;
            }
            accesslib_clear_all_caches(false);
            return $counts;
        } finally {
            $lock->release();
        }
    }

    /**
     * Rewrite only previous component URLs in this site's custom menu.
     *
     * @param string $menu Existing menu, including labels and language suffixes.
     * @param string $wwwroot Site root, which can include a subdirectory.
     * @return array The menu and number of changed links.
     */
    public static function rewrite_menu(string $menu, string $wwwroot): array {
        $parts = preg_split('/(\r\n|\r|\n)/', $menu, -1, PREG_SPLIT_DELIM_CAPTURE);
        $count = 0;
        $bases = ['/local/learning_analytics', rtrim($wwwroot, '/') . '/local/learning_analytics'];
        foreach ($parts as &$line) {
            $fields = explode('|', $line);
            if (count($fields) < 2) {
                continue;
            }
            preg_match('/^(\s*)(.*?)(\s*)$/', $fields[1], $urlparts);
            $url = $urlparts[2];
            foreach ($bases as $base) {
                if (preg_match('~^' . preg_quote($base, '~') . '(?=/|[?#]|$)~', $url)) {
                    $replacement = substr($base, 0, -strlen('learning_analytics')) . 'mulima_analytics';
                    $fields[1] = $urlparts[1] . $replacement . substr($url, strlen($base)) . $urlparts[3];
                    $line = implode('|', $fields);
                    $count++;
                    break;
                }
            }
        }
        unset($line);
        return [implode('', $parts), $count];
    }

    /** @return bool True only for the author's recent report-suite installation. */
    private static function recognises_source(): bool {
        $version = get_config(self::SOURCE, 'version');
        return is_numeric($version) && (int)$version >= 2026091100 && self::capabilities_registered(self::SOURCE);
    }

    /**
     * Verify the suite's complete capability signature, not just its component.
     *
     * @param string $component Component to inspect.
     * @return bool
     */
    private static function capabilities_registered(string $component): bool {
        global $DB;
        $records = $DB->get_records('capabilities', ['component' => $component], '', 'name,contextlevel,captype');
        $prefix = str_replace('local_', 'local/', $component) . ':';
        foreach (self::CAPABILITIES as $suffix) {
            $name = $prefix . $suffix;
            $record = $records[$name] ?? null;
            $type = $suffix === 'manageattendance' ? 'write' : 'read';
            if (!$record || (int)$record->contextlevel !== CONTEXT_SYSTEM || $record->captype !== $type) {
                return false;
            }
        }
        return true;
    }

    /** @return array Saved completion counts; rerunning never replaces new edits. */
    private static function completed_counts(): array {
        $counts = json_decode((string)get_config(self::TARGET, 'legacy_migration_result'), true);
        return is_array($counts) ? $counts : [];
    }

    /** @return array Values to import and counts of existing destination values. */
    private static function snapshot(): array {
        global $DB;
        $source = $DB->get_records('config_plugins', ['plugin' => self::SOURCE], '', 'name,value');
        $target = $DB->get_records('config_plugins', ['plugin' => self::TARGET], '', 'name,value');
        $settings = [];
        $settingskept = 0;
        foreach ($source as $name => $setting) {
            if ($name === 'version' || strpos($name, 'legacy_migration_') === 0) {
                continue;
            }
            if (isset($target[$name])) {
                $settingskept++;
            } else {
                $settings[] = $setting;
            }
        }
        $capabilities = array_map(function($suffix) {
            return 'local/learning_analytics:' . $suffix;
        }, self::CAPABILITIES);
        $permissions = $DB->get_records_list('role_capabilities', 'capability', $capabilities, 'id');
        $files = [];
        $fileskept = 0;
        $fs = get_file_storage();
        $context = \context_system::instance();
        foreach (self::FILE_AREAS as $area) {
            $existing = $fs->get_area_files($context->id, self::TARGET, $area, false, 'id', false);
            $candidates = $fs->get_area_files($context->id, self::SOURCE, $area, false, 'id', true);
            foreach ($candidates as $file) {
                // Retain an already configured destination logo area intact.
                if ($existing || $fs->get_file($context->id, self::TARGET, $area, $file->get_itemid(),
                        $file->get_filepath(), $file->get_filename())) {
                    $fileskept++;
                } else {
                    $files[] = $file;
                }
            }
        }
        usort($files, function($left, $right) {
            if ($left->is_directory() !== $right->is_directory()) {
                return $left->is_directory() ? -1 : 1;
            }
            if ($left->is_directory()) {
                // Parents before children, before any image can create them.
                return strcmp($left->get_filepath(), $right->get_filepath());
            }
            // Preserve which uploaded logo was most recent when an area holds
            // more than one image and the report selects its latest file ID.
            return $left->get_id() <=> $right->get_id();
        });
        return compact('settings', 'settingskept', 'permissions', 'files', 'fileskept');
    }
}
