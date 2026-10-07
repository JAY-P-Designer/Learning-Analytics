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
 * Learning Analytics regression checks: report metrics.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Real report SQL against an isolated SQLite database; no Moodle installation is modified.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require(__DIR__.'/access_scope.php');
define('DAYSECS',86400);
function get_archetype_roles($archetype) { return $archetype === 'student' ? [(object)['id'=>5]] : []; }
function get_string($name, $component) { return $name === 'scope_platform' ? 'Plataforma em geral' : $name; }
function userdate($timestamp, $format) { return gmdate('d/m/Y H:i', $timestamp); }
$scoretestconfig = [];
function get_config($component, $key) { global $scoretestconfig; return $scoretestconfig[$component][$key] ?? false; }
function set_config($key, $value, $component) { global $scoretestconfig; $scoretestconfig[$component][$key] = $value; }
require_once(__DIR__.'/../../classes/local/roles.php');
require_once(__DIR__.'/../../classes/local/risk_report.php');
require_once(__DIR__.'/../../classes/local/teachers_report.php');
require_once(__DIR__.'/../../classes/local/teacher_scoring.php');
require_once(__DIR__.'/../../classes/local/assignment_progress.php');
use local_mulima_analytics\local\risk_report;
use local_mulima_analytics\local\teachers_report;
$now=1800000000;
foreach ([
    'CREATE TABLE user (id INTEGER PRIMARY KEY,firstname TEXT,lastname TEXT,email TEXT,phone1 TEXT,phone2 TEXT,lastaccess INTEGER,deleted INTEGER,suspended INTEGER)',
    'CREATE TABLE enrol (id INTEGER PRIMARY KEY,courseid INTEGER,status INTEGER)',
    'CREATE TABLE user_enrolments (id INTEGER PRIMARY KEY,enrolid INTEGER,userid INTEGER,status INTEGER)',
    'CREATE TABLE context (id INTEGER PRIMARY KEY,instanceid INTEGER,contextlevel INTEGER)',
    'CREATE TABLE role_assignments (id INTEGER PRIMARY KEY,contextid INTEGER,userid INTEGER,roleid INTEGER)',
    'CREATE TABLE role (id INTEGER PRIMARY KEY,shortname TEXT)',
    'CREATE TABLE logstore_standard_log (id INTEGER PRIMARY KEY,courseid INTEGER,userid INTEGER,timecreated INTEGER,action TEXT,component TEXT,target TEXT DEFAULT "course_module",objecttable TEXT DEFAULT "",objectid INTEGER DEFAULT 0,eventname TEXT DEFAULT "")',
    'CREATE TABLE user_lastaccess (id INTEGER PRIMARY KEY,userid INTEGER,courseid INTEGER,timeaccess INTEGER)',
    'CREATE TABLE modules (id INTEGER PRIMARY KEY,name TEXT)',
    'CREATE TABLE course_modules (id INTEGER PRIMARY KEY,course INTEGER,module INTEGER,instance INTEGER,deletioninprogress INTEGER DEFAULT 0,visible INTEGER DEFAULT 1)',
    'CREATE TABLE forum (id INTEGER PRIMARY KEY,course INTEGER,name TEXT DEFAULT "Fórum",type TEXT DEFAULT "general")',
    'CREATE TABLE forum_discussions (id INTEGER PRIMARY KEY,forum INTEGER,userid INTEGER,firstpost INTEGER)',
    'CREATE TABLE forum_posts (id INTEGER PRIMARY KEY,discussion INTEGER,parent INTEGER,userid INTEGER,created INTEGER,deleted INTEGER DEFAULT 0,privatereplyto INTEGER DEFAULT 0)',
    'CREATE TABLE assign (id INTEGER PRIMARY KEY,course INTEGER,name TEXT,teamsubmission INTEGER DEFAULT 0,teamsubmissiongroupingid INTEGER DEFAULT 0,nosubmissions INTEGER DEFAULT 0,grade REAL DEFAULT 100,markingworkflow INTEGER DEFAULT 0)',
    'CREATE TABLE assign_submission (id INTEGER PRIMARY KEY,assignment INTEGER,userid INTEGER,groupid INTEGER DEFAULT 0,attemptnumber INTEGER DEFAULT 0,latest INTEGER DEFAULT 1,status TEXT DEFAULT "submitted",timemodified INTEGER)',
    'CREATE TABLE assign_grades (id INTEGER PRIMARY KEY,assignment INTEGER,userid INTEGER,attemptnumber INTEGER DEFAULT 0,grader INTEGER,grade REAL,timemodified INTEGER)',
    'CREATE TABLE assign_user_flags (id INTEGER PRIMARY KEY,assignment INTEGER,userid INTEGER,workflowstate TEXT)',
    'CREATE TABLE groups (id INTEGER PRIMARY KEY,courseid INTEGER,participation INTEGER DEFAULT 1)',
    'CREATE TABLE groups_members (id INTEGER PRIMARY KEY,groupid INTEGER,userid INTEGER)',
    'CREATE TABLE groupings_groups (id INTEGER PRIMARY KEY,groupingid INTEGER,groupid INTEGER)',
] as $sql) {$DB->pdo->exec($sql);}
function insertrow($table, array $values) {
    global $DB;
    $sql="INSERT INTO $table (".implode(',',array_keys($values)).') VALUES ('.implode(',',array_fill(0,count($values),'?')).')';
    $DB->pdo->prepare($sql)->execute(array_values($values));
}
foreach ([[1,'manager'],[3,'editingteacher'],[4,'teacher'],[5,'student']] as [$id,$shortname]) {insertrow('role',compact('id','shortname'));}
foreach ([[201,1,0],[202,40,0],[203,0,0],[204,40,1],[205,10,0],[206,0,0],[301,3,0],[302,3,0],[303,0,0]] as [$id,$days,$suspended]) {
    insertrow('user',['id'=>$id,'firstname'=>'User','lastname'=>(string)$id,'email'=>$id.'@example.test',
        'phone1'=>'','phone2'=>'123','lastaccess'=>$days?$now-$days*DAYSECS:0,'deleted'=>0,'suspended'=>$suspended]);
}
foreach ([104,105,107,108] as $id) {
    insertrow('context',['id'=>$id,'instanceid'=>$id,'contextlevel'=>50]);
    insertrow('enrol',['id'=>$id,'courseid'=>$id,'status'=>0]);
}
foreach ([[201,104],[201,105],[202,104],[202,105],[203,105],[204,104],[205,104],[206,107]] as [$uid,$cid]) {
    insertrow('user_enrolments',['enrolid'=>$cid,'userid'=>$uid,'status'=>0]);
    insertrow('role_assignments',['contextid'=>$cid,'userid'=>$uid,'roleid'=>5]);
}
// A second active enrolment must not count the same student/course twice.
insertrow('enrol',['id'=>999,'courseid'=>104,'status'=>0]);
insertrow('user_enrolments',['enrolid'=>999,'userid'=>202,'status'=>0]);
foreach ([[201,104,35],[201,105,35],[201,108,1],[202,104,40],[202,105,20],[205,104,40]] as [$uid,$cid,$days]) {
    insertrow('logstore_standard_log',['courseid'=>$cid,'userid'=>$uid,'timecreated'=>$now-$days*DAYSECS,'action'=>'viewed','component'=>'core']);
}
insertrow('user_lastaccess',['userid'=>205,'courseid'=>104,'timeaccess'=>$now-12*DAYSECS]);
$platform=risk_report::get(1,30,0,7,'platform',$now);
$ids=array_column($platform['students'],'userid');sort($ids);
same([202,203,205],$ids,'Platform mode counts unique enrolled students and respects site last access');
same(['critical'=>2,'alert'=>0,'warn'=>1],$platform['stats'],'Platform risk classifications');
same(['Plataforma em geral'],array_values(array_unique(array_column($platform['students'],'coursename'))),'Platform records are explicitly identified');
$bycourse=risk_report::get(1,30,0,7,'courses',$now);
same(6,count($bycourse['students']),'Course mode keeps separate student/course records without duplicate enrolments');
same(['critical'=>4,'alert'=>1,'warn'=>1],$bycourse['stats'],'Course risk classifications remain separate from platform mode');
$newer = array_values(array_filter($bycourse['students'], function($s) {
    return $s['userid'] === 205;
}));
same(12,$newer[0]['days_since'],'Use the newest of course access and retained logs');
same(4,count(risk_report::get(1,30,0,30,'courses',$now)['students']),'Days threshold affects only the risk cohort');
same(2,count(risk_report::get(1,30,104,7,'platform',$now)['students']),'Selecting a course limits the platform cohort but keeps site-wide last access');
same([],risk_report::get(1,11,0,7,'platform',$now)['students'],'Empty category gives an empty risk report');
foreach ([[301,104,3],[301,104,4],[301,105,3],[302,107,4],[303,104,1]] as [$uid,$cid,$rid]) {
    insertrow('role_assignments',['contextid'=>$cid,'userid'=>$uid,'roleid'=>$rid]);
}
foreach ([[104,'created','mod_assign',3],[104,'updated','mod_quiz',3],[104,'created','mod_book',3],
    [104,'graded','mod_assign',3],[105,'created','core',3],
    [105,'updated','core_course',3],[105,'created','mod_page',60]] as [$cid,$action,$component,$days]) {
    insertrow('logstore_standard_log',['courseid'=>$cid,'userid'=>301,'timecreated'=>$now-$days*DAYSECS,'action'=>$action,'component'=>$component]);
}
insertrow('modules',['id'=>1,'name'=>'forum']);
insertrow('modules',['id'=>2,'name'=>'quiz']);
insertrow('modules',['id'=>3,'name'=>'assign']);
insertrow('assign',['id'=>100,'course'=>104,'name'=>'Trabalho inicial']);
insertrow('course_modules',['id'=>100,'course'=>104,'module'=>3,'instance'=>100]);
insertrow('modules',['id'=>4,'name'=>'book']);
insertrow('modules',['id'=>5,'name'=>'page']);
insertrow('course_modules',['id'=>1000,'course'=>104,'module'=>4,'instance'=>1000]);
insertrow('course_modules',['id'=>1001,'course'=>105,'module'=>5,'instance'=>1001]);
foreach ([[104,100,3],[104,1000,3],[105,1001,60]] as [$cid,$cmid,$days]) {
    insertrow('logstore_standard_log',['courseid'=>$cid,'userid'=>301,'timecreated'=>$now-$days*DAYSECS,
        'action'=>'created','component'=>'core','objecttable'=>'course_modules','objectid'=>$cmid,
        'eventname'=>'\\core\\event\\course_module_created']);
}
insertrow('assign_submission',['id'=>100,'assignment'=>100,'userid'=>201,'timemodified'=>$now-5*DAYSECS]);
insertrow('assign_grades',['id'=>100,'assignment'=>100,'userid'=>201,'grader'=>301,'grade'=>75,'timemodified'=>$now-3*DAYSECS]);
insertrow('forum',['id'=>1,'course'=>105]);
insertrow('course_modules',['id'=>1,'course'=>105,'module'=>1,'instance'=>1]);
// Another user starts a discussion; the teacher replies, with no log history.
insertrow('forum_discussions',['id'=>1,'forum'=>1,'userid'=>201,'firstpost'=>1]);
insertrow('forum_posts',['id'=>1,'discussion'=>1,'parent'=>0,'userid'=>201,'created'=>$now-4*DAYSECS]);
insertrow('forum_posts',['id'=>2,'discussion'=>1,'parent'=>1,'userid'=>301,'created'=>$now-3*DAYSECS]);
$teachers=teachers_report::get(1,30,0,30,$now);
same([301,303],array_column($teachers,'userid'),'Teacher category scope and existing teacher/manager eligibility');
$t=$teachers[0];
same([1,1,1,1,10],[$t['activities'],$t['resources'],$t['graded'],$t['forum_posts'],$t['score']],'Distinct core creation events and separate resources use the default 3/2/4/1 weights');
same(2,count($t['courses']),'Multiple roles do not multiply the same course');
same(12,teachers_report::get(1,30,0,90,$now)[0]['score'],'Activity interval changes the teacher score');
same(0,teachers_report::get(1,30,0,1,$now)[0]['score'],'No activity inside interval produces zero score');
same(9,teachers_report::get(1,30,104,30,$now)[0]['score'],'Course selection excludes activity in other courses');
same([],teachers_report::get(1,11,0,30,$now),'Empty category gives an empty teacher report');
same([302],array_column(teachers_report::get(1,21,0,30,$now),'userid'),'Sibling department returns only its own teachers');
$directrisk=risk_report::get(1,30,0,7,'platform',$now,false);
$directids=array_column($directrisk['students'],'userid');sort($directids);
same([202,205],$directids,'Direct platform cohort excludes students only enrolled in unselected descendants');
same(3,count(risk_report::get(1,30,0,7,'courses',$now,false)['students']),'Direct course risk excludes unselected descendant disciplines');
$directteachers=teachers_report::get(1,30,0,30,$now,false);
same(9,$directteachers[0]['score'],'Direct teacher score excludes activity in unselected descendants');
same(1,count($directteachers[0]['courses']),'Direct teacher report lists only directly selected disciplines');
echo "RESULT $passed total scope/metric SQL assertions passed.\n";
