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
 * Learning Analytics assignment progress.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics\local;

defined('MOODLE_INTERNAL') || die();

/** Current assignment backlog and the recorded grader's contribution. */
final class assignment_progress {
    /**
     * Inventory/backlog cover all current submissions. Only personal grading
     * activity is limited by $since/$now, so old pending work cannot disappear.
     */
    public static function get(array $courseids, int $since, int $now): array {
        global $DB;
        $result = ['courses' => [], 'graders' => []];
        if (!$courseids) { return $result; }
        [$csql, $cp] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'ac');
        $inventory = $DB->get_records_sql(
            "SELECT a.id, a.course, a.name, a.teamsubmission, a.teamsubmissiongroupingid,
                    a.nosubmissions, cm.id AS cmid
               FROM {assign} a
               JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = a.course
               JOIN {modules} md ON md.id = cm.module AND md.name = 'assign'
              WHERE a.course $csql AND cm.deletioninprogress = 0
           ORDER BY a.course, a.name, a.id", $cp);
        if (!$inventory) { return $result; }
        [$asql, $ap] = $DB->get_in_or_equal(array_keys($inventory), SQL_PARAMS_NAMED, 'as');

        // Grade 0 is a real mark; -1 and NULL are ungraded placeholders. Match the
        // current attempt and require grading after the student's last edit, as in
        // mod_assign's needs-grading logic. Do not use retained log event counts.
        $validgrade = "((g.grade IS NOT NULL AND g.grade >= 0) OR (a.grade = 0 AND g.grader > 0))
                       AND (a.markingworkflow = 0 OR uf.workflowstate IN ('readyforreview','inreview','readyforrelease','released'))";
        $corrected = "$validgrade AND g.timemodified > s.timemodified";
        $individual = "FROM {assign_submission} s
                       JOIN {assign} a ON a.id = s.assignment AND a.teamsubmission = 0 AND a.nosubmissions = 0
                       JOIN {user} u ON u.id = s.userid AND u.deleted = 0
                  LEFT JOIN {assign_grades} g ON g.assignment = s.assignment AND g.userid = s.userid
                            AND g.attemptnumber = s.attemptnumber
                  LEFT JOIN {assign_user_flags} uf ON uf.assignment = a.id AND uf.userid = s.userid
                      WHERE s.assignment $asql AND s.latest = 1 AND s.status = 'submitted'
                            AND s.userid > 0";
        $counts = $DB->get_records_sql(
            "SELECT s.assignment, COUNT(s.id) AS submitted,
                    SUM(CASE WHEN $corrected THEN 1 ELSE 0 END) AS corrected
               $individual GROUP BY s.assignment", $ap);
        $graders = $DB->get_records_sql(
            "SELECT " . $DB->sql_concat('g.grader', "'_'", 'a.course') . " AS rk,
                    COUNT(DISTINCT g.id) AS total
               $individual AND $corrected AND g.grader > 0
                    AND g.timemodified >= :since AND g.timemodified <= :until
           GROUP BY g.grader, a.course", array_merge($ap, ['since' => $since, 'until' => $now]));
        foreach ($graders as $rk => $row) { $result['graders'][$rk] = (int)$row->total; }

        $teams = array_filter($inventory, function($a) { return !empty($a->teamsubmission); });
        if ($teams) {
            [$tsql, $tp] = $DB->get_in_or_equal(array_keys($teams), SQL_PARAMS_NAMED, 'ta');
            [$rsql, $rp] = $DB->get_in_or_equal(roles::students() ?: [0], SQL_PARAMS_NAMED, 'ar');
            // Resolve one current submission group per enrolled student. Moodle
            // puts students with zero or multiple eligible groups in group 0.
            // Distinct membership and enrolment prevent duplicate denominators.
            $members = "SELECT a.id AS assignment, u.id AS userid,
                               CASE WHEN COUNT(DISTINCT gr.id) = 1 THEN MIN(gr.id) ELSE 0 END AS groupid
                          FROM {assign} a
                          JOIN {enrol} e ON e.courseid = a.course AND e.status = 0
                          JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
                          JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
                          JOIN {context} ctx ON ctx.instanceid = a.course AND ctx.contextlevel = 50
                          JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id
                                    AND ra.roleid $rsql
                     LEFT JOIN {groups_members} gm ON gm.userid = u.id
                     LEFT JOIN {groups} gr ON gr.id = gm.groupid AND gr.courseid = a.course AND gr.participation = 1
                                    AND (a.teamsubmissiongroupingid = 0 OR EXISTS (
                                        SELECT 1 FROM {groupings_groups} gg
                                         WHERE gg.groupingid = a.teamsubmissiongroupingid AND gg.groupid = gr.id))
                         WHERE a.id $tsql
                      GROUP BY a.id, u.id";
            // The inventory IDs are already validated; use a separate bind prefix
            // for the outer query because Moodle forbids reusing named parameters.
            [$sssql, $ssp] = $DB->get_in_or_equal(array_keys($teams), SQL_PARAMS_NAMED, 'st');
            $groupjoins = "FROM {assign_submission} s
                           JOIN {assign} a ON a.id = s.assignment
                      LEFT JOIN ($members) members ON members.assignment = s.assignment AND members.groupid = s.groupid
                      LEFT JOIN {assign_grades} g ON g.assignment = s.assignment AND g.userid = members.userid
                                AND g.attemptnumber = s.attemptnumber
                      LEFT JOIN {assign_user_flags} uf ON uf.assignment = a.id AND uf.userid = members.userid
                          WHERE s.assignment $sssql AND s.latest = 1 AND s.status = 'submitted' AND s.userid = 0";
            $params = array_merge($tp, $rp, $ssp);
            $groupcounts = $DB->get_records_sql(
                "SELECT q.assignment, COUNT(q.submissionid) AS submitted,
                        SUM(CASE WHEN q.members > 0 AND q.corrected = q.members THEN 1 ELSE 0 END) AS corrected,
                        SUM(CASE WHEN q.corrected > 0 AND q.corrected < q.members THEN 1 ELSE 0 END) AS partial
                   FROM (SELECT s.id AS submissionid, s.assignment, COUNT(members.userid) AS members,
                                SUM(CASE WHEN $corrected THEN 1 ELSE 0 END) AS corrected
                           $groupjoins GROUP BY s.id, s.assignment) q
               GROUP BY q.assignment", $params);
            foreach ($groupcounts as $id => $row) { $counts[$id] = $row; }
            // Personal contribution counts individual grades recorded by that
            // teacher, even when some other members of a team remain ungraded.
            $groupgraders = $DB->get_records_sql(
                "SELECT " . $DB->sql_concat('g.grader', "'_'", 'a.course') . " AS rk,
                        COUNT(DISTINCT g.id) AS total
                   $groupjoins AND $corrected AND g.grader > 0
                        AND g.timemodified >= :since AND g.timemodified <= :until
               GROUP BY g.grader, a.course", array_merge($params, ['since' => $since, 'until' => $now]));
            foreach ($groupgraders as $rk => $row) {
                $result['graders'][$rk] = ($result['graders'][$rk] ?? 0) + (int)$row->total;
            }
        }

        // Offline work has no received online submission to use as a denominator,
        // but actual grading by the teacher still contributes to personal activity.
        if (array_filter($inventory, function($a) { return !empty($a->nosubmissions); })) {
            $offline = $DB->get_records_sql(
                "SELECT " . $DB->sql_concat('g.grader', "'_'", 'a.course') . " AS rk,
                        COUNT(g.id) AS total
                   FROM {assign_grades} g
                   JOIN {assign} a ON a.id = g.assignment AND a.nosubmissions = 1
                   JOIN {user} u ON u.id = g.userid AND u.deleted = 0
              LEFT JOIN {assign_user_flags} uf ON uf.assignment = a.id AND uf.userid = g.userid
                  WHERE a.id $asql AND $validgrade AND g.grader > 0
                    AND g.timemodified >= :since AND g.timemodified <= :until
                    AND NOT EXISTS (SELECT 1 FROM {assign_grades} newer
                                     WHERE newer.assignment = g.assignment AND newer.userid = g.userid
                                       AND newer.attemptnumber > g.attemptnumber)
               GROUP BY g.grader, a.course", array_merge($ap, ['since' => $since, 'until' => $now]));
            foreach ($offline as $rk => $row) {
                $result['graders'][$rk] = ($result['graders'][$rk] ?? 0) + (int)$row->total;
            }
        }

        foreach ($inventory as $a) {
            $submitted = (int)($counts[$a->id]->submitted ?? 0);
            $graded = (int)($counts[$a->id]->corrected ?? 0);
            $partial = (int)($counts[$a->id]->partial ?? 0);
            $result['courses'][(int)$a->course][] = [
                'id' => (int)$a->id, 'name' => $a->name, 'courseid' => (int)$a->course,
                'cmid' => (int)$a->cmid, 'team' => !empty($a->teamsubmission),
                'offline' => !empty($a->nosubmissions),
                'submitted' => $submitted, 'corrected' => $graded,
                'pending' => $submitted - $graded,
                'partial' => $partial,
                'percent' => self::percent($submitted, $graded),
                'level' => self::level($submitted, $graded, $partial),
            ];
        }
        return $result;
    }

    public static function percent(int $submitted, int $corrected): ?float {
        if (!$submitted) { return null; }
        // Do not display 100% while a submission is still pending.
        return $corrected === $submitted ? 100.0 : min(99.9, round(100 * $corrected / $submitted, 1));
    }

    public static function level(int $submitted, int $corrected, int $partial = 0): string {
        if (!$submitted) { return 'no_submissions'; }
        if (!$corrected && !$partial) { return 'not_started'; }
        return $corrected === $submitted ? 'complete' : 'in_progress';
    }
}
