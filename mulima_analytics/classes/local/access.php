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
 * Learning Analytics access.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Central access policy for every report and export.
 *
 * Keeping capability checks here prevents pages and AJAX endpoints from
 * drifting apart when new reports are added.
 */
final class access {
    /** @var array<string, string> */
    private const REPORT_CAPABILITIES = [
        'dashboard' => 'local/mulima_analytics:viewdashboard',
        'accesses' => 'local/mulima_analytics:viewaccesses',
        'coverage' => 'local/mulima_analytics:viewcoverage',
        'risk' => 'local/mulima_analytics:viewrisk',
        'teachers' => 'local/mulima_analytics:viewteachers',
        'attendance' => 'local/mulima_analytics:viewattendance',
    ];

    public static function require_report(string $report): void {
        require_login();
        $context = \context_system::instance();
        require_capability(self::capability_for($report), $context);
    }

    public static function require_export(string $report): void {
        self::require_report($report);
        require_capability('local/mulima_analytics:exportdata', \context_system::instance());
    }

    public static function require_manage_attendance(): void {
        self::require_report('attendance');
        require_capability('local/mulima_analytics:manageattendance', \context_system::instance());
    }

    public static function can_view(string $report): bool {
        return has_capability(self::capability_for($report), \context_system::instance());
    }

    /** Capabilities granted by the plugin's simple "who can view" control. */
    public static function grantable_capabilities(): array {
        return array_merge(array_values(self::REPORT_CAPABILITIES), [
            'local/mulima_analytics:exportdata',
        ]);
    }

    private static function capability_for(string $report): string {
        if (!isset(self::REPORT_CAPABILITIES[$report])) {
            throw new \coding_exception('Unknown Learning Analytics report: ' . $report);
        }
        return self::REPORT_CAPABILITIES[$report];
    }
}
