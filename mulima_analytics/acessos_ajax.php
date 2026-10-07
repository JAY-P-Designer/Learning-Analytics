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
 * Activity access report data endpoint.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/accesslib.php');
\local_mulima_analytics\local\access::require_report('accesses');
$PAGE->set_context(context_system::instance());
header('Content-Type: application/json; charset=utf-8');

if (!confirm_sesskey()) { echo json_encode(['ok'=>false,'error'=>'Sesskey']); exit; }

$op = required_param('op', PARAM_ALPHA);

// Access checks and sesskey validation have completed. Long report queries must
// not hold the session lock and block the next category/course selection.
\core\session\manager::write_close();

// Context level constants - explicit values for reliability in AJAX context

// Moodle logs ALL user interactions using these actions.
// We count any event at module context level so we capture views, submissions,
// attempts, forum posts, etc. - everything that proves the student was there.
// Data comes from logstore_standard_log which exists before this plugin was installed.

if (empty($PAGE->context)) { $PAGE->set_context(context_system::instance()); }
try { switch ($op) {

    // ── Sub-categories of a period ────────────────────────────────────
    case 'cats':
        $period = required_param('period', PARAM_INT);
        $parent = optional_param('parent', $period, PARAM_INT);
        $out = \local_mulima_analytics\local\access_filters::children($period, $parent);
        echo json_encode(['ok'=>true, 'cats'=>$out]);
        break;

    // ── Courses in period/category ────────────────────────────────────
    case 'courses':
        $period = required_param('period', PARAM_INT);
        $catid  = optional_param('catid', 0, PARAM_INT);
        $courses = \local_mulima_analytics\local\access_filters::courses($period, $catid, 0, optional_param('descendants', false, PARAM_BOOL));
        $out = [];
        foreach ($courses as $c) $out[] = ['id'=>(int)$c->id, 'name'=>$c->fullname, 'short'=>$c->shortname];
        echo json_encode(['ok'=>true, 'courses'=>$out]);
        break;

    // ── Panoramic: all courses summary ───────────────────────────────
    case 'panoramic':
        $period = required_param('period', PARAM_INT);
        $catid  = optional_param('catid', 0, PARAM_INT);
        $all = \local_mulima_analytics\local\access_filters::courses($period, $catid, 0, optional_param('descendants', false, PARAM_BOOL));
        if (empty($all)) { echo json_encode(['ok'=>true,'courses'=>[]]); break; }
        $cids = array_keys($all);
        list($cidsql, $cidp) = $DB->get_in_or_equal($cids, SQL_PARAMS_NAMED, 'ci');
        $vrows = $DB->get_records_sql(
            "SELECT courseid, COUNT(*) AS v, COUNT(DISTINCT userid) AS s
             FROM {logstore_standard_log}
             WHERE courseid $cidsql AND contextlevel = 70 AND action = 'viewed'
             GROUP BY courseid", $cidp);
        $vmap = [];
        foreach ($vrows as $r) $vmap[(int)$r->courseid] = [(int)$r->v, (int)$r->s];
        $erows = $DB->get_records_sql(
            "SELECT e.courseid, COUNT(DISTINCT ue.userid) AS cnt
             FROM {user_enrolments} ue JOIN {enrol} e ON e.id=ue.enrolid
             JOIN {user} u ON u.id=ue.userid
             WHERE e.courseid $cidsql AND ue.status=0 AND u.deleted=0
             GROUP BY e.courseid", $cidp);
        $emap = [];
        foreach ($erows as $r) $emap[(int)$r->courseid] = (int)$r->cnt;
        $cat_ids = array_unique(array_map(function($co){ return (int)$co->category; }, $all));
        list($cnsql, $cnp) = $DB->get_in_or_equal($cat_ids, SQL_PARAMS_NAMED, 'cn');
        $catrecs = $DB->get_records_select('course_categories', "id $cnsql", $cnp, '', 'id,name');
        $cnames = []; foreach ($catrecs as $cr) $cnames[$cr->id] = $cr->name;
        $out = [];
        foreach ($all as $co) {
            $cid = (int)$co->id; $st = $vmap[$cid] ?? [0, 0];
            $out[] = ['id'=>$cid,'name'=>$co->fullname,'short'=>$co->shortname,
                'cat'=>$cnames[(int)$co->category]??'','views'=>$st[0],'students'=>$st[1],'enrolled'=>$emap[$cid]??0];
        }
        usort($out, function($a,$b){ return $b['views']-$a['views']; });
        echo json_encode(['ok'=>true, 'courses'=>$out]);
        break;


    // ── Activities + access stats for a course ────────────────────────
    case 'activities':
        $courseid = required_param('courseid', PARAM_INT);
        $course   = $DB->get_record('course', ['id'=>$courseid], 'id,fullname,shortname');
        if (!$course) { echo json_encode(['ok'=>false,'error'=>get_string('course_not_found', 'local_mulima_analytics')]); break; }

        // ── 1. Enrolled student count (single query) ──────────────────
        $total_enrolled = (int)$DB->get_field_sql(
            "SELECT COUNT(DISTINCT ue.userid)
             FROM {user_enrolments} ue
             JOIN {enrol} e ON e.id = ue.enrolid
             JOIN {user} u ON u.id = ue.userid
             WHERE e.courseid = :cid AND ue.status = 0 AND u.deleted = 0",
            ['cid' => $courseid]);

        // ── 2. ALL log stats for this course in ONE query (replaces N queries) ──
        // Instead of querying logstore per-activity in a loop,
        // we fetch total_views + unique_users for ALL cmids at once.
        $log_stats = [];
        // recordset (not get_records_sql) so NULL/duplicate first columns can never
        // corrupt or abort the result - and no silent catch masking real errors.
        $rs = $DB->get_recordset_sql(
            "SELECT l.contextinstanceid AS cmid,
                    COUNT(*) AS total_views,
                    COUNT(DISTINCT l.userid) AS unique_users
             FROM {logstore_standard_log} l
             WHERE l.courseid = :cid AND l.contextlevel = 70
               AND l.action = 'viewed' AND l.contextinstanceid IS NOT NULL
             GROUP BY l.contextinstanceid",
            ['cid' => $courseid]);
        foreach ($rs as $r) {
            $log_stats[(int)$r->cmid] = [
                'total_views'  => (int)$r->total_views,
                'unique_users' => (int)$r->unique_users,
            ];
        }
        $rs->close();

        // ── 3. Course modules list (single query) ─────────────────────
        $cms = $DB->get_records_sql(
            "SELECT cm.id AS cmid, m.name AS modname, cm.instance,
                    cs.section AS section_num, cs.name AS section_name_raw
             FROM {course_modules} cm
             JOIN {modules} m ON m.id = cm.module
             JOIN {course_sections} cs ON cs.id = cm.section
             WHERE cm.course = :cid AND cm.visible = 1
             ORDER BY cs.section, cm.id",
            ['cid' => $courseid]);

        // ── 4. Activity names - one query per module type (batch) ─────
        // Groups all instances of the same type (e.g., all assigns) and
        // fetches their names in a single IN() query per type.
        $by_type = [];
        foreach ($cms as $cm) {
            $by_type[$cm->modname][(int)$cm->instance] = true;
        }
        $names = []; // $names[$modname][$instance_id] = 'Name'
        foreach ($by_type as $modname => $instances) {
            try {
                $ids = array_keys($instances);
                list($insql, $inparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'i');
                $recs = $DB->get_records_select($modname, "id $insql", $inparams, '', 'id,name');
                foreach ($recs as $r) {
                    $names[$modname][(int)$r->id] = $r->name;
                }
            } catch (\Throwable $e) {
                // Module table may not exist (deleted plugin) - skip silently
            }
        }

        // ── 5. Build result array ─────────────────────────────────────
        $activities  = [];
        $total_views = 0;
        foreach ($cms as $cm) {
            $name = $names[$cm->modname][(int)$cm->instance] ?? null;
            if (empty($name)) continue;

            $stats  = $log_stats[(int)$cm->cmid] ?? null;
            $views  = $stats ? $stats['total_views']  : 0;
            $unique = $stats ? $stats['unique_users'] : 0;
            $total_views += $views;

            $section_label = !empty($cm->section_name_raw)
                ? $cm->section_name_raw
                : get_string('section_n', 'local_mulima_analytics', $cm->section_num);

            $activities[] = [
                'cmid'            => (int)$cm->cmid,
                'name'            => $name,
                'modname'         => $cm->modname,
                'section_name'    => $section_label,
                'total_views'     => $views,
                'unique_students' => $unique,
                'enrolled'        => $total_enrolled,
            ];
        }

        // Sort by views descending (done in PHP, no extra query)
        usort($activities, function($a,$b){ return $b['total_views'] - $a['total_views']; });

        // Course-wide unique users (already have per-cmid, sum unique across course)
        $total_users_with_access = (int)$DB->get_field_sql(
            "SELECT COUNT(DISTINCT userid) FROM {logstore_standard_log}
             WHERE courseid = :cid AND contextlevel = 70
               AND action = 'viewed'",
            ['cid' => $courseid]);

        echo json_encode([
            'ok'          => true,
            'course_name' => $course->fullname,
            'activities'  => $activities,
            'stats'       => [
                'total_views' => $total_views,
                'students'    => $total_users_with_access,
                'enrolled'    => $total_enrolled,
            ],
        ]);
        break;

    // ── Per-user detail for one activity ─────────────────────────────
    case 'detail':
        $cmid     = required_param('cmid', PARAM_INT);
        $courseid = required_param('courseid', PARAM_INT);

        // All events at this module context, grouped by user (single JOIN query)
        $rows = $DB->get_records_sql(
            "SELECT l.userid, u.firstname, u.lastname, u.email,
                    COUNT(*) AS views,
                    MIN(l.timecreated) AS first_access,
                    MAX(l.timecreated) AS last_access
             FROM {logstore_standard_log} l
             JOIN {user} u ON u.id = l.userid AND u.deleted = 0
             WHERE l.contextinstanceid = :cmid
               AND l.contextlevel = 70
               AND l.action = 'viewed'
             GROUP BY l.userid, u.firstname, u.lastname, u.email
             ORDER BY views DESC",
            ['cmid' => $cmid]);

        $students    = [];
        $total_views = 0;
        $first_ts    = 0;
        foreach ($rows as $r) {
            $total_views += (int)$r->views;
            if (!$first_ts || $r->first_access < $first_ts) $first_ts = (int)$r->first_access;
            $students[] = [
                'fullname'     => trim($r->firstname . ' ' . $r->lastname),
                'email'        => $r->email,
                'views'        => (int)$r->views,
                'first_access' => userdate($r->first_access, '%d/%m/%Y %H:%M'),
                'last_access'  => userdate($r->last_access,  '%d/%m/%Y %H:%M'),
            ];
        }

        echo json_encode(['ok'=>true, 'students'=>$students, 'total_views'=>$total_views,
            'first_overall'=>$first_ts ? userdate($first_ts, '%d/%m/%Y') : '-']);
        break;

    default:
        echo json_encode(['ok'=>false, 'error'=>get_string('invalid_operation', 'local_mulima_analytics')]);

}} catch (\Throwable $e) {
    error_log('local_mulima_analytics acessos_ajax: ' . $e->getMessage());
    echo json_encode(['ok'=>false, 'error'=>get_string('ajax_error', 'local_mulima_analytics')]);
}
