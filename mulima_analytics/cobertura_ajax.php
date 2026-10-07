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
 * Assessment coverage report data endpoint.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
\local_mulima_analytics\local\access::require_report('coverage');
$PAGE->set_context(context_system::instance());
header('Content-Type: application/json; charset=utf-8');
if (!confirm_sesskey()) { echo json_encode(['ok'=>false,'error'=>'Sesskey']); exit; }

$op = required_param('op', PARAM_ALPHA);
\core\session\manager::write_close();

if (empty($PAGE->context)) { $PAGE->set_context(context_system::instance()); }
try { switch ($op) {

    case 'cats':
        $period = required_param('period', PARAM_INT);
        $parent = optional_param('parent', $period, PARAM_INT);
        $out = \local_mulima_analytics\local\access_filters::children($period, $parent);
        echo json_encode(['ok'=>true, 'cats'=>$out]);
        break;

    case 'courses':
        $period = required_param('period', PARAM_INT);
        $catid = optional_param('catid', 0, PARAM_INT);
        $courses = \local_mulima_analytics\local\access_filters::courses(
            $period, $catid, 0, optional_param('descendants', false, PARAM_BOOL));
        $out = [];
        foreach ($courses as $c) $out[] = ['id' => (int)$c->id, 'name' => $c->fullname];
        echo json_encode(['ok' => true, 'courses' => $out]);
        break;

    // ── Per-discipline assessment overview (batch queries) ────────
    case 'overview':
        $data   = json_decode(file_get_contents('php://input'), true);
        $period = (int)($data['period'] ?? 0);
        $catid  = (int)($data['catid'] ?? 0);

        $courses = \local_mulima_analytics\local\access_filters::courses($period, $catid, 0, !empty($data['descendants']));
        if (empty($courses)) { echo json_encode(['ok'=>true,'courses'=>[]]); break; }

        $course_ids = array_map('intval', array_keys($courses));
        list($cq, $cp) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'co');

        $catnames = [];
        foreach ($DB->get_records_list('course_categories', 'id',
            array_unique(array_map(function($c){ return (int)$c->category; }, $courses)), '', 'id,name') as $cc) {
            $catnames[(int)$cc->id] = $cc->name;
        }

        // 1. Gradable items per course - SAME rule as the Painel (index.php):
        //    grademax > 0 (not gradetype=1, which excludes scales but also some
        //    legitimate 'value' items configured differently), forums are only
        //    counted once at least one student has been graded on them, and the
        //    points total honours a 'Plano de Avaliação' grade category when the
        //    teacher has configured one - its own grademax wins over a plain sum.
        $items_by_course = [];
        $all_gi = $DB->get_records_sql(
            "SELECT id, courseid, itemname, itemmodule, grademax, categoryid
             FROM {grade_items}
             WHERE courseid $cq AND itemtype = 'mod' AND grademax > 0
             ORDER BY courseid, sortorder", $cp);

        $forum_ids = array_values(array_filter(array_map(function($gi){
            return $gi->itemmodule === 'forum' ? (int)$gi->id : null;
        }, $all_gi)));
        $forum_has_grade = [];
        if ($forum_ids) {
            list($fq,$fp) = $DB->get_in_or_equal($forum_ids, SQL_PARAMS_NAMED, 'fg');
            foreach ($DB->get_records_sql(
                "SELECT DISTINCT itemid FROM {grade_grades} WHERE itemid $fq AND finalgrade IS NOT NULL", $fp) as $r) {
                $forum_has_grade[(int)$r->itemid] = 1;
            }
        }
        foreach ($all_gi as $giid => $giobj) {
            if ($giobj->itemmodule === 'forum' && empty($forum_has_grade[(int)$giobj->id])) continue;
            $items_by_course[(int)$giobj->courseid][] = $giobj;
        }

        // Plano de Avaliação category + its configured total per course.
        $pa_categories = []; $pa_totals = [];
        foreach ($DB->get_records_sql(
            "SELECT id, courseid FROM {grade_categories}
             WHERE fullname = 'Plano de Avaliação' AND courseid $cq", $cp) as $pr) {
            $pa_categories[(int)$pr->courseid] = (int)$pr->id;
        }
        if ($pa_categories) {
            list($pq,$pp) = $DB->get_in_or_equal(array_values($pa_categories), SQL_PARAMS_NAMED, 'pt');
            foreach ($DB->get_records_sql(
                "SELECT courseid, grademax FROM {grade_items}
                 WHERE itemtype = 'category' AND iteminstance $pq", $pp) as $pgi) {
                $pa_totals[(int)$pgi->courseid] = (float)$pgi->grademax;
            }
        }

        $gi = [];
        foreach ($course_ids as $cid) {
            $its = $items_by_course[$cid] ?? [];
            $pa_catid = $pa_categories[$cid] ?? 0;
            $sum_pa = 0.0;
            foreach ($its as $it) {
                $in_pa = $pa_catid ? ((int)$it->categoryid === $pa_catid) : true;
                if ($in_pa) $sum_pa += (float)$it->grademax;
            }
            $pts = isset($pa_totals[$cid]) ? $pa_totals[$cid] : $sum_pa;
            $gi[$cid] = ['n'=>count($its), 'pts'=>round($pts, 1)];
        }

        // 2. Graded entries per course, restricted to the same item set above.
        $graded = [];
        foreach ($items_by_course as $cid => $its) {
            $ids = array_map(function($it){ return (int)$it->id; }, $its);
            if (!$ids) continue;
            list($gq,$gp) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'gd');
            $graded[$cid] = (int)$DB->count_records_select('grade_grades',
                "itemid $gq AND finalgrade IS NOT NULL", $gp);
        }

        // 3. Assign: submitted + pending correction per course.
        $asub = $apend = [];
        list($cq2,$cp2) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'ca');
        foreach ($DB->get_records_sql(
            "SELECT a.course AS courseid, COUNT(s.id) AS n
             FROM {assign_submission} s
             JOIN {assign} a ON a.id = s.assignment
             WHERE a.course $cq2 AND s.latest = 1 AND s.status = 'submitted'
             GROUP BY a.course", $cp2) as $r) $asub[(int)$r->courseid] = (int)$r->n;

        list($cq3,$cp3) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'cb');
        foreach ($DB->get_records_sql(
            "SELECT a.course AS courseid, COUNT(s.id) AS n
             FROM {assign_submission} s
             JOIN {assign} a ON a.id = s.assignment
             JOIN {grade_items} i ON i.itemmodule = 'assign' AND i.itemtype = 'mod'
                  AND i.iteminstance = a.id AND i.courseid = a.course
             LEFT JOIN {grade_grades} g ON g.itemid = i.id AND g.userid = s.userid
                  AND g.finalgrade IS NOT NULL
             WHERE a.course $cq3 AND s.latest = 1 AND s.status = 'submitted' AND g.id IS NULL
             GROUP BY a.course", $cp3) as $r) $apend[(int)$r->courseid] = (int)$r->n;

        // 4. Quiz: finished attempts (distinct user/quiz) + needing manual grading.
        $qsub = $qpend = [];
        list($cq4,$cp4) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'cd');
        $rk = $DB->sql_concat('qa.quiz', "'_'", 'qa.userid');
        foreach ($DB->get_records_sql(
            "SELECT q.course AS courseid, COUNT(DISTINCT $rk) AS n
             FROM {quiz_attempts} qa
             JOIN {quiz} q ON q.id = qa.quiz
             WHERE q.course $cq4 AND qa.state = 'finished'
             GROUP BY q.course", $cp4) as $r) $qsub[(int)$r->courseid] = (int)$r->n;

        list($cq5,$cp5) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'ce');
        foreach ($DB->get_records_sql(
            "SELECT q.course AS courseid, COUNT(DISTINCT $rk) AS n
             FROM {quiz_attempts} qa
             JOIN {quiz} q ON q.id = qa.quiz
             WHERE q.course $cq5 AND qa.state = 'finished' AND qa.sumgrades IS NULL
             GROUP BY q.course", $cp5) as $r) $qpend[(int)$r->courseid] = (int)$r->n;

        $out = [];
        foreach ($courses as $co) {
            $cid = (int)$co->id;
            $g   = $gi[$cid] ?? ['n'=>0,'pts'=>0];
            $sub = ($asub[$cid] ?? 0) + ($qsub[$cid] ?? 0);
            $pen = ($apend[$cid] ?? 0) + ($qpend[$cid] ?? 0);
            $out[] = [
                'id'       => $cid,
                'name'     => $co->fullname,
                'short'    => $co->shortname,
                'catname'  => $catnames[(int)$co->category] ?? '',
                'n_items'  => $g['n'],
                'points'   => $g['pts'],
                'graded'   => $graded[$cid] ?? 0,
                'submitted'=> $sub,
                'pending'  => $pen,
                'course_url' => (new moodle_url('/course/view.php', ['id'=>$cid]))->out(false),
            ];
        }
        echo json_encode(['ok'=>true, 'courses'=>$out]);
        break;

    // ── Gradable activities of one course ─────────────────────────
    case 'acts':
        $cid = required_param('courseid', PARAM_INT);
        $items = $DB->get_records_sql(
            "SELECT i.id, i.itemname, i.itemmodule, i.iteminstance, i.grademax, i.categoryid
             FROM {grade_items} i
             WHERE i.courseid = :cid AND i.itemtype = 'mod' AND i.grademax > 0
             ORDER BY i.sortorder, i.id", ['cid'=>$cid]);

        // Same rule as the Painel/overview: a forum only counts once at least
        // one student has been graded on it.
        $forum_ids = array_values(array_filter(array_map(function($it){
            return $it->itemmodule === 'forum' ? (int)$it->id : null;
        }, $items)));
        $forum_has_grade = [];
        if ($forum_ids) {
            list($fq,$fp) = $DB->get_in_or_equal($forum_ids, SQL_PARAMS_NAMED, 'fg');
            foreach ($DB->get_records_sql(
                "SELECT DISTINCT itemid FROM {grade_grades} WHERE itemid $fq AND finalgrade IS NOT NULL", $fp) as $r) {
                $forum_has_grade[(int)$r->itemid] = 1;
            }
        }
        $items = array_filter($items, function($it) use ($forum_has_grade) {
            return $it->itemmodule !== 'forum' || !empty($forum_has_grade[(int)$it->id]);
        });

        $out = [];
        foreach ($items as $it) {
            $mod = $it->itemmodule; $inst = (int)$it->iteminstance;

            // cmid for deep links.
            $cmid = (int)$DB->get_field_sql(
                "SELECT cm.id FROM {course_modules} cm
                 JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :cid AND m.name = :mod AND cm.instance = :inst",
                ['cid'=>$cid, 'mod'=>$mod, 'inst'=>$inst]);

            $submitted = 0; $graded = 0; $pending = 0;
            if ($mod === 'assign') {
                $submitted = (int)$DB->count_records_select('assign_submission',
                    "assignment = :a AND latest = 1 AND status = 'submitted'", ['a'=>$inst]);
                $graded = (int)$DB->get_field_sql(
                    "SELECT COUNT(g.id) FROM {grade_grades} g
                     WHERE g.itemid = :it AND g.finalgrade IS NOT NULL", ['it'=>$it->id]);
                $pending = (int)$DB->get_field_sql(
                    "SELECT COUNT(s.id) FROM {assign_submission} s
                     LEFT JOIN {grade_grades} g ON g.itemid = :it AND g.userid = s.userid
                          AND g.finalgrade IS NOT NULL
                     WHERE s.assignment = :a AND s.latest = 1 AND s.status = 'submitted'
                       AND g.id IS NULL", ['it'=>$it->id, 'a'=>$inst]);
            } else if ($mod === 'quiz') {
                $rk = $DB->sql_concat('userid', "'_'", 'quiz');
                $submitted = (int)$DB->get_field_sql(
                    "SELECT COUNT(DISTINCT $rk) FROM {quiz_attempts}
                     WHERE quiz = :q AND state = 'finished'", ['q'=>$inst]);
                $graded = (int)$DB->get_field_sql(
                    "SELECT COUNT(g.id) FROM {grade_grades} g
                     WHERE g.itemid = :it AND g.finalgrade IS NOT NULL", ['it'=>$it->id]);
                $pending = (int)$DB->get_field_sql(
                    "SELECT COUNT(DISTINCT $rk) FROM {quiz_attempts}
                     WHERE quiz = :q AND state = 'finished' AND sumgrades IS NULL", ['q'=>$inst]);
            } else {
                $graded = (int)$DB->get_field_sql(
                    "SELECT COUNT(g.id) FROM {grade_grades} g
                     WHERE g.itemid = :it AND g.finalgrade IS NOT NULL", ['it'=>$it->id]);
                $submitted = $graded;
            }

            $grade_url = '';
            if ($cmid) {
                $grade_url = $mod === 'assign'
                    ? (new moodle_url('/mod/assign/view.php', ['id'=>$cmid, 'action'=>'grading']))->out(false)
                    : (new moodle_url('/mod/'.$mod.'/view.php', ['id'=>$cmid]))->out(false);
            }

            $out[] = [
                'itemid'    => (int)$it->id,
                'name'      => $it->itemname ?: get_string('sem_nome', 'local_mulima_analytics'),
                'module'    => $mod,
                'grademax'  => round((float)$it->grademax, 1),
                'submitted' => $submitted,
                'graded'    => $graded,
                'pending'   => $pending,
                'grade_url' => $grade_url,
            ];
        }
        echo json_encode(['ok'=>true, 'acts'=>$out]);
        break;

    // ── Submissions of one gradable activity ──────────────────────
    case 'detail':
        $itemid = required_param('itemid', PARAM_INT);
        $it = $DB->get_record('grade_items', ['id'=>$itemid], '*', MUST_EXIST);
        $mod = $it->itemmodule; $inst = (int)$it->iteminstance;
        $now = time();

        $rows = [];
        if ($mod === 'assign') {
            $rows = $DB->get_records_sql(
                "SELECT s.id AS sid, u.id AS userid, u.firstname, u.lastname, u.email,
                        s.timemodified AS ts, g.finalgrade
                 FROM {assign_submission} s
                 JOIN {user} u ON u.id = s.userid AND u.deleted = 0
                 LEFT JOIN {grade_grades} g ON g.itemid = :it AND g.userid = s.userid
                 WHERE s.assignment = :a AND s.latest = 1 AND s.status = 'submitted'
                 ORDER BY g.finalgrade IS NULL DESC, s.timemodified ASC",
                ['it'=>$itemid, 'a'=>$inst]);
        } else if ($mod === 'quiz') {
            $rows = $DB->get_records_sql(
                "SELECT qa.id AS sid, u.id AS userid, u.firstname, u.lastname, u.email,
                        MAX(qa.timefinish) AS ts, g.finalgrade
                 FROM {quiz_attempts} qa
                 JOIN {user} u ON u.id = qa.userid AND u.deleted = 0
                 LEFT JOIN {grade_grades} g ON g.itemid = :it AND g.userid = qa.userid
                 WHERE qa.quiz = :q AND qa.state = 'finished'
                 GROUP BY qa.id, u.id, u.firstname, u.lastname, u.email, g.finalgrade
                 ORDER BY ts ASC",
                ['it'=>$itemid, 'q'=>$inst]);
            // dedupe per user (keep latest finish).
            $byuser = [];
            foreach ($rows as $r) {
                $uid = (int)$r->userid;
                if (!isset($byuser[$uid]) || $r->ts > $byuser[$uid]->ts) $byuser[$uid] = $r;
            }
            $rows = $byuser;
        } else {
            $rows = $DB->get_records_sql(
                "SELECT g.id AS sid, u.id AS userid, u.firstname, u.lastname, u.email,
                        g.timemodified AS ts, g.finalgrade
                 FROM {grade_grades} g
                 JOIN {user} u ON u.id = g.userid AND u.deleted = 0
                 WHERE g.itemid = :it AND g.finalgrade IS NOT NULL
                 ORDER BY g.finalgrade DESC", ['it'=>$itemid]);
        }

        $out = [];
        foreach ($rows as $r) {
            $gradedflag = $r->finalgrade !== null;
            $days = $r->ts ? (int)floor(($now - (int)$r->ts) / 86400) : 0;
            $out[] = [
                'fullname'    => trim($r->firstname . ' ' . $r->lastname),
                'email'       => $r->email,
                'submitted_at'=> $r->ts ? userdate((int)$r->ts, '%d/%m/%Y %H:%M') : null,
                'days_wait'   => $gradedflag ? 0 : $days,
                'grade'       => $gradedflag ? round((float)$r->finalgrade, 1) : null,
                'graded'      => $gradedflag,
            ];
        }
        echo json_encode(['ok'=>true, 'students'=>$out,
            'grademax'=>round((float)$it->grademax,1), 'name'=>$it->itemname]);
        break;

    default:
        echo json_encode(['ok'=>false, 'error'=>get_string('invalid_operation', 'local_mulima_analytics')]);

}} catch (\Throwable $e) {
    error_log('local_mulima_analytics cobertura_ajax: ' . $e->getMessage());
    echo json_encode(['ok'=>false, 'error'=>get_string('ajax_error', 'local_mulima_analytics')]);
}
