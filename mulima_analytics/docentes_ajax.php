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
 * Teacher activity report data endpoint.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/accesslib.php');
\local_mulima_analytics\local\access::require_report('teachers');
$PAGE->set_context(context_system::instance());
header('Content-Type: application/json; charset=utf-8');
if (!confirm_sesskey()) { echo json_encode(['ok'=>false,'error'=>'Sesskey']); exit; }

$op = required_param('op', PARAM_ALPHA);
\core\session\manager::write_close();

// Explicit context level values (not PHP constants - reliable in AJAX context)
define('GAC_DOC_CTX_COURSE', 50);

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
        $catid  = optional_param('catid', 0, PARAM_INT);
        $courses = \local_mulima_analytics\local\access_filters::courses($period, $catid, 0, optional_param('descendants', false, PARAM_BOOL));
        $out = [];
        foreach ($courses as $c) $out[] = ['id'=>(int)$c->id, 'name'=>$c->fullname];
        echo json_encode(['ok'=>true, 'courses'=>$out]);
        break;

    case 'teachers':
        $data     = json_decode(file_get_contents('php://input'), true);
        $period   = (int)($data['period'] ?? 0);
        $catid    = (int)($data['catid'] ?? 0);
        $courseid = (int)($data['courseid'] ?? 0);
        $range = max(1, min(365, (int)($data['range'] ?? 30)));
        $scoring = \local_mulima_analytics\local\teacher_scoring::settings();
        $teachers = \local_mulima_analytics\local\teachers_report::get($period, $catid, $courseid, $range, null, !empty($data['descendants']), $scoring);
        echo json_encode(['ok' => true, 'teachers' => $teachers, 'scoring' => $scoring]);
        break;

    default:
        echo json_encode(['ok'=>false, 'error'=>get_string('invalid_operation', 'local_mulima_analytics')]);

}} catch (\Throwable $e) {
    error_log('local_mulima_analytics docentes_ajax: ' . $e->getMessage());
    echo json_encode(['ok'=>false, 'error'=>get_string('ajax_error', 'local_mulima_analytics')]);
}
