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
 * Learning Analytics roles.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics\local;

defined('MOODLE_INTERNAL') || die();

/** Resolves all roles based on Moodle archetypes, including customised roles. */
final class roles {
    /** @return int[] */
    public static function students(): array {
        return self::from_archetypes(['student']);
    }

    /** @return int[] */
    public static function teachers(): array {
        return self::from_archetypes(['editingteacher', 'teacher']);
    }

    /** @return int[] */
    private static function from_archetypes(array $archetypes): array {
        $ids = [];
        foreach ($archetypes as $archetype) {
            foreach (get_archetype_roles($archetype) as $role) {
                $ids[(int)$role->id] = (int)$role->id;
            }
        }
        return array_values($ids);
    }
}
