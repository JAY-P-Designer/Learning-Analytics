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
 * Student assessment details.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
\local_mulima_analytics\local\access::require_report('dashboard');
require_sesskey();

$cid = required_param('id', PARAM_INT);
$itemid = required_param('item', PARAM_INT);

$course = $DB->get_record('course', ['id' => $cid], '*', MUST_EXIST);
$gi = $DB->get_record('grade_items', ['id' => $itemid, 'courseid' => $cid, 'itemtype' => 'mod'], '*', MUST_EXIST);

\local_mulima_analytics\local\page::setup('students.php', $gi->itemname . ' - ' . get_string('estudantes', 'local_mulima_analytics'));
$PAGE->set_url(new moodle_url('/local/mulima_analytics/students.php', ['id' => $cid, 'item' => $itemid]));

$srid = (int)($DB->get_field('role', 'id', ['shortname' => 'student']) ?: 5);
$ctx = $DB->get_record_sql("SELECT id FROM {context} WHERE contextlevel = 50 AND instanceid = :cid", ['cid' => $cid]);

// Get all enrolled students.
$students = [];
if ($ctx) {
    $students = $DB->get_records_sql(
        "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
         FROM {role_assignments} ra
         JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
         WHERE ra.contextid = :ctx AND ra.roleid = :rid
         ORDER BY u.lastname, u.firstname",
        ['ctx' => $ctx->id, 'rid' => $srid]);
}

// Get grades for this item.
$grades = [];
$recs = $DB->get_records('grade_grades', ['itemid' => $itemid], '', 'userid,finalgrade,rawgrade,timemodified');
foreach ($recs as $g) $grades[(int)$g->userid] = $g;

// Check submissions for assignments.
$submissions = [];
if ($gi->itemmodule === 'assign') {
    $cm = $DB->get_record_sql(
        "SELECT cm.id, cm.instance FROM {course_modules} cm
         JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
         WHERE cm.course = :cid AND cm.instance = :iid",
        ['cid' => $cid, 'iid' => $gi->iteminstance]);
    if ($cm) {
        $subs = $DB->get_records_sql(
            "SELECT userid, status, timemodified FROM {assign_submission}
             WHERE assignment = :aid AND latest = 1 AND userid > 0
             ORDER BY timemodified DESC",
            ['aid' => $cm->instance]);
        foreach ($subs as $s) $submissions[(int)$s->userid] = $s;
    }
}

// Check attempts for quizzes.
$attempts = [];
if ($gi->itemmodule === 'quiz') {
    $atts = $DB->get_records_sql(
        "SELECT userid, state, timefinish FROM {quiz_attempts}
         WHERE quiz = :qid AND state = 'finished'
         ORDER BY timefinish DESC",
        ['qid' => $gi->iteminstance]);
    foreach ($atts as $a) {
        if (!isset($attempts[(int)$a->userid])) $attempts[(int)$a->userid] = $a;
    }
}

// Classify students.
$graded = [];
$submitted_not_graded = [];
$not_submitted = [];

foreach ($students as $stu) {
    $uid = (int)$stu->id;
    $has_grade = isset($grades[$uid]) && $grades[$uid]->finalgrade !== null;
    $has_submission = false;

    if ($gi->itemmodule === 'assign') {
        $has_submission = isset($submissions[$uid]) && $submissions[$uid]->status === 'submitted';
    } else if ($gi->itemmodule === 'quiz') {
        $has_submission = isset($attempts[$uid]);
    } else {
        // For forums and other types, check if rawgrade exists.
        $has_submission = isset($grades[$uid]) && $grades[$uid]->rawgrade !== null;
    }

    if ($has_grade) {
        $graded[] = $stu;
    } else if ($has_submission) {
        $submitted_not_graded[] = $stu;
    } else {
        $not_submitted[] = $stu;
    }
}

$back_url = (new moodle_url('/local/mulima_analytics/detail.php', ['id' => $cid, 'sesskey' => sesskey()]))->out(false);
$types = ['quiz'=>get_string('mod_quiz','local_mulima_analytics'),'assign'=>get_string('mod_assign','local_mulima_analytics'),'forum'=>get_string('mod_forum','local_mulima_analytics'),'lesson'=>get_string('mod_lesson','local_mulima_analytics'),'workshop'=>get_string('mod_workshop','local_mulima_analytics'),'attendance'=>get_string('mod_attendance','local_mulima_analytics'),'scorm'=>get_string('mod_scorm','local_mulima_analytics'),'h5pactivity'=>get_string('mod_h5p','local_mulima_analytics')];
$typename = $types[$gi->itemmodule] ?? $gi->itemmodule;

echo $OUTPUT->header();
?>
<style>
.sw{max-width:960px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif}
.sh{background:linear-gradient(135deg,#0f2b46,#1e4976);border-radius:12px;padding:24px 28px;color:#fff !important;margin-bottom:20px;box-shadow:0 4px 12px rgba(0,0,0,.08)}
.sh h2{margin:0 0 4px;color:#fff !important;font-size:20px;font-weight:700}.sh-s{font-size:13px;opacity:.7}
.ss{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px}
.ss>div{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 18px;flex:1;min-width:160px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.ss-v{font-size:24px;font-weight:800;line-height:1}.ss-l{font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-top:3px}
.sg{margin-bottom:20px}
.sg-h{padding:14px 18px;border-radius:10px 10px 0 0;font-size:13px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.sg-r{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-bottom:none}
.sg-g{background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;border-bottom:none}
.sg-y{background:#fffbeb;color:#d97706;border:1px solid #fde68a;border-bottom:none}
.sg-cnt{font-size:20px;font-weight:800}
.sg table{width:100%;border-collapse:collapse;font-size:12px;background:#fff;border:1px solid #e2e8f0;border-radius:0 0 10px 10px;overflow:hidden}
.sg th{background:#f8fafc;padding:10px 14px;font-weight:600;font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:#64748b;text-align:left;border-bottom:1px solid #e2e8f0}
.sg td{padding:9px 14px;border-bottom:1px solid #f1f5f9}
.sg tr:last-child td{border-bottom:none}
.sg tr:hover td{background:#f8fafc}
.sg-name{font-weight:600;color:#0f172a}.sg-email{font-size:11px;color:#94a3b8}
.sg-empty{padding:20px;text-align:center;color:#94a3b8;font-size:12px;background:#fff;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 10px 10px}
.bk{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;background:#f1f5f9;color:#475569;font-size:12px;font-weight:600;text-decoration:none;margin-bottom:16px;border:1px solid #e2e8f0}.bk:hover{background:#e2e8f0;text-decoration:none;color:#0f172a}
.ty{padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;background:#f1f5f9;color:#475569;display:inline-block;margin-top:6px}
</style>

<div id="relatorio-die-detail" class="sw">
<a href="<?php echo $back_url;?>" class="bk"><i class="fa fa-arrow-left"></i> <?php echo get_string('voltar_a', 'local_mulima_analytics', s($course->shortname)); ?></a>

<div class="sh">
    <h2><?php echo s($gi->itemname ?: get_string('sem_nome', 'local_mulima_analytics'));?></h2>
    <div class="sh-s"><?php echo s($course->fullname);?></div>
    <span class="ty"><?php echo s($typename);?> &middot; <?php echo get_string('nota_maxima_valor', 'local_mulima_analytics', (float)$gi->grademax); ?></span>
</div>

<div class="ss">
    <div><div class="ss-v"><?php echo count($students);?></div><div class="ss-l"><?php echo s(get_string('total_estudantes', 'local_mulima_analytics')); ?></div></div>
    <div><div class="ss-v" style="color:#059669"><?php echo count($graded);?></div><div class="ss-l"><?php echo s(get_string('avaliados', 'local_mulima_analytics')); ?></div></div>
    <div><div class="ss-v" style="color:#dc2626"><?php echo count($submitted_not_graded);?></div><div class="ss-l"><?php echo s(get_string('submeteram_sem_nota', 'local_mulima_analytics')); ?></div></div>
    <div><div class="ss-v" style="color:#d97706"><?php echo count($not_submitted);?></div><div class="ss-l"><?php echo s(get_string('sem_submissao', 'local_mulima_analytics')); ?></div></div>
</div>

<?php if (!empty($submitted_not_graded)): ?>
<div class="sg">
    <div class="sg-h sg-r"><?php echo s(get_string('submeteram_nao_avaliados', 'local_mulima_analytics')); ?> <span class="sg-cnt"><?php echo count($submitted_not_graded);?></span></div>
    <table><thead><tr><th>#</th><th><?php echo s(get_string('estudante', 'local_mulima_analytics')); ?></th><th><?php echo s(get_string('email', 'local_mulima_analytics')); ?></th></tr></thead><tbody>
    <?php $n=0; foreach($submitted_not_graded as $s): $n++;?>
    <tr><td style="color:#94a3b8;font-size:10px"><?php echo $n;?></td><td><span class="sg-name"><?php echo s($s->firstname.' '.$s->lastname);?></span></td><td class="sg-email"><?php echo s($s->email);?></td></tr>
    <?php endforeach;?>
    </tbody></table>
</div>
<?php endif; ?>

<?php if (!empty($not_submitted)): ?>
<div class="sg">
    <div class="sg-h sg-y"><?php echo s(get_string('sem_submissao', 'local_mulima_analytics')); ?> <span class="sg-cnt"><?php echo count($not_submitted);?></span></div>
    <table><thead><tr><th>#</th><th><?php echo s(get_string('estudante', 'local_mulima_analytics')); ?></th><th><?php echo s(get_string('email', 'local_mulima_analytics')); ?></th></tr></thead><tbody>
    <?php $n=0; foreach($not_submitted as $s): $n++;?>
    <tr><td style="color:#94a3b8;font-size:10px"><?php echo $n;?></td><td><span class="sg-name"><?php echo s($s->firstname.' '.$s->lastname);?></span></td><td class="sg-email"><?php echo s($s->email);?></td></tr>
    <?php endforeach;?>
    </tbody></table>
</div>
<?php endif; ?>

<?php if (!empty($graded)): ?>
<div class="sg">
    <div class="sg-h sg-g"><?php echo s(get_string('avaliados', 'local_mulima_analytics')); ?> <span class="sg-cnt"><?php echo count($graded);?></span></div>
    <table><thead><tr><th>#</th><th><?php echo s(get_string('estudante', 'local_mulima_analytics')); ?></th><th><?php echo s(get_string('email', 'local_mulima_analytics')); ?></th></tr></thead><tbody>
    <?php $n=0; foreach($graded as $s): $n++;?>
    <tr><td style="color:#94a3b8;font-size:10px"><?php echo $n;?></td><td><span class="sg-name"><?php echo s($s->firstname.' '.$s->lastname);?></span></td><td class="sg-email"><?php echo s($s->email);?></td></tr>
    <?php endforeach;?>
    </tbody></table>
</div>
<?php endif; ?>

<?php if (empty($students)): ?>
<div class="sg-empty"><?php echo s(get_string('nenhum_estudante_inscrito', 'local_mulima_analytics')); ?></div>
<?php endif; ?>

</div>
<?php
\local_mulima_analytics\local\branding::render_footer('students');
echo $OUTPUT->footer();
?>
