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
 * Dashboard report export.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');
\local_mulima_analytics\local\access::require_export('dashboard');
$PAGE->set_context(context_system::instance());
require_sesskey();
\local_mulima_analytics\local\audit::export('dashboard', 'export.php');

$cat_filter = optional_param('categories', '', PARAM_TEXT);
$search = optional_param('search', '', PARAM_TEXT);
$maxpoints = (int)(get_config('local_mulima_analytics', 'maxpoints') ?: 800);

$student_role = $DB->get_record('role', ['shortname' => 'student'], 'id');
$teacher_role = $DB->get_record('role', ['shortname' => 'editingteacher'], 'id');
$student_rid = $student_role ? (int)$student_role->id : 5;
$teacher_rid = $teacher_role ? (int)$teacher_role->id : 3;

$where = 'id <> :siteid';
$params = ['siteid' => SITEID];

if (!empty($cat_filter)) {
    $cat_ids = array_map('intval', explode(',', $cat_filter));
    $expanded = $cat_ids;
    foreach ($cat_ids as $cid) {
        $subs = $DB->get_fieldset_select('course_categories', 'id',
            "path LIKE :p", ['p' => '%/' . $cid . '/%']);
        $expanded = array_merge($expanded, $subs);
    }
    $expanded = array_unique(array_map('intval', $expanded));
    if (!empty($expanded)) {
        list($insql, $inparams) = $DB->get_in_or_equal($expanded, SQL_PARAMS_NAMED, 'xc');
        $where .= " AND category $insql";
        $params = array_merge($params, $inparams);
    }
}

if (!empty($search)) {
    $where .= " AND " . $DB->sql_like('fullname', ':search', false);
    $params['search'] = '%' . $DB->sql_like_escape($search) . '%';
}

$courses = $DB->get_records_select('course', $where, $params, 'fullname', 'id,fullname,shortname,category');

// Create Excel workbook.
$filename = 'Learning_Analytics_' . date('Y-m-d_H-i');
$workbook = new MoodleExcelWorkbook($filename);
$sheet = $workbook->add_worksheet('Learning Analytics');

// Header formats.
$fh = $workbook->add_format(['bold' => 1, 'bg_color' => '#0f2b46', 'color' => 'white', 'size' => 10, 'text_wrap' => true]);
$fn = $workbook->add_format(['size' => 10]);
$fb = $workbook->add_format(['bold' => 1, 'size' => 10]);
$fg = $workbook->add_format(['bold' => 1, 'size' => 10, 'color' => 'green']);
$fr = $workbook->add_format(['bold' => 1, 'size' => 10, 'color' => 'red']);
$fa = $workbook->add_format(['bold' => 1, 'size' => 10, 'color' => '#b45309']);
$fs = $workbook->add_format(['size' => 9, 'color' => '#666666', 'italic' => true]);

// Column widths.
$sheet->set_column(0, 0, 35); // Disciplina
$sheet->set_column(1, 1, 12); // Código
$sheet->set_column(2, 2, 20); // Categoria
$sheet->set_column(3, 3, 30); // Docentes
$sheet->set_column(4, 4, 12); // Estudantes
$sheet->set_column(5, 5, 14); // Participativos
$sheet->set_column(6, 6, 12); // Avaliações
$sheet->set_column(7, 7, 14); // Soma Max
$sheet->set_column(8, 8, 12); // Esperado
$sheet->set_column(9, 9, 12); // Diferenca
$sheet->set_column(10, 10, 12); // Corrigidas
$sheet->set_column(11, 11, 12); // Sem nota
$sheet->set_column(12, 12, 20); // Submetidos sem correcção
$sheet->set_column(13, 13, 50); // Nomes

// Headers.
$headers = [
    get_string('disciplina', 'local_mulima_analytics'), get_string('codigo', 'local_mulima_analytics'), get_string('categoria', 'local_mulima_analytics'), get_string('docentes_lbl', 'local_mulima_analytics'), get_string('estudantes', 'local_mulima_analytics'),
    get_string('participating_students', 'local_mulima_analytics'), get_string('avaliacoes', 'local_mulima_analytics'), get_string('soma_maximos', 'local_mulima_analytics'), get_string('maximo_esperado', 'local_mulima_analytics'), get_string('diferenca', 'local_mulima_analytics'), get_string('corrigidas', 'local_mulima_analytics'), get_string('sem_nota', 'local_mulima_analytics'), get_string('submeteram_sem_nota', 'local_mulima_analytics'), get_string('nome_estudante', 'local_mulima_analytics'),
];
for ($i = 0; $i < count($headers); $i++) {
    $sheet->write_string(0, $i, $headers[$i], $fh);
}

$row = 1;
foreach ($courses as $co) {
    try {
        $cid = (int)$co->id;
        $ctx = context_course::instance($cid, IGNORE_MISSING);
        if (!$ctx) continue;

        $catname = '';
        $cx = $DB->get_record('course_categories', ['id' => $co->category], 'id,name');
        if ($cx) $catname = $cx->name;

        $teachers = [];
        $tlist = $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname
             FROM {role_assignments} ra JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
             WHERE ra.contextid = :ctx AND ra.roleid = :rid ORDER BY u.lastname",
            ['ctx' => $ctx->id, 'rid' => $teacher_rid]);
        foreach ($tlist as $t) $teachers[] = $t->firstname . ' ' . $t->lastname;

        $total_students = (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT ra.userid) FROM {role_assignments} ra
             WHERE ra.contextid = :ctx AND ra.roleid = :rid",
            ['ctx' => $ctx->id, 'rid' => $student_rid]);

        $items_raw = $DB->get_records_select('grade_items',
            "courseid = :cid AND itemtype = 'mod' AND grademax > 0",
            ['cid' => $cid], 'sortorder', 'id,itemname,itemmodule,grademax,categoryid');

        // Find PA category.
        $pa_cat = $DB->get_record('grade_categories', [
            'courseid' => $cid, 'fullname' => 'Plano de Avaliação'
        ], 'id');
        $pa_catid = $pa_cat ? (int)$pa_cat->id : 0;

        // Filter ungraded forums.
        $items = [];
        foreach ($items_raw as $gi) {
            if ($gi->itemmodule === 'forum') {
                $has = $DB->record_exists_sql(
                    "SELECT 1 FROM {grade_grades} WHERE itemid = :iid AND finalgrade IS NOT NULL",
                    ['iid' => $gi->id]);
                if (!$has) continue;
            }
            $items[] = $gi;
        }

        $total_items = count($items);
        $sum_max = 0; $graded = 0; $ungraded = 0;
        foreach ($items as $gi) {
            $in_pa = ($pa_catid > 0) ? ((int)$gi->categoryid === $pa_catid) : true;
            if ($in_pa) $sum_max += (float)$gi->grademax;
            $hg = $DB->record_exists_sql(
                "SELECT 1 FROM {grade_grades} WHERE itemid = :iid AND finalgrade IS NOT NULL",
                ['iid' => $gi->id]);
            $hg ? $graded++ : $ungraded++;
        }

        $active = 0;
        if ($total_students > 0 && !empty($items)) {
            $iids = array_map(function($g){return (int)$g->id;}, $items);
            list($isql, $ip) = $DB->get_in_or_equal($iids, SQL_PARAMS_NAMED, 'xg');
            $active = (int)$DB->count_records_sql(
                "SELECT COUNT(DISTINCT gg.userid) FROM {grade_grades} gg
                 JOIN {role_assignments} ra ON ra.userid = gg.userid AND ra.contextid = :ctx AND ra.roleid = :rid
                 WHERE gg.itemid $isql AND gg.finalgrade IS NOT NULL",
                array_merge(['ctx' => $ctx->id, 'rid' => $student_rid], $ip));
        }

        $diff = (int)$sum_max - $maxpoints;

        // Course row.
        $sheet->write_string($row, 0, $co->fullname, $fb);
        $sheet->write_string($row, 1, $co->shortname, $fn);
        $sheet->write_string($row, 2, $catname, $fn);
        $sheet->write_string($row, 3, implode(', ', $teachers), $fn);
        $sheet->write_number($row, 4, $total_students, $fn);
        $sheet->write_number($row, 5, $active, $fn);
        $sheet->write_number($row, 6, $total_items, $fn);
        $sheet->write_number($row, 7, (int)$sum_max, $fb);
        $sheet->write_number($row, 8, $maxpoints, $fn);
        $df = $diff === 0 ? $fg : ($diff < 0 ? $fa : $fr);
        $sheet->write_number($row, 9, $diff, $df);
        $sheet->write_number($row, 10, $graded, $fg);
        $sheet->write_number($row, 11, $ungraded, $ungraded > 0 ? $fr : $fg);
        $sheet->write_string($row, 12, '', $fn);
        $sheet->write_string($row, 13, '', $fn);
        $row++;

        // Detail rows per assessment.
        foreach ($items as $gi) {
            $gc = (int)$DB->count_records_sql(
                "SELECT COUNT(DISTINCT userid) FROM {grade_grades}
                 WHERE itemid = :iid AND finalgrade IS NOT NULL", ['iid' => $gi->id]);
            $pend = $total_students - $gc;

            $sheet->write_string($row, 0, '   > ' . ($gi->itemname ?: get_string('sem_nome', 'local_mulima_analytics')), $fs);
            $sheet->write_string($row, 1, '', $fs);
            $sheet->write_string($row, 2, '', $fs);
            $sheet->write_string($row, 3, $gi->itemmodule, $fs);
            $sheet->write_string($row, 4, '', $fs);
            $sheet->write_string($row, 5, '', $fs);
            $sheet->write_string($row, 6, '', $fs);
            $sheet->write_number($row, 7, (float)$gi->grademax, $fs);
            $sheet->write_string($row, 8, '', $fs);
            $sheet->write_string($row, 9, '', $fs);
            // Check submitted but ungraded.
            $sub_ungraded = [];
            if ($gi->itemmodule === 'assign') {
                $cm = $DB->get_record_sql(
                    "SELECT cm.instance FROM {course_modules} cm
                     JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                     WHERE cm.course = :cid AND cm.instance = :iid",
                    ['cid' => $cid, 'iid' => $gi->iteminstance]);
                if ($cm) {
                    $sub_ungraded = $DB->get_records_sql(
                        "SELECT u.firstname, u.lastname
                         FROM {assign_submission} asub
                         JOIN {user} u ON u.id = asub.userid AND u.deleted = 0
                         LEFT JOIN {grade_grades} gg ON gg.userid = asub.userid AND gg.itemid = :giid
                         WHERE asub.assignment = :aid AND asub.latest = 1 AND asub.status = 'submitted'
                               AND asub.userid > 0 AND (gg.finalgrade IS NULL OR gg.id IS NULL)
                         ORDER BY u.lastname",
                        ['aid' => $cm->instance, 'giid' => $gi->id]);
                }
            } else if ($gi->itemmodule === 'quiz') {
                $sub_ungraded = $DB->get_records_sql(
                    "SELECT DISTINCT u.firstname, u.lastname
                     FROM {quiz_attempts} qa
                     JOIN {user} u ON u.id = qa.userid AND u.deleted = 0
                     LEFT JOIN {grade_grades} gg ON gg.userid = qa.userid AND gg.itemid = :giid
                     WHERE qa.quiz = :qid AND qa.state = 'finished'
                           AND (gg.finalgrade IS NULL OR gg.id IS NULL)
                     ORDER BY u.lastname",
                    ['qid' => $gi->iteminstance, 'giid' => $gi->id]);
            }
            $sub_names = [];
            foreach ($sub_ungraded as $su) $sub_names[] = $su->firstname . ' ' . $su->lastname;

            $sheet->write_number($row, 10, $gc, $fs);
            $sheet->write_number($row, 11, $pend, $fs);
            $sheet->write_number($row, 12, count($sub_names), count($sub_names) > 0 ? $fr : $fs);
            $sheet->write_string($row, 13, implode(', ', $sub_names), $fs);
            $row++;
        }

    } catch (\Throwable $e) { continue; }
}

$workbook->close();
exit;
