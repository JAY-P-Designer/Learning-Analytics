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
 * Attendance list preview.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
\local_mulima_analytics\local\access::require_report('attendance');
// Config helper: reads local_mulima_analytics, falls back to local_listas_exame (legacy).
if (!function_exists('local_mulima_analytics_attendance_config')) {
    function local_mulima_analytics_attendance_config($key) {
        return \local_mulima_analytics\local\attendance_config::get($key);
    }
}

$PAGE->set_context(context_system::instance());
require_sesskey();

$epoch   = required_param('epoch', PARAM_ALPHA);
$scope   = required_param('scope', PARAM_ALPHA);
$cat_id  = optional_param('cat', 0, PARAM_INT);
$course_id = optional_param('course', 0, PARAM_INT);
$group_id  = optional_param('group', 0, PARAM_INT);

$cfg_inst = local_mulima_analytics_attendance_config('institution_name') ?: 'INSTITUTO SUPERIOR DE TRANSPORTES E COMUNICACOES';
$cfg_short = local_mulima_analytics_attendance_config('institution_short') ?: 'ISUTC';
$cfg_pass = (int)(local_mulima_analytics_attendance_config('pass_grade') ?: 10);
$cfg_footer_l = local_mulima_analytics_attendance_config('footer_left') ?: get_string('lp_signature_teacher', 'local_mulima_analytics');
$cfg_footer_r = local_mulima_analytics_attendance_config('footer_right') ?: get_string('lp_signature_head', 'local_mulima_analytics');
$cfg_footer_note = local_mulima_analytics_attendance_config('footer_note') ?: '';
$cfg_show_code = (int)(local_mulima_analytics_attendance_config('show_code') ?? 1);
$cfg_show_nota = (int)(local_mulima_analytics_attendance_config('show_nota') ?? 1);
$cfg_show_sign = (int)(local_mulima_analytics_attendance_config('show_signature') ?? 1);
$cfg_show_email = (int)(local_mulima_analytics_attendance_config('show_email') ?? 0);
$cfg_show_obs = (int)(local_mulima_analytics_attendance_config('show_obs') ?? 1);
$cfg_show_discipline = (int)(local_mulima_analytics_attendance_config('show_discipline') ?? 1);
$cfg_show_period = (int)(local_mulima_analytics_attendance_config('show_period') ?? 1);
$cfg_show_teacher = (int)(local_mulima_analytics_attendance_config('show_teacher') ?? 1);
$cfg_show_count = (int)(local_mulima_analytics_attendance_config('show_count') ?? 1);
$cfg_show_group = (int)(local_mulima_analytics_attendance_config('show_group') ?? 1);
$cfg_show_date = (int)(local_mulima_analytics_attendance_config('show_date') ?? 1);

$epoch_labels = ['normal' => get_string('exame_normal', 'local_mulima_analytics'), 'recorrencia' => get_string('exame_recorrencia', 'local_mulima_analytics'), 'especial' => get_string('exame_especial', 'local_mulima_analytics')];
$epoch_label = $epoch_labels[$epoch] ?? get_string('exame', 'local_mulima_analytics');

$student_rid = (int)($DB->get_field('role', 'id', ['shortname' => 'student']) ?: 5);
$teacher_rid = (int)($DB->get_field('role', 'id', ['shortname' => 'editingteacher']) ?: 3);
$logo_url = '';
$fs = get_file_storage();
$logo_files = $fs->get_area_files(
    context_system::instance()->id,
    'local_mulima_analytics',
    'logo_pdf',
    0,
    'id DESC',
    false
);
if ($logo_files) {
    $logo_file = reset($logo_files);
    $logo_url = moodle_url::make_pluginfile_url(
        $logo_file->get_contextid(),
        $logo_file->get_component(),
        $logo_file->get_filearea(),
        $logo_file->get_itemid(),
        $logo_file->get_filepath(),
        $logo_file->get_filename()
    )->out(false);
}

$group_name = '';
if ($group_id > 0) {
    $grp = $DB->get_record('groups', ['id' => $group_id], 'name');
    if ($grp) $group_name = $grp->name;
}

// Get courses (same logic as export.php).
$courses = [];
if (($scope === 'course' || $scope === 'group') && $course_id > 0) {
    $co = $DB->get_record('course', ['id' => $course_id], 'id,fullname,shortname,category');
    if ($co) $courses = [$co];
} else if ($scope === 'category' && $cat_id > 0) {
    $expanded = [$cat_id];
    $subs = $DB->get_fieldset_select('course_categories', 'id', "path LIKE :p", ['p' => '%/'.$cat_id.'/%']);
    $expanded = array_unique(array_map('intval', array_merge($expanded, $subs)));
    list($insql, $inparams) = $DB->get_in_or_equal($expanded, SQL_PARAMS_NAMED, 'ec');
    $courses = $DB->get_records_select('course', "category $insql AND id <> :s",
        array_merge($inparams, ['s' => SITEID]), 'fullname', 'id,fullname,shortname,category');
}

// Build data (same as export.php with group split).
$all_data = [];
foreach ($courses as $co) {
    $cid = (int)$co->id;
    $ctx = context_course::instance($cid, IGNORE_MISSING);
    if (!$ctx) continue;

    $teachers = [];
    $trecs = $DB->get_records_sql(
        "SELECT DISTINCT u.firstname, u.lastname FROM {role_assignments} ra
         JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
         WHERE ra.contextid = :ctx AND ra.roleid = :rid ORDER BY u.lastname",
        ['ctx' => $ctx->id, 'rid' => $teacher_rid]);
    foreach ($trecs as $t) $teachers[] = $t->firstname . ' ' . $t->lastname;

    $catname = '';
    $cx = $DB->get_record('course_categories', ['id' => $co->category], 'id,name,path');
    if ($cx) {
        // Get only the root (top-level) category as período.
        $parts = explode('/', trim($cx->path, '/'));
        if (!empty($parts)) {
            $root_cat = $DB->get_record('course_categories', ['id' => (int)$parts[0]], 'name');
            if ($root_cat) $catname = $root_cat->name;
        }
    }

    $sql = "SELECT DISTINCT u.id, u.username, u.idnumber, u.firstname, u.lastname, u.email
            FROM {role_assignments} ra JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
            WHERE ra.contextid = :ctx AND ra.roleid = :rid";
    $params = ['ctx' => $ctx->id, 'rid' => $student_rid];
    if ($scope === 'group' && $group_id > 0) {
        $sql .= " AND u.id IN (SELECT userid FROM {groups_members} WHERE groupid = :gid)";
        $params['gid'] = $group_id;
    }
    $sql .= " ORDER BY u.lastname, u.firstname";
    $students = $DB->get_records_sql($sql, $params);

    // Filter for recorrência/especial.
    if ($epoch !== 'normal' && !empty($students)) {
        // Find course total (Media) grade item.
        $course_gi = $DB->get_record('grade_items', ['courseid' => $cid, 'itemtype' => 'course']);
        $pass = 10; // default pass grade
        if ($course_gi && (float)$course_gi->gradepass > 0) {
            $pass = (float)$course_gi->gradepass;
        }

        // Get Media grades.
        $media_grades = [];
        if ($course_gi) {
            foreach ($DB->get_records('grade_grades', ['itemid' => $course_gi->id], '', 'userid,finalgrade,feedback') as $g) {
                $media_grades[(int)$g->userid] = [
                    'grade' => (float)($g->finalgrade ?? 0),
                    'feedback' => $g->feedback ?? '',
                ];
            }
        }

        // Also check exam grades (NE, ER) to detect who passed via exam.
        // Find exam items by idnumber first, then by name.
        $ne_gi = $DB->get_record('grade_items', ['courseid' => $cid, 'idnumber' => 'NE']);
        if (!$ne_gi) {
            $ne_gi = $DB->get_record_sql(
                "SELECT * FROM {grade_items} WHERE courseid = :cid AND itemtype = 'manual'
                 AND (LOWER(itemname) LIKE '%exame%' OR LOWER(itemname) LIKE '%exam%')
                 ORDER BY id LIMIT 1", ['cid' => $cid]);
        }
        $er_gi = $DB->get_record('grade_items', ['courseid' => $cid, 'idnumber' => 'ER']);
        if (!$er_gi) {
            $er_gi = $DB->get_record_sql(
                "SELECT * FROM {grade_items} WHERE courseid = :cid AND itemtype = 'manual'
                 AND (LOWER(itemname) LIKE '%recorr%' OR LOWER(itemname) LIKE '%recur%')
                 ORDER BY id LIMIT 1", ['cid' => $cid]);
        }
        $exam_threshold = 0;
        if ($ne_gi) $exam_threshold = (float)$ne_gi->grademax / 2;
        $ne_grades = [];
        $er_grades = [];
        if ($ne_gi) {
            foreach ($DB->get_records('grade_grades', ['itemid' => $ne_gi->id], '', 'userid,finalgrade') as $g)
                $ne_grades[(int)$g->userid] = (float)($g->finalgrade ?? 0);
        }
        if ($er_gi) {
            foreach ($DB->get_records('grade_grades', ['itemid' => $er_gi->id], '', 'userid,finalgrade') as $g)
                $er_grades[(int)$g->userid] = (float)($g->finalgrade ?? 0);
        }
        $fr_students = [];
        $fr_items = [];
        if ($ne_gi) $fr_items[] = $ne_gi->id;
        if ($er_gi) $fr_items[] = $er_gi->id;
        if (empty($fr_items)) {
            $fr_items = $DB->get_fieldset_select('grade_items', 'id',
                "courseid = :cid AND (itemtype = 'manual' OR itemtype = 'course')", ['cid' => $cid]);
        }
        foreach ($fr_items as $fid) {
            $fgs = $DB->get_records('grade_grades', ['itemid' => $fid], '', 'userid,feedback,excluded');
            foreach ($fgs as $fg) {
                $fb = strtoupper(strip_tags($fg->feedback ?? ''));
                if (strpos($fb, 'FR') !== false || strpos($fb, 'FRAUDE') !== false || (int)$fg->excluded > 0) {
                    $fr_students[(int)$fg->userid] = true;
                }
            }
        }
        $filtered = [];
        foreach ($students as $st) {
            $uid = (int)$st->id;
            if (($media_grades[$uid] ?? 0) >= $pass) continue;
            if (!$cfg_include_fr && isset($fr_students[$uid])) continue;
            $filtered[$uid] = $st;
        }
        $students = $filtered;
    }

    if (empty($students)) continue;

    // Split by groups.
    $course_groups = $DB->get_records('groups', ['courseid' => $cid], 'name', 'id,name');
    $should_split = (!empty($course_groups)) && ($scope === 'category' || ($scope === 'group' && $group_id == 0));
    if ($should_split) {
        foreach ($course_groups as $grp) {
            $gm_ids = $DB->get_fieldset_select('groups_members', 'userid', 'groupid = :gid', ['gid' => $grp->id]);
            if (empty($gm_ids)) continue;
            $gs = [];
            foreach ($students as $st) { if (in_array((int)$st->id, $gm_ids)) $gs[(int)$st->id] = $st; }
            if (!empty($gs)) {
                $gt = [];
                $gtr = $DB->get_records_sql(
                    "SELECT DISTINCT u.firstname, u.lastname FROM {role_assignments} ra
                     JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
                     JOIN {groups_members} gm ON gm.userid = u.id AND gm.groupid = :gid
                     WHERE ra.contextid = :ctx AND ra.roleid = :rid ORDER BY u.lastname",
                    ['ctx' => $ctx->id, 'rid' => $teacher_rid, 'gid' => $grp->id]);
                foreach ($gtr as $t) $gt[] = $t->firstname . ' ' . $t->lastname;
                if (empty($gt)) $gt = $teachers;
                $all_data[] = ['course' => $co, 'catname' => $catname, 'teachers' => $gt, 'students' => $gs, 'group' => $grp->name];
            }
        }
        $all_gm = [];
        foreach ($course_groups as $grp) { $gm = $DB->get_fieldset_select('groups_members', 'userid', 'groupid = :gid', ['gid' => $grp->id]); $all_gm = array_merge($all_gm, $gm); }
        $all_gm = array_unique(array_map('intval', $all_gm));
        $ng = [];
        foreach ($students as $st) { if (!in_array((int)$st->id, $all_gm)) $ng[(int)$st->id] = $st; }
        if (!empty($ng)) $all_data[] = ['course' => $co, 'catname' => $catname, 'teachers' => $teachers, 'students' => $ng, 'group' => get_string('lp_sem_turma', 'local_mulima_analytics')];
    } else {
        $gn = ($scope === 'group' && $group_name) ? $group_name : '';
        $all_data[] = ['course' => $co, 'catname' => $catname, 'teachers' => $teachers, 'students' => $students, 'group' => $gn];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo s(get_string('title_presenca', 'local_mulima_analytics')); ?> - <?php echo s($epoch_label); ?></title>
<style>
@media print {
    html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; }
    .no-print { display: none !important; }
    .page { page-break-after: always !important; break-after: page !important; box-shadow: none !important; margin: 0 !important; padding: 12mm !important; max-width: none !important; }
    .page:last-child { page-break-after: auto !important; break-after: auto !important; }
    @page { size: A4 portrait; margin: 8mm; }
    table { page-break-inside: auto; }
    tr { page-break-inside: avoid; }
}
.page-num { position: absolute; bottom: 8px; right: 20px; font-size: 8px; color: #94a3b8; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; color: #0f172a; background: #f1f5f9; }
.no-print { background: #0a1628; padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
.no-print span { color: #fff; font-size: 13px; font-weight: 600; }
.no-print button { padding: 8px 20px; border-radius: 8px; border: none; font-size: 12px; font-weight: 700; cursor: pointer; color: #fff; background: #2563eb; font-family: inherit; }
.no-print button:hover { background: #3b82f6; }
.page { background: #fff; max-width: 210mm; margin: 16px auto; padding: 15mm; box-shadow: 0 2px 12px rgba(0,0,0,.08); border: 1px solid #e2e8f0; }
.hdr { display: flex; align-items: center; gap: 14px; margin-bottom: 6px; justify-content: center; }
.hdr img { height: 38px; }
.hdr-txt { font-size: 12px; font-weight: 700; color: #0a1628; text-align: center; }
.hdr-sub { font-size: 8px; color: #94a3b8; text-align: center; }
.epoch { text-align: center; font-size: 14px; font-weight: 800; color: #1e3d6f; margin: 12px 0 10px; padding-bottom: 6px; border-bottom: 2px solid #1e3d6f; }
.info-grid { font-size: 9px; margin-bottom: 10px; display: grid; grid-template-columns: 70px 1fr 70px 1fr; gap: 3px 0; align-items: baseline; }
.info-l { font-weight: 700; color: #64748b; text-align: left; padding-right: 8px; }
.info-v { color: #0f172a; text-align: left; }
.info-full { grid-column: 2 / -1; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 9px; }
th { background: #0a1628; color: #fff; padding: 6px 8px; font-weight: 700; font-size: 8px; text-transform: uppercase; letter-spacing: .5px; text-align: center; }
th:nth-child(3) { text-align: left; }
td { padding: 5px 8px; border: 1px solid #e2e8f0; }
td:nth-child(1), td:nth-child(2) { text-align: center; }
td:nth-child(2) { font-weight: 700; color: #1e3d6f; }
tr:nth-child(even) td { background: #f8fafc; }
.ft { margin-top: 16px; display: flex; justify-content: space-between; font-size: 9px; color: #475569; }
.gen { text-align: center; margin-top: 14px; font-size: 7px; color: #94a3b8; }
</style>
</head>
<body>

<div class="no-print">
    <span><?php echo s($epoch_label); ?> - <?php echo get_string('listas_count', 'local_mulima_analytics', count($all_data)); ?></span>
    <button onclick="window.print()"><i class="fa fa-print"></i> <?php echo s(get_string('imprimir', 'local_mulima_analytics')); ?></button>
</div>

<?php $page_counter = 0; foreach ($all_data as $d): $page_counter++;
    $co = $d['course'];
    $gname = $d['group'] ?? '';
?>
<div class="page" style="position:relative">
    <div class="hdr">
        <?php if ($logo_url): ?><img src="<?php echo $logo_url; ?>" alt="<?php echo s($cfg_short); ?>"><?php endif; ?>
        <div>
            <div class="hdr-txt"><?php echo s($cfg_inst); ?></div>
            <div class="hdr-sub"><?php echo s(get_string('title_presenca', 'local_mulima_analytics')); ?></div>
        </div>
    </div>

    <div class="epoch"><?php echo strtoupper($epoch_label); ?></div>

    <div class="info-grid">
        <?php if ($cfg_show_discipline): ?>
        <span class="info-l"><?php echo s(get_string('lp_disciplina', 'local_mulima_analytics')); ?></span><span class="info-v info-full"><?php echo s($co->fullname); ?></span>
        <?php endif; ?>
        <?php if ($cfg_show_period): ?>
        <span class="info-l"><?php echo s(get_string('lp_periodo', 'local_mulima_analytics')); ?></span><span class="info-v info-full"><?php echo s($d['catname']); ?></span>
        <?php endif; ?>
        <?php if ($cfg_show_teacher): ?>
        <span class="info-l"><?php echo s(get_string('lp_docente', 'local_mulima_analytics')); ?></span><span class="info-v info-full"><?php echo s(implode(', ', $d['teachers'])); ?></span>
        <?php endif; ?>
        <?php if ($cfg_show_count): ?>
        <span class="info-l"><?php echo s(get_string('lp_inscritos', 'local_mulima_analytics')); ?></span><span class="info-v"><?php echo get_string('lp_inscritos_n', 'local_mulima_analytics', count($d['students'])); ?></span>
        <?php endif; ?>
        <?php if ($cfg_show_group && $gname): ?><span class="info-l"><?php echo s(get_string('lp_turma', 'local_mulima_analytics')); ?></span><span class="info-v"><?php echo s($gname); ?></span><?php endif; ?>
        <?php if ($cfg_show_date): ?>
        <span class="info-l"><?php echo s(get_string('lp_data', 'local_mulima_analytics')); ?></span><span class="info-v">____/____/________</span>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th><?php echo s(get_string('lp_numero', 'local_mulima_analytics')); ?></th>
                <?php if ($cfg_show_code): ?><th><?php echo s(get_string('lp_codigo', 'local_mulima_analytics')); ?></th><?php endif; ?>
                <th><?php echo s(get_string('lp_nome_completo', 'local_mulima_analytics')); ?></th>
                <?php if ($cfg_show_email): ?><th><?php echo s(get_string('email', 'local_mulima_analytics')); ?></th><?php endif; ?>
                <?php if ($cfg_show_nota): ?><th><?php echo s(get_string('nota', 'local_mulima_analytics')); ?></th><?php endif; ?>
                <?php if ($cfg_show_sign): ?><th><?php echo s(get_string('cfg_assinatura', 'local_mulima_analytics')); ?></th><?php endif; ?>
                <?php if ($cfg_show_obs): ?><th><?php echo s(get_string('cfg_observacoes', 'local_mulima_analytics')); ?></th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php $n = 0; foreach ($d['students'] as $st): $n++;
                $code = !empty($st->idnumber) ? $st->idnumber : $st->username;
            ?>
            <tr>
                <td><?php echo $n; ?></td>
                <?php if ($cfg_show_code): ?><td><?php echo s($code); ?></td><?php endif; ?>
                <td><?php echo s($st->firstname . ' ' . $st->lastname); ?></td>
                <?php if ($cfg_show_email): ?><td><?php echo s($st->email); ?></td><?php endif; ?>
                <?php if ($cfg_show_nota): ?><td style="width:50px"></td><?php endif; ?>
                <?php if ($cfg_show_sign): ?><td style="width:120px"></td><?php endif; ?>
                <?php if ($cfg_show_obs): ?><td style="width:90px"></td><?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($cfg_show_sign): ?><div class="ft">
        <span><?php echo s($cfg_footer_l); ?>: ___________________________</span>
        <span><?php echo s($cfg_footer_r); ?>: ___________________________</span>
    </div><?php endif; ?>
    <?php if ($cfg_footer_note): ?><div class="gen" style="text-align:center"><?php echo s($cfg_footer_note); ?></div><?php endif; ?>
    <div class="gen"><?php echo get_string('lp_gerado_em', 'local_mulima_analytics', date('d/m/Y H:i')); ?> | <?php echo s(get_string('title_presenca', 'local_mulima_analytics')); ?> <?php echo s($cfg_short); ?></div>
    <div class="page-num"><?php echo s(get_string('page_number', 'local_mulima_analytics', $page_counter)); ?></div>
</div>
<?php endforeach; ?>

<?php \local_mulima_analytics\local\branding::render_footer('preview'); ?>

<script>
window.onload = function() {
    setTimeout(function(){ window.print(); }, 800);
};
</script>
</body>
</html>
