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
 * Learning Analytics regression checks: course scope test.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics;

defined('MOODLE_INTERNAL') || die();

/** Tests category-tree expansion used by all report filters. */
final class course_scope_test extends \advanced_testcase {
    public function test_scope_includes_root_descendants_and_courses(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $root = $generator->create_category();
        $child = $generator->create_category(['parent' => $root->id]);
        $course1 = $generator->create_course(['category' => $root->id]);
        $course2 = $generator->create_course(['category' => $child->id]);

        $categoryids = \local_mulima_analytics\local\course_scope::category_ids($root->id);
        $courseids = \local_mulima_analytics\local\course_scope::course_ids($root->id);

        $this->assertContains((int)$root->id, $categoryids);
        $this->assertContains((int)$child->id, $categoryids);
        $this->assertEqualsCanonicalizing([(int)$course1->id, (int)$course2->id], $courseids);
    }

    public function test_invalid_category_returns_empty_scope(): void {
        $this->resetAfterTest();
        $this->assertSame([], \local_mulima_analytics\local\course_scope::category_ids(999999));
        $this->assertSame([], \local_mulima_analytics\local\course_scope::course_ids(999999));
    }
}
