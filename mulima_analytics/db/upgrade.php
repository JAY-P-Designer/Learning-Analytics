<?php
// This file is part of Moodle - https://moodle.org/
// Copyright 2026 Joaquim Pascoal Mulima Junior.
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Upgrade steps for this component; importing the previous component is explicit.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade this component without replaying the previous component's role grants.
 *
 * @param int $oldversion Installed version.
 * @return bool
 */
function xmldb_local_mulima_analytics_upgrade($oldversion) {
    if ($oldversion < 2026100206) {
        upgrade_plugin_savepoint(true, 2026100206, 'local', 'mulima_analytics');
    }
    return true;
}
