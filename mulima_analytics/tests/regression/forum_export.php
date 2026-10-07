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
 * Learning Analytics regression checks: forum export.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Validate the actual Excel writer's column mapping with the report's SQL output.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require(__DIR__.'/forum_participation.php');

final class forum_test_sheet {
    public $cells = [];
    public function write_string($row, $column, $value, $format) { $this->cells[$row][$column] = $value; }
    public function write_number($row, $column, $value, $format) { $this->cells[$row][$column] = $value; }
    public function set_column($first, $last, $width) {}
}
final class MoodleExcelWorkbook {
    public $sheet;
    public $sheets = [];
    public function __construct($filename) { $this->sheet = new forum_test_sheet(); }
    public function add_worksheet($name) {
        $sheet = $this->sheets ? new forum_test_sheet() : $this->sheet;
        $this->sheets[$name] = $sheet;
        return $sheet;
    }
    public function add_format($options) { return $options; }
    public function close() {}
}
$range = 30;
$rows = [teacherrow(301), teacherrow(303)];
$scoring = \local_mulima_analytics\local\teacher_scoring::settings();
$source = file_get_contents(__DIR__.'/../../docentes_export.php');
$offset = strpos($source, '$workbook = new MoodleExcelWorkbook(');
if ($offset === false) { throw new RuntimeException('Missing export writer'); }
eval(substr($source, $offset));
$cells = $workbook->sheet->cells;
same(['doc_forums_total','doc_forums_participated','doc_forum_messages','doc_forum_topics',
    'doc_forum_replies','doc_forum_last'],array_slice($cells[3],7,6),'Excel headers identify forum inventory and participation separately');
same([4,2,2,1,1],array_slice($cells[4],7,5),'Excel writes exactly the same numeric forum measures as the report');
same($rows[0]['forum_last_post'],$cells[4][12],'Excel last forum participation matches the screen data');
same($rows[0]['last_access'],$cells[4][13],'Last course access stays separate from last forum message');
same($rows[0]['score'],$cells[4][14],'Excel keeps the corrected own-message score');
same('doc_forum_no_posts',$cells[5][12],'Excel identifies no participation without inventing a date');
echo "RESULT $passed total forum/export/scope/metric assertions passed.\n";
