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
 * Learning Analytics regression checks: teacher scoring.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require(__DIR__.'/forum_export.php');
use local_mulima_analytics\local\teacher_scoring;

$defaults = teacher_scoring::defaults();
same($defaults,teacher_scoring::settings(),'Missing configuration uses the documented defaults');
$baseline = teacherrow(301);
same(11,$baseline['score'],'One activity, one resource, one assessment and two forum posts total 11');
same(2,count($baseline['course_scores']),'The teacher has one score detail per selected course');
same(11,array_sum(array_column($baseline['course_scores'],'score_raw')),'Per-course contributions reconcile with the overall raw score');
foreach (range(1,4) as $unused) {
    insertrow('logstore_standard_log',['courseid'=>104,'userid'=>301,'timecreated'=>$now,
        'action'=>'created','component'=>'core','objecttable'=>'course_modules','objectid'=>100,
        'eventname'=>'\\core\\event\\course_module_created']);
    insertrow('logstore_standard_log',['courseid'=>104,'userid'=>301,'timecreated'=>$now,
        'action'=>'updated','component'=>'core','objecttable'=>'course_modules','objectid'=>100,
        'eventname'=>'\\core\\event\\course_module_updated']);
}
same(11,teacherrow(301)['score'],'Repeated edits and duplicate creation records do not add points');
same([1,1],[teacherrow(301)['activities'],teacherrow(301)['resources']],'A resource is counted separately and never again as an activity');
insertrow('course_modules',['id'=>2000,'course'=>104,'module'=>2,'instance'=>2000]);
insertrow('logstore_standard_log',['courseid'=>104,'userid'=>301,'timecreated'=>$now+1,
    'action'=>'created','component'=>'core','objecttable'=>'course_modules','objectid'=>2000,
    'eventname'=>'\\core\\event\\course_module_created']);
same(11,teacherrow(301)['score'],'Future creation events cannot add current points');
insertrow('course_modules',['id'=>2001,'course'=>104,'module'=>2,'instance'=>2001,'deletioninprogress'=>1]);
insertrow('logstore_standard_log',['courseid'=>104,'userid'=>301,'timecreated'=>$now,
    'action'=>'created','component'=>'core','objecttable'=>'course_modules','objectid'=>2001,
    'eventname'=>'\\core\\event\\course_module_created']);
same(11,teacherrow(301)['score'],'Activities pending deletion are not scored');
same(0,teacherrow(303)['score'],'A co-teacher does not inherit another teacher\'s creation events');

$custom = ['weight_activities'=>5,'weight_resources'=>7,'weight_grading'=>2,'weight_forums'=>3,'maximum'=>99,'moderate'=>10,'high'=>20];
teacher_scoring::save($custom);
same($custom,teacher_scoring::settings(),'All configured weights and thresholds are persisted together');
$t = teacherrow(301);
same([20,20,'high'],[$t['score'],$t['score_raw'],$t['score_level']],'Custom criteria determine the displayed score and the high boundary');
same('moderate',teacher_scoring::calculate(['activities'=>2],$custom)['score_level'],'The moderate threshold is inclusive');
same('low',teacher_scoring::calculate(['activities'=>1],$custom)['score_level'],'A positive score below the moderate threshold is low');
same([1,5,5],array_values($t['score_components']['activities']),'Breakdown exposes actual quantity, saved weight and contribution');
same([17,3],array_column($t['course_scores'],'score_raw'),'Each course contains only its own assessed actions');

$capped = array_replace($custom,['maximum'=>15,'moderate'=>5,'high'=>12]);
teacher_scoring::save($capped);
$t = teacherrow(301);
same([20,15],[$t['score_raw'],$t['score']],'The overall maximum is applied once after summing raw points');
same([15,3],array_column($t['course_scores'],'score'),'The course scores use the same cap without replacing raw contributions');
$rows = [teacherrow(301),teacherrow(303)];
$scoring = $capped;
eval(substr($source,$offset));
$scorecells = $workbook->sheets['score_sheet']->cells;
same([1,5,5,1,7,7,1,2,2,1,3,3,17,15,15,'score_level_high'],array_slice($scorecells[6],3),
    'Excel contains real per-course quantities, weights, points, cap and level');
same('score_level_none',$workbook->sheet->cells[5][15],'Excel and the screen identify zero as no score');

$zero = array_replace($defaults,['weight_activities'=>0,'weight_resources'=>0,'weight_grading'=>0,'weight_forums'=>0]);
teacher_scoring::save($zero);
same($zero,teacher_scoring::settings(),'A saved zero weight is not replaced by its default');
$t = teacherrow(301);
same([0,'none',true],[$t['score'],$t['score_level'],$t['has_activity']],
    'Disabling all score weights does not falsely classify actual activity as absent');
foreach (['',-1,101,'1.5','1e2',[],true] as $invalid) {
    $bad = array_replace($defaults,['weight_resources'=>$invalid]);
    same(true,isset(teacher_scoring::errors($bad)['weight_resources']),'Invalid weight is rejected: '.json_encode($invalid));
}
foreach ([['maximum'=>1],['moderate'=>0],['moderate'=>60,'high'=>60],['high'=>100]] as $invalid) {
    same(true,(bool)teacher_scoring::errors(array_replace($defaults,$invalid)),'Invalid maximum/threshold ordering is rejected');
}
try { teacher_scoring::save(array_replace($defaults,['maximum'=>1])); throw new RuntimeException('Invalid save was accepted'); }
catch (invalid_parameter_exception $e) { same($zero,teacher_scoring::settings(),'An invalid save leaves the previous complete configuration intact'); }

// Exercise the actual settings POST handler, including its capability and CSRF gates.
define('PARAM_ALPHA',1);
define('PARAM_RAW_TRIMMED',2);
final class score_test_redirect extends Exception {}
final class score_test_denied extends Exception {}
final class moodle_url {
    public function __construct($url) {}
    public function set_anchor($anchor) {}
}
function optional_param($name,$default,$type) { return $_POST[$name] ?? $default; }
function require_sesskey() { if (empty($GLOBALS['scoretestsession'])) { throw new score_test_denied('sesskey'); } }
function require_capability($capability,$context) { if (empty($GLOBALS['scoretestadmin'])) { throw new score_test_denied('capability'); } }
function redirect(...$args) { throw new score_test_redirect(); }
class score_test_notification { const NOTIFY_SUCCESS = 1; }
class_alias(score_test_notification::class,'core\output\notification');
$pagecode = file_get_contents(__DIR__.'/../../presenca_config.php');
$start = strpos($pagecode,"if (\$_SERVER['REQUEST_METHOD']");
$handler = substr($pagecode,$start,strpos($pagecode,'// Load current values.')-$start);
$sysctx = (object)['id'=>1];
$config_url = new moodle_url('test');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array_merge($custom,['settingssection'=>'teacherscore']);
$scoretestsession = true;
$scoretestadmin = true;
$scorevalues = $defaults;
set_config('institution_name','Keep this institution','local_mulima_analytics');
try { eval($handler); throw new RuntimeException('Successful save did not redirect'); }
catch (score_test_redirect $e) { same($custom,teacher_scoring::settings(),'The actual settings form saves and redirects on valid input'); }
same('Keep this institution',get_config('local_mulima_analytics','institution_name'),'Saving teacher criteria leaves other configuration untouched');
$_POST['high'] = '5';
eval($handler);
same(true,isset($scoreerrors['high']),'Invalid form values are returned with a field error');
same($custom,teacher_scoring::settings(),'Invalid form submission does not save any criteria');
$_POST['high'] = '20';
$scoretestadmin = false;
try { eval($handler); throw new RuntimeException('Unprivileged settings save was accepted'); }
catch (score_test_denied $e) { same('capability',$e->getMessage(),'Settings save requires site configuration permission'); }
$scoretestadmin = true;
$scoretestsession = false;
try { eval($handler); throw new RuntimeException('Missing session key was accepted'); }
catch (score_test_denied $e) { same('sesskey',$e->getMessage(),'Settings save rejects an invalid session key'); }
teacher_scoring::save($defaults);
echo "RESULT $passed total scoring/settings/export/scope/metric assertions passed.\n";
