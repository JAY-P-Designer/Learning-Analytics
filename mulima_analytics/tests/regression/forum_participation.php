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
 * Learning Analytics regression checks: forum participation.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Execute the actual report queries with teacher, student and other-author content.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require(__DIR__.'/report_metrics.php');
use local_mulima_analytics\local\teachers_report;

function teacherrow(int $userid, int $days = 30, int $category = 30, int $course = 0,
        bool $recursive = true): array {
    global $now;
    $rows = teachers_report::get(1, $category, $course, $days, $now, $recursive);
    foreach ($rows as $row) { if ($row['userid'] === $userid) { return $row; } }
    throw new RuntimeException('Missing teacher '.$userid);
}
function forumvalues(array $t): array {
    return [$t['forums_total'],$t['forums_participated'],$t['forum_posts'],
        $t['forum_discussions'],$t['forum_replies']];
}
$t = teacherrow(301);
same([1,1,1,0,1],forumvalues($t),'A teacher reply in another author\'s forum is counted without logs');
same($now-3*DAYSECS,$t['forum_last_ts'],'Last participation uses the actual post date');

// Existing, hidden, empty, orphaned and asynchronously deleted forums.
foreach ([[2,104],[3,104],[4,105],[5,104],[6,107],[7,104],[8,104]] as [$id,$course]) {
    insertrow('forum',compact('id','course'));
    if ($id === 7) { continue; } // Orphaned instance, no live course module.
    insertrow('course_modules',['id'=>$id,'course'=>$course,'module'=>$id===8?2:1,
        'instance'=>$id,'deletioninprogress'=>$id===5?1:0,'visible'=>$id===3?0:1]);
}
foreach ([[2,2,201,3],[3,3,301,4],[4,4,202,6],[5,5,301,7],[6,6,301,8],
        [7,2,301,9],[8,2,301,10],[9,7,301,11],[10,8,301,12]] as [$id,$forum,$userid,$firstpost]) {
    insertrow('forum_discussions',compact('id','forum','userid','firstpost'));
}
// Two distinct forums participated in; two teacher messages in the same forum
// must not increase the count of forums. Other authors' posts must never count.
foreach ([
    [3,2,0,201,$now-5*DAYSECS,0],
    [4,3,0,301,$now-30*DAYSECS,0], // Inclusive lower boundary, hidden forum.
    [5,3,4,301,$now,0],             // Inclusive upper boundary.
    [6,4,0,202,$now-2*DAYSECS,0],
    [7,5,0,301,$now-2*DAYSECS,0],  // Forum deletion in progress.
    [8,6,0,301,$now-2*DAYSECS,0],  // Course in which this user is not a teacher.
    [9,7,0,301,$now-31*DAYSECS,0], // Outside 30-day period.
    [10,8,0,301,$now-2*DAYSECS,1], // Soft-deleted message.
    [11,9,0,301,$now-2*DAYSECS,0], // Orphaned forum.
    [12,10,0,301,$now-2*DAYSECS,0],// Wrong module type with same instance id.
    [13,3,4,301,$now+1,0],         // Future date.
    [14,3,4,302,$now-2*DAYSECS,0], // Another teacher's reply, wrong course role.
    [15,3,4,301,$now-30*DAYSECS-1,0], // Just outside lower boundary.
] as [$id,$discussion,$parent,$userid,$created,$deleted]) {
    insertrow('forum_posts',compact('id','discussion','parent','userid','created','deleted'));
}
$t = teacherrow(301);
same([4,2,3,1,2],forumvalues($t),'Forums inventory and participation respect scope, authorship, deletion and exact date bounds');
same(12,$t['score'],'Only actual teacher messages add forum points; existing forums add none');
same([2,0,0,0,0],forumvalues(teacherrow(303)),'Teacher with no posts still sees course forum inventory');
same(0,teacherrow(303)['score'],'Forums created by other people do not make an inactive teacher active');
same([2,1,2,1,1],forumvalues(teacherrow(301,30,30,104)),'Selecting a discipline restricts all forum measures');
same([2,1,2,1,1],forumvalues(teacherrow(301,30,30,0,false)),'Direct category selection excludes descendant forums');
same([4,3,5,2,3],forumvalues(teacherrow(301,90)),'Changing period includes older own posts but does not change forum inventory');
same([4,1,1,0,1],forumvalues(teacherrow(301,1)),'A recent reply alone makes the teacher active');
same(1,teacherrow(301,1)['score'],'Replies contribute one forum point each');
same([1,0,0,0,0],forumvalues(teacherrow(302,30,21)),'A different teacher\'s posts are not assigned to this teacher');
same([4,2,3,1,2],forumvalues(teacherrow(301,30,10)),'A teacher posting in another selected course is not credited as its teacher');

// Log events can duplicate a discussion's first post or describe subscription /
// assessment actions. They must not replace content counts or double-score posts.
foreach (['discussion','post','post'] as $target) {
    insertrow('logstore_standard_log',['courseid'=>104,'userid'=>301,'timecreated'=>$now,
        'action'=>'created','component'=>'mod_forum','target'=>$target]);
}
same([4,2,3,1,2],forumvalues(teacherrow(301)),'Forum events do not duplicate actual messages');
same(12,teacherrow(301)['score'],'Post and discussion events are not scored again as created activities');

// Removing a post from Moodle must remove its contribution even if logs remain.
$DB->pdo->exec('DELETE FROM forum_posts WHERE id = 5');
same([4,2,2,1,1],forumvalues(teacherrow(301)),'Deleted content is excluded even with retained creation events');
same([4,0,0,0,0],forumvalues(teacherrow(301,1)),'No publication in period preserves inventory with zero participation');
same(null,teacherrow(301,1)['forum_last_post'],'No last-post date is invented for an empty period');
echo "RESULT $passed total forum/scope/metric SQL assertions passed.\n";
