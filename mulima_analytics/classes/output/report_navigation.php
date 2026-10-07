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
 * Learning Analytics report navigation.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics\output;

defined('MOODLE_INTERNAL') || die();

/** Template data for the navigation shared by all report pages. */
final class report_navigation implements \renderable, \templatable {
    /** @var string */
    private $current;

    /** @var array */
    private $items;

    /** @var array */
    private $reports;

    public function __construct(
        string $current,
        array $items,
        array $reports
    ) {
        $this->current = $current;
        $this->items = $items;
        $this->reports = $reports;
    }

    public function export_for_template(\renderer_base $output): array {
        $navigation = [];
        foreach ($this->items as $key => $item) {
            if (!\local_mulima_analytics\local\access::can_view($this->reports[$key])) {
                continue;
            }
            $navigation[] = [
                'label' => $item['l'],
                'url' => (new \moodle_url('/local/mulima_analytics/' . $item['f']))->out(false),
                'active' => $key === $this->current,
            ];
        }

        return [
            'label' => get_string('pluginname', 'local_mulima_analytics'),
            'items' => $navigation,
            'showsettings' => has_capability('moodle/site:config', \context_system::instance()),
            'settingsurl' => (new \moodle_url('/local/mulima_analytics/presenca_config.php'))->out(false),
            'settingslabel' => get_string('settings'),
        ];
    }
}
