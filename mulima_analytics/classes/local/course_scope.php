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
 * Learning Analytics course scope.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics\local;

defined('MOODLE_INTERNAL') || die();

/** Resolves category trees and their courses consistently. */
final class course_scope {
    /** @return int[] */
    public static function category_ids(int $rootid): array {
        global $DB;

        if ($rootid <= 0 || !$DB->record_exists('course_categories', ['id' => $rootid])) {
            return [];
        }
        $path = '%/' . $DB->sql_like_escape((string)$rootid) . '/%';
        $children = $DB->get_fieldset_select(
            'course_categories',
            'id',
            $DB->sql_like('path', ':path'),
            ['path' => $path]
        );
        return array_values(array_unique(array_merge([$rootid], array_map('intval', $children))));
    }

    /** @return int[] */
    public static function course_ids(int $rootid): array {
        global $DB;

        $categoryids = self::category_ids($rootid);
        if (!$categoryids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'scope');
        $params['siteid'] = SITEID;
        return array_map('intval', $DB->get_fieldset_select(
            'course',
            'id',
            "category $insql AND id <> :siteid",
            $params
        ));
    }
}
