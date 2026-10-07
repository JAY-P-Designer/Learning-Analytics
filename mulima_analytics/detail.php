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
 * Course assessment details.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
\local_mulima_analytics\local\access::require_report('dashboard');
require_sesskey();

$cid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $cid], '*', MUST_EXIST);
$maxpoints = (int)(get_config('local_mulima_analytics', 'maxpoints') ?: 800);

\local_mulima_analytics\local\page::setup('detail.php', $course->fullname . ' - ' . get_string('detail', 'local_mulima_analytics'));
$PAGE->set_url(new moodle_url('/local/mulima_analytics/detail.php', ['id' => $cid]));

$srid = (int)($DB->get_field('role', 'id', ['shortname' => 'student']) ?: 5);
$ctx = $DB->get_record_sql("SELECT id FROM {context} WHERE contextlevel = 50 AND instanceid = :cid", ['cid' => $cid]);
$total_students = 0;
if ($ctx) {
    $total_students = (int)$DB->count_records_sql(
        "SELECT COUNT(DISTINCT ra.userid) FROM {role_assignments} ra WHERE ra.contextid = :ctx AND ra.roleid = :rid",
        ['ctx' => $ctx->id, 'rid' => $srid]);
}

$all_items_raw = $DB->get_records_select('grade_items',
    "courseid = :cid AND itemtype = 'mod'",
    ['cid' => $cid], 'sortorder', 'id,itemname,itemmodule,grademax');
// Filter forums: only include if at least one student graded.
$items = [];
foreach ($all_items_raw as $gi) {
    if ($gi->itemmodule === 'forum') {
        $has = $DB->record_exists_sql(
            "SELECT 1 FROM {grade_grades} WHERE itemid = :iid AND finalgrade IS NOT NULL",
            ['iid' => $gi->id]);
        if (!$has) continue;
    }
    $items[$gi->id] = $gi;
}

$sum = 0;
$details = [];
foreach ($items as $gi) {
    $gc = (int)$DB->count_records_sql(
        "SELECT COUNT(DISTINCT userid) FROM {grade_grades} WHERE itemid = :iid AND finalgrade IS NOT NULL",
        ['iid' => $gi->id]);
    $sum += (float)$gi->grademax;
    $details[] = ['id' => $gi->id, 'name' => $gi->itemname ?: get_string('sem_nome', 'local_mulima_analytics'), 'type' => $gi->itemmodule,
        'max' => (float)$gi->grademax, 'graded' => $gc, 'pending' => max(0, $total_students - $gc)];
}

$diff = (int)$sum - $maxpoints;
$back_url = (new moodle_url('/local/mulima_analytics/index.php'))->out(false);
$students_base = (new moodle_url('/local/mulima_analytics/students.php'))->out(false);

echo $OUTPUT->header();
?>
<style>
.dw{max-width:960px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif}
.dh{background:linear-gradient(135deg,#0f2b46,#1e4976);border-radius:12px;padding:24px 28px;color:#fff !important;margin-bottom:20px;box-shadow:0 4px 12px rgba(0,0,0,.08)}
.dh h2{margin:0 0 4px;color:#fff !important;font-size:20px;font-weight:700}.dh-s{font-size:13px;opacity:.7}
.ds{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:18px}
.ds>div{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 18px;flex:1;min-width:140px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.ds-v{font-size:22px;font-weight:800;color:#0f172a}.ds-l{font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-top:2px}
.dt{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04)}
table.dd{width:100%;border-collapse:collapse;font-size:12px}
.dd th{background:#0f2b46;padding:11px 14px;font-weight:600;font-size:10px;text-transform:uppercase;letter-spacing:.7px;color:rgba(255,255,255,.8);text-align:left}.dd th.c{text-align:center}
.dd td{padding:10px 14px;border-bottom:1px solid #f1f5f9}.dd td.c{text-align:center}
.dd tr:hover td{background:#f8fafc}
.pl{display:inline-flex;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700}
.pl-g{background:#ecfdf5;color:#10b981}.pl-a{background:#fffbeb;color:#f59e0b}.pl-r{background:#fef2f2;color:#ef4444}
.ty{padding:2px 8px;border-radius:4px;font-size:10px;font-weight:600;background:#f1f5f9;color:#475569}
.prg{display:flex;align-items:center;gap:6px}.prg-b{width:68px;height:5px;background:#e2e8f0;border-radius:3px;overflow:hidden}.prg-f{height:100%;border-radius:3px}.prg-t{font-size:10px;font-weight:700;min-width:28px}
.sv{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:6px;font-size:11px;font-weight:600;color:#0d9488;background:rgba(13,148,136,.08);text-decoration:none;transition:all .12s}.sv:hover{background:rgba(13,148,136,.15);color:#0d9488;text-decoration:none}
.bk{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;background:#f1f5f9;color:#475569;font-size:12px;font-weight:600;text-decoration:none;margin-bottom:16px;border:1px solid #e2e8f0}.bk:hover{background:#e2e8f0;text-decoration:none;color:#0f172a}
</style>
<div id="relatorio-die-detail" class="dw">
<a href="<?php echo $back_url;?>" class="bk"><i class="fa fa-arrow-left"></i> <?php echo s(get_string('voltar_relatorio', 'local_mulima_analytics')); ?></a>
<div class="dh">
    <h2><?php echo s($course->fullname);?></h2>
    <div class="dh-s"><?php echo s($course->shortname);?></div>
</div>
<div class="ds">
    <div><div class="ds-v"><?php echo count($details);?></div><div class="ds-l"><?php echo s(get_string('avaliacoes', 'local_mulima_analytics')); ?></div></div>
    <div><div class="ds-v"><?php echo (int)$sum;?></div><div class="ds-l"><?php echo s(get_string('soma_maximos', 'local_mulima_analytics')); ?></div></div>
    <div><div class="ds-v"><?php echo $maxpoints;?></div><div class="ds-l"><?php echo s(get_string('maximo_esperado', 'local_mulima_analytics')); ?></div></div>
    <div><div class="ds-v" style="color:<?php echo $diff===0?'#10b981':($diff<0?'#f59e0b':'#ef4444');?>"><?php echo $diff===0?get_string('estado_ok','local_mulima_analytics'):($diff>0?'+'.$diff:$diff);?></div><div class="ds-l"><?php echo s(get_string('diferenca', 'local_mulima_analytics')); ?></div></div>
    <div><div class="ds-v"><?php echo $total_students;?></div><div class="ds-l"><?php echo s(get_string('estudantes', 'local_mulima_analytics')); ?></div></div>
</div>
<div class="dt"><table class="dd">
<thead><tr><th>#</th><th><?php echo s(get_string('avaliacao', 'local_mulima_analytics')); ?></th><th><?php echo s(get_string('tipo', 'local_mulima_analytics')); ?></th><th class="c"><?php echo s(get_string('nota_maxima', 'local_mulima_analytics')); ?></th><th class="c"><?php echo s(get_string('trabalhos_corrigidos', 'local_mulima_analytics')); ?></th><th class="c"><?php echo s(get_string('sem_nota', 'local_mulima_analytics')); ?></th><th class="c"><?php echo s(get_string('progresso', 'local_mulima_analytics')); ?></th><th class="c"><?php echo s(get_string('estudantes', 'local_mulima_analytics')); ?></th></tr></thead>
<tbody>
<?php $idx=0; foreach($details as $d): $idx++;
    $p = $total_students > 0 ? round($d['graded']/$total_students*100) : 0;
    $pc = $p===100 ? '#10b981' : ($p>=50 ? '#f59e0b' : '#ef4444');
    $types = ['quiz'=>get_string('mod_quiz','local_mulima_analytics'),'assign'=>get_string('mod_assign','local_mulima_analytics'),'forum'=>get_string('mod_forum','local_mulima_analytics'),'lesson'=>get_string('mod_lesson','local_mulima_analytics'),'workshop'=>get_string('mod_workshop','local_mulima_analytics'),'attendance'=>get_string('mod_attendance','local_mulima_analytics'),'scorm'=>get_string('mod_scorm','local_mulima_analytics'),'h5pactivity'=>get_string('mod_h5p','local_mulima_analytics')];
    $tn = $types[$d['type']] ?? $d['type'];
?>
<tr>
    <td style="color:#94a3b8;font-size:10px;font-weight:600"><?php echo $idx;?></td>
    <td style="font-weight:600"><?php echo s($d['name']);?></td>
    <td><span class="ty"><?php echo s($tn);?></span></td>
    <td class="c"><strong><?php echo $d['max'];?></strong></td>
    <td class="c"><span class="pl pl-g"><?php echo $d['graded'];?></span></td>
    <td class="c"><?php echo $d['pending']>0?'<span class="pl pl-a">'.$d['pending'].'</span>':'<span class="pl pl-g">0</span>';?></td>
    <td class="c"><div class="prg"><div class="prg-b"><div class="prg-f" style="width:<?php echo $p;?>%;background:<?php echo $pc;?>"></div></div><span class="prg-t" style="color:<?php echo $pc;?>"><?php echo $p;?>%</span></div></td>
    <td class="c"><a href="<?php echo $students_base;?>?id=<?php echo $cid;?>&item=<?php echo $d['id'];?>&sesskey=<?php echo sesskey();?>" class="sv" ><i class="fa fa-users"></i> <?php echo s(get_string('ver', 'local_mulima_analytics')); ?></a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</div>
<?php
\local_mulima_analytics\local\branding::render_footer('detail');
echo $OUTPUT->footer();
?>
