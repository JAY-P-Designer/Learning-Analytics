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
 * Attendance list report data endpoint.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
\local_mulima_analytics\local\access::require_report('attendance');
// Config helper: reads local_mulima_analytics, falls back to local_listas_exame (legacy).
if (!function_exists('local_mulima_analytics_attendance_config')) {
    function local_mulima_analytics_attendance_config($key) {
        return \local_mulima_analytics\local\attendance_config::get($key);
    }
}

$PAGE->set_context(context_system::instance());
header('Content-Type: application/json; charset=utf-8');

if (empty($PAGE->context)) { $PAGE->set_context(context_system::instance()); }
try {
    $op = required_param('op', PARAM_ALPHA);
    require_sesskey();
    \core\session\manager::write_close();

    if ($op === 'cats') {
        $period = required_param('period', PARAM_INT);
        $parent = optional_param('parent', $period, PARAM_INT);
        $out = \local_mulima_analytics\local\access_filters::children($period, $parent);
        echo json_encode(['ok' => true, 'cats' => $out]);
        exit;
    }

    if ($op === 'courses') {
        $period = required_param('period', PARAM_INT);
        $catid  = optional_param('catid', 0, PARAM_INT);
        $courses = \local_mulima_analytics\local\access_filters::courses(
            $period, $catid, 0, optional_param('descendants', false, PARAM_BOOL));
        $out = [];
        foreach ($courses as $co) $out[] = ['id' => (int)$co->id, 'name' => $co->fullname, 'short' => $co->shortname];
        echo json_encode(['ok' => true, 'courses' => $out]);
        exit;
    }

    if ($op === 'groups') {
        $cid = required_param('courseid', PARAM_INT);
        $groups = $DB->get_records('groups', ['courseid' => $cid], 'name', 'id,name');
        $list = [];
        foreach ($groups as $g) {
            $cnt = (int)$DB->count_records('groups_members', ['groupid' => $g->id]);
            $list[] = ['id' => (int)$g->id, 'name' => $g->name, 'count' => $cnt];
        }
        echo json_encode(['ok' => true, 'groups' => $list]);
    }
} catch (\Throwable $e) {
    error_log('local_mulima_analytics presenca_ajax: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => get_string('ajax_error', 'local_mulima_analytics')]);
}
