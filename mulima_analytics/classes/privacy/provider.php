<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Privacy API implementation for Learning Analytics.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/** Handles the plugin's uploaded logos; Moodle owns the source report data and logs. */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /** @var string The component that owns the files. */
    private const COMPONENT = 'local_mulima_analytics';

    /** @var string[] Only these system-context file areas belong to this provider. */
    private const FILE_AREAS = ['logo_pdf', 'logo_excel'];

    /**
     * Describe uploaded files and export audit events.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_subsystem_link('core_files', [
            'userid' => 'privacy:metadata:files:userid',
            'filename' => 'privacy:metadata:files:filename',
            'content' => 'privacy:metadata:files:content',
            'timecreated' => 'privacy:metadata:files:timecreated',
            'timemodified' => 'privacy:metadata:files:timemodified',
        ], 'privacy:metadata:files');
        $collection->add_plugintype_link('logstore', [
            'userid' => 'privacy:metadata:logs:userid',
            'timecreated' => 'privacy:metadata:logs:timecreated',
            'ip' => 'privacy:metadata:logs:ip',
            'other' => 'privacy:metadata:logs:other',
        ], 'privacy:metadata:logs');
        return $collection;
    }

    /**
     * Find the system context when the user owns logo files or directory metadata.
     *
     * @param int $userid User ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        if ($userid <= 0) {
            return $contextlist;
        }
        [$where, $params] = self::file_selection([$userid]);
        $contextlist->add_from_sql("SELECT DISTINCT contextid FROM {files} WHERE $where", $params);
        return $contextlist;
    }

    /**
     * Find the owners of this plugin's logo files in an approved context.
     *
     * @param userlist $userlist Context and users being collected.
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system || $userlist->get_component() !== self::COMPONENT) {
            return;
        }
        [$where, $params] = self::file_selection();
        $userlist->add_from_sql('userid', "SELECT DISTINCT userid FROM {files} WHERE $where AND userid > 0", $params);
    }

    /**
     * Export only files and file metadata belonging to the requesting user.
     *
     * @param approved_contextlist $contextlist User and approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        $context = \context_system::instance();
        if ($userid <= 0 || $contextlist->get_component() !== self::COMPONENT ||
                !in_array($context->id, $contextlist->get_contextids())) {
            return;
        }

        [$where, $params] = self::file_selection([$userid]);
        $records = $DB->get_recordset_select('files', $where, $params, 'id');
        $fs = get_file_storage();
        $metadata = [];
        $path = [get_string('pluginname', self::COMPONENT), get_string('privacy:export:logos', self::COMPONENT)];
        try {
            foreach ($records as $record) {
                $file = $fs->get_file_by_id($record->id);
                if (!$file) {
                    continue;
                }
                $metadata[] = (object)[
                    'id' => $file->get_id(),
                    'userid' => $file->get_userid(),
                    'filearea' => $file->get_filearea(),
                    'itemid' => $file->get_itemid(),
                    'filepath' => $file->get_filepath(),
                    'filename' => $file->get_filename(),
                    'is_directory' => $file->is_directory(),
                    'filesize' => $file->get_filesize(),
                    'mimetype' => $file->get_mimetype(),
                    'source' => $file->get_source(),
                    'author' => $file->get_author(),
                    'license' => $file->get_license(),
                    'timecreated' => $file->get_timecreated(),
                    'timemodified' => $file->get_timemodified(),
                ];
                if (!$file->is_directory()) {
                    $filepath = array_merge($path, [$file->get_filearea(), (string)$file->get_id()]);
                    writer::with_context($context)->export_file($filepath, $file);
                }
            }
        } finally {
            $records->close();
        }
        if ($metadata) {
            writer::with_context($context)->export_data($path, (object)['files' => $metadata]);
        }
    }

    /**
     * Delete all plugin logos when the entire system context is approved.
     *
     * @param \context $context Approved context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!$context instanceof \context_system) {
            return;
        }
        $fs = get_file_storage();
        foreach (self::FILE_AREAS as $filearea) {
            $fs->delete_area_files($context->id, self::COMPONENT, $filearea);
        }
    }

    /**
     * Delete one user's logo uploads only in the approved system context.
     *
     * @param approved_contextlist $contextlist User and approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int)$contextlist->get_user()->id;
        if ($userid <= 0 || $contextlist->get_component() !== self::COMPONENT ||
                !in_array(\context_system::instance()->id, $contextlist->get_contextids())) {
            return;
        }
        self::delete_owned_files([$userid]);
    }

    /**
     * Delete the approved users' logos, leaving other users' files intact.
     *
     * @param approved_userlist $userlist Approved context and user IDs.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system || $userlist->get_component() !== self::COMPONENT) {
            return;
        }
        $userids = array_values(array_filter(array_map('intval', $userlist->get_userids()), function(int $userid): bool {
            return $userid > 0;
        }));
        if ($userids) {
            self::delete_owned_files($userids);
        }
    }

    /**
     * Select logo records without touching core course data or the legacy plugin.
     *
     * Directory records are included because their uploader ID is personal data too.
     * Unattributed legacy files are never assigned to an arbitrary user.
     *
     * @param int[] $userids Restrict to these owners, or all owners when empty.
     * @return array SQL condition and named parameters.
     */
    private static function file_selection(array $userids = []): array {
        global $DB;

        [$areasql, $params] = $DB->get_in_or_equal(self::FILE_AREAS, SQL_PARAMS_NAMED, 'logoarea');
        $where = "contextid = :logocontext AND component = :logocomponent AND filearea $areasql";
        $params['logocontext'] = \context_system::instance()->id;
        $params['logocomponent'] = self::COMPONENT;
        if ($userids) {
            [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'logoowner');
            $where .= " AND userid $usersql";
            $params += $userparams;
        }
        return [$where, $params];
    }

    /**
     * Delete via the Files API so Moodle can manage shared content correctly.
     *
     * @param int[] $userids Positive, approved user IDs.
     */
    private static function delete_owned_files(array $userids): void {
        global $DB;

        [$where, $params] = self::file_selection($userids);
        $records = $DB->get_recordset_select('files', $where, $params, 'id');
        $fs = get_file_storage();
        try {
            foreach ($records as $record) {
                $file = $fs->get_file_by_id($record->id);
                if ($file) {
                    $file->delete();
                }
            }
        } finally {
            $records->close();
        }
    }
}
