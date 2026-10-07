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
 * Learning Analytics json.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics\local;

defined('MOODLE_INTERNAL') || die();

/** Safe JSON responses shared by AJAX endpoints. */
final class json {
    /**
     * Send a JSON response and terminate the current request.
     *
     * The method intentionally has no `never` return type so the plugin also
     * parses on PHP 7.3, supported by the first Moodle 4.0 releases.
     */
    public static function send(array $payload, int $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(\Throwable $exception, string $source) {
        error_log('local_mulima_analytics ' . $source . ': ' . $exception->getMessage());
        self::send([
            'ok' => false,
            'error' => get_string('ajax_error', 'local_mulima_analytics'),
        ], 500);
    }
}
