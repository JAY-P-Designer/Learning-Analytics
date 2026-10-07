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
 * Learning Analytics regression checks: forum inventory.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Inventory is independent of discussions, authors and the participation period.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require(__DIR__.'/forum_export.php');

function forumrow(array $teacher, int $id): array {
    foreach ($teacher['forums'] as $forum) { if ($forum['id'] === $id) { return $forum; } }
    throw new RuntimeException('Missing forum '.$id);
}

insertrow('forum',['id'=>9,'course'=>104,'name'=>'Fórum "sem tópicos" <A & B>']);
insertrow('course_modules',['id'=>900,'course'=>104,'module'=>1,'instance'=>9]);
insertrow('forum',['id'=>10,'course'=>105,'name'=>'Fórum vazio e oculto']);
insertrow('course_modules',['id'=>901,'course'=>105,'module'=>1,'instance'=>10,'visible'=>0]);
$t = teacherrow(301);
same(6,$t['forums_total'],'Visible and hidden forums without any discussion are both counted');
same($t['forums_total'],count($t['forums']),'The total equals the actual inventory list');
same(6,count(array_unique(array_column($t['forums'],'id'))),'Multiple teacher roles do not duplicate forum inventory');
$empty = forumrow($t,9);
same([9,900,104,'Fórum "sem tópicos" <A & B>',0,0,false,null],
    [$empty['id'],$empty['cmid'],$empty['courseid'],$empty['name'],$empty['topics'],$empty['posts'],$empty['participated'],$empty['last_post']],
    'An empty forum retains its name and correct module ID with no invented participation');
same(false,forumrow($t,10)['visible'],'Hidden empty activity is identified explicitly');
same(3,teacherrow(303)['forums_total'],'Another teacher sees the shared course inventory including an empty forum');
same(false,forumrow(teacherrow(303),3)['participated'],'Another author\'s posts do not count as this teacher\'s participation');
same([1,1,0],array_values(array_intersect_key(forumrow($t,3),array_flip(['topics','posts','replies']))),
    'Per-forum content and own replies remain distinct');
same(true,forumrow($t,3)['participated'],'The teacher\'s own topic counts as participation');
same(false,forumrow($t,2)['participated'],'An older post does not become current participation');
same(true,forumrow(teacherrow(301,90),2)['participated'],'Changing the period includes older participation in the correct forum');
same(6,teacherrow(301,1)['forums_total'],'A short participation period still includes all existing forums');
same(0,teacherrow(301,1)['forums_participated'],'No current publications leaves participation at zero');
same(3,teacherrow(301,30,30,104)['forums_total'],'A selected course limits the inventory to its own forums');
same(3,teacherrow(301,30,30,0,false)['forums_total'],'Direct category mode excludes descendant forums');
same(1,teacherrow(302,30,21)['forums_total'],'A sibling category cannot leak into the inventory');

$range = 30;
$rows = [teacherrow(301),teacherrow(303)];
eval(substr($source,$offset));
$forumcells = array_slice($workbook->sheets['doc_forum_sheet']->cells,4,null,true);
same(9,count($forumcells),'Excel lists one row for each teacher/forum pair, including empty forums');
$emptycells = array_values(array_filter($forumcells, function($r) {
    return $r[3] === 'Fórum "sem tópicos" <A & B>';
}));
same(2,count($emptycells),'A shared empty forum appears for each assigned teacher in the detail export');
same([0,'doc_forum_visible','doc_forum_without_participation',0,0,0,'doc_forum_no_posts'],
    array_slice($emptycells[0],4),'Excel preserves zero topics and the explicit absence of teacher interaction');
same(6,$workbook->sheet->cells[4][7],'Excel summary and detailed inventory agree');

echo "RESULT $passed total forum inventory/export/scope/metric assertions passed.\n";
