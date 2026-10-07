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
 * Learning Analytics risk report.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics\local;

defined('MOODLE_INTERNAL') || die();

/** Risk data shared by the page and export; category filters select the enrolled population. */
final class risk_report {
    public static function get(int $period, int $category = 0, int $course = 0,
            int $days = 7, string $scope = 'platform', ?int $now = null, bool $recursive = true): array {
        global $DB;
        $now = $now ?? time();
        $days = max(1, min(365, $days));
        $scope = $scope === 'courses' ? 'courses' : 'platform';
        $courseids = access_filters::course_ids($period, $category, $course, $recursive);
        $result = ['students' => [], 'stats' => ['critical' => 0, 'alert' => 0, 'warn' => 0]];
        if (!$courseids) {
            return $result;
        }
        [$csql, $cparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'co');
        [$rsql, $rparams] = $DB->get_in_or_equal(roles::students() ?: [0], SQL_PARAMS_NAMED, 'sr');
        $rows = $DB->get_records_sql(
            "SELECT DISTINCT " . $DB->sql_concat('u.id', "'_'", 'e.courseid') . " AS rk,
                    u.id AS userid, u.firstname, u.lastname, u.email, u.phone1, u.phone2, u.lastaccess,
                    e.courseid, c.fullname AS coursename, cc.name AS catname
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
               JOIN {course} c ON c.id = e.courseid
               JOIN {course_categories} cc ON cc.id = c.category
               JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = 50
               JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid $rsql
              WHERE e.courseid $csql AND e.status = 0 AND ue.status = 0",
            array_merge($cparams, $rparams));
        if (!$rows) {
            return $result;
        }
        $lastlog = $lastcourse = [];
        if ($scope === 'courses') {
            [$lsql, $lparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'lg');
            [$usql, $uparams] = $DB->get_in_or_equal(array_unique(array_column($rows, 'userid')),
                SQL_PARAMS_NAMED, 'lu');
            foreach ($DB->get_records_sql(
                "SELECT " . $DB->sql_concat('userid', "'_'", 'courseid') . " AS rk, MAX(timecreated) AS ts
                   FROM {logstore_standard_log}
                  WHERE courseid $lsql AND userid $usql GROUP BY userid, courseid",
                array_merge($lparams, $uparams)) as $row) {
                $lastlog[$row->rk] = (int)$row->ts;
            }
            [$asql, $aparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'la');
            foreach ($DB->get_records_sql(
                "SELECT " . $DB->sql_concat('userid', "'_'", 'courseid') . " AS rk, timeaccess AS ts
                   FROM {user_lastaccess} WHERE courseid $asql", $aparams) as $row) {
                $lastcourse[$row->rk] = (int)$row->ts;
            }
        } else {
            // A student may be active elsewhere on Moodle. Use site last access,
            // not the last log entry in the selected courses, and count them once.
            $unique = [];
            foreach ($rows as $row) {
                if (!isset($unique[$row->userid])) {
                    $unique[$row->userid] = clone $row;
                    $unique[$row->userid]->categories = [];
                }
                $unique[$row->userid]->categories[$row->catname] = $row->catname;
            }
            foreach ($unique as $row) {
                $row->coursename = get_string('scope_platform', 'local_mulima_analytics');
                $row->catname = implode(', ', array_values($row->categories));
            }
            $rows = $unique;
        }
        $cutoff = $now - $days * DAYSECS;
        foreach ($rows as $row) {
            $last = $scope === 'platform' ? (int)$row->lastaccess :
                max($lastlog[$row->rk] ?? 0, $lastcourse[$row->rk] ?? 0);
            if ($last && $last >= $cutoff) {
                continue;
            }
            $elapsed = $last ? (int)floor(($now - $last) / DAYSECS) : 9999;
            $level = $elapsed > 30 ? 'critical' : ($elapsed > 15 ? 'alert' : 'warn');
            $result['stats'][$level]++;
            $phone = trim((string)$row->phone1) ?: trim((string)$row->phone2);
            $result['students'][] = [
                'userid' => (int)$row->userid,
                'fullname' => trim($row->firstname . ' ' . $row->lastname),
                'email' => $row->email, 'phone' => $phone ?: null,
                'coursename' => $row->coursename, 'catname' => $row->catname,
                'last_access' => $last ? userdate($last, '%d/%m/%Y %H:%M') : null,
                'days_since' => $elapsed,
            ];
        }
        usort($result['students'], function($a, $b) { return $b['days_since'] <=> $a['days_since']; });
        return $result;
    }
}
