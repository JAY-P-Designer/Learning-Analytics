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
 * Assessment coverage report export.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');
\local_mulima_analytics\local\access::require_export('coverage');
$PAGE->set_context(context_system::instance());
require_sesskey();
\local_mulima_analytics\local\audit::export('coverage', 'cobertura_export.php');
\core\session\manager::write_close();

$period = required_param('period', PARAM_INT);
$catid  = optional_param('catid', 0, PARAM_INT);
$target = max(1, optional_param('target', 800, PARAM_INT));

$courses = \local_mulima_analytics\local\access_filters::courses($period, $catid, 0, optional_param('descendants', false, PARAM_BOOL));

$gi = $graded = $asub = $apend = $qsub = $qpend = $catnames = [];
if ($courses) {
    $course_ids = array_map('intval', array_keys($courses));
    foreach ($DB->get_records_list('course_categories', 'id',
        array_unique(array_map(function($c){ return (int)$c->category; }, $courses)), '', 'id,name') as $cc) {
        $catnames[(int)$cc->id] = $cc->name;
    }
    list($cq,$cp) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'co');

    // Same rule as the Painel (index.php): grademax > 0, forums only count once
    // graded, and the points total honours a 'Plano de Avaliação' category.
    $items_by_course = [];
    $all_gi = $DB->get_records_sql(
        "SELECT id, courseid, itemmodule, grademax, categoryid FROM {grade_items}
         WHERE courseid $cq AND itemtype = 'mod' AND grademax > 0
         ORDER BY courseid, sortorder", $cp);
    $forum_ids = array_values(array_filter(array_map(function($g){
        return $g->itemmodule === 'forum' ? (int)$g->id : null;
    }, $all_gi)));
    $forum_has_grade = [];
    if ($forum_ids) {
        list($fq,$fp) = $DB->get_in_or_equal($forum_ids, SQL_PARAMS_NAMED, 'fg');
        foreach ($DB->get_records_sql(
            "SELECT DISTINCT itemid FROM {grade_grades} WHERE itemid $fq AND finalgrade IS NOT NULL", $fp) as $r)
            $forum_has_grade[(int)$r->itemid] = 1;
    }
    foreach ($all_gi as $giobj) {
        if ($giobj->itemmodule === 'forum' && empty($forum_has_grade[(int)$giobj->id])) continue;
        $items_by_course[(int)$giobj->courseid][] = $giobj;
    }

    $pa_categories = []; $pa_totals = [];
    foreach ($DB->get_records_sql(
        "SELECT id, courseid FROM {grade_categories}
         WHERE fullname = 'Plano de Avaliação' AND courseid $cq", $cp) as $pr)
        $pa_categories[(int)$pr->courseid] = (int)$pr->id;
    if ($pa_categories) {
        list($pq,$pp) = $DB->get_in_or_equal(array_values($pa_categories), SQL_PARAMS_NAMED, 'pt');
        foreach ($DB->get_records_sql(
            "SELECT courseid, grademax FROM {grade_items}
             WHERE itemtype = 'category' AND iteminstance $pq", $pp) as $pgi)
            $pa_totals[(int)$pgi->courseid] = (float)$pgi->grademax;
    }

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
    foreach ($items_by_course as $cid => $its) {
        $ids = array_map(function($it){ return (int)$it->id; }, $its);
        if (!$ids) continue;
        list($gq,$gp) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'gd');
        $graded[$cid] = (int)$DB->count_records_select('grade_grades',
            "itemid $gq AND finalgrade IS NOT NULL", $gp);
    }

    list($cq2,$cp2) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'ca');
    foreach ($DB->get_records_sql(
        "SELECT a.course AS courseid, COUNT(s.id) AS n FROM {assign_submission} s
         JOIN {assign} a ON a.id = s.assignment
         WHERE a.course $cq2 AND s.latest = 1 AND s.status = 'submitted' GROUP BY a.course", $cp2) as $r)
        $asub[(int)$r->courseid] = (int)$r->n;

    list($cq3,$cp3) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'cb');
    foreach ($DB->get_records_sql(
        "SELECT a.course AS courseid, COUNT(s.id) AS n FROM {assign_submission} s
         JOIN {assign} a ON a.id = s.assignment
         JOIN {grade_items} i ON i.itemmodule = 'assign' AND i.itemtype = 'mod'
              AND i.iteminstance = a.id AND i.courseid = a.course
         LEFT JOIN {grade_grades} g ON g.itemid = i.id AND g.userid = s.userid AND g.finalgrade IS NOT NULL
         WHERE a.course $cq3 AND s.latest = 1 AND s.status = 'submitted' AND g.id IS NULL
         GROUP BY a.course", $cp3) as $r) $apend[(int)$r->courseid] = (int)$r->n;

    $rk = $DB->sql_concat('qa.quiz', "'_'", 'qa.userid');
    list($cq4,$cp4) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'cd');
    foreach ($DB->get_records_sql(
        "SELECT q.course AS courseid, COUNT(DISTINCT $rk) AS n FROM {quiz_attempts} qa
         JOIN {quiz} q ON q.id = qa.quiz WHERE q.course $cq4 AND qa.state = 'finished'
         GROUP BY q.course", $cp4) as $r) $qsub[(int)$r->courseid] = (int)$r->n;

    list($cq5,$cp5) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'ce');
    foreach ($DB->get_records_sql(
        "SELECT q.course AS courseid, COUNT(DISTINCT $rk) AS n FROM {quiz_attempts} qa
         JOIN {quiz} q ON q.id = qa.quiz
         WHERE q.course $cq5 AND qa.state = 'finished' AND qa.sumgrades IS NULL
         GROUP BY q.course", $cp5) as $r) $qpend[(int)$r->courseid] = (int)$r->n;
}

$workbook = new MoodleExcelWorkbook('Cobertura_Avaliacao_' . date('Ymd'));
$sh = $workbook->add_worksheet(get_string('nav_cobertura_l', 'local_mulima_analytics'));
$fT = $workbook->add_format(['bold'=>1,'size'=>13]);
$fS = $workbook->add_format(['size'=>10,'color'=>'#64748b']);
$fH = $workbook->add_format(['bold'=>1,'size'=>9,'bg_color'=>'#0a1628','color'=>'white','border'=>1,'align'=>'center','v_align'=>'center']);
$fC = $workbook->add_format(['size'=>10,'border'=>1,'v_align'=>'center']);
$fN = $workbook->add_format(['size'=>10,'border'=>1,'align'=>'center','v_align'=>'center']);
$fOk  = $workbook->add_format(['size'=>10,'border'=>1,'align'=>'center','bold'=>1,'color'=>'#15803d','v_align'=>'center']);
$fBad = $workbook->add_format(['size'=>10,'border'=>1,'align'=>'center','bold'=>1,'color'=>'#b91c1c','v_align'=>'center']);
$fWarn= $workbook->add_format(['size'=>10,'border'=>1,'align'=>'center','bold'=>1,'color'=>'#b45309','v_align'=>'center']);

$sh->set_column(0,0,5); $sh->set_column(1,1,45); $sh->set_column(2,2,16);
$sh->set_column(3,3,26); $sh->set_column(4,9,14); $sh->set_column(10,10,18);

$r = 0;
$sh->write_string($r,0,get_string('cobertura_avaliacao_disciplinas', 'local_mulima_analytics'),$fT); $r++;
$sh->write_string($r,0,get_string('meta_gerado_via', 'local_mulima_analytics', (object)['target'=>$target,'date'=>date('d/m/Y H:i')]),$fS); $r+=2;

foreach (['#',get_string('disciplina','local_mulima_analytics'),get_string('codigo','local_mulima_analytics'),get_string('categoria','local_mulima_analytics'),get_string('avaliaveis','local_mulima_analytics'),get_string('pontuacao','local_mulima_analytics'),get_string('meta','local_mulima_analytics'),get_string('submetidos','local_mulima_analytics'),get_string('avaliados','local_mulima_analytics'),get_string('por_corrigir','local_mulima_analytics'),get_string('estado','local_mulima_analytics')] as $i=>$h)
    $sh->write_string($r,$i,$h,$fH);
$sh->set_row($r,20); $r++;

$n=0;
foreach ($courses as $co) {
    $cid=(int)$co->id; $n++;
    $g   = $gi[$cid] ?? ['n'=>0,'pts'=>0];
    $sub = ($asub[$cid] ?? 0) + ($qsub[$cid] ?? 0);
    $pen = ($apend[$cid] ?? 0) + ($qpend[$cid] ?? 0);
    $onTarget = ((int)round($g['pts'])) === $target;
    $estado = $onTarget ? ($pen ? get_string('meta_ok_corrigir', 'local_mulima_analytics') : get_string('conforme', 'local_mulima_analytics'))
                        : ($g['pts'] > $target ? get_string('acima_meta', 'local_mulima_analytics') : get_string('abaixo_meta', 'local_mulima_analytics'));
    $fmt = $onTarget ? ($pen ? $fWarn : $fOk) : $fBad;
    $sh->write_number($r,0,$n,$fN);
    $sh->write_string($r,1,$co->fullname,$fC);
    $sh->write_string($r,2,$co->shortname,$fN);
    $sh->write_string($r,3,$catnames[(int)$co->category]??'',$fC);
    $sh->write_number($r,4,$g['n'],$fN);
    $sh->write_number($r,5,$g['pts'],$onTarget?$fOk:$fBad);
    $sh->write_number($r,6,$target,$fN);
    $sh->write_number($r,7,$sub,$fN);
    $sh->write_number($r,8,$graded[$cid]??0,$fN);
    $sh->write_number($r,9,$pen,$pen?$fBad:$fN);
    $sh->write_string($r,10,$estado,$fmt);
    $r++;
}
$workbook->close();
exit;
