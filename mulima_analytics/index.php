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
 * Learning Analytics dashboard.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
\local_mulima_analytics\local\access::require_report('dashboard');

// Prevent browser caching - always fetch fresh data.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

\local_mulima_analytics\local\page::setup('index.php', get_string('pagetitle', 'local_mulima_analytics'));

$maxpoints = (int)(get_config('local_mulima_analytics', 'maxpoints') ?: 800);
$export_url = (new moodle_url('/local/mulima_analytics/export.php'))->out(false);
$settings_url = (new moodle_url('/local/mulima_analytics/presenca_config.php'))->out(false);
$detail_url = (new moodle_url('/local/mulima_analytics/detail.php'))->out(false);

// ── Load all courses ──
$where = 'id <> :siteid';
$params = ['siteid' => SITEID];

$courses = $DB->get_records_select('course', $where, $params, 'fullname', 'id,fullname,shortname,category');
$course_ids = array_keys($courses);

// ── Pre-load all data in batch ──
$cat_names = [];
$all_items = [];
$graded_ids = [];
$teachers = [];
$stu_counts = [];
$active_counts = [];
$cat_paths = [];

if (!empty($course_ids)) {
    // Categories.
    $catids = array_unique(array_map(function($c){return (int)$c->category;}, $courses));
    if (!empty($catids)) {
        list($s,$p) = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED, 'cn');
        foreach ($DB->get_records_select('course_categories', "id $s", $p, '', 'id,name,path') as $r) {
            $cat_names[(int)$r->id] = $r->name;
            $cat_paths[(int)$r->id] = $r->path;
        }
    }

    // Grade items.
    list($ci1,$cp1) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'a1');
    $recs = $DB->get_records_select('grade_items', "courseid $ci1 AND itemtype = 'mod' AND grademax > 0", $cp1, 'courseid,sortorder', 'id,courseid,itemname,itemmodule,grademax,categoryid');
    // Load PA categories and their configured totals.
    $pa_categories = [];
    $pa_totals = [];
    if (!empty($course_ids)) {
        $pa_recs = $DB->get_records_select('grade_categories',
            "fullname = 'Plano de Avaliação' AND courseid $ci1", $cp1, '', 'id,courseid');
        foreach ($pa_recs as $pr) $pa_categories[(int)$pr->courseid] = (int)$pr->id;

        // Get the configured grademax from the PA category grade_item (Frequência total).
        if (!empty($pa_categories)) {
            $pa_cat_ids = array_values($pa_categories);
            list($pasql, $paparams) = $DB->get_in_or_equal($pa_cat_ids, SQL_PARAMS_NAMED, 'pt');
            $pa_gi_recs = $DB->get_records_select('grade_items',
                "itemtype = 'category' AND iteminstance $pasql",
                $paparams, '', 'id,courseid,grademax');
            foreach ($pa_gi_recs as $pgi) {
                $pa_totals[(int)$pgi->courseid] = (int)$pgi->grademax;
            }
        }
    }

    // Forums only count as assessments if at least one student was graded.
    $forum_ids_with_grades = [];
    $forum_items = array_filter($recs, function($gi) { return $gi->itemmodule === 'forum'; });
    if (!empty($forum_items)) {
        $fids = array_map(function($gi) { return (int)$gi->id; }, $forum_items);
        list($fsql, $fparams) = $DB->get_in_or_equal($fids, SQL_PARAMS_NAMED, 'fg');
        $graded_forums = $DB->get_records_sql(
            "SELECT DISTINCT gg.itemid FROM {grade_grades} gg WHERE gg.itemid $fsql AND gg.finalgrade IS NOT NULL",
            $fparams);
        foreach ($graded_forums as $gf) $forum_ids_with_grades[(int)$gf->itemid] = 1;
    }
    foreach ($recs as $gi) {
        // Skip forums without any graded student.
        if ($gi->itemmodule === 'forum' && !isset($forum_ids_with_grades[(int)$gi->id])) continue;
        $all_items[(int)$gi->courseid][] = $gi;
    }

    // Graded items.
    list($ci2,$cp2) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'a2');
    $sql = "SELECT DISTINCT gi.id FROM {grade_items} gi JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.finalgrade IS NOT NULL WHERE gi.courseid $ci2 AND gi.itemtype = 'mod'";
    foreach ($DB->get_records_sql($sql, $cp2) as $r) $graded_ids[(int)$r->id] = 1;

    // Teachers.
    list($ci3,$cp3) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'a3');
    $teacherroleids = \local_mulima_analytics\local\roles::teachers() ?: [0];
    [$trsql, $trparams] = $DB->get_in_or_equal($teacherroleids, SQL_PARAMS_NAMED, 'tr');
    $sql = "SELECT ra.id as raid, ctx.instanceid as cid, u.firstname, u.lastname FROM {role_assignments} ra JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 JOIN {user} u ON u.id = ra.userid AND u.deleted = 0 WHERE ra.roleid $trsql AND ctx.instanceid $ci3 ORDER BY u.lastname";
    foreach ($DB->get_records_sql($sql, array_merge($trparams, $cp3)) as $t) {
        $k = (int)$t->cid; $n = $t->firstname.' '.$t->lastname;
        if (!isset($teachers[$k])) $teachers[$k] = [];
        if (!in_array($n, $teachers[$k])) $teachers[$k][] = $n;
    }

    // Students.
    list($ci4,$cp4) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'a4');
    $studentroleids = \local_mulima_analytics\local\roles::students() ?: [0];
    [$srsql, $srparams] = $DB->get_in_or_equal($studentroleids, SQL_PARAMS_NAMED, 'sr');
    $sql = "SELECT ctx.instanceid as cid, COUNT(DISTINCT ra.userid) as cnt
              FROM {role_assignments} ra
              JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
              JOIN {enrol} e ON e.courseid = ctx.instanceid AND e.status = 0
              JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = ra.userid AND ue.status = 0
              JOIN {user} u ON u.id = ra.userid AND u.deleted = 0 AND u.suspended = 0
             WHERE ra.roleid $srsql AND ctx.instanceid $ci4
          GROUP BY ctx.instanceid";
    foreach ($DB->get_records_sql($sql, array_merge($srparams, $cp4)) as $r)
        $stu_counts[(int)$r->cid] = (int)$r->cnt;

    // Active students.
    list($ci5,$cp5) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'a5');
    $sql = "SELECT gi.courseid as cid, COUNT(DISTINCT gg.userid) as cnt
              FROM {grade_grades} gg
              JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'mod'
              JOIN {context} ctx ON ctx.contextlevel = 50 AND ctx.instanceid = gi.courseid
              JOIN {role_assignments} ra ON ra.userid = gg.userid AND ra.contextid = ctx.id AND ra.roleid $srsql
              JOIN {enrol} e ON e.courseid = gi.courseid AND e.status = 0
              JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = gg.userid AND ue.status = 0
              JOIN {user} u ON u.id = gg.userid AND u.deleted = 0 AND u.suspended = 0
             WHERE gg.finalgrade IS NOT NULL AND gi.courseid $ci5
          GROUP BY gi.courseid";
    foreach ($DB->get_records_sql($sql, array_merge($srparams, $cp5)) as $r)
        $active_counts[(int)$r->cid] = (int)$r->cnt;
}

// ── Unique students count ──
$unique_students = 0;
if (!empty($course_ids)) {
    list($ci6, $cp6) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'a6');
    $unique_students = (int)$DB->count_records_sql(
        "SELECT COUNT(DISTINCT ra.userid)
           FROM {role_assignments} ra
           JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
           JOIN {enrol} e ON e.courseid = ctx.instanceid AND e.status = 0
           JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = ra.userid AND ue.status = 0
           JOIN {user} u ON u.id = ra.userid AND u.deleted = 0 AND u.suspended = 0
          WHERE ra.roleid $srsql AND ctx.instanceid $ci6",
        array_merge($srparams, $cp6));
}

// ── Build rows ──
$rows = [];
$totals = ['courses'=>0,'students'=>0,'eval'=>0,'graded'=>0,'ungraded'=>0,];
foreach ($courses as $co) {
    $cid = (int)$co->id;
    $items = $all_items[$cid] ?? [];
    $sum = 0; $quiz = false; $gr = 0; $ugr = 0;
    $pa_catid = $pa_categories[$cid] ?? 0;
    $sum_pa = 0;
    foreach ($items as $gi) {
        $in_pa = ($pa_catid > 0) ? ((int)$gi->categoryid === $pa_catid) : true;
        if ($in_pa) $sum_pa += (float)$gi->grademax;
        isset($graded_ids[(int)$gi->id]) ? $gr++ : $ugr++;
    }
    // Use the configured PA total if available, otherwise use sum of items.
    $sum = isset($pa_totals[$cid]) ? (int)$pa_totals[$cid] : (int)$sum_pa;
    $stu = $stu_counts[$cid] ?? 0;
    $act = $active_counts[$cid] ?? 0;
    $rows[] = [
        'id'=>$cid, 'name'=>$co->fullname, 'short'=>$co->shortname,
        'cat'=>$cat_names[(int)$co->category] ?? '', 'catid'=>(int)$co->category, 'catpath'=>$cat_paths[(int)$co->category] ?? '',
        'teachers'=>$teachers[$cid] ?? [],
        'stu'=>$stu, 'act'=>$act, 'eval'=>count($items),
        'sum'=>(int)$sum, 'gr'=>$gr, 'ugr'=>$ugr, 'quiz'=>$quiz,
    ];
    $totals['courses']++;
    $totals['students'] += $stu;
    $totals['eval'] += count($items);
    $totals['graded'] += $gr;
    $totals['ungraded'] += $ugr;
}

// Category tree for filter.
$all_cats = $DB->get_records('course_categories', null, 'name', 'id,name,parent,path,depth');
$cat_tree = [];
foreach ($all_cats as $c) {
    $bc = '';
    if ($c->depth > 1) {
        $parts = explode('/', trim($c->path, '/')); array_pop($parts); $names = [];
        foreach ($parts as $pid) { if (isset($all_cats[(int)$pid])) $names[] = $all_cats[(int)$pid]->name; }
        $bc = implode(' > ', $names);
    }
    $cat_tree[] = ['id'=>$c->id,'name'=>$c->name,'d'=>$c->depth-1,'bc'=>$bc];
}

$page_url = new moodle_url('/local/mulima_analytics/index.php');
echo $OUTPUT->header();
$DIE_PAGE     = 'index';
$DIE_TITLE    = get_string('title_index', 'local_mulima_analytics');
$DIE_SUBTITLE = get_string('subtitle_index', 'local_mulima_analytics');
require_once(__DIR__.'/die_layout.php');

// ── Chart data ──────────────────────────────────────────────────────
$graded_pct  = \local_mulima_analytics\local\math::percentage($totals['graded'], $totals['eval']);
$pending_pct = \local_mulima_analytics\local\math::percentage($totals['ungraded'], $totals['eval']);

$sorted_top = $rows;
usort($sorted_top, function($a,$b){ return $b['eval'] - $a['eval']; });
$chart_courses  = array_slice($sorted_top, 0, 8);
$chart_max_eval = max(array_map(function($r){ return $r['eval']; }, $rows) ?: [1]);

$cat_eval = [];
foreach ($rows as $r) { $cat = $r['cat'] ?: get_string('sem_categoria_abr', 'local_mulima_analytics'); $cat_eval[$cat] = ($cat_eval[$cat]??0)+$r['eval']; }
arsort($cat_eval); $cat_eval = array_slice($cat_eval, 0, 5, true);
$cat_max = max($cat_eval ?: [1]);

// ── Attention table: courses with sum≠MP or pending ungraded ────────
$attention = array_filter($rows, function($r) use ($maxpoints){
    return $r['ugr'] > 0 || ($r['sum'] > 0 && $r['sum'] !== $maxpoints);
});
usort($attention, function($a,$b){ return $b['ugr'] - $a['ugr']; });
$attention = array_slice($attention, 0, 20);
?>

<!-- ━━ KPI CARDS ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">

  <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-start gap-3 shadow-sm">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#eff6ff">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
    </div>
    <div><div class="text-2xl font-extrabold text-slate-900 leading-none"><?php echo number_format($totals['courses'],0,',','.'); ?></div><div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mt-1"><?php echo s(get_string('disciplinas', 'local_mulima_analytics')); ?></div></div>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-start gap-3 shadow-sm">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#f0fdf4">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    </div>
    <div><div class="text-2xl font-extrabold text-slate-900 leading-none"><?php echo number_format($unique_students,0,',','.'); ?></div><div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mt-1"><?php echo s(get_string('estudantes', 'local_mulima_analytics')); ?></div></div>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-start gap-3 shadow-sm col-span-2 sm:col-span-1">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#fefce8">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
    </div>
    <div class="flex-1 min-w-0">
      <div class="flex justify-between items-baseline">
        <div class="text-2xl font-extrabold text-slate-900 leading-none"><?php echo number_format($totals['graded'],0,',','.'); ?></div>
        <span class="text-xs font-bold" style="color:<?php echo $graded_pct>=80?'#16a34a':($graded_pct>=50?'#d97706':'#dc2626'); ?>"><?php echo $graded_pct; ?>%</span>
      </div>
      <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mt-1"><?php echo get_string('corrigidas_de', 'local_mulima_analytics', number_format($totals['eval'],0,',','.')); ?></div>
      <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden mt-2">
        <div class="h-full rounded-full die-progress-fill" style="width:<?php echo $graded_pct; ?>%;background:<?php echo $graded_pct>=80?'#16a34a':($graded_pct>=50?'#d97706':'#dc2626'); ?>"></div>
      </div>
    </div>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-start gap-3 shadow-sm">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#fef2f2">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    </div>
    <div><div class="text-2xl font-extrabold leading-none" style="color:#dc2626"><?php echo number_format($totals['ungraded'],0,',','.'); ?></div><div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mt-1"><?php echo s(get_string('pendentes', 'local_mulima_analytics')); ?></div></div>
  </div>

</div>

<!-- ━━ CHARTS ROW ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">

  <!-- Bar chart: top courses -->
  <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="flex items-center gap-2 px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <?php echo s(get_string('top_disciplinas_avaliacoes', 'local_mulima_analytics')); ?>
    </div>
    <div class="p-4 space-y-3">
      <?php foreach ($chart_courses as $r):
        $w = \local_mulima_analytics\local\math::percentage($r['eval'], $chart_max_eval);
        $barcolor = $r['ugr'] > 0 ? '#fca5a5' : '#0d9488';
      ?>
      <div>
        <div class="flex justify-between items-baseline mb-1">
          <span class="text-xs font-semibold text-slate-700 truncate max-w-[55%]"><?php echo s($r['short'] ?: $r['name']); ?></span>
          <span class="text-[10px] text-slate-400 whitespace-nowrap ml-2"><?php echo s(get_string('dashboard_chart_counts', 'local_mulima_analytics', (object)['assessments' => $r['eval'], 'graded' => $r['gr']])); ?></span>
        </div>
        <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
          <div class="h-full rounded-full die-progress-fill" style="width:<?php echo $w; ?>%;background:<?php echo $barcolor; ?>"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($chart_courses)): ?>
      <div class="text-center py-6 text-slate-400 text-xs"><?php echo s(get_string('sem_dados', 'local_mulima_analytics')); ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Donut: evaluation status -->
  <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="flex items-center gap-2 px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
      <?php echo s(get_string('estado_avaliacoes', 'local_mulima_analytics')); ?>
    </div>
    <div class="p-4 flex items-center gap-6">
      <?php
        $circ = 213.6;
        $g_d  = $totals['eval'] ? round($totals['graded']   / $totals['eval'] * $circ, 1) : 0;
        $u_d  = $totals['eval'] ? round($totals['ungraded']  / $totals['eval'] * $circ, 1) : 0;
        $n_d  = max(0, $circ - $g_d - $u_d);
      ?>
      <div style="position:relative;width:110px;height:110px;flex-shrink:0">
        <svg width="110" height="110" viewBox="0 0 100 100" style="transform:rotate(-90deg)">
          <circle cx="50" cy="50" r="34" fill="none" stroke="#f1f5f9" stroke-width="10"/>
          <?php if($g_d>0): ?><circle cx="50" cy="50" r="34" fill="none" stroke="#0d9488" stroke-width="10" stroke-dasharray="<?php echo $g_d.' '.($circ-$g_d); ?>" stroke-dashoffset="0"/><?php endif; ?>
          <?php if($u_d>0): ?><circle cx="50" cy="50" r="34" fill="none" stroke="#f59e0b" stroke-width="10" stroke-dasharray="<?php echo $u_d.' '.($circ-$u_d); ?>" stroke-dashoffset="-<?php echo $g_d; ?>"/><?php endif; ?>
          <?php if($n_d>0.5): ?><circle cx="50" cy="50" r="34" fill="none" stroke="#e2e8f0" stroke-width="10" stroke-dasharray="<?php echo $n_d.' '.($circ-$n_d); ?>" stroke-dashoffset="-<?php echo $g_d+$u_d; ?>"/><?php endif; ?>
        </svg>
        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;text-align:center">
          <div style="font-size:18px;font-weight:800;color:#0f172a"><?php echo $graded_pct; ?>%</div>
          <div style="font-size:8px;font-weight:600;color:#94a3b8;text-transform:uppercase"><?php echo s(get_string('corrigidas', 'local_mulima_analytics')); ?></div>
        </div>
      </div>
      <div class="flex-1 space-y-3 min-w-0">
        <?php $legend = [['#0d9488',$totals['graded'],ucfirst(get_string('corrigidas','local_mulima_analytics')),$graded_pct],['#f59e0b',$totals['ungraded'],get_string('pendentes','local_mulima_analytics'),$pending_pct],['#e2e8f0',max(0,$totals['eval']-$totals['graded']-$totals['ungraded']),get_string('sem_submissao','local_mulima_analytics'),0]]; ?>
        <?php foreach ($legend as $l): if($l[1]<1) continue; ?>
        <div class="flex items-center gap-2">
          <div style="width:10px;height:10px;border-radius:50%;background:<?php echo $l[0]; ?>;flex-shrink:0"></div>
          <div class="min-w-0 flex-1">
            <div class="text-xs font-semibold text-slate-800"><?php echo number_format($l[1],0,',','.'); ?> <?php echo $l[2]; ?></div>
            <?php if($l[3]>0): ?><div class="text-[10px] text-slate-400"><?php echo $l[3]; ?><?php echo s(get_string('pct_do_total', 'local_mulima_analytics')); ?></div><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<!-- ━━ CATEGORY HEATMAP ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<?php if (!empty($cat_eval)): ?>
<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-6">
  <div class="flex items-center gap-2 px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    <?php echo s(get_string('avaliacoes_por_categoria', 'local_mulima_analytics')); ?>
  </div>
  <div class="p-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
    <?php $cat_colors=['#0d9488','#2563eb','#7c3aed','#d97706','#dc2626']; $ci=0;
    foreach ($cat_eval as $cat => $ev): $ci++;
      $pct2 = \local_mulima_analytics\local\math::percentage($ev, $cat_max);
      $col  = $cat_colors[($ci-1) % count($cat_colors)];
    ?>
    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
      <div class="text-[10px] font-bold text-slate-500 mb-2 truncate"><?php echo s($cat); ?></div>
      <div class="text-2xl font-extrabold mb-0.5" style="color:<?php echo $col; ?>"><?php echo $ev; ?></div>
      <div class="text-[10px] text-slate-400 mb-2"><?php echo s(get_string('avaliacoes', 'local_mulima_analytics')); ?></div>
      <div class="h-1 bg-slate-200 rounded-full overflow-hidden">
        <div class="h-full rounded-full die-progress-fill" style="width:<?php echo $pct2; ?>%;background:<?php echo $col; ?>"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ━━ ATTENTION TABLE (soma vs MP + pendentes) ━━━━━━━━━━━━━━━━━ -->
<?php if (!empty($attention)): ?>
<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-6">
  <div class="flex items-center justify-between gap-3 px-4 py-2.5 bg-slate-50 border-b border-slate-100">
    <div class="flex items-center gap-2 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <?php echo s(get_string('requer_atencao_titulo', 'local_mulima_analytics')); ?>
      <span class="ml-1 px-2 py-0.5 rounded-full text-[9px] font-bold text-white" style="background:#dc2626"><?php echo count($attention); ?></span>
    </div>
    <span class="text-[10px] text-slate-400"><?php echo get_string('maximo_configurado', 'local_mulima_analytics', number_format($maxpoints,0,',','.')); ?></span>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-xs border-collapse">
      <thead>
        <tr class="border-b-2 border-slate-200 bg-slate-50">
          <th class="text-left px-4 py-2.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider"><?php echo s(get_string('disciplina', 'local_mulima_analytics')); ?></th>
          <th class="text-left px-4 py-2.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider hidden sm:table-cell"><?php echo s(get_string('docente', 'local_mulima_analytics')); ?></th>
          <th class="text-center px-4 py-2.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider"><?php echo s(get_string('soma_pa', 'local_mulima_analytics')); ?></th>
          <th class="text-center px-4 py-2.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider"><?php echo s(get_string('trabalhos_corrigidos', 'local_mulima_analytics')); ?></th>
          <th class="text-center px-4 py-2.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider"><?php echo s(get_string('pendentes', 'local_mulima_analytics')); ?></th>
          <th class="text-center px-4 py-2.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider"><?php echo s(get_string('estado', 'local_mulima_analytics')); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($attention as $r):
          $soma_ok  = ($r['sum'] === $maxpoints || $r['sum'] === 0);
          $pend_ok  = ($r['ugr'] === 0);
          $teachers_str = implode(', ', array_slice($r['teachers'], 0, 2));
          if (count($r['teachers']) > 2) $teachers_str .= ' +'.( count($r['teachers'])-2);
        ?>
        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
          <td class="px-4 py-2.5">
            <div class="font-semibold text-slate-800 truncate max-w-[180px]"><?php echo s($r['name']); ?></div>
            <div class="text-[10px] text-slate-400"><?php echo s($r['cat']); ?></div>
          </td>
          <td class="px-4 py-2.5 hidden sm:table-cell text-slate-600 text-[11px]"><?php echo s($teachers_str ?: '-'); ?></td>
          <td class="px-4 py-2.5 text-center">
            <?php if ($r['sum'] === 0): ?>
              <span class="text-slate-400">-</span>
            <?php elseif ($soma_ok): ?>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-700"><?php echo number_format($r['sum'],0,',','.'); ?></span>
            <?php else: ?>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700"><?php echo number_format($r['sum'],0,',','.'); ?></span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-2.5 text-center font-bold text-slate-800"><?php echo $r['gr']; ?></td>
          <td class="px-4 py-2.5 text-center">
            <?php if ($r['ugr'] > 0): ?>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-700"><?php echo $r['ugr']; ?></span>
            <?php else: ?>
              <span class="text-green-600 font-bold">0</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-2.5 text-center">
            <?php if ($soma_ok && $pend_ok): ?>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-700"><?php echo s(get_string('estado_ok', 'local_mulima_analytics')); ?></span>
            <?php elseif (!$soma_ok && !$pend_ok): ?>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-700"><?php echo s(get_string('diverge_pendentes', 'local_mulima_analytics')); ?></span>
            <?php elseif (!$soma_ok): ?>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700"><?php echo s(get_string('soma_diverge', 'local_mulima_analytics')); ?></span>
            <?php else: ?>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-50 text-orange-700"><?php echo s(get_string('pendentes', 'local_mulima_analytics')); ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ━━ QUICK ACCESS ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-2">
  <?php $quick = [
    ['acessos.php',get_string('title_acessos', 'local_mulima_analytics'),get_string('qa_acessos_sub', 'local_mulima_analytics'),'#0369a1','#eff6ff','M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'],
    ['risco.php',get_string('title_risco', 'local_mulima_analytics'),get_string('qa_risco_sub', 'local_mulima_analytics'),'#dc2626','#fef2f2','M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
    ['docentes.php',get_string('title_docentes', 'local_mulima_analytics'),get_string('qa_docentes_sub', 'local_mulima_analytics'),'#2563eb','#eff6ff','M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
  ]; foreach ($quick as $q):
    $url = (new moodle_url('/local/mulima_analytics/'.$q[0]))->out(false); ?>
  <a href="<?php echo $url; ?>" style="text-decoration:none"
     class="group bg-white border border-slate-200 rounded-2xl p-4 flex items-center gap-3 hover:border-slate-300 hover:shadow-md transition-all">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-110" style="background:<?php echo $q[4]; ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="stroke:<?php echo $q[3]; ?>">
        <?php $ps=explode(' M',' '.$q[5]); foreach($ps as $pi=>$p){$p=trim($p);if(!$p)continue;echo '<path d="'.($pi>0?'M':'').$p.'"/>';} ?>
      </svg>
    </div>
    <div class="min-w-0 flex-1">
      <div class="text-xs font-bold text-slate-800"><?php echo $q[1]; ?></div>
      <div class="text-[10px] text-slate-500 mt-0.5"><?php echo $q[2]; ?></div>
    </div>
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-slate-300 group-hover:text-slate-500 transition-colors shrink-0"><polyline points="9 18 15 12 9 6"/></svg>
  </a>
  <?php endforeach; ?>
</div>
<p class="text-right text-[10px] text-slate-400 mt-3"><?php echo get_string('actualizado_em', 'local_mulima_analytics', userdate(time(), get_string('strftimedatetimeshort', 'langconfig'))); ?></p>

  <?php \local_mulima_analytics\local\branding::render_footer(); ?>
  </main><!-- content -->
</div><!-- main -->
</div><!-- #die-root -->
<?php
echo $OUTPUT->footer();
?>
