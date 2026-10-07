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
 * Learning Analytics attendance config.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics\local;

defined('MOODLE_INTERNAL') || die();

/** Typed access to attendance-list settings with legacy migration fallback. */
final class attendance_config {
    /**
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null) {
        $value = get_config('local_mulima_analytics', $key);
        if ($value === false) {
            $value = get_config('local_listas_exame', $key);
        }
        return $value === false ? $default : $value;
    }

    public static function integer(string $key, int $default = 0): int {
        return (int)self::get($key, $default);
    }
}
