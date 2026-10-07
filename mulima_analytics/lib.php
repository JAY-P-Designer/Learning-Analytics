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
 * Moodle integration callbacks.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

function local_mulima_analytics_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM) return false;
    if ($filearea !== 'logo_pdf' && $filearea !== 'logo_excel') return false;
    \local_mulima_analytics\local\access::require_report('attendance');
    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_mulima_analytics', $filearea, $itemid, $filepath, $filename);
    if (!$file) return false;
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Applies the role selection made in "Quem pode ver o relatório" (settings.php)
 * to the actual local/mulima_analytics:view capability at system context.
 *
 * Called as the updatedcallback of the admin_setting_configmulticheckbox.
 * Roles ticked get an explicit CAP_ALLOW; roles unticked have any previous
 * override from this screen removed (falling back to the capability's
 * normal definition - by default only the Manager archetype).
 */
function local_mulima_analytics_apply_view_roles() {
    global $DB;

    $raw = get_config('local_mulima_analytics', 'allowedroles');
    $selected = [];
    if ($raw !== false && $raw !== '') {
        foreach (explode(',', $raw) as $rid) {
            $rid = (int)$rid;
            if ($rid > 0) $selected[$rid] = true;
        }
    }

    // Roles built on the Manager archetype are permanently protected: this
    // screen can only grant access to EXTRA roles, it can never take away
    // the Manager's own default access, no matter what was (or wasn't)
    // ticked when the form was submitted. This is what went wrong before:
    // saving the form with Manager unticked (e.g. from a stale page load)
    // silently locked managers out of their own report.
    $protected = [];
    foreach ($DB->get_records('role', ['archetype' => 'manager'], '', 'id') as $r) {
        $protected[(int)$r->id] = true;
    }

    $sysctx = context_system::instance();
    foreach (get_all_roles() as $role) {
        $rid = (int)$role->id;
        if (isset($protected[$rid]) || isset($selected[$rid])) {
            assign_capability('local/mulima_analytics:view', CAP_ALLOW, $rid, $sysctx->id, true);
            foreach (\local_mulima_analytics\local\access::grantable_capabilities() as $capability) {
                assign_capability($capability, CAP_ALLOW, $rid, $sysctx->id, true);
            }
        } else {
            // Only clears an override made from this screen; if the role never
            // had one, this is a harmless no-op.
            unassign_capability('local/mulima_analytics:view', $rid, $sysctx->id);
            foreach (\local_mulima_analytics\local\access::grantable_capabilities() as $capability) {
                unassign_capability($capability, $rid, $sysctx->id);
            }
        }
    }
    accesslib_clear_all_caches(false);
}
