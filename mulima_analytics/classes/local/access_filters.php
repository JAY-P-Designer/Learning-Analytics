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
 * Learning Analytics access filters.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// This file is part of Moodle - http://moodle.org/
// Moodle is distributed under the GNU GPL v3 or later.

namespace local_mulima_analytics\local;

defined('MOODLE_INTERNAL') || die();

/** Validated category scope shared by report pickers, results and exports. */
final class access_filters {
    /** Reject stale category IDs from another period instead of widening the report. */
    public static function root(int $period, int $category = 0): \stdClass {
        global $DB;
        $periodrecord = $DB->get_record('course_categories', ['id' => $period], 'id,parent,path,name');
        if (!$periodrecord || (int)$periodrecord->parent !== 0) {
            throw new \invalid_parameter_exception('Invalid execution period.');
        }
        if (!$category || $category === $period) {
            return $periodrecord;
        }
        $selected = $DB->get_record('course_categories', ['id' => $category], 'id,parent,path,name');
        if (!$selected || strpos($selected->path, $periodrecord->path . '/') !== 0) {
            throw new \invalid_parameter_exception('Category does not belong to this execution period.');
        }
        return $selected;
    }

    /** Selected category; descendants are included only when requested by the caller. */
    public static function category_ids(int $period, int $category = 0, bool $recursive = true): array {
        global $DB;
        $root = self::root($period, $category);
        if (!$recursive) {
            return [(int)$root->id];
        }
        $descendants = $DB->get_fieldset_select('course_categories', 'id', $DB->sql_like('path', ':path'),
            ['path' => $root->path . '/%']);
        return array_merge([(int)$root->id], array_map('intval', $descendants));
    }

    /** Same course selection for autocomplete, panoramic report and XLSX. */
    public static function courses(int $period, int $category = 0, int $course = 0, bool $recursive = true): array {
        global $DB;
        $ids = self::category_ids($period, $category, $recursive);
        [$sql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'ac');
        $params['siteid'] = SITEID;
        $courses = $DB->get_records_select('course', "category $sql AND id <> :siteid", $params,
            'fullname', 'id,fullname,shortname,category');
        if ($course) {
            if (!isset($courses[$course])) {
                throw new \invalid_parameter_exception('Course does not belong to the selected category.');
            }
            return [$course => $courses[$course]];
        }
        return $courses;
    }

    /** Course IDs optionally narrowed to one validated discipline. */
    public static function course_ids(int $period, int $category = 0, int $course = 0, bool $recursive = true): array {
        return array_map('intval', array_keys(self::courses($period, $category, $course, $recursive)));
    }

    /** Direct children with subtree counts, without a database query per category. */
    public static function children(int $period, int $parent): array {
        global $DB;
        $root = self::root($period, $parent);
        $path = $root->path . '/%';
        $descendants = $DB->get_records_select('course_categories', $DB->sql_like('path', ':path'),
            ['path' => $path], 'sortorder', 'id,parent,path,name');
        $children = [];
        $haschildren = [];
        foreach ($descendants as $category) {
            $haschildren[(int)$category->parent] = true;
            if ((int)$category->parent === (int)$root->id) {
                $children[(int)$category->id] = [
                    'id' => (int)$category->id, 'name' => $category->name, 'count' => 0,
                ];
            }
        }
        if (!$children) {
            return [];
        }
        $where = $DB->sql_like('cc.path', ':path');
        $counts = $DB->get_records_sql(
            "SELECT c.category, COUNT(c.id) AS coursecount
               FROM {course} c JOIN {course_categories} cc ON cc.id = c.category
              WHERE $where AND c.id <> :siteid
           GROUP BY c.category", ['path' => $path, 'siteid' => SITEID]);
        foreach ($counts as $count) {
            if (!isset($descendants[$count->category])) {
                continue; // The category may have been created during these two reads.
            }
            // The first path segment below the parent identifies its direct child.
            $relative = substr($descendants[$count->category]->path, strlen($root->path) + 1);
            $child = (int)explode('/', $relative)[0];
            if (isset($children[$child])) {
                $children[$child]['count'] += (int)$count->coursecount;
            }
        }
        foreach ($children as &$child) {
            $child['haschildren'] = isset($haschildren[$child['id']]);
        }
        unset($child);
        return array_values($children);
    }
}
