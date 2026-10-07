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
 * Student inactivity report export.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');
\local_mulima_analytics\local\access::require_export('risk');
$PAGE->set_context(context_system::instance());
require_sesskey();
\local_mulima_analytics\local\audit::export('risk', 'risco_export.php');
\core\session\manager::write_close();

$period   = required_param('period', PARAM_INT);
$catid    = optional_param('catid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$days = max(1, min(365, optional_param('days', 7, PARAM_INT)));
$scope = optional_param('scope', 'platform', PARAM_ALPHA) === 'courses' ? 'courses' : 'platform';
$data = \local_mulima_analytics\local\risk_report::get($period, $catid, $courseid, $days, $scope, null, optional_param('descendants', false, PARAM_BOOL));
$rows_out = [];
foreach ($data['students'] as $student) {
    $elapsed = $student['days_since'];
    $rows_out[] = [$student['fullname'], $student['email'], $student['phone'] ?? '',
        $student['coursename'], $student['catname'], $student['last_access'] ?? get_string('risk_no_access_record', 'local_mulima_analytics'),
        $elapsed, get_string($elapsed > 30 ? 'critico' : ($elapsed > 15 ? 'alerta' : 'aviso'), 'local_mulima_analytics')];
}

$workbook = new MoodleExcelWorkbook('risco_estudantes.xlsx');
$sheet    = $workbook->add_worksheet(get_string('nav_risco_l', 'local_mulima_analytics'));

$hfmt = $workbook->add_format(['bold'=>1,'size'=>11,'color'=>'FFFFFF','bg_color'=>'991B1B']);
$bfmt = $workbook->add_format(['bold'=>1,'size'=>11]);
$nfmt = $workbook->add_format(['size'=>10]);
$nfmt_c = $workbook->add_format(['size'=>10,'align'=>'center']);
$red_fmt = $workbook->add_format(['size'=>10,'align'=>'center','bold'=>1,'color'=>'DC2626']);
$amb_fmt = $workbook->add_format(['size'=>10,'align'=>'center','bold'=>1,'color'=>'D97706']);
$blu_fmt = $workbook->add_format(['size'=>10,'align'=>'center','bold'=>1,'color'=>'2563EB']);

$sheet->write_string(0, 0, get_string('relatorio_risco_abandono', 'local_mulima_analytics'), $bfmt);
$sheet->write_string(1, 0, get_string('sem_acesso_ha_mais', 'local_mulima_analytics', $days).' · Gerado: '.userdate(time(),'%d/%m/%Y %H:%M'), $nfmt);

$sheet->write_string(2, 0, get_string('risk_management_title', 'local_mulima_analytics').' | '.
    get_string($scope === 'courses' ? 'risk_scope_courses' : 'risk_scope_platform', 'local_mulima_analytics'), $nfmt);

$headers = ['#',get_string('estudante','local_mulima_analytics'),get_string('email','local_mulima_analytics'),get_string('contacto','local_mulima_analytics'),get_string('disciplina','local_mulima_analytics'),get_string('categoria','local_mulima_analytics'),get_string('ultimo_acesso','local_mulima_analytics'),get_string('dias_sem_acesso','local_mulima_analytics'),get_string('risk_level','local_mulima_analytics')];
foreach ($headers as $col=>$h) $sheet->write_string(3,$col,$h,$hfmt);
$sheet->set_column(0,0,5); $sheet->set_column(1,1,32); $sheet->set_column(2,2,32);
$sheet->set_column(3,3,18); $sheet->set_column(4,4,36); $sheet->set_column(5,5,22);
$sheet->set_column(6,6,20); $sheet->set_column(7,7,16); $sheet->set_column(8,8,14);

$i = 0;
foreach ($rows_out as $r) {
    $row = 4 + $i;
    $sheet->write_number($row, 0, $i+1, $nfmt_c);
    $sheet->write_string($row, 1, $r[0], $nfmt);
    $sheet->write_string($row, 2, $r[1], $nfmt);
    $sheet->write_string($row, 3, $r[2] !== '' ? $r[2] : '-', $nfmt_c);
    $sheet->write_string($row, 4, $r[3], $nfmt);
    $sheet->write_string($row, 5, $r[4], $nfmt);
    $sheet->write_string($row, 6, $r[5], $nfmt);
    $dfmt = $r[6] > 30 ? $red_fmt : ($r[6] > 15 ? $amb_fmt : $blu_fmt);
    $sheet->write_string($row, 7, $r[6] >= 9999 ? get_string('risk_no_access_record', 'local_mulima_analytics') : get_string('x_dias', 'local_mulima_analytics', $r[6]), $dfmt);
    $sheet->write_string($row, 8, $r[7], $nfmt_c);
    $i++;
}
$workbook->close();
