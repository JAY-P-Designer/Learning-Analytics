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
 * Dashboard report data.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
\local_mulima_analytics\local\access::require_report('dashboard');
$PAGE->set_context(context_system::instance());
header('Content-Type: application/json; charset=utf-8');
ob_start();

if (empty($PAGE->context)) { $PAGE->set_context(context_system::instance()); }
try {
    $op = required_param('op', PARAM_ALPHA);
    $sesskey = required_param('sesskey', PARAM_RAW);
    if (!confirm_sesskey($sesskey)) throw new \Exception('Sesskey');

    $student_rid = (int)($DB->get_field('role', 'id', ['shortname' => 'student']) ?: 5);
    $teacher_rid = (int)($DB->get_field('role', 'id', ['shortname' => 'editingteacher']) ?: 3);

    switch ($op) {

        case 'data':
            $data = json_decode(file_get_contents('php://input'), true);
            $cat_filter = $data['categories'] ?? '';
            $maxpoints = (int)(get_config('local_mulima_analytics', 'maxpoints') ?: 800);

            $where = 'id <> :siteid';
            $params = ['siteid' => SITEID];

            if (!empty($cat_filter)) {
                $cat_ids = array_map('intval', explode(',', $cat_filter));
                $expanded = $cat_ids;
                foreach ($cat_ids as $cid) {
                    $subs = $DB->get_fieldset_select('course_categories', 'id',
                        "path LIKE :p", ['p' => '%/' . $cid . '/%']);
                    $expanded = array_merge($expanded, $subs);
                }
                $expanded = array_unique(array_map('intval', $expanded));
                if (!empty($expanded)) {
                    list($insql, $inparams) = $DB->get_in_or_equal($expanded, SQL_PARAMS_NAMED, 'fcat');
                    $where .= " AND category $insql";
                    $params = array_merge($params, $inparams);
                }
            }

            $courses = $DB->get_records_select('course', $where, $params, 'fullname',
                'id,fullname,shortname,category');
            if (empty($courses)) {
                ob_end_clean();
                echo json_encode(['ok' => true, 'courses' => [], 'maxpoints' => $maxpoints]);
                break;
            }

            $course_ids = array_keys($courses);

            // 1. Category names.
            $cat_names = [];
            $catids = array_unique(array_values(array_map(function($c){return (int)$c->category;}, $courses)));
            if (!empty($catids)) {
                list($s,$p) = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED, 'cn');
                foreach ($DB->get_records_select('course_categories', "id $s", $p, '', 'id,name') as $r)
                    $cat_names[(int)$r->id] = $r->name;
            }

            // 2. Grade items per course.
            list($cisql, $ciparams) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'ci');
            $all_items = [];
            $recs = $DB->get_records_select('grade_items',
                "courseid $cisql AND itemtype = 'mod'", $ciparams,
                'courseid,sortorder', 'id,courseid,itemname,itemmodule,grademax');
            foreach ($recs as $gi) {
                $all_items[(int)$gi->courseid][] = $gi;
            }

            // 3. Which items have at least one grade.
            list($cisql2, $ciparams2) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'gi');
            $graded_ids = [];
            $sql = "SELECT DISTINCT gi.id
                    FROM {grade_items} gi
                    JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.finalgrade IS NOT NULL
                    WHERE gi.courseid $cisql2 AND gi.itemtype = 'mod'";
            foreach ($DB->get_records_sql($sql, $ciparams2) as $r) $graded_ids[(int)$r->id] = 1;

            // 4. Teachers per course.
            list($cisql3, $ciparams3) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'tc');
            $teachers = [];
            $sql = "SELECT ra.id as raid, ctx.instanceid as cid, u.firstname, u.lastname
                    FROM {role_assignments} ra
                    JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                    JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
                    WHERE ra.roleid = :trid AND ctx.instanceid $cisql3
                    ORDER BY u.lastname";
            foreach ($DB->get_records_sql($sql, array_merge(['trid' => $teacher_rid], $ciparams3)) as $t) {
                $k = (int)$t->cid;
                $n = $t->firstname . ' ' . $t->lastname;
                if (!isset($teachers[$k])) $teachers[$k] = [];
                if (!in_array($n, $teachers[$k])) $teachers[$k][] = $n;
            }

            // 5. Student counts per course.
            list($cisql4, $ciparams4) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'sc');
            $stu_counts = [];
            $sql = "SELECT ctx.instanceid as cid, COUNT(DISTINCT ra.userid) as cnt
                    FROM {role_assignments} ra
                    JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                    WHERE ra.roleid = :srid AND ctx.instanceid $cisql4
                    GROUP BY ctx.instanceid";
            foreach ($DB->get_records_sql($sql, array_merge(['srid' => $student_rid], $ciparams4)) as $r)
                $stu_counts[(int)$r->cid] = (int)$r->cnt;

            // 6. Active students per course.
            list($cisql5, $ciparams5) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'ac');
            $active_counts = [];
            $sql = "SELECT gi.courseid as cid, COUNT(DISTINCT gg.userid) as cnt
                    FROM {grade_grades} gg
                    JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'mod'
                    JOIN {context} ctx ON ctx.contextlevel = 50 AND ctx.instanceid = gi.courseid
                    JOIN {role_assignments} ra ON ra.userid = gg.userid AND ra.contextid = ctx.id AND ra.roleid = :arid
                    WHERE gg.finalgrade IS NOT NULL AND gi.courseid $cisql5
                    GROUP BY gi.courseid";
            foreach ($DB->get_records_sql($sql, array_merge(['arid' => $student_rid], $ciparams5)) as $r)
                $active_counts[(int)$r->cid] = (int)$r->cnt;

            // Build result.
            $result = [];
            foreach ($courses as $co) {
                $cid = (int)$co->id;
                $items = $all_items[$cid] ?? [];
                $sum = 0; $quiz = false; $gr = 0; $ugr = 0;
                foreach ($items as $gi) {
                    $sum += (float)$gi->grademax;
                    if ($gi->itemmodule === 'quiz') $quiz = true;
                    isset($graded_ids[(int)$gi->id]) ? $gr++ : $ugr++;
                }
                $result[] = [
                    'id' => $cid, 'name' => $co->fullname, 'shortname' => $co->shortname,
                    'category' => $cat_names[(int)$co->category] ?? '',
                    'teachers' => $teachers[$cid] ?? [],
                    'students' => $stu_counts[$cid] ?? 0,
                    'active' => $active_counts[$cid] ?? 0,
                    'assessments' => count($items), 'sum_max' => (int)$sum,
                    'graded' => $gr, 'ungraded' => $ugr, 'has_quiz' => $quiz,
                ];
            }
            ob_end_clean();
            echo json_encode(['ok' => true, 'courses' => $result, 'maxpoints' => $maxpoints]);
            break;

        case 'detail':
            $data = json_decode(file_get_contents('php://input'), true);
            $cid = (int)($data['courseid'] ?? 0);
            if (!$cid) throw new \Exception('courseid em falta');

            $total = 0;
            $ctx = $DB->get_record_sql(
                "SELECT id FROM {context} WHERE contextlevel = 50 AND instanceid = :cid",
                ['cid' => $cid]);
            if ($ctx) {
                $total = (int)$DB->count_records_sql(
                    "SELECT COUNT(DISTINCT ra.userid) FROM {role_assignments} ra
                     WHERE ra.contextid = :ctx AND ra.roleid = :rid",
                    ['ctx' => $ctx->id, 'rid' => $student_rid]);
            }

            $items = $DB->get_records_select('grade_items',
                "courseid = :cid AND itemtype = 'mod' AND grademax > 0",
                ['cid' => $cid], 'sortorder', 'id,itemname,itemmodule,iteminstance,grademax,categoryid');

            // Find PA category to determine which items count for frequency.
            $pa_cat = $DB->get_record('grade_categories', [
                'courseid' => $cid, 'fullname' => 'Plano de Avaliação'
            ], 'id');
            $pa_catid = $pa_cat ? (int)$pa_cat->id : 0;

            $details = [];
            foreach ($items as $gi) {
                $gc = (int)$DB->count_records_sql(
                    "SELECT COUNT(DISTINCT userid) FROM {grade_grades}
                     WHERE itemid = :iid AND finalgrade IS NOT NULL", ['iid' => $gi->id]);
                $in_pa = ($pa_catid > 0) ? ((int)$gi->categoryid === $pa_catid) : true;

                // Get course module URL.
                $cm_url = '';
                $cm = $DB->get_record_sql(
                    "SELECT cm.id FROM {course_modules} cm
                     JOIN {modules} m ON m.id = cm.module AND m.name = :mod
                     WHERE cm.course = :cid AND cm.instance = :iid",
                    ['mod' => $gi->itemmodule, 'cid' => $cid, 'iid' => $gi->iteminstance]);
                if ($cm) {
                    $cm_url = $CFG->wwwroot . '/mod/' . $gi->itemmodule . '/view.php?id=' . $cm->id;
                }

                $details[] = [
                    'id' => (int)$gi->id,
                    'name' => $gi->itemname ?: get_string('sem_nome', 'local_mulima_analytics'),
                    'type' => $gi->itemmodule,
                    'max' => (float)$gi->grademax,
                    'in_pa' => $in_pa,
                    'url' => $cm_url,
                    'graded' => $gc,
                    'total' => $total,
                    'pending' => max(0, $total - $gc),
                ];
            }
            ob_end_clean();
            echo json_encode(['ok' => true, 'items' => $details, 'total_students' => $total]);
            break;

        default:
            throw new \Exception(get_string('invalid_operation', 'local_mulima_analytics'));
    }
} catch (\Throwable $e) {
    ob_end_clean();
    error_log('local_mulima_analytics ajax: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => get_string('ajax_error', 'local_mulima_analytics')]);
}
