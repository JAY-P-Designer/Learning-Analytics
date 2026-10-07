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
 * Learning Analytics page.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics\local;

defined('MOODLE_INTERNAL') || die();

/** Shared Moodle page configuration for report screens. */
final class page {
    public static function setup(string $relativepath, string $title, string $layout = 'report'): void {
        global $PAGE;

        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(new \moodle_url('/local/mulima_analytics/' . ltrim($relativepath, '/')));
        $PAGE->set_pagelayout($layout);
        $PAGE->set_title($title);
        // The report header is rendered inside the content region so themes do
        // not display a second, duplicate title above it.
        $PAGE->set_heading('');
        $PAGE->requires->js_call_amd('local_mulima_analytics/common', 'init', [[
            'close' => get_string('close', 'local_mulima_analytics'),
            'invalid_response' => get_string('invalid_response', 'local_mulima_analytics'),
        ]]);
    }
}
