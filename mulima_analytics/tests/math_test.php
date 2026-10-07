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
 * Learning Analytics regression checks: math test.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics;

defined('MOODLE_INTERNAL') || die();

/** Tests for defensive report calculations. */
final class math_test extends \advanced_testcase {
    public function test_percentage_returns_zero_for_zero_total(): void {
        $this->assertSame(0.0, \local_mulima_analytics\local\math::percentage(0, 0));
        $this->assertSame(0.0, \local_mulima_analytics\local\math::percentage(5, 0));
    }

    public function test_percentage_calculates_valid_value(): void {
        $this->assertSame(50.0, \local_mulima_analytics\local\math::percentage(2, 4));
        $this->assertSame(33.33, \local_mulima_analytics\local\math::percentage(1, 3, 2));
    }
}
