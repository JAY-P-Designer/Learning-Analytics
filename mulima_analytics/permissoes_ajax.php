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
 * Report permission settings data endpoint.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());
$PAGE->set_context(context_system::instance());
header('Content-Type: application/json; charset=utf-8');
if (!confirm_sesskey()) { echo json_encode(['ok'=>false,'error'=>'Sesskey']); exit; }

$op = required_param('op', PARAM_ALPHANUMEXT);

$writeops = ['toggle_role', 'set_nav', 'add_to_menu', 'remove_from_menu'];
if (in_array($op, $writeops, true) && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['ok' => false, 'error' => get_string('invalidrequest', 'error')]);
    exit;
}

/**
 * Text fields that may contain accented characters travel as Base64 rather
 * than raw query-string text. This sidesteps any server/proxy-specific
 * mangling of multi-byte UTF-8 sequences in URL-encoded GET parameters
 * (observed in production: "Relatório DIE" arriving truncated to "Relató").
 */
function local_mulima_analytics_decode_menu($raw) {
    if ($raw === '' || $raw === null) return '';
    $decoded = base64_decode($raw, true);
    if ($decoded === false) return '';
    // Reject anything that isn't valid UTF-8 rather than silently mangling it.
    return mb_check_encoding($decoded, 'UTF-8') ? $decoded : '';
}

if (empty($PAGE->context)) { $PAGE->set_context(context_system::instance()); }
try { switch ($op) {

    // ── Current state: roles + nav settings, in one shot ──────────
    case 'state':
        $sysctx = context_system::instance();
        $managerroleids = $DB->get_records_menu('role', ['archetype' => 'manager'], '', 'id,id');
        $roles = [];
        foreach (get_all_roles() as $role) {
            $ismanager = isset($managerroleids[(int)$role->id]);
            $perm = $DB->get_field('role_capabilities', 'permission', [
                'roleid' => $role->id, 'contextid' => $sysctx->id,
                'capability' => 'local/mulima_analytics:view',
            ]);
            $roles[] = [
                'id'      => (int)$role->id,
                'name'    => role_get_name($role, $sysctx, ROLENAME_ORIGINALANDSHORT),
                'manager' => $ismanager,
                'allowed' => $ismanager || (int)$perm === CAP_ALLOW,
            ];
        }
        usort($roles, function($a, $b) { return strcmp($a['name'], $b['name']); });

        $navlabel = get_config('local_mulima_analytics', 'navlabel');
        $label    = trim((string)($navlabel === false ? '' : $navlabel));
        if ($label === '') $label = get_string('pluginname', 'local_mulima_analytics');

        $reporturl = (new moodle_url('/local/mulima_analytics/index.php'))->out_as_local_url(false);
        $menuline  = $label . '|' . $reporturl;

        // Is our line already present in the site's custom menu?
        $custommenu = (string)(get_config(null, 'custommenuitems') ?: '');
        $inmenu = strpos($custommenu, $reporturl) !== false;

        echo json_encode(['ok'=>true, 'roles'=>$roles,
            'navlabel' => base64_encode($label),
            'menuline' => $menuline,
            'inmenu'   => $inmenu,
        ]);
        break;

    // ── Instant role toggle (no save button, no page reload) ──────
    case 'toggle_role':
        $roleid = required_param('roleid', PARAM_INT);
        $on     = required_param('on', PARAM_INT);

        $managerroleids = $DB->get_records_menu('role', ['archetype' => 'manager'], '', 'id,id');
        if (isset($managerroleids[$roleid])) {
            echo json_encode(['ok'=>true, 'allowed'=>true, 'protected'=>true]);
            break;
        }

        $sysctx = context_system::instance();
        if ($on) {
            assign_capability('local/mulima_analytics:view', CAP_ALLOW, $roleid, $sysctx->id, true);
            foreach (\local_mulima_analytics\local\access::grantable_capabilities() as $capability) {
                assign_capability($capability, CAP_ALLOW, $roleid, $sysctx->id, true);
            }
        } else {
            unassign_capability('local/mulima_analytics:view', $roleid, $sysctx->id);
            foreach (\local_mulima_analytics\local\access::grantable_capabilities() as $capability) {
                unassign_capability($capability, $roleid, $sysctx->id);
            }
        }

        // Keep the admin_setting_configmulticheckbox in sync so the full
        // settings page shows the same state if opened afterwards.
        $selected = [];
        foreach ($DB->get_records_sql(
            "SELECT DISTINCT roleid FROM {role_capabilities}
             WHERE contextid = :ctx AND capability = :cap AND permission = :allow",
            ['ctx'=>$sysctx->id, 'cap'=>'local/mulima_analytics:view', 'allow'=>CAP_ALLOW]) as $r) {
            if (!isset($managerroleids[(int)$r->roleid])) $selected[] = (int)$r->roleid;
        }
        set_config('allowedroles', implode(',', $selected), 'local_mulima_analytics');

        accesslib_clear_all_caches(false);
        echo json_encode(['ok'=>true, 'allowed'=>(bool)$on]);
        break;

    // ── Instant nav-label update (label travels as Base64) ─────────
    case 'set_nav':
        $navlabelraw = optional_param('navlabel_b64', '', PARAM_RAW);
        $navlabel = local_mulima_analytics_decode_menu($navlabelraw);
        set_config('navlabel', trim($navlabel), 'local_mulima_analytics');

        $label = trim($navlabel) !== '' ? trim($navlabel) : get_string('pluginname', 'local_mulima_analytics');
        $menuline = $label . '|' . (new moodle_url('/local/mulima_analytics/index.php'))->out_as_local_url(false);
        echo json_encode(['ok'=>true, 'menuline'=>$menuline]);
        break;

    // ── Write our entry straight into the theme's Custom menu items ──
    // (the officially supported Moodle 4+ way to add a top-bar tab).
    case 'add_to_menu':
        $navlabelraw = optional_param('navlabel_b64', '', PARAM_RAW);
        $label = local_mulima_analytics_decode_menu($navlabelraw);
        if ($label === '') $label = get_string('pluginname', 'local_mulima_analytics');

        $reporturl = (new moodle_url('/local/mulima_analytics/index.php'))->out_as_local_url(false);
        $newline   = $label . '|' . $reporturl;

        $current = (string)(get_config(null, 'custommenuitems') ?: '');
        $lines = $current === '' ? [] : preg_split('/\r\n|\r|\n/', $current);

        // Remove any previous entry we added (by URL match), then append the
        // fresh one - keeps this idempotent across repeated clicks / label
        // changes instead of accumulating duplicates.
        $lines = array_values(array_filter($lines, function($l) use ($reporturl) {
            return trim($l) !== '' && strpos($l, $reporturl) === false;
        }));
        $lines[] = $newline;

        set_config('custommenuitems', implode("\n", $lines));

        // Custom menu output is cached with the rest of the theme output.
        if (function_exists('theme_reset_all_caches')) {
            theme_reset_all_caches();
        } else {
            cache_helper::purge_by_definition('core', 'plugin_manager');
        }

        echo json_encode(['ok'=>true]);
        break;

    // ── Remove our entry from the theme's Custom menu items ─────────
    case 'remove_from_menu':
        $reporturl = (new moodle_url('/local/mulima_analytics/index.php'))->out_as_local_url(false);
        $current = (string)(get_config(null, 'custommenuitems') ?: '');
        $lines = $current === '' ? [] : preg_split('/\r\n|\r|\n/', $current);
        $lines = array_values(array_filter($lines, function($l) use ($reporturl) {
            return trim($l) !== '' && strpos($l, $reporturl) === false;
        }));
        set_config('custommenuitems', implode("\n", $lines));
        if (function_exists('theme_reset_all_caches')) {
            theme_reset_all_caches();
        }
        echo json_encode(['ok'=>true]);
        break;

    default:
        echo json_encode(['ok'=>false, 'error'=>get_string('invalid_operation', 'local_mulima_analytics')]);

}} catch (\Throwable $e) {
    error_log('local_mulima_analytics permissoes_ajax: ' . $e->getMessage());
    echo json_encode(['ok'=>false, 'error'=>get_string('ajax_error', 'local_mulima_analytics')]);
}
