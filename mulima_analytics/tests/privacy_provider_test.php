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
 * Moodle integration tests for logo privacy and data isolation.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_mulima_analytics\privacy\provider;

defined('MOODLE_INTERNAL') || die();

/** Tests the real Moodle Files API, contexts and Privacy API export writer. */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /** @var string The component under test. */
    private const COMPONENT = 'local_mulima_analytics';

    /**
     * Create attributed, unattributed and unrelated files.
     *
     * @return array Users, contexts and files.
     */
    private function fixture(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $empty = $this->getDataGenerator()->create_user();
        $system = \context_system::instance();
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $files = [];
        foreach (['logo_pdf', 'logo_excel'] as $area) {
            $files['a_' . $area] = $this->logo($a->id, $system, self::COMPONENT, $area, 'a.png');
            $files['b_' . $area] = $this->logo($b->id, $system, self::COMPONENT, $area, 'b.png');
        }
        $files['legacy'] = $this->logo(0, $system, self::COMPONENT, 'logo_pdf', 'legacy.png');
        $files['other_component'] = $this->logo($a->id, $system, 'local_listas_exame', 'logo_pdf', 'old.png');
        $files['other_area'] = $this->logo($a->id, $system, self::COMPONENT, 'unrelated', 'other.png');
        $files['other_context'] = $this->logo($a->id, $coursecontext, self::COMPONENT, 'logo_pdf', 'course.png');
        return compact('a', 'b', 'empty', 'system', 'coursecontext', 'files');
    }

    /**
     * Store a fixture through Moodle's Files API.
     *
     * @param int $userid File owner, or zero for a legacy unattributed file.
     * @param \context $context File context.
     * @param string $component File component.
     * @param string $area File area.
     * @param string $name File name.
     * @return \stored_file
     */
    private function logo(int $userid, \context $context, string $component, string $area, string $name): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => $component,
            'filearea' => $area,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $name,
            'userid' => $userid,
        ], 'Fictional logo content ' . $name);
    }

    /** Metadata links have valid translated identifiers. */
    public function test_metadata(): void {
        $metadata = provider::get_metadata(new collection(self::COMPONENT))->get_collection();
        $names = [];
        foreach ($metadata as $item) {
            $names[] = $item->get_name();
            $keys = array_merge([$item->get_summary()], array_values($item->get_privacy_fields()));
            foreach ($keys as $key) {
                $this->assertTrue(get_string_manager()->string_exists($key, self::COMPONENT));
            }
        }
        $this->assertEqualsCanonicalizing(['core_files', 'logstore'], $names);
    }

    /** Only owners in the system-context logo areas are discoverable. */
    public function test_discovery(): void {
        $f = $this->fixture();
        $this->assertEquals([$f['system']->id], provider::get_contexts_for_userid($f['a']->id)->get_contextids());
        $this->assertEmpty(provider::get_contexts_for_userid($f['empty']->id)->get_contextids());
        $this->assertEmpty(provider::get_contexts_for_userid(0)->get_contextids());
        $list = new userlist($f['system'], self::COMPONENT);
        provider::get_users_in_context($list);
        $this->assertEqualsCanonicalizing([$f['a']->id, $f['b']->id], $list->get_userids());
        $other = new userlist($f['coursecontext'], self::COMPONENT);
        provider::get_users_in_context($other);
        $this->assertEmpty($other->get_userids());
    }

    /** Export includes both owned areas and never includes another user's files. */
    public function test_export_is_scoped_to_owner_and_approved_context(): void {
        $f = $this->fixture();
        provider::export_user_data(new approved_contextlist($f['a'], self::COMPONENT, [$f['coursecontext']->id]));
        $this->assertFalse(writer::with_context($f['system'])->has_any_data());
        provider::export_user_data(new approved_contextlist($f['a'], self::COMPONENT, [$f['system']->id]));
        $path = [get_string('pluginname', self::COMPONENT), get_string('privacy:export:logos', self::COMPONENT)];
        $export = writer::with_context($f['system']);
        $data = $export->get_data($path);
        $this->assertNotEmpty($data->files);
        $actualids = [];
        foreach ($data->files as $metadata) {
            $this->assertEquals($f['a']->id, $metadata->userid);
            if (!$metadata->is_directory) {
                $actualids[] = $metadata->id;
                $files = $export->get_files(array_merge($path, [$metadata->filearea, (string)$metadata->id]));
                $this->assertCount(1, $files);
                $this->assertEquals($metadata->id, reset($files)->get_id());
            }
        }
        $this->assertEqualsCanonicalizing([
            $f['files']['a_logo_pdf']->get_id(), $f['files']['a_logo_excel']->get_id(),
        ], $actualids);
    }

    /** Single-user deletion also removes owned directory metadata, without widening scope. */
    public function test_delete_one_user_preserves_other_records(): void {
        global $DB;
        $f = $this->fixture();
        provider::delete_data_for_user(new approved_contextlist($f['a'], self::COMPONENT, []));
        $this->assertNotFalse(get_file_storage()->get_file_by_id($f['files']['a_logo_pdf']->get_id()));
        provider::delete_data_for_user(new approved_contextlist($f['a'], self::COMPONENT, [$f['system']->id]));
        foreach ($f['files'] as $name => $file) {
            $remaining = get_file_storage()->get_file_by_id($file->get_id());
            if (strpos($name, 'a_logo_') === 0) {
                $this->assertFalse($remaining, $name);
            } else {
                $this->assertNotFalse($remaining, $name);
            }
        }
        $this->assertEmpty(provider::get_contexts_for_userid($f['a']->id)->get_contextids());
        $this->assertEquals($f['a']->firstname, $DB->get_field('user', 'firstname', ['id' => $f['a']->id]));
    }

    /** Bulk deletion accepts only approved positive owners in the correct context. */
    public function test_delete_multiple_users(): void {
        $f = $this->fixture();
        foreach ([
            new approved_userlist($f['system'], self::COMPONENT, []),
            new approved_userlist($f['system'], self::COMPONENT, [0]),
            new approved_userlist($f['coursecontext'], self::COMPONENT, [$f['a']->id]),
        ] as $list) {
            provider::delete_data_for_users($list);
        }
        $this->assertNotFalse(get_file_storage()->get_file_by_id($f['files']['a_logo_pdf']->get_id()));
        provider::delete_data_for_users(new approved_userlist($f['system'], self::COMPONENT, [$f['b']->id]));
        $this->assertFalse(get_file_storage()->get_file_by_id($f['files']['b_logo_pdf']->get_id()));
        $this->assertNotFalse(get_file_storage()->get_file_by_id($f['files']['a_logo_pdf']->get_id()));
        provider::delete_data_for_users(new approved_userlist($f['system'], self::COMPONENT, [$f['a']->id, $f['b']->id]));
        $this->assertFalse(get_file_storage()->get_file_by_id($f['files']['a_logo_excel']->get_id()));
        foreach (['legacy', 'other_component', 'other_area', 'other_context'] as $name) {
            $this->assertNotFalse(get_file_storage()->get_file_by_id($f['files'][$name]->get_id()), $name);
        }
    }

    /** A whole-context request affects all plugin logos but nothing else. */
    public function test_delete_all_in_context(): void {
        $f = $this->fixture();
        provider::delete_data_for_all_users_in_context($f['coursecontext']);
        $this->assertNotFalse(get_file_storage()->get_file_by_id($f['files']['other_context']->get_id()));
        provider::delete_data_for_all_users_in_context($f['system']);
        foreach ($f['files'] as $name => $file) {
            $remaining = get_file_storage()->get_file_by_id($file->get_id());
            if (strpos($name, 'other_') === 0) {
                $this->assertNotFalse($remaining, $name);
            } else {
                $this->assertFalse($remaining, $name);
            }
        }
    }

    /** Directory-only ownership remains discoverable and can be removed. */
    public function test_directory_metadata_is_not_left_behind(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $system = \context_system::instance();
        $file = $this->logo($user->id, $system, self::COMPONENT, 'logo_pdf', 'removed.png');
        $file->delete();
        $this->assertEquals([$system->id], provider::get_contexts_for_userid($user->id)->get_contextids());
        provider::delete_data_for_user(new approved_contextlist($user, self::COMPONENT, [$system->id]));
        $this->assertEmpty(provider::get_contexts_for_userid($user->id)->get_contextids());
    }
}
