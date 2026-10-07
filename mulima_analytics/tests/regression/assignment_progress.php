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
 * Learning Analytics regression checks: assignment progress.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require(__DIR__.'/forum_export.php');
use local_mulima_analytics\local\assignment_progress;

function addassignment(int $id, int $course = 104, array $extra = [], bool $module = true): void {
    insertrow('assign',array_merge(['id'=>$id,'course'=>$course,'name'=>'Trabalho '.$id],$extra));
    if ($module) { insertrow('course_modules',['id'=>$id+1000,'course'=>$course,'module'=>3,'instance'=>$id]); }
}
function addsubmission(int $id, int $assignment, int $userid, int $days, array $extra = []): void {
    global $now;
    insertrow('assign_submission',array_merge(['id'=>$id,'assignment'=>$assignment,'userid'=>$userid,
        'timemodified'=>$now-$days*DAYSECS],$extra));
}
function addgrade(int $id, int $assignment, int $userid, int $grader, $grade, int $days, array $extra = []): void {
    global $now;
    insertrow('assign_grades',array_merge(compact('id','assignment','userid','grader','grade'),
        ['timemodified'=>$now-$days*DAYSECS],$extra));
}
function assignmentrow(int $id, int $days = 30): array {
    foreach (teacherrow(301,$days)['assignments'] as $a) { if ($a['id'] === $id) { return $a; } }
    throw new RuntimeException('Missing assignment '.$id);
}
function progressvalues(array $a): array {
    return [$a['submitted'],$a['corrected'],$a['pending'],$a['percent'],$a['level']];
}
same([1,1,0,100.0,'complete'],progressvalues(assignmentrow(100)),'Existing assignment is reported without depending on its creator or creation logs');
addassignment(200);
addsubmission(200,200,201,8,['latest'=>0]);
addgrade(200,200,201,301,90,7);
addsubmission(201,200,201,4,['attemptnumber'=>1]);
addsubmission(202,200,202,4);
addgrade(202,200,202,301,0,3);
addsubmission(203,200,203,2);
addgrade(203,200,203,301,80,3);
addsubmission(204,200,204,2,['status'=>'draft']);
addsubmission(205,200,205,50);
addgrade(205,200,205,302,65,40);
same([4,2,2,50.0,'in_progress'],progressvalues(assignmentrow(200)),'Current attempts only: zero is graded, previous-attempt and stale grades are pending, drafts excluded');
same(progressvalues(assignmentrow(200,30)),progressvalues(assignmentrow(200,1)),'Old submissions and grading remain in the backlog when the activity interval narrows');
same(2,teacherrow(301)['graded'],'Only current valid assessments attributed to this teacher affect personal activity');
same(0,teacherrow(303)['graded'],'Sharing the course does not attribute another teacher\'s corrections');
addassignment(201);
same([0,0,0,null,'no_submissions'],progressvalues(assignmentrow(201)),'No submissions is distinct from zero percent corrected and complete');
addassignment(202,105);
addsubmission(206,202,203,4);
addgrade(206,202,203,303,60,3);
same([1,1,0,100.0,'complete'],progressvalues(assignmentrow(202)),'Grades recorded by another person contribute to course completion');
same(2,teacherrow(301)['graded'],'Another grader does not receive credit on the current teacher\'s score');
same(0,teacherrow(303)['graded'],'A grader is not credited as teacher in an unassigned course');
same(2,teacherrow(301,30,30,104)['assignments_total']-1,'Selected discipline excludes descendant assignments');
addassignment(203);
$DB->pdo->exec('UPDATE course_modules SET deletioninprogress = 1 WHERE instance = 203 AND module = 3');
addassignment(204,104,[],false);
addassignment(205,107);
$ids=array_column(teacherrow(301)['assignments'],'id');sort($ids);
same([100,200,201,202],$ids,'Deleted, orphaned and out-of-scope assignments are excluded from inventory');
addassignment(206,104,['nosubmissions'=>1]);
addgrade(207,206,201,301,70,3);
same([0,0,0,null,'no_submissions'],progressvalues(assignmentrow(206)),'Offline work has no fabricated online submission percentage');
same(3,teacherrow(301)['graded'],'Actual offline grading still counts as the teacher\'s own activity');
addassignment(207,104,['grade'=>0]);
addsubmission(207,207,201,4);
addgrade(208,207,201,301,-1,3);
same([1,1,0,100.0,'complete'],progressvalues(assignmentrow(207)),'Work without a numeric grade uses recorded completed assessment');
addassignment(208,104,['grade'=>-7]);
addsubmission(208,208,201,4);
addgrade(209,208,201,301,-1,3);
same([1,0,1,0.0,'not_started'],progressvalues(assignmentrow(208)),'An unselected scale is not a correction');
$DB->pdo->exec('UPDATE assign_grades SET grade = 1 WHERE id = 209');
same([1,1,0,100.0,'complete'],progressvalues(assignmentrow(208)),'A valid scale grade is a correction');
addassignment(209,104,['markingworkflow'=>1]);
addsubmission(209,209,201,4);
addgrade(210,209,201,301,90,3);
insertrow('assign_user_flags',['assignment'=>209,'userid'=>201,'workflowstate'=>'inmarking']);
same(1,assignmentrow(209)['pending'],'Assessment still in marking is pending even if a draft mark exists');
$DB->pdo->exec("UPDATE assign_user_flags SET workflowstate = 'readyforreview' WHERE assignment = 209");
same(1,assignmentrow(209)['corrected'],'Finished marking can await review or release without being ungraded');

// A team submission counts once, not once per member or enrolment method.
addassignment(210,104,['teamsubmission'=>1,'teamsubmissiongroupingid'=>7]);
insertrow('groups',['id'=>501,'courseid'=>104]);
insertrow('groups',['id'=>502,'courseid'=>105]);
insertrow('groups',['id'=>503,'courseid'=>104]);
insertrow('groups',['id'=>504,'courseid'=>104,'participation'=>0]);
insertrow('groupings_groups',['groupingid'=>7,'groupid'=>501]);
insertrow('groupings_groups',['groupingid'=>7,'groupid'=>504]);
foreach ([201,202,205,204] as $userid) { insertrow('groups_members',['groupid'=>501,'userid'=>$userid]); }
insertrow('groups_members',['groupid'=>502,'userid'=>201]); // Other course.
insertrow('groups_members',['groupid'=>503,'userid'=>201]); // Outside the assignment grouping.
insertrow('groups_members',['groupid'=>504,'userid'=>201]); // Non-participating group.
addsubmission(210,210,0,4,['groupid'=>501]);
addsubmission(211,210,201,4); // Per-member status row must not count as another submission.
addgrade(211,210,201,301,50,3);
addgrade(212,210,202,303,null,3);
addgrade(213,210,205,301,75,3);
same([1,0,1,0.0,'in_progress'],progressvalues(assignmentrow(210)),'A partially assessed team is in progress, stays pending and counts as one received submission');
$DB->pdo->exec('UPDATE assign_grades SET grade = 0 WHERE id = 212');
same([1,1,0,100.0,'complete'],progressvalues(assignmentrow(210)),'A team is complete when all eligible members are graded, including zero marks');
// Default group: an enrolled student with no group in the assignment grouping.
insertrow('user_enrolments',['enrolid'=>104,'userid'=>203,'status'=>0]);
insertrow('role_assignments',['contextid'=>104,'userid'=>203,'roleid'=>5]);
addsubmission(212,210,0,4,['groupid'=>0]);
same([2,1,1,50.0,'in_progress'],progressvalues(assignmentrow(210)),'Default-group submission is included and pending before its member is graded');
addgrade(214,210,203,301,80,3);
same([2,2,0,100.0,'complete'],progressvalues(assignmentrow(210)),'Default group completes without duplicating individually graded team members');
same(1,teacherrow(303)['graded'],'Personal contribution counts only this grader\'s valid team-member assessment');
same(99.9,assignment_progress::percent(10000,9999),'Rounding never labels incomplete grading as 100 percent');
same('in_progress',assignment_progress::level(10000,9999),'Completion is determined by counts, not rounded percentages');
same(null,assignment_progress::percent(0,0),'No division by zero when no submissions exist');

// Validate new Excel columns and one detail row per assignment shared by teachers.
$rows=[teacherrow(301),teacherrow(303)];
eval(substr($source,$offset));
$cells=$workbook->sheet->cells;
same('doc_assign_own_header',$cells[3][6],'Own assessments are explicitly identified in Excel');
same([$rows[0]['assignments_total'],$rows[0]['submissions_total'],$rows[0]['submissions_graded'],
    $rows[0]['submissions_pending'],$rows[0]['grading_percent']],array_slice($cells[4],16,5),'Teacher Excel values match current assignment metrics');
$detail=$workbook->sheets['doc_assign_sheet']->cells;
$detailrows=array_filter(array_keys($detail),function($r){return $r>=4;});
same(count($rows[0]['assignments']),count($detailrows),'Detail sheet lists each assignment once despite shared teaching roles');
echo "RESULT $passed total assignment/forum/export/scope/metric assertions passed.\n";
