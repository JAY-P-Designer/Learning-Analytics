<?php
// This file is part of Moodle - https://moodle.org/
// Copyright 2026 Joaquim Pascoal Mulima Junior.
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Migration integration checks using Moodle's real database and Files API.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics;

use local_mulima_analytics\local\legacy_migration;

defined('MOODLE_INTERNAL') || die();

/** @covers \local_mulima_analytics\local\legacy_migration */
final class legacy_migration_test extends \advanced_testcase {
    /**
     * Prepare the previous author's capability signature in the isolated test DB.
     */
    private function prepare_source(): void {
        global $DB;
        set_config('version', 2026100205, 'local_learning_analytics');
        foreach (['view', 'viewdashboard', 'viewaccesses', 'viewcoverage', 'viewrisk',
                'viewteachers', 'viewattendance', 'exportdata', 'manageattendance'] as $suffix) {
            $record = (object)[
                'name' => 'local/learning_analytics:' . $suffix,
                'component' => 'local_learning_analytics',
                'captype' => $suffix === 'manageattendance' ? 'write' : 'read',
                'contextlevel' => CONTEXT_SYSTEM,
                'riskbitmask' => 0,
            ];
            $existing = $DB->get_record('capabilities', ['name' => $record->name]);
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('capabilities', $record);
            } else {
                $DB->insert_record('capabilities', $record);
            }
            $DB->delete_records('role_capabilities', ['capability' => $record->name]);
        }
    }

    /** Verify ownership, path hashes, permissions, zero values and safe retries. */
    public function test_transfer_with_real_file_storage(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->prepare_source();
        $context = \context_system::instance();
        $uploader = $this->getDataGenerator()->create_user();
        $roleid = create_role('Migration role', 'migrationrole', '');
        assign_capability('local/learning_analytics:viewteachers', CAP_ALLOW, $roleid, $context->id, true);
        assign_capability('local/learning_analytics:exportdata', CAP_PROHIBIT, $roleid, $context->id, true);
        assign_capability('local/mulima_analytics:exportdata', CAP_ALLOW, $roleid, $context->id, true);
        set_config('weight_forums', 0, 'local_learning_analytics');
        set_config('footer_note', '', 'local_learning_analytics');
        set_config('custommenuitems', 'Reports|/local/learning_analytics/index.php');
        $fs = get_file_storage();
        $source = $fs->create_file_from_string([
            'component' => 'local_learning_analytics', 'contextid' => $context->id,
            'filearea' => 'logo_pdf', 'itemid' => 0, 'filepath' => '/logos/',
            'filename' => 'test.png', 'userid' => $uploader->id,
        ], 'DISPOSABLE LOGO CONTENT');
        $summary = legacy_migration::summary();
        $this->assertSame('ready', $summary['status']);
        $post = $_POST;
        try {
            $_POST['sesskey'] = sesskey();
            $counts = legacy_migration::migrate();
            $this->assertSame($summary['counts'], $counts);
            $copy = $fs->get_file($context->id, 'local_mulima_analytics', 'logo_pdf', 0, '/logos/', 'test.png');
            $this->assertNotFalse($copy);
            $this->assertSame($source->get_content(), $copy->get_content());
            $this->assertEquals($uploader->id, $copy->get_userid());
            foreach (['/', '/logos/'] as $path) {
                $sourcedir = $fs->get_file($context->id, 'local_learning_analytics', 'logo_pdf', 0, $path, '.');
                $targetdir = $fs->get_file($context->id, 'local_mulima_analytics', 'logo_pdf', 0, $path, '.');
                $this->assertNotFalse($targetdir);
                $this->assertEquals($sourcedir->get_userid(), $targetdir->get_userid());
            }
            $this->assertNotSame($source->get_pathnamehash(), $copy->get_pathnamehash());
            $this->assertTrue($source->compare_to_string('DISPOSABLE LOGO CONTENT'));
            $this->assertEquals(CAP_PROHIBIT, $DB->get_field('role_capabilities', 'permission', [
                'roleid' => $roleid, 'contextid' => $context->id, 'capability' => 'local/mulima_analytics:exportdata',
            ]));
            $this->assertEquals(2, $DB->count_records_select('role_capabilities', 'capability LIKE :prefix',
                ['prefix' => 'local/mulima_analytics:%']));
            $this->assertSame('0', get_config('local_mulima_analytics', 'weight_forums'));
            $this->assertSame('', get_config('local_mulima_analytics', 'footer_note'));
            $this->assertSame('Reports|/local/mulima_analytics/index.php', $CFG->custommenuitems);
            set_config('footer_note', 'New edit', 'local_mulima_analytics');
            $this->assertSame($counts, legacy_migration::migrate());
            $this->assertSame('New edit', get_config('local_mulima_analytics', 'footer_note'));
            $this->assertSame('completed', legacy_migration::summary()['status']);
        } finally {
            $_POST = $post;
        }
    }

    /** A component name alone must never identify the author's previous suite. */
    public function test_unrelated_component_is_not_imported(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('version', 2026100299, 'local_learning_analytics');
        set_config('institution_name', 'Unrelated source', 'local_learning_analytics');
        $DB->delete_records('capabilities', ['name' => 'local/learning_analytics:manageattendance']);
        $this->assertSame('unavailable', legacy_migration::summary()['status']);
        $this->assertFalse(get_config('local_mulima_analytics', 'institution_name'));
    }
}
