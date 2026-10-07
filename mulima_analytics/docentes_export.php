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
 * Teacher activity report export.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');
\local_mulima_analytics\local\access::require_export('teachers');
$PAGE->set_context(context_system::instance());
require_sesskey();
\local_mulima_analytics\local\audit::export('teachers', 'docentes_export.php');
\core\session\manager::write_close();

$period   = required_param('period', PARAM_INT);
$catid    = optional_param('catid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$range    = max(1, min(365, optional_param('range', 30, PARAM_INT)));
$scoring = \local_mulima_analytics\local\teacher_scoring::settings();
$rows = \local_mulima_analytics\local\teachers_report::get($period, $catid, $courseid, $range, null, optional_param('descendants', false, PARAM_BOOL), $scoring);

$workbook = new MoodleExcelWorkbook('actividade_docentes.xlsx');
$sheet = $workbook->add_worksheet(get_string('nav_docentes_l', 'local_mulima_analytics'));
$hfmt = $workbook->add_format(['bold'=>1,'size'=>11,'color'=>'FFFFFF','bg_color'=>'1D4ED8']);
$bfmt = $workbook->add_format(['bold'=>1,'size'=>11]);
$nfmt = $workbook->add_format(['size'=>10]);
$nfmt_c = $workbook->add_format(['size'=>10,'align'=>'center']);
$nfmt_n = $workbook->add_format(['size'=>10,'align'=>'center','num_format'=>'#,##0']);
$nfmt_pct = $workbook->add_format(['size'=>10,'align'=>'center','num_format'=>'0.0"%"']);
$hi_fmt = $workbook->add_format(['size'=>10,'align'=>'center','bold'=>1,'color'=>'15803D','bg_color'=>'DCFCE7']);
$md_fmt = $workbook->add_format(['size'=>10,'align'=>'center','bold'=>1,'color'=>'92400E','bg_color'=>'FEF3C7']);
$lo_fmt = $workbook->add_format(['size'=>10,'align'=>'center','bold'=>1,'color'=>'991B1B','bg_color'=>'FECACA']);

$sheet->write_string(0,0,get_string('relatorio_actividade_docentes', 'local_mulima_analytics'), $bfmt);
$sheet->write_string(1,0,get_string('ultimos_x_dias', 'local_mulima_analytics', $range).' · Gerado: '.userdate(time(),'%d/%m/%Y %H:%M'), $nfmt);
$sheet->write_string(2,0,get_string('doc_assign_help', 'local_mulima_analytics').' '.get_string('doc_forum_help', 'local_mulima_analytics'), $nfmt);
$headers = ['#',get_string('docente','local_mulima_analytics'),get_string('email','local_mulima_analytics'),
    get_string('disciplinas_s','local_mulima_analytics'),get_string('actividades_criadas','local_mulima_analytics'),
    get_string('recursos_publicados','local_mulima_analytics'),get_string('doc_assign_own_header','local_mulima_analytics'),
    get_string('doc_forums_total','local_mulima_analytics'),get_string('doc_forums_participated','local_mulima_analytics'),
    get_string('doc_forum_messages','local_mulima_analytics'),get_string('doc_forum_topics','local_mulima_analytics'),
    get_string('doc_forum_replies','local_mulima_analytics'),get_string('doc_forum_last','local_mulima_analytics'),
    get_string('ultimo_acesso','local_mulima_analytics'),get_string('pontuacao','local_mulima_analytics'),get_string('nivel','local_mulima_analytics'),
    get_string('doc_assign_total','local_mulima_analytics'),get_string('doc_assign_received','local_mulima_analytics'),
    get_string('doc_assign_corrected','local_mulima_analytics'),get_string('doc_assign_pending','local_mulima_analytics'),
    get_string('doc_assign_percent','local_mulima_analytics'),get_string('doc_assign_progress','local_mulima_analytics')];
foreach ($headers as $c=>$h) $sheet->write_string(3,$c,$h,$hfmt);
foreach ([5,32,32,40,20,20,36,20,25,25,22,16,30,20,12,12,22,22,24,18,18,22] as $c=>$w) $sheet->set_column($c,$c,$w);

foreach ($rows as $i=>$r) {
    $row=4+$i;
    $sheet->write_number($row,0,$i+1,$nfmt_c);
    $sheet->write_string($row,1,$r['fullname'],$nfmt);
    $sheet->write_string($row,2,$r['email'],$nfmt);
    $sheet->write_string($row,3,implode(', ',$r['courses']),$nfmt);
    $sheet->write_number($row,4,$r['activities'],$nfmt_n);
    $sheet->write_number($row,5,$r['resources'],$nfmt_n);
    $sheet->write_number($row,6,$r['graded'],$nfmt_n);
    $sheet->write_number($row,7,$r['forums_total'],$nfmt_n);
    $sheet->write_number($row,8,$r['forums_participated'],$nfmt_n);
    $sheet->write_number($row,9,$r['forum_posts'],$nfmt_n);
    $sheet->write_number($row,10,$r['forum_discussions'],$nfmt_n);
    $sheet->write_number($row,11,$r['forum_replies'],$nfmt_n);
    $sheet->write_string($row,12,$r['forum_last_post']??get_string('doc_forum_no_posts','local_mulima_analytics'),$nfmt);
    $sheet->write_string($row,13,$r['last_access']??'-',$nfmt);
    $sfmt=$r['score_level']==='high'?$hi_fmt:($r['score_level']==='moderate'?$md_fmt:($r['score_level']==='low'?$lo_fmt:$nfmt_c));
    $sheet->write_number($row,14,$r['score'],$sfmt);
    $sheet->write_string($row,15,get_string('score_level_'.$r['score_level'],'local_mulima_analytics'),$sfmt);
    $sheet->write_number($row,16,$r['assignments_total'],$nfmt_n);
    $sheet->write_number($row,17,$r['submissions_total'],$nfmt_n);
    $sheet->write_number($row,18,$r['submissions_graded'],$nfmt_n);
    $sheet->write_number($row,19,$r['submissions_pending'],$nfmt_n);
    if ($r['grading_percent'] !== null) { $sheet->write_number($row,20,$r['grading_percent'],$nfmt_pct); }
    $sheet->write_string($row,21,get_string('doc_assign_'.$r['grading_level'],'local_mulima_analytics'),$nfmt);
}

// Each assignment appears once, even when it is shared by several teachers.
$detail = $workbook->add_worksheet(get_string('doc_assign_sheet','local_mulima_analytics'));
$detail->write_string(0,0,get_string('doc_assign_title','local_mulima_analytics'),$bfmt);
$detail->write_string(1,0,get_string('doc_assign_help','local_mulima_analytics'),$nfmt);
$detailheaders = ['disciplinas_s','doc_assign_name','doc_assign_type','doc_assign_received',
    'doc_assign_corrected','doc_assign_pending','doc_assign_percent','doc_assign_progress'];
foreach ($detailheaders as $c=>$key) { $detail->write_string(3,$c,get_string($key,'local_mulima_analytics'),$hfmt); }
foreach ([36,42,26,22,24,18,18,22] as $c=>$w) { $detail->set_column($c,$c,$w); }
$seen = [];
$dr = 4;
foreach ($rows as $r) {
    foreach ($r['assignments'] as $a) {
        if (isset($seen[$a['id']])) { continue; }
        $seen[$a['id']] = true;
        $detail->write_string($dr,0,$a['coursename'],$nfmt);
        $detail->write_string($dr,1,$a['name'],$nfmt);
        $type = $a['offline'] ? 'offline' : ($a['team'] ? 'team' : 'individual');
        $detail->write_string($dr,2,get_string('doc_assign_'.$type,'local_mulima_analytics'),$nfmt);
        $detail->write_number($dr,3,$a['submitted'],$nfmt_n);
        $detail->write_number($dr,4,$a['corrected'],$nfmt_n);
        $detail->write_number($dr,5,$a['pending'],$nfmt_n);
        if ($a['percent'] !== null) { $detail->write_number($dr,6,$a['percent'],$nfmt_pct); }
        $detail->write_string($dr,7,get_string('doc_assign_'.$a['level'],'local_mulima_analytics'),$nfmt);
        $dr++;
    }
}
// One row per teacher/forum: inventory remains present even with no topics/posts.
$forumdetail = $workbook->add_worksheet(get_string('doc_forum_sheet','local_mulima_analytics'));
$forumdetail->write_string(0,0,get_string('doc_forum_title','local_mulima_analytics'),$bfmt);
$forumdetail->write_string(1,0,get_string('doc_forum_period','local_mulima_analytics',$range),$nfmt);
$forumdetail->write_string(2,0,get_string('doc_forum_help','local_mulima_analytics'),$nfmt);
$forumheaders = ['docente','email','disciplinas_s','doc_forum_name','doc_forum_existing_topics',
    'doc_forum_visibility','doc_forum_participation','doc_forum_messages','doc_forum_topics',
    'doc_forum_replies','doc_forum_last'];
foreach ($forumheaders as $c=>$key) { $forumdetail->write_string(3,$c,get_string($key,'local_mulima_analytics'),$hfmt); }
foreach ([32,32,40,40,22,22,36,22,22,18,28] as $c=>$w) { $forumdetail->set_column($c,$c,$w); }
$fr = 4;
foreach ($rows as $r) {
    foreach ($r['forums'] as $f) {
        $forumdetail->write_string($fr,0,$r['fullname'],$nfmt);
        $forumdetail->write_string($fr,1,$r['email'],$nfmt);
        $forumdetail->write_string($fr,2,$f['coursename'],$nfmt);
        $forumdetail->write_string($fr,3,$f['name'],$nfmt);
        $forumdetail->write_number($fr,4,$f['topics'],$nfmt_n);
        $forumdetail->write_string($fr,5,get_string($f['visible']?'doc_forum_visible':'doc_forum_hidden','local_mulima_analytics'),$nfmt);
        $forumdetail->write_string($fr,6,get_string($f['participated']?'doc_forum_with_participation':'doc_forum_without_participation','local_mulima_analytics'),$nfmt);
        $forumdetail->write_number($fr,7,$f['posts'],$nfmt_n);
        $forumdetail->write_number($fr,8,$f['discussions'],$nfmt_n);
        $forumdetail->write_number($fr,9,$f['replies'],$nfmt_n);
        $forumdetail->write_string($fr,10,$f['last_post']??get_string('doc_forum_no_posts','local_mulima_analytics'),$nfmt);
        $fr++;
    }
}
// Values, weights and contributions in one row per teacher/course.
$scoresheet = $workbook->add_worksheet(get_string('score_sheet','local_mulima_analytics'));
$scoresheet->write_string(0,0,get_string('score_dialog_title','local_mulima_analytics'),$bfmt);
$scoresheet->write_string(1,0,get_string('score_period','local_mulima_analytics',$range),$nfmt);
$scoresheet->write_string(2,0,get_string('score_scope_note','local_mulima_analytics'),$nfmt);
$scoresheet->write_string(3,0,get_string('score_maximum','local_mulima_analytics').': '.$scoring['maximum'].'; '.
    get_string('score_moderate','local_mulima_analytics').': '.$scoring['moderate'].'; '.
    get_string('score_high','local_mulima_analytics').': '.$scoring['high'],$nfmt);
$scoreheaders = [get_string('docente','local_mulima_analytics'),get_string('email','local_mulima_analytics'),get_string('score_course','local_mulima_analytics')];
foreach (\local_mulima_analytics\local\teacher_scoring::criteria() as $criterion=>$setting) {
    foreach (['count','weight','points'] as $part) {
        $scoreheaders[] = get_string('score_'.$criterion,'local_mulima_analytics').' / '.get_string('score_'.$part,'local_mulima_analytics');
    }
}
foreach (['score_raw','score_maximum','score_final','nivel'] as $key) { $scoreheaders[] = get_string($key,'local_mulima_analytics'); }
foreach ($scoreheaders as $c=>$header) {
    $scoresheet->write_string(5,$c,$header,$hfmt);
    $scoresheet->set_column($c,$c,$c<3?36:24);
}
$sr = 6;
foreach ($rows as $r) {
    foreach ($r['course_scores'] as $course) {
        $scoresheet->write_string($sr,0,$r['fullname'],$nfmt);
        $scoresheet->write_string($sr,1,$r['email'],$nfmt);
        $scoresheet->write_string($sr,2,$course['coursename'],$nfmt);
        $col = 3;
        foreach ($course['score_components'] as $component) {
            foreach (['count','weight','points'] as $part) { $scoresheet->write_number($sr,$col++,$component[$part],$nfmt_n); }
        }
        $scoresheet->write_number($sr,$col++,$course['score_raw'],$nfmt_n);
        $scoresheet->write_number($sr,$col++,$course['score_max'],$nfmt_n);
        $scoresheet->write_number($sr,$col++,$course['score'],$nfmt_n);
        $scoresheet->write_string($sr,$col,get_string('score_level_'.$course['score_level'],'local_mulima_analytics'),$nfmt);
        $sr++;
    }
}
$workbook->close();
