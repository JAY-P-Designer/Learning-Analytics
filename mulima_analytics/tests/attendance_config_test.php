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
 * Learning Analytics regression checks: attendance config test.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics;

defined('MOODLE_INTERNAL') || die();

/** Tests for attendance configuration migration behaviour. */
final class attendance_config_test extends \advanced_testcase {
    public function test_current_plugin_value_has_priority(): void {
        $this->resetAfterTest();
        set_config('institution_short', 'LEGACY', 'local_listas_exame');
        set_config('institution_short', 'DIE', 'local_mulima_analytics');

        $this->assertSame('DIE', \local_mulima_analytics\local\attendance_config::get('institution_short'));
    }

    public function test_legacy_value_is_used_as_fallback(): void {
        $this->resetAfterTest();
        unset_config('institution_short', 'local_mulima_analytics');
        set_config('institution_short', 'LEGACY', 'local_listas_exame');

        $this->assertSame('LEGACY', \local_mulima_analytics\local\attendance_config::get('institution_short'));
    }

    public function test_default_is_returned_when_value_is_missing(): void {
        $this->resetAfterTest();
        unset_config('unknown_setting', 'local_mulima_analytics');
        unset_config('unknown_setting', 'local_listas_exame');

        $this->assertSame('fallback', \local_mulima_analytics\local\attendance_config::get('unknown_setting', 'fallback'));
    }
}
