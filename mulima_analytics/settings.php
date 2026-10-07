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
 * Administration navigation and settings.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

// Main report entry. Register this independently so a settings problem can
// never remove the report from Site administration.
$ADMIN->add('reports', new admin_externalpage(
    'local_mulima_analytics',
    get_string('pluginname', 'local_mulima_analytics'),
    new moodle_url('/local/mulima_analytics/index.php'),
    'local/mulima_analytics:viewdashboard'
));

// One canonical settings interface: General and Permissions. The former
// Advanced form only duplicated controls already available on those pages.
if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_mulima_analytics_settings',
        get_string('cfg_page_title', 'local_mulima_analytics'),
        new moodle_url('/local/mulima_analytics/presenca_config.php'),
        'moodle/site:config'
    ));
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_mulima_analytics_migration',
        get_string('migration_title', 'local_mulima_analytics'),
        new moodle_url('/local/mulima_analytics/migration.php'),
        'moodle/site:config',
        true
    ));
}
