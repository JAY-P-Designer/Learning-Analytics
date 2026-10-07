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
 * Attendance list report export.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
\local_mulima_analytics\local\access::require_export('attendance');
// Config helper: reads local_mulima_analytics, falls back to local_listas_exame (legacy).
if (!function_exists('local_mulima_analytics_attendance_config')) {
    function local_mulima_analytics_attendance_config($key) {
        return \local_mulima_analytics\local\attendance_config::get($key);
    }
}

$PAGE->set_context(context_system::instance());
require_sesskey();
\local_mulima_analytics\local\audit::export('attendance', 'presenca_export.php');

$epoch   = required_param('epoch', PARAM_ALPHA);
$format  = required_param('format', PARAM_ALPHA);
$scope   = required_param('scope', PARAM_ALPHA);
$cat_id  = optional_param('cat', 0, PARAM_INT);
$course_id = optional_param('course', 0, PARAM_INT);
$group_id  = optional_param('group', 0, PARAM_INT);

// Load plugin config.
$cfg_inst = local_mulima_analytics_attendance_config('institution_name') ?: 'INSTITUTO SUPERIOR DE TRANSPORTES E COMUNICACOES';
$cfg_short = local_mulima_analytics_attendance_config('institution_short') ?: 'ISUTC';
$cfg_pass = (int)(local_mulima_analytics_attendance_config('pass_grade') ?: 10);
$cfg_threshold_pct = (int)(local_mulima_analytics_attendance_config('exam_threshold_pct') ?: 50);
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
$cfg_include_fr = (int)(local_mulima_analytics_attendance_config('include_fr') ?? 0);
$cfg_periodo = local_mulima_analytics_attendance_config('periodo_lectivo') ?: '';
$cfg_período = get_config('local_listas_exame', 'período_lectivo') ?: '';
$cfg_fr_keywords = local_mulima_analytics_attendance_config('fr_keywords') ?: 'FR,FRAUDE,FRAUD';
$cfg_footer_note = local_mulima_analytics_attendance_config('footer_note') ?: '';

// Load uploaded logo for PDF.
$legacy_logo = $CFG->dirroot . '/local/listas_exame/pix/logo.png';
$logo_pdf_path = file_exists($legacy_logo) ? $legacy_logo : '';
$fs = get_file_storage();
$logo_files = $fs->get_area_files(context_system::instance()->id, 'local_mulima_analytics', 'logo_pdf', 0, 'id', false);
if (empty($logo_files)) {
    $logo_files = $fs->get_area_files(context_system::instance()->id, 'local_listas_exame', 'logo_pdf', 0, 'id', false);
}
if (!empty($logo_files)) {
    $logo_file = reset($logo_files);
    // Moodle removes this private temporary copy when the request finishes.
    $tmp_logo = make_request_directory() . '/logo';
    $logo_file->copy_content_to($tmp_logo);
    $logo_pdf_path = $tmp_logo;
}
$cfg_footer_l = local_mulima_analytics_attendance_config('footer_left') ?: get_string('lp_signature_teacher', 'local_mulima_analytics');
$cfg_footer_r = local_mulima_analytics_attendance_config('footer_right') ?: get_string('lp_signature_head', 'local_mulima_analytics');

$epoch_labels = [
    'normal' => get_string('exame_normal', 'local_mulima_analytics'),
    'recorrencia' => get_string('exame_recorrencia', 'local_mulima_analytics'),
    'especial' => get_string('exame_especial', 'local_mulima_analytics'),
];
$epoch_label = $epoch_labels[$epoch] ?? get_string('exame', 'local_mulima_analytics');

$student_rid = (int)($DB->get_field('role', 'id', ['shortname' => 'student']) ?: 5);
$teacher_rid = (int)($DB->get_field('role', 'id', ['shortname' => 'editingteacher']) ?: 3);


// Group name.
$group_name = '';
if ($group_id > 0) {
    $grp = $DB->get_record('groups', ['id' => $group_id], 'name');
    if ($grp) $group_name = $grp->name;
}

// Get courses.
$courses = [];
if (($scope === 'course' || $scope === 'group') && $course_id > 0) {
    $co = $DB->get_record('course', ['id' => $course_id], 'id,fullname,shortname,category');
    if ($co) $courses = [$co];
} else if ($scope === 'category' && $cat_id > 0) {
    $expanded = [$cat_id];
    $subs = optional_param('descendants', false, PARAM_BOOL)
        ? $DB->get_fieldset_select('course_categories', 'id', "path LIKE :p", ['p' => '%/'.$cat_id.'/%']) : [];
    $expanded = array_unique(array_map('intval', array_merge($expanded, $subs)));
    list($insql, $inparams) = $DB->get_in_or_equal($expanded, SQL_PARAMS_NAMED, 'ec');
    $courses = $DB->get_records_select('course', "category $insql AND id <> :s",
        array_merge($inparams, ['s' => SITEID]), 'fullname', 'id,fullname,shortname,category');
}

if (empty($courses)) {
    redirect(new moodle_url('/local/mulima_analytics/presenca.php'), get_string('no_courses_scope', 'local_mulima_analytics'));
}

// Build data.
$all_data = [];
foreach ($courses as $co) {
    $cid = (int)$co->id;
    $cat_ano = $cat_curso = $cat_periodo = '';
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
        // Build full path.
        $parts = explode('/', trim($cx->path, '/'));
        $names = [];
        foreach ($parts as $pid) {
            $pc = $DB->get_record('course_categories', ['id' => (int)$pid], 'name');
            if ($pc) $names[] = $pc->name;
        }
        $catname = implode(' > ', $names);
        $cat_ano     = $names[0] ?? '';
        $cat_curso   = $names[1] ?? '';
        $cat_periodo = $names[2] ?? '';
    }

    $sql = "SELECT DISTINCT u.id, u.username, u.idnumber, u.firstname, u.lastname, u.email
            FROM {role_assignments} ra JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
            WHERE ra.contextid = :ctx AND ra.roleid = :rid";
    $params = ['ctx' => $ctx->id, 'rid' => $student_rid];
    if ($scope === 'group' && $group_id > 0) {
        $sql .= " AND u.id IN (SELECT userid FROM {groups_members} WHERE groupid = :gid)";
        $params['gid'] = $group_id;
    }
    $sql .= " ORDER BY u.firstname, u.lastname";
    $students = $DB->get_records_sql($sql, $params);


    // Always detect FR (for marking in nota column).
    $fr_students = [];
    $ne_gi_fr = $DB->get_record('grade_items', ['courseid' => $cid, 'idnumber' => 'NE']);
    if (!$ne_gi_fr) {
        $ne_gi_fr = $DB->get_record_sql(
            "SELECT * FROM {grade_items} WHERE courseid = :cid AND itemtype = 'manual'
             AND (LOWER(itemname) LIKE '%exame%' OR LOWER(itemname) LIKE '%exam%')
             ORDER BY id LIMIT 1", ['cid' => $cid]);
    }
    $er_gi_fr = $DB->get_record('grade_items', ['courseid' => $cid, 'idnumber' => 'ER']);
    if (!$er_gi_fr) {
        $er_gi_fr = $DB->get_record_sql(
            "SELECT * FROM {grade_items} WHERE courseid = :cid AND itemtype = 'manual'
             AND (LOWER(itemname) LIKE '%recorr%' OR LOWER(itemname) LIKE '%recur%')
             ORDER BY id LIMIT 1", ['cid' => $cid]);
    }
    $fr_items_check = [];
    if ($ne_gi_fr) $fr_items_check[] = $ne_gi_fr->id;
    if ($er_gi_fr) $fr_items_check[] = $er_gi_fr->id;
    if (empty($fr_items_check)) {
        $fr_items_check = $DB->get_fieldset_select('grade_items', 'id',
            "courseid = :cid AND (itemtype = 'manual' OR itemtype = 'course')", ['cid' => $cid]);
    }
    foreach ($fr_items_check as $frid) {
        $fg_recs = $DB->get_records('grade_grades', ['itemid' => $frid], '', 'userid,feedback,excluded');
        foreach ($fg_recs as $fg) {
            $fb = strtoupper(strip_tags($fg->feedback ?? ''));
            $fr_found = false;
            foreach (explode(',', $cfg_fr_keywords) as $kw) {
                if (strpos($fb, strtoupper(trim($kw))) !== false) { $fr_found = true; break; }
            }
            if ($fr_found || (int)$fg->excluded > 0) {
                $fr_students[(int)$fg->userid] = true;
            }
        }
    }

    if ($epoch !== 'normal' && !empty($students)) {
        // Find course total (Media) grade item.
        $course_gi = $DB->get_record('grade_items', ['courseid' => $cid, 'itemtype' => 'course']);
        $pass = $cfg_pass;
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

        // FR already detected above.

        $filtered = [];
        foreach ($students as $st) {
            $uid = (int)$st->id;
            $mg = $media_grades[$uid] ?? ['grade' => 0, 'feedback' => ''];

            // Exclude FR unless config says include.
            if (!$cfg_include_fr && isset($fr_students[$uid])) continue;

            // Exclude approved: Media >= pass grade.
            if ($mg['grade'] >= $pass && $mg['grade'] > 0) continue;

            if ($epoch === 'especial') {
                // Especial: only students who failed/were absent in BOTH exams.
                $ne_grade = $ne_grades[$uid] ?? 0;
                $er_grade = $er_grades[$uid] ?? 0;

                // If passed via normal exam (NE >= threshold), exclude.
                if ($ne_grade >= $exam_threshold && $exam_threshold > 0) {
                    // Check if combined grade would pass.
                    // Already excluded above if Media >= pass, so this student failed despite exam.
                    // Keep them - they need especial.
                }

                // If passed via recorrência (ER >= threshold and got passing media), exclude.
                if ($er_grade >= $exam_threshold && $exam_threshold > 0 && $mg['grade'] >= $pass && $mg['grade'] > 0) {
                    continue;
                }
            }

            $filtered[$uid] = $st;
        }
        $students = $filtered;
    }

    if (empty($students)) continue;

    // Split by groups if course has groups.
    $course_groups = $DB->get_records('groups', ['courseid' => $cid], 'name', 'id,name');

    // Auto-split by groups for category scope, or group scope with "all groups" (group_id=0).
    $should_split = (!empty($course_groups)) && ($scope === 'category' || ($scope === 'group' && $group_id == 0));

    if ($should_split) {
        // Add one entry per group.
        foreach ($course_groups as $grp) {
            $gm_ids = $DB->get_fieldset_select('groups_members', 'userid', 'groupid = :gid', ['gid' => $grp->id]);
            if (empty($gm_ids)) continue;
            $group_students = [];
            foreach ($students as $st) {
                if (in_array((int)$st->id, $gm_ids)) {
                    $group_students[(int)$st->id] = $st;
                }
            }
            if (!empty($group_students)) {
                $all_data[] = [
                    'course' => $co, 'catname' => $catname,
                    'teachers' => $teachers, 'students' => $group_students,
                    'group' => $grp->name, 'ano' => $cat_ano, 'periodo' => $cat_periodo, 'curso' => $cat_curso, 'fr' => $fr_students,
                ];
            }
        }
        // Also add students without any group.
        $all_gm = [];
        foreach ($course_groups as $grp) {
            $gm = $DB->get_fieldset_select('groups_members', 'userid', 'groupid = :gid', ['gid' => $grp->id]);
            $all_gm = array_merge($all_gm, $gm);
        }
        $all_gm = array_unique(array_map('intval', $all_gm));
        $no_group = [];
        foreach ($students as $st) {
            if (!in_array((int)$st->id, $all_gm)) $no_group[(int)$st->id] = $st;
        }
        if (!empty($no_group)) {
            $all_data[] = [
                'course' => $co, 'catname' => $catname,
                'teachers' => $teachers, 'students' => $no_group,
                'group' => get_string('lp_sem_turma', 'local_mulima_analytics'), 'ano' => $cat_ano, 'periodo' => $cat_periodo, 'curso' => $cat_curso, 'fr' => $fr_students,
            ];
        }
    } else {
        // Single group selected or no groups.
        $gn = '';
        if ($scope === 'group' && $group_name) $gn = $group_name;
        $all_data[] = [
            'course' => $co, 'catname' => $catname,
            'teachers' => $teachers, 'students' => $students,
            'group' => $gn, 'ano' => $cat_ano, 'periodo' => $cat_periodo, 'curso' => $cat_curso, 'fr' => $fr_students,
        ];
    }
}

if (empty($all_data)) {
    redirect(new moodle_url('/local/mulima_analytics/presenca.php'), get_string('no_students_found', 'local_mulima_analytics'));
}

// ═══════════════════════════════════════
// EXCEL
// ═══════════════════════════════════════
if ($format === 'excel') {
    require_once($CFG->libdir . '/excellib.class.php');

    $fname = get_string('attendance_filename', 'local_mulima_analytics') . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $epoch_label);
    if ($scope === 'category' && $cat_id > 0) {
        $cat_rec = $DB->get_record('course_categories', ['id' => $cat_id], 'name');
        if ($cat_rec) $fname .= '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $cat_rec->name);
    } else if (count($all_data) === 1) {
        $fname .= '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $all_data[0]['course']->shortname);
    }
    if ($group_name) $fname .= '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $group_name);
    $fname .= '_' . date('Ymd');

    $workbook = new MoodleExcelWorkbook($fname);

    $fT = $workbook->add_format(['bold' => 1, 'size' => 13, 'align' => 'center', 'color' => '#0a1628']);
    $fE = $workbook->add_format(['bold' => 1, 'size' => 11, 'align' => 'center', 'color' => '#1e3d6f', 'bottom' => 2, 'bottom_color' => '#1e3d6f']);
    $fLb = $workbook->add_format(['bold' => 1, 'size' => 9, 'color' => '#64748b', 'align' => 'left']);
    $fVl = $workbook->add_format(['size' => 10, 'color' => '#0f172a', 'align' => 'left']);
    $fH = $workbook->add_format(['bold' => 1, 'size' => 9, 'bg_color' => '#0a1628', 'color' => 'white', 'border' => 1, 'align' => 'center', 'v_align' => 'center']);
    $fN = $workbook->add_format(['size' => 10, 'border' => 1, 'align' => 'center', 'v_align' => 'center']);
    $fCd = $workbook->add_format(['size' => 10, 'border' => 1, 'align' => 'center', 'bold' => 1, 'color' => '#1e3d6f', 'v_align' => 'center']);
    $fNm = $workbook->add_format(['size' => 10, 'border' => 1, 'v_align' => 'center']);
    $fNt = $workbook->add_format(['size' => 10, 'border' => 1, 'align' => 'center', 'bg_color' => '#f1f5f9', 'v_align' => 'center']);
    $fSg = $workbook->add_format(['size' => 10, 'border' => 1, 'bg_color' => '#f1f5f9', 'v_align' => 'center']);
    $fFt = $workbook->add_format(['size' => 7, 'color' => '#94a3b8', 'align' => 'center']);
    $fSm = $workbook->add_format(['size' => 9, 'color' => '#475569']);

    foreach ($all_data as $d) {
        $co = $d['course'];
        $gname = $d['group'] ?? '';
        $stag = $co->shortname ?: $co->fullname;
        if ($gname) $stag .= ' ' . $gname;
        $sname = mb_substr(preg_replace('/[^a-zA-Z0-9_ -]/', '_', $stag), 0, 31);
        // Ensure unique sheet name.
        if (!isset($used_sheets)) $used_sheets = [];
        $base = $sname;
        $idx = 2;
        while (in_array($sname, $used_sheets)) {
            $sname = mb_substr($base, 0, 28) . '_' . $idx;
            $idx++;
        }
        $used_sheets[] = $sname;
        $sh = $workbook->add_worksheet($sname);

        $columns = [['key' => 'number', 'label' => core_text::strtoupper(get_string('lp_numero', 'local_mulima_analytics')), 'width' => 6, 'format' => $fN]];
        if ($cfg_show_code) $columns[] = ['key' => 'code', 'label' => core_text::strtoupper(get_string('lp_codigo', 'local_mulima_analytics')), 'width' => 18, 'format' => $fCd];
        $columns[] = ['key' => 'name', 'label' => core_text::strtoupper(get_string('lp_nome_completo', 'local_mulima_analytics')), 'width' => 42, 'format' => $fNm];
        if ($cfg_show_email) $columns[] = ['key' => 'email', 'label' => core_text::strtoupper(get_string('email', 'local_mulima_analytics')), 'width' => 32, 'format' => $fNm];
        if ($cfg_show_nota) $columns[] = ['key' => 'grade', 'label' => core_text::strtoupper(get_string('nota', 'local_mulima_analytics')), 'width' => 12, 'format' => $fNt];
        if ($cfg_show_sign) $columns[] = ['key' => 'signature', 'label' => core_text::strtoupper(get_string('cfg_assinatura', 'local_mulima_analytics')), 'width' => 30, 'format' => $fSg];
        if ($cfg_show_obs) $columns[] = ['key' => 'notes', 'label' => core_text::strtoupper(get_string('cfg_observacoes', 'local_mulima_analytics')), 'width' => 24, 'format' => $fSg];
        $lastcol = count($columns) - 1;
        foreach ($columns as $index => $column) $sh->set_column($index, $index, $column['width']);

        $r = 0;

        // Institution name.
        $sh->set_row($r, 22);
        $sh->write_string($r, 0, $cfg_inst, $fT);
        $sh->merge_cells($r, 0, $r, $lastcol);
        $r += 2;

        // Title: LISTA DE PRESENÇA: EXAME NORMAL.
        $sh->set_row($r, 18);
        $sh->write_string($r, 0, core_text::strtoupper(get_string('attendance_list', 'local_mulima_analytics') . ': ' . $epoch_label), $fE);
        $sh->merge_cells($r, 0, $r, $lastcol);
        $r += 3;

        // Info - same format as PDF.
        $gname = $d['group'] ?? '';
        $cat_root = $d['ano'] ?? '';
        $periodo_cfg_xl = !empty($cfg_periodo) ? $cfg_periodo : '';
        if ($cat_root && $periodo_cfg_xl) {
            $periodo_xl = $cat_root . ' > ' . $periodo_cfg_xl;
        } else if ($cat_root) {
            $periodo_xl = $cat_root;
        } else {
            $periodo_xl = $periodo_cfg_xl;
        }
        $fLbx = $workbook->add_format(['bold' => 1, 'size' => 9, 'color' => '#64748b']);

        $info = [];
        if ($cfg_show_discipline) $info[] = [get_string('lp_disciplina', 'local_mulima_analytics') . ':', $co->fullname];
        if ($cfg_show_period) $info[] = [get_string('lp_periodo', 'local_mulima_analytics') . ':', $periodo_xl];
        if ($cfg_show_teacher) $info[] = [get_string('lp_docente', 'local_mulima_analytics') . ':', implode(', ', $d['teachers'])];
        if ($cfg_show_count) $info[] = [get_string('lp_inscritos', 'local_mulima_analytics') . ':', get_string('lp_inscritos_n', 'local_mulima_analytics', count($d['students']))];
        if ($cfg_show_group && $gname) $info[] = [get_string('lp_turma', 'local_mulima_analytics') . ':', $gname];
        if ($cfg_show_date) $info[] = [get_string('lp_data', 'local_mulima_analytics') . ':', '____/____/________'];
        foreach ($info as [$label, $value]) {
            $sh->write_string($r, 0, $label, $fLbx);
            $sh->write_string($r, 1, $value, $fVl);
            if ($lastcol > 1) $sh->merge_cells($r, 1, $r, $lastcol);
            $r++;
        }

        $r += 2;

        // Table header.
        foreach ($columns as $index => $column) $sh->write_string($r, $index, $column['label'], $fH);
        $sh->set_row($r, 20);
        $r++;

        // Students.
        $n = 0;
        foreach ($d['students'] as $st) {
            $n++;
            $code = !empty($st->idnumber) ? $st->idnumber : $st->username;
            $is_fr = ($cfg_include_fr && isset($d['fr'][(int)$st->id]));
            $values = ['number' => $n, 'code' => $code, 'name' => $st->firstname . ' ' . $st->lastname,
                'email' => $st->email, 'grade' => $is_fr ? get_string('fraud', 'local_mulima_analytics') : '', 'signature' => '', 'notes' => ''];
            foreach ($columns as $index => $column) {
                if ($column['key'] === 'number') $sh->write_number($r, $index, $n, $column['format']);
                else $sh->write_string($r, $index, $values[$column['key']], $column['format']);
            }
            $sh->set_row($r, 20);
            $r++;
        }

        $r += 2;
        if ($cfg_show_sign) {
            $sh->write_string($r++, 0, $cfg_footer_l . ': ___________________________    ' . $cfg_footer_r . ': ___________________________', $fSm);
            $sh->merge_cells($r - 1, 0, $r - 1, $lastcol);
        }
        if ($cfg_footer_note !== '') {
            $sh->write_string($r++, 0, $cfg_footer_note, $fSm);
            $sh->merge_cells($r - 1, 0, $r - 1, $lastcol);
        }
        $r += 2;
        $sh->write_string($r, 0, get_string('lp_gerado_em', 'local_mulima_analytics', userdate(time(), get_string('strftimedatetimeshort', 'langconfig'))) . ' | ' . get_string('attendance_list', 'local_mulima_analytics') . ' ' . $cfg_short, $fFt);
        $sh->merge_cells($r, 0, $r, $lastcol);
    }

    $workbook->close();
    exit;
}

// ═══════════════════════════════════════
// PDF
// ═══════════════════════════════════════
if ($format === 'pdf') {
    require_once($CFG->libdir . '/pdflib.php');

    $pdf = new pdf('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetCreator('ISUTC');
    $pdf->SetAuthor('ISUTC');
    $pdf->SetTitle(get_string('attendance_list', 'local_mulima_analytics') . ' - ' . $epoch_label);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 20);

    // Dynamic columns (relative widths are normalised to the 180 mm content area).
    $pdfcols = [['key' => 'number', 'label' => core_text::strtoupper(get_string('lp_numero', 'local_mulima_analytics')), 'weight' => 10, 'align' => 'C']];
    if ($cfg_show_code) $pdfcols[] = ['key' => 'code', 'label' => core_text::strtoupper(get_string('lp_codigo', 'local_mulima_analytics')), 'weight' => 28, 'align' => 'C'];
    $pdfcols[] = ['key' => 'name', 'label' => core_text::strtoupper(get_string('lp_nome_completo', 'local_mulima_analytics')), 'weight' => 70, 'align' => 'L'];
    if ($cfg_show_email) $pdfcols[] = ['key' => 'email', 'label' => core_text::strtoupper(get_string('email', 'local_mulima_analytics')), 'weight' => 42, 'align' => 'L'];
    if ($cfg_show_nota) $pdfcols[] = ['key' => 'grade', 'label' => core_text::strtoupper(get_string('nota', 'local_mulima_analytics')), 'weight' => 20, 'align' => 'C'];
    if ($cfg_show_sign) $pdfcols[] = ['key' => 'signature', 'label' => core_text::strtoupper(get_string('cfg_assinatura', 'local_mulima_analytics')), 'weight' => 40, 'align' => 'C'];
    if ($cfg_show_obs) $pdfcols[] = ['key' => 'notes', 'label' => core_text::strtoupper(get_string('cfg_observacoes', 'local_mulima_analytics')), 'weight' => 34, 'align' => 'L'];
    $totalweight = array_sum(array_column($pdfcols, 'weight'));
    foreach ($pdfcols as &$pdfcol) $pdfcol['width'] = 180 * $pdfcol['weight'] / $totalweight;
    unset($pdfcol);

    foreach ($all_data as $d) {
        $co = $d['course'];
        $pdf->AddPage();

        // ── Header: logo + institution on same line ──
        $y0 = $pdf->GetY();
        if ($logo_pdf_path && file_exists($logo_pdf_path)) {
            $pdf->Image($logo_pdf_path, 15, $y0, 22);
        }
        $pdf->SetY($y0 + 1);
        $pdf->SetX(40);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(10, 22, 40);
        $pdf->Cell(140, 5, $cfg_inst, 0, 1, 'C');

        $pdf->SetY($y0 + 14);

        // ── Epoch title ──
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(30, 61, 111);
        $pdf->Cell(180, 7, core_text::strtoupper(get_string('attendance_list', 'local_mulima_analytics') . ': ' . $epoch_label), 'B', 1, 'C');
        $pdf->Ln(4);

        // ── Info fields - all left aligned ──
        $gname = $d['group'] ?? '';
        $cat_root = $d['ano'] ?? '';
        $periodo_cfg = !empty($cfg_periodo) ? $cfg_periodo : '';
        if ($cat_root && $periodo_cfg) {
            $periodo_text = $cat_root . ' > ' . $periodo_cfg;
        } else if ($cat_root) {
            $periodo_text = $cat_root;
        } else {
            $periodo_text = $periodo_cfg;
        }
        $lbl = 32;

        $pdfinfo = [];
        if ($cfg_show_discipline) $pdfinfo[] = [get_string('lp_disciplina', 'local_mulima_analytics'), $co->fullname];
        if ($cfg_show_period) $pdfinfo[] = [get_string('lp_periodo', 'local_mulima_analytics'), $periodo_text];
        if ($cfg_show_teacher) $pdfinfo[] = [get_string('lp_docente', 'local_mulima_analytics'), implode(', ', $d['teachers'])];
        if ($cfg_show_count) $pdfinfo[] = [get_string('lp_inscritos', 'local_mulima_analytics'), get_string('lp_inscritos_n', 'local_mulima_analytics', count($d['students']))];
        if ($cfg_show_group && $gname) $pdfinfo[] = [get_string('lp_turma', 'local_mulima_analytics'), $gname];
        if ($cfg_show_date) $pdfinfo[] = [get_string('lp_data', 'local_mulima_analytics'), '____/____/________'];
        foreach ($pdfinfo as [$infolabel, $infovalue]) {
            $pdf->SetFont('helvetica', 'B', 8); $pdf->SetTextColor(100, 116, 139);
            $pdf->Cell($lbl, 6, $infolabel, 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9); $pdf->SetTextColor(10, 22, 40);
            $pdf->Cell(0, 6, $infovalue, 0, 1);
        }

        $pdf->Ln(4);

        // ── Table header ──
        $pdf->SetFillColor(10, 22, 40);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8);
        foreach ($pdfcols as $index => $column) {
            $pdf->Cell($column['width'], 7, $column['label'], 1, $index === count($pdfcols) - 1 ? 1 : 0, 'C', true);
        }

        // ── Student rows ──
        $pdf->SetTextColor(10, 22, 40);
        $n = 0;
        foreach ($d['students'] as $st) {
            $n++;
            $code = !empty($st->idnumber) ? $st->idnumber : $st->username;
            $fill = ($n % 2 === 0);
            if ($fill) $pdf->SetFillColor(248, 250, 252);

            $is_fr = ($cfg_include_fr && isset($d['fr'][(int)$st->id]));
            $values = ['number' => $n, 'code' => $code, 'name' => $st->firstname . ' ' . $st->lastname,
                'email' => $st->email, 'grade' => $is_fr ? get_string('fraud', 'local_mulima_analytics') : '', 'signature' => '', 'notes' => ''];
            foreach ($pdfcols as $index => $column) {
                $pdf->SetFont('helvetica', $column['key'] === 'code' || ($column['key'] === 'grade' && $is_fr) ? 'B' : '', 8);
                $pdf->SetTextColor($column['key'] === 'grade' && $is_fr ? 220 : 10,
                    $column['key'] === 'grade' && $is_fr ? 38 : 22, $column['key'] === 'grade' && $is_fr ? 38 : 40);
                $pdf->Cell($column['width'], 7, $values[$column['key']], 1,
                    $index === count($pdfcols) - 1 ? 1 : 0, $column['align'], $fill);
            }

            // Page break with header repeat.
            if ($pdf->GetY() > 262) {
                $pdf->AddPage();
                $pdf->SetFillColor(10, 22, 40);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('helvetica', 'B', 8);
                foreach ($pdfcols as $index => $column) {
                    $pdf->Cell($column['width'], 7, $column['label'], 1,
                        $index === count($pdfcols) - 1 ? 1 : 0, 'C', true);
                }
                $pdf->SetTextColor(10, 22, 40);
            }
        }

        // ── Footer ──
        $pdf->Ln(8);
        if ($cfg_show_sign) {
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(71, 85, 105);
            $pdf->Cell(90, 6, $cfg_footer_l . ': ___________________________', 0, 0);
            $pdf->Cell(0, 6, $cfg_footer_r . ': ___________________________', 0, 1);
        }
        if ($cfg_footer_note !== '') {
            $pdf->Ln(3);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->MultiCell(0, 5, $cfg_footer_note, 0, 'C');
        }
        $pdf->Ln(6);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(148, 163, 184);
        $pdf->Cell(0, 4, get_string('lp_gerado_em', 'local_mulima_analytics', userdate(time(), get_string('strftimedatetimeshort', 'langconfig'))) . ' | ' . get_string('attendance_list', 'local_mulima_analytics') . ' ' . $cfg_short, 0, 1, 'C');
    }

    $fname = get_string('attendance_filename', 'local_mulima_analytics') . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $epoch_label);
    if ($scope === 'category' && $cat_id > 0) {
        $cat_rec = $DB->get_record('course_categories', ['id' => $cat_id], 'name');
        if ($cat_rec) $fname .= '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $cat_rec->name);
    } else if (count($all_data) === 1) {
        $fname .= '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $all_data[0]['course']->shortname);
    }
    if ($group_name) $fname .= '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $group_name);
    $fname .= '_' . date('Ymd');

    $pdf->Output($fname . '.pdf', 'D');
    exit;
}

redirect(new moodle_url('/local/mulima_analytics/presenca.php'), get_string('unsupported_format', 'local_mulima_analytics'));
