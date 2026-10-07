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
 * Activity access report export.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');
\local_mulima_analytics\local\access::require_export('accesses');
$PAGE->set_context(context_system::instance());
require_sesskey();
\local_mulima_analytics\local\audit::export('accesses', 'acessos_export.php');

$op       = required_param('op', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$cmid     = optional_param('cmid', 0, PARAM_INT);
$period   = optional_param('period', 0, PARAM_INT);
$catid    = optional_param('catid', 0, PARAM_INT);

// Resolve the complete export scope before sending any workbook headers.
$scopecourses = [];
if ($op === 'panoramic') {
    $rootcat = \local_mulima_analytics\local\access_filters::root($period, $catid);
    $scopecourses = \local_mulima_analytics\local\access_filters::courses($period, $catid, 0, optional_param('descendants', false, PARAM_BOOL));
}
\core\session\manager::write_close();

// MoodleExcelWorkbook::$filename is protected - it can only be set via the
// constructor, never assigned afterwards. Work out the filename for this
// request up front, before the workbook object is created.
$fname = 'acessos_export.xlsx';
if ($op === 'activities' && $courseid) {
    $co = $DB->get_record('course', ['id' => $courseid], 'id,shortname');
    if ($co) $fname = 'acessos_' . clean_filename($co->shortname) . '.xlsx';
} else if ($op === 'detail' && $cmid && $courseid) {
    $cm = $DB->get_record_sql(
        "SELECT cm.id, m.name AS modname, cm.instance
         FROM {course_modules} cm JOIN {modules} m ON m.id=cm.module
         WHERE cm.id = :id", ['id'=>$cmid]);
    $act_name = '';
    if ($cm) {
        try { $r=$DB->get_record($cm->modname,['id'=>$cm->instance],'id,name'); if($r)$act_name=$r->name; } catch(\Throwable $e){}
    }
    $fname = 'acessos_detalhe_' . clean_filename($act_name ?: 'actividade') . '.xlsx';
} else if ($op === 'panoramic' && ($period || $catid)) {
    $fname = 'acessos_panoramica_' . clean_filename($rootcat ? $rootcat->name : 'periodo') . '.xlsx';
}

$workbook = new MoodleExcelWorkbook($fname);
$sheet    = $workbook->add_worksheet(get_string('nav_acessos_l', 'local_mulima_analytics'));

// Styles
$hfmt   = $workbook->add_format(['bold'=>1,'size'=>11,'color'=>'FFFFFF','bg_color'=>'0369A1']);
$bfmt   = $workbook->add_format(['bold'=>1,'size'=>10]);
$nfmt   = $workbook->add_format(['size'=>10]);
$nfmt_c = $workbook->add_format(['size'=>10,'align'=>'center']);
$nfmt_n = $workbook->add_format(['size'=>10,'align'=>'center','num_format'=>'#,##0']);

// NOTE: criteria kept identical to acessos_ajax.php (contextlevel=70, action='viewed')
// so the Excel numbers always match the on-screen numbers.

if ($op === 'activities' && $courseid) {
    $course = $DB->get_record('course', ['id' => $courseid], 'id,fullname,shortname');
    if (!$course) { $workbook->close(); exit; }

    $sheet->write_string(0, 0, get_string('relatorio_acessos_actividades', 'local_mulima_analytics'), $bfmt);
    $sheet->write_string(1, 0, $course->fullname, $nfmt);
    $sheet->write_string(1, 3, get_string('gerado_x', 'local_mulima_analytics', userdate(time(), '%d/%m/%Y %H:%M')), $nfmt);

    $headers = ['#', get_string('actividade','local_mulima_analytics'), get_string('tipo','local_mulima_analytics'), get_string('seccao','local_mulima_analytics'), get_string('utilizadores_unicos','local_mulima_analytics'), get_string('total_visualizacoes','local_mulima_analytics')];
    foreach ($headers as $col => $h) $sheet->write_string(3, $col, $h, $hfmt);
    $sheet->set_column(0,0,5); $sheet->set_column(1,1,40); $sheet->set_column(2,2,18);
    $sheet->set_column(3,3,20); $sheet->set_column(4,4,20); $sheet->set_column(5,5,20);

    // Batch: all log stats for the course in one query
    $log_stats = [];
    $lrows = $DB->get_records_sql(
        "SELECT l.contextinstanceid AS cmid, COUNT(*) AS v, COUNT(DISTINCT l.userid) AS u
         FROM {logstore_standard_log} l
         WHERE l.courseid = :cid AND l.contextlevel = 70 AND l.action = 'viewed'
         GROUP BY l.contextinstanceid", ['cid'=>$courseid]);
    foreach ($lrows as $r) $log_stats[(int)$r->cmid] = [(int)$r->v, (int)$r->u];

    $cms = $DB->get_records_sql(
        "SELECT cm.id, cm.instance, m.name AS modname, cs.name AS section_name, cs.section AS section_num
         FROM {course_modules} cm
         JOIN {modules} m ON m.id = cm.module
         JOIN {course_sections} cs ON cs.id = cm.section
         WHERE cm.course = :cid AND cm.visible = 1
         ORDER BY cs.section, cm.id", ['cid'=>$courseid]);

    // Batch: activity names, one IN() query per module type
    $by_type = [];
    foreach ($cms as $cm) $by_type[$cm->modname][(int)$cm->instance] = true;
    $names = [];
    foreach ($by_type as $modname => $instances) {
        try {
            list($insql, $inparams) = $DB->get_in_or_equal(array_keys($instances), SQL_PARAMS_NAMED, 'i');
            $recs = $DB->get_records_select($modname, "id $insql", $inparams, '', 'id,name');
            foreach ($recs as $r) $names[$modname][(int)$r->id] = $r->name;
        } catch (\Throwable $e) {}
    }

    $rows = [];
    foreach ($cms as $cm) {
        $name = $names[$cm->modname][(int)$cm->instance] ?? null;
        if (empty($name)) continue;
        $st = $log_stats[(int)$cm->id] ?? [0, 0];
        $rows[] = [$name, $cm->modname, ($cm->section_name ?: get_string('section_n', 'local_mulima_analytics', $cm->section_num)), $st[1], $st[0]];
    }
    usort($rows, function($a, $b) {
        return $b[4] <=> $a[4];
    });
    foreach ($rows as $i => $r) {
        $row = 4 + $i;
        $sheet->write_number($row,0,$i+1,$nfmt_c);
        $sheet->write_string($row,1,$r[0],$nfmt);
        $sheet->write_string($row,2,$r[1],$nfmt);
        $sheet->write_string($row,3,$r[2],$nfmt);
        $sheet->write_number($row,4,$r[3],$nfmt_n);
        $sheet->write_number($row,5,$r[4],$nfmt_n);
    }

} else if ($op === 'detail' && $cmid && $courseid) {
    $course = $DB->get_record('course', ['id' => $courseid], 'id,fullname,shortname');
    if (!$course) { $workbook->close(); exit; }
    $cm = $DB->get_record_sql(
        "SELECT cm.id, m.name AS modname, cm.instance
         FROM {course_modules} cm JOIN {modules} m ON m.id=cm.module
         WHERE cm.id = :id", ['id'=>$cmid]);
    $act_name = '';
    if ($cm) {
        try { $r=$DB->get_record($cm->modname,['id'=>$cm->instance],'id,name'); if($r)$act_name=$r->name; } catch(\Throwable $e){}
    }

    $sheet->write_string(0,0,get_string('relatorio_acessos_detalhe', 'local_mulima_analytics'), $bfmt);
    $sheet->write_string(1,0,$course->fullname . ' · ' . $act_name, $nfmt);
    $sheet->write_string(1,4,get_string('gerado_x', 'local_mulima_analytics', userdate(time(),'%d/%m/%Y %H:%M')), $nfmt);

    $headers = ['#',get_string('nome_estudante','local_mulima_analytics'),get_string('email','local_mulima_analytics'),get_string('visualizacoes','local_mulima_analytics'),get_string('primeiro_acesso','local_mulima_analytics'),get_string('ultimo_acesso','local_mulima_analytics')];
    foreach ($headers as $col=>$h) $sheet->write_string(3,$col,$h,$hfmt);
    $sheet->set_column(0,0,5); $sheet->set_column(1,1,35); $sheet->set_column(2,2,35);
    $sheet->set_column(3,3,16); $sheet->set_column(4,4,20); $sheet->set_column(5,5,20);

    // Single JOIN query - same criteria as the on-screen modal
    $rows = $DB->get_records_sql(
        "SELECT l.userid, u.firstname, u.lastname, u.email,
                COUNT(*) AS views,
                MIN(l.timecreated) AS first_access,
                MAX(l.timecreated) AS last_access
         FROM {logstore_standard_log} l
         JOIN {user} u ON u.id = l.userid AND u.deleted = 0
         WHERE l.contextinstanceid = :cmid AND l.contextlevel = 70 AND l.action = 'viewed'
         GROUP BY l.userid, u.firstname, u.lastname, u.email
         ORDER BY views DESC", ['cmid'=>$cmid]);
    $i = 0;
    foreach ($rows as $r) {
        $row = 4 + $i;
        $sheet->write_number($row,0,$i+1,$nfmt_c);
        $sheet->write_string($row,1,trim($r->firstname.' '.$r->lastname),$nfmt);
        $sheet->write_string($row,2,$r->email,$nfmt);
        $sheet->write_number($row,3,(int)$r->views,$nfmt_n);
        $sheet->write_string($row,4,userdate($r->first_access,'%d/%m/%Y %H:%M'),$nfmt);
        $sheet->write_string($row,5,userdate($r->last_access,'%d/%m/%Y %H:%M'),$nfmt);
        $i++;
    }

} else if ($op === 'panoramic' && ($period || $catid)) {

    $sheet->write_string(0,0,get_string('relatorio_acessos_todas', 'local_mulima_analytics'), $bfmt);
    $sheet->write_string(1,0,$rootcat ? $rootcat->name : '', $nfmt);
    $sheet->write_string(1,4,get_string('gerado_x', 'local_mulima_analytics', userdate(time(),'%d/%m/%Y %H:%M')), $nfmt);

    $headers = ['#',get_string('disciplina','local_mulima_analytics'),get_string('codigo','local_mulima_analytics'),get_string('categoria','local_mulima_analytics'),get_string('estudantes_com_acesso','local_mulima_analytics'),get_string('inscritos','local_mulima_analytics'),get_string('cobertura_pct','local_mulima_analytics'),get_string('visualizacoes','local_mulima_analytics')];
    foreach ($headers as $col=>$h) $sheet->write_string(3,$col,$h,$hfmt);
    $sheet->set_column(0,0,5); $sheet->set_column(1,1,42); $sheet->set_column(2,2,14);
    $sheet->set_column(3,3,26); $sheet->set_column(4,4,18); $sheet->set_column(5,5,12);
    $sheet->set_column(6,6,13); $sheet->set_column(7,7,15);

    $all = $scopecourses;

    if ($all) {
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
        $cat_ids = array_unique(array_map(function($co) {
            return (int)$co->category;
        }, $all));
        list($cnsql, $cnp) = $DB->get_in_or_equal($cat_ids, SQL_PARAMS_NAMED, 'cn');
        $catrecs = $DB->get_records_select('course_categories', "id $cnsql", $cnp, '', 'id,name');
        $cnames = []; foreach ($catrecs as $cr) $cnames[$cr->id] = $cr->name;

        $out = [];
        foreach ($all as $co) {
            $cid = (int)$co->id; $st = $vmap[$cid] ?? [0, 0]; $enr = $emap[$cid] ?? 0;
            $out[] = [$co->fullname, $co->shortname, $cnames[(int)$co->category] ?? '',
                      $st[1], $enr, $enr ? round($st[1]/$enr*100) : 0, $st[0]];
        }
        usort($out, function($a, $b) {
            return $b[6] <=> $a[6];
        });
        foreach ($out as $i => $r) {
            $row = 4 + $i;
            $sheet->write_number($row,0,$i+1,$nfmt_c);
            $sheet->write_string($row,1,$r[0],$nfmt);
            $sheet->write_string($row,2,$r[1],$nfmt);
            $sheet->write_string($row,3,$r[2],$nfmt);
            $sheet->write_number($row,4,$r[3],$nfmt_n);
            $sheet->write_number($row,5,$r[4],$nfmt_n);
            $sheet->write_number($row,6,$r[5],$nfmt_c);
            $sheet->write_number($row,7,$r[6],$nfmt_n);
        }
    }
}

$workbook->close();
