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
 * Learning Analytics teachers report.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics\local;

defined('MOODLE_INTERNAL') || die();

/** Teacher activity and forum participation, shared by the screen and export. */
final class teachers_report {
    public static function get(int $period, int $category = 0, int $course = 0,
            int $range = 30, ?int $now = null, bool $recursive = true, ?array $scoring = null): array {
        global $DB;
        $now = $now ?? time();
        $scoring = $scoring ?? teacher_scoring::settings();
        $since = $now - max(1, min(365, $range)) * DAYSECS;
        $course_ids = access_filters::course_ids($period, $category, $course, $recursive);
        if (!$course_ids) { return []; }
        // ── Teacher roles ──────────────────────────────────────────
        $teacher_roleids = [];
        foreach (['editingteacher','teacher','manager'] as $shortname) {
            $rid = $DB->get_field('role', 'id', ['shortname'=>$shortname]);
            if ($rid) $teacher_roleids[] = (int)$rid;
        }
        if (empty($teacher_roleids)) $teacher_roleids = [3,4];

        // ── 1. All (teacher, course) pairs in ONE query ────────────
        list($cinsql, $cinparams) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'co');
        list($rinsql, $rinparams) = $DB->get_in_or_equal($teacher_roleids, SQL_PARAMS_NAMED, 'rr');
        $pairs = $DB->get_records_sql(
            "SELECT DISTINCT " . $DB->sql_concat('u.id', "'_'", 'ctx.instanceid') . " AS rk,
                    u.id AS userid, u.firstname, u.lastname, u.email,
                    ctx.instanceid AS courseid, c.fullname AS coursename
             FROM {role_assignments} ra
             JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
             JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
             JOIN {course} c ON c.id = ctx.instanceid
             WHERE ctx.instanceid $cinsql AND ra.roleid $rinsql",
            array_merge($cinparams, $rinparams));

        if (empty($pairs)) { return []; }

        $teacher_ids = array_unique(array_map(function($p){ return (int)$p->userid; }, $pairs));

        // Moodle records module creation as core\event\course_module_created,
        // with the course_modules ID in objectid. Count each surviving module once
        // per teacher/course; resources and other activities are mutually exclusive.
        // Updates, content-level posts and duplicate event records cannot add points.
        $res_mods = "'resource','url','page','folder','scorm','imscp','book'";
        list($c2sql, $c2p) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'lg');
        list($u2sql, $u2p) = $DB->get_in_or_equal($teacher_ids, SQL_PARAMS_NAMED, 'ut');
        $mrows = $DB->get_records_sql(
            "SELECT " . $DB->sql_concat('l.userid', "'_'", 'l.courseid') . " AS rk,
                    COUNT(DISTINCT CASE WHEN l.timecreated >= :s1 AND md.name NOT IN ($res_mods)
                                       THEN cm.id ELSE NULL END) AS acts,
                    COUNT(DISTINCT CASE WHEN l.timecreated >= :s2 AND md.name IN ($res_mods)
                                       THEN cm.id ELSE NULL END) AS res,
                    MAX(l.timecreated) AS last_ts
               FROM {logstore_standard_log} l
          LEFT JOIN {course_modules} cm ON cm.id = l.objectid AND cm.course = l.courseid
                    AND l.objecttable = 'course_modules' AND l.eventname = :creation
                    AND cm.deletioninprogress = 0
          LEFT JOIN {modules} md ON md.id = cm.module
              WHERE l.courseid $c2sql AND l.userid $u2sql AND l.timecreated <= :until
           GROUP BY l.userid, l.courseid",
            array_merge($c2p, $u2p, ['s1'=>$since,'s2'=>$since,'until'=>$now,
                'creation'=>'\\core\\event\\course_module_created']));

        $assignments = assignment_progress::get($course_ids, $since, $now);

        // Inventory includes every existing forum in the selected teaching courses,
        // including forums created by other teachers, administrators or imports.
        // Exclude activities waiting for asynchronous deletion, but include hidden ones
        // because this is a management report, not a student's availability list.
        list($fcsql, $fcparams) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'fc');
        $forumjoins = "FROM {forum} f
                       JOIN {course_modules} cm ON cm.instance = f.id AND cm.course = f.course
                       JOIN {modules} md ON md.id = cm.module AND md.name = 'forum'";
        // A LEFT JOIN is essential: an activity with no discussions is still a
        // forum. Inventory and existing topic counts are independent of dates/authors.
        $forums = $DB->get_records_sql(
            "SELECT f.id, f.course AS courseid, f.name, cm.id AS cmid, cm.visible,
                    COUNT(DISTINCT d.id) AS topics
               $forumjoins
          LEFT JOIN {forum_discussions} d ON d.forum = f.id
              WHERE f.course $fcsql AND cm.deletioninprogress = 0
           GROUP BY f.id, f.course, f.name, cm.id, cm.visible
           ORDER BY f.course, f.name, f.id", $fcparams);
        $forumsbycourse = [];
        foreach ($forums as $forum) { $forumsbycourse[(int)$forum->courseid][] = $forum; }

        $participation = [];
        if ($forums) {
            list($fisql, $fiparams) = $DB->get_in_or_equal(array_keys($forums), SQL_PARAMS_NAMED, 'fi');
            list($fusql, $fuparams) = $DB->get_in_or_equal($teacher_ids, SQL_PARAMS_NAMED, 'fu');
            $participation = $DB->get_records_sql(
                "SELECT " . $DB->sql_concat('p.userid', "'_'", 'd.forum') . " AS rk,
                        COUNT(p.id) AS posts,
                        SUM(CASE WHEN p.parent = 0 THEN 1 ELSE 0 END) AS discussions,
                        SUM(CASE WHEN p.parent <> 0 THEN 1 ELSE 0 END) AS replies,
                        MAX(p.created) AS lastpost
                   FROM {forum_discussions} d
                   JOIN {forum_posts} p ON p.discussion = d.id
                  WHERE d.forum $fisql AND p.userid $fusql AND p.deleted = 0
                    AND p.created >= :since AND p.created <= :until
               GROUP BY p.userid, d.forum",
                array_merge($fiparams, $fuparams, ['since' => $since, 'until' => $now]));
        }

        // ── 3. Merge ───────────────────────────────────────────────
        $teacher_data = [];
        foreach ($pairs as $p) {
            $uid = (int)$p->userid;
            if (!isset($teacher_data[$uid])) {
                $teacher_data[$uid] = [
                    'userid'=>$uid, 'fullname'=>trim($p->firstname.' '.$p->lastname),
                    'email'=>$p->email, 'courses'=>[], 'course_details'=>[], 'activities'=>0, 'resources'=>0,
                    'graded'=>0, 'forum_posts'=>0, 'forums'=>[], 'forums_total'=>0, 'forums_participated'=>0,
                    'forum_discussions'=>0, 'forum_replies'=>0, 'forum_last_ts'=>0,
                    'forum_last_post'=>null, '_last_ts'=>0, 'last_access'=>null,
                    'assignments'=>[], 'assignments_total'=>0, 'submissions_total'=>0,
                    'submissions_graded'=>0, 'submissions_pending'=>0,
                    'submissions_partial'=>0, 'course_scores'=>[],
                ];
            }
            $teacher_data[$uid]['courses'][] = $p->coursename;
            // Keep course IDs independently of names: different courses may share a name.
            $teacher_data[$uid]['course_details'][(int)$p->courseid] = [
                'id' => (int)$p->courseid, 'name' => $p->coursename,
            ];
            $m = $mrows[$p->rk] ?? null;
            $coursecounts = ['activities'=>(int)($m->acts ?? 0), 'resources'=>(int)($m->res ?? 0),
                'graded'=>(int)($assignments['graders'][$p->rk] ?? 0), 'forum_posts'=>0];
            if ($m) {
                $teacher_data[$uid]['activities']  += (int)$m->acts;
                $teacher_data[$uid]['resources']   += (int)$m->res;
                if ((int)$m->last_ts > $teacher_data[$uid]['_last_ts']) {
                    $teacher_data[$uid]['_last_ts']    = (int)$m->last_ts;
                    $teacher_data[$uid]['last_access'] = userdate((int)$m->last_ts, '%d/%m/%Y %H:%M');
                }
            }
            $teacher_data[$uid]['graded'] += $assignments['graders'][$p->rk] ?? 0;
            foreach ($assignments['courses'][(int)$p->courseid] ?? [] as $assignment) {
                $assignment['coursename'] = $p->coursename;
                $teacher_data[$uid]['assignments'][] = $assignment;
                $teacher_data[$uid]['assignments_total']++;
                $teacher_data[$uid]['submissions_total'] += $assignment['submitted'];
                $teacher_data[$uid]['submissions_graded'] += $assignment['corrected'];
                $teacher_data[$uid]['submissions_pending'] += $assignment['pending'];
                $teacher_data[$uid]['submissions_partial'] += $assignment['partial'];
            }
            // Only merge a course where this user is a teacher; authorship in a
            // different selected course must not be attributed to their teaching role.
            foreach ($forumsbycourse[(int)$p->courseid] ?? [] as $forum) {
                $f = $participation[$uid.'_'.$forum->id] ?? null;
                $posts = (int)($f->posts ?? 0);
                $discussions = (int)($f->discussions ?? 0);
                $replies = (int)($f->replies ?? 0);
                $lastpost = (int)($f->lastpost ?? 0);
                $teacher_data[$uid]['forums'][] = [
                    'id' => (int)$forum->id, 'cmid' => (int)$forum->cmid,
                    'courseid' => (int)$p->courseid, 'coursename' => $p->coursename,
                    'name' => $forum->name, 'visible' => (bool)$forum->visible,
                    'topics' => (int)$forum->topics, 'participated' => $posts > 0,
                    'posts' => $posts, 'discussions' => $discussions, 'replies' => $replies,
                    'last_ts' => $lastpost,
                    'last_post' => $lastpost ? userdate($lastpost, '%d/%m/%Y %H:%M') : null,
                ];
                $teacher_data[$uid]['forums_total']++;
                $teacher_data[$uid]['forums_participated'] += $posts > 0 ? 1 : 0;
                $teacher_data[$uid]['forum_posts'] += $posts;
                $coursecounts['forum_posts'] += $posts;
                $teacher_data[$uid]['forum_discussions'] += $discussions;
                $teacher_data[$uid]['forum_replies'] += $replies;
                $teacher_data[$uid]['forum_last_ts'] = max($teacher_data[$uid]['forum_last_ts'], $lastpost);
            }
            $teacher_data[$uid]['course_scores'][] = array_merge(
                ['courseid'=>(int)$p->courseid, 'coursename'=>$p->coursename],
                teacher_scoring::calculate($coursecounts, $scoring));
        }

        $out = [];
        foreach ($teacher_data as $t) {
            $t['courses'] = array_values(array_unique($t['courses']));
            $t['course_details'] = array_values($t['course_details']);
            $t['grading_percent'] = assignment_progress::percent($t['submissions_total'], $t['submissions_graded']);
            $t['grading_level'] = $t['assignments_total'] ?
                assignment_progress::level($t['submissions_total'], $t['submissions_graded'], $t['submissions_partial']) : 'no_assignments';
            // Apply the overall cap once, after summing the raw course contributions.
            $t = array_merge($t, teacher_scoring::calculate($t, $scoring));
            $t['last_ts'] = (int)$t['_last_ts'];
            if ($t['forum_last_ts']) {
                $t['forum_last_post'] = userdate($t['forum_last_ts'], '%d/%m/%Y %H:%M');
            }
            unset($t['_last_ts']);
            $out[] = $t;
        }
        usort($out, function($a,$b){ return $b['score']-$a['score']; });
        return array_values($out);
    }
}
