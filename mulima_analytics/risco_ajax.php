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
 * Student inactivity report data endpoint.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/accesslib.php');
\local_mulima_analytics\local\access::require_report('risk');
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
        $catid  = optional_param('catid', 0, PARAM_INT);
        $courses = \local_mulima_analytics\local\access_filters::courses($period, $catid, 0, optional_param('descendants', false, PARAM_BOOL));
        $out = [];
        foreach ($courses as $c) $out[] = ['id'=>(int)$c->id, 'name'=>$c->fullname, 'short'=>$c->shortname];
        echo json_encode(['ok'=>true, 'courses'=>$out]);
        break;

    case 'risk':
        $data     = json_decode(file_get_contents('php://input'), true);
        $period   = (int)($data['period'] ?? 0);
        $catid    = (int)($data['catid'] ?? 0);
        $courseid = (int)($data['courseid'] ?? 0);
        $days = max(1, min(365, (int)($data['days'] ?? 7)));
        $scope = ($data['scope'] ?? 'platform') === 'courses' ? 'courses' : 'platform';
        $result = \local_mulima_analytics\local\risk_report::get($period, $catid, $courseid, $days, $scope, null, !empty($data['descendants']));
        echo json_encode(['ok' => true] + $result);
        break;

    default:
        echo json_encode(['ok'=>false, 'error'=>get_string('invalid_operation', 'local_mulima_analytics')]);

}} catch (\Throwable $e) {
    error_log('local_mulima_analytics risco_ajax: ' . $e->getMessage());
    echo json_encode(['ok'=>false, 'error'=>get_string('ajax_error', 'local_mulima_analytics')]);
}
