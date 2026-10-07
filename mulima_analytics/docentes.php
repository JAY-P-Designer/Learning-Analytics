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
 * Teacher activity report.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
\local_mulima_analytics\local\access::require_report('teachers');
\local_mulima_analytics\local\page::setup('docentes.php', get_string('title_docentes', 'local_mulima_analytics'));

$SK  = sesskey();
$AX  = (new moodle_url('/local/mulima_analytics/docentes_ajax.php'))->out(false);
$XLS = (new moodle_url('/local/mulima_analytics/docentes_export.php'))->out(false);
$COURSE_VIEW = (new moodle_url('/course/view.php'))->out(false);
$FORUM_VIEW = (new moodle_url('/mod/forum/view.php'))->out(false);
$scoring = \local_mulima_analytics\local\teacher_scoring::settings();
$SCORE_CONFIG = (new moodle_url('/local/mulima_analytics/presenca_config.php'))->out(false).'#teacher-scoring';

$root_cats = $DB->get_records_select('course_categories', 'depth = 1', [], 'sortorder', 'id,name');

echo $OUTPUT->header();
?>
<?php
$DIE_PAGE     = 'docentes';
$DIE_TITLE    = get_string('title_docentes', 'local_mulima_analytics');
$DIE_SUBTITLE = get_string('subtitle_docentes', 'local_mulima_analytics');
require_once(__DIR__.'/die_layout.php');
require_once(__DIR__.'/filter_controls.php');
?>

<div id="doc">

<!-- Header -->

<!-- Filters -->
<div class="die-card bg-white border border-slate-200 rounded-xl shadow-sm mb-4">
  <div class="die-card-head flex items-center justify-between gap-2 px-4 py-2.5 bg-slate-50 border-b border-slate-100 rounded-t-xl text-[10px] font-bold text-slate-500 uppercase tracking-wider">
    <span>
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
      <?php echo s(get_string('filtros', 'local_mulima_analytics')); ?>
    </span>
  </div>
  <div class="die-card-body p-4">
    <div class="die-filters la-report-filters flex flex-wrap gap-3 items-end">
      <div class="die-fg flex flex-col gap-1.5 flex-1 min-w-36">
        <label for="flt_period" class="text-[10px] font-bold text-slate-500 uppercase tracking-wide"><?php echo s(get_string('periodo_execucao', 'local_mulima_analytics')); ?></label>
        <select class="die-select w-full text-sm px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800 transition-colors" id="flt_period" onchange="onPeriod()">
          <option value=""></option>
          <?php foreach ($root_cats as $rc): ?>
          <option value="<?php echo $rc->id; ?>"><?php echo s($rc->name); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="die-fg flex flex-col gap-1.5 flex-1 min-w-36">
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide"><?php echo s(get_string('subcategoria', 'local_mulima_analytics')); ?></label>
        <div id="flt_cat_tree" class="la-category-tree" role="group" aria-label="<?php echo s(get_string('subcategoria', 'local_mulima_analytics')); ?>"></div><input type="hidden" id="flt_cat" value="">
      </div>
      <div class="flex-1 min-w-44" id="dcs_container"></div>
      <div class="die-fg flex flex-col gap-1.5 flex-1 min-w-36" style="max-width:140px">
        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1.5"><?php echo s(get_string('period_days', 'local_mulima_analytics')); ?></label>
        <select class="die-select w-full text-sm px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800 transition-colors" id="flt_range" onchange="onFilter()">
          <option value="7"><?php echo s(get_string('ultimos_x_dias', 'local_mulima_analytics', 7)); ?></option>
          <option value="30" selected><?php echo s(get_string('ultimos_x_dias', 'local_mulima_analytics', 30)); ?></option>
          <option value="90"><?php echo s(get_string('ultimos_x_dias', 'local_mulima_analytics', 90)); ?></option>
          <option value="365"><?php echo s(get_string('ultimos_x_dias', 'local_mulima_analytics', 365)); ?></option>
        </select>
      </div>
    </div>
  </div>
</div>

<style>
/* ── Docentes page styles ── */
#doc .die-t thead th.num, #doc .die-t tbody td.num { text-align:center; }
#doc .dc-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px;
  font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; white-space:nowrap; }
#doc .dc-pill::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }
#doc .dc-pill-g { background:#dcfce7; color:#15803d; }
#doc .dc-pill-a { background:#fef3c7; color:#b45309; }
#doc .dc-pill-r { background:#fee2e2; color:#b91c1c; }
#doc .dc-score { display:inline-flex; align-items:center; justify-content:center; min-width:38px; height:28px;
  padding:0 8px; border-radius:8px; font-size:13px; font-weight:800; }
#doc .dc-score-hi { background:#dcfce7; color:#15803d; }
#doc .dc-score-md { background:#fef3c7; color:#b45309; }
#doc .dc-score-lo { background:#f1f5f9; color:#64748b; }
#doc .dc-m { font-size:15px; font-weight:800; color:#0f172a; }
#doc .dc-m.z { color:#cbd5e1; font-weight:600; }
#doc .dc-chip { display:inline-block; padding:2px 8px; border-radius:6px; background:#f1f5f9;
  color:#475569; font-size:10px; font-weight:600; margin:1px 2px 1px 0; white-space:nowrap;
  max-width:150px; overflow:hidden; text-overflow:ellipsis; vertical-align:middle; }
#doc .dc-course-cell { min-width:180px; max-width:240px; }
#doc .dc-courses, #doc .dc-more-courses > div { display:flex; flex-direction:column; align-items:flex-start; gap:5px; }
#doc .dc-course-link { display:inline-flex; align-items:baseline; gap:5px; color:var(--die-acc); text-decoration:underline;
  text-underline-offset:2px; white-space:normal; overflow-wrap:anywhere; }
#doc .dc-course-link.dc-chip { max-width:220px; padding:5px 8px; color:var(--die-acc); font-size:11px; }
#doc .dc-course-link:hover { color:#075985; background:#e0f2fe; }
#doc .dc-course-link:focus-visible { outline:2px solid var(--die-acc); outline-offset:2px; border-radius:4px; }
#doc .dc-course-link svg { flex-shrink:0; }
#doc .dc-more-courses summary { cursor:pointer; color:var(--die-acc); font-size:11px; padding:5px 0; }
#doc .dc-fchip { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:20px;
  font-size:11px; font-weight:600; cursor:pointer; border:1.5px solid #e2e8f0; background:#fff;
  color:#64748b; transition:all .15s; user-select:none; }
#doc .dc-fchip:hover { border-color:#cbd5e1; }
#doc .dc-fchip.on { border-color:var(--die-acc); background:rgba(var(--die-acc-rgb),.08); color:var(--die-acc); }
#doc .dc-fchip .n { font-size:10px; font-weight:800; padding:1px 6px; border-radius:10px; background:#f1f5f9; }
#doc th.sortable { cursor:pointer; }
#doc th.sortable:hover { color:#334155; }
#doc th.sortable .arr { opacity:.35; font-size:8px; margin-left:3px; }
#doc th.sortable.act .arr { opacity:1; color:var(--die-acc); }
#doc .dc-la { font-size:11px; font-weight:600; }
#doc .dc-la.ok { color:#15803d; } #doc .dc-la.mid { color:#b45309; } #doc .dc-la.old { color:#dc2626; }
#doc .dc-forum { display:flex; flex-direction:column; gap:5px; min-width:210px; max-width:310px; text-align:left; }
#doc .dc-forum-main { display:flex; align-items:baseline; gap:6px; color:#475569; }
#doc .dc-forum-meta { font-size:10px; color:#64748b; line-height:1.5; white-space:normal; }
#doc .dc-forum-help { padding:12px 16px; border-bottom:1px solid #e2e8f0; color:#475569; font-size:12px; line-height:1.6; }
#doc .dc-forum-help p { margin:3px 0 0; }
#doc .dc-forum details summary { cursor:pointer; color:#475569; }
#doc .dc-forum-list { list-style:none; padding:0; margin:8px 0 0; max-height:360px; overflow-y:auto; }
#doc .dc-forum-list li { display:flex; flex-direction:column; align-items:flex-start; gap:5px; padding:10px 0;
  border-top:1px solid #e2e8f0; white-space:normal; overflow-wrap:anywhere; }
#doc .dc-forum-list strong { font-size:12px; }
#doc .dc-forum-status { border-radius:5px; padding:3px 6px; font-size:10px; line-height:1.4; background:#f1f5f9; color:#475569; }
#doc .dc-forum-status.active { background:#dcfce7; color:#166534; }
#doc .dc-forum .dc-forum-details > summary { color:var(--die-acc); font-size:11px; font-weight:600; }
#doc .dc-assign { min-width:210px; max-width:310px; text-align:left; display:flex; flex-direction:column; gap:5px; }
#doc .dc-assign-meta { font-size:11px; color:#64748b; line-height:1.5; white-space:normal; }
#doc .dc-assign-progress { height:6px; background:#e2e8f0; border-radius:8px; overflow:hidden; }
#doc .dc-assign-progress span { display:block; height:100%; background:#d97706; }
#doc .dc-assign-progress.complete span { background:#16a34a; }
#doc .dc-assign-level { color:#b45309; font-weight:700; font-size:11px; }
#doc .dc-assign-level.complete { color:#15803d; }
#doc .dc-assign-list { list-style:none; padding:0; margin:8px 0 0; max-height:320px; overflow-y:auto; }
#doc .dc-assign-list li { padding:8px 0; border-top:1px solid #e2e8f0; overflow-wrap:anywhere; }
#doc .dc-assign-list strong { color:#334155; display:block; }
#doc .dc-assign summary { cursor:pointer; color:var(--die-acc); font-size:11px; font-weight:600; }
#doc #doc_tbody td { vertical-align:top; }
#doc .dc-metric-guides { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); border-bottom:1px solid #e2e8f0; }
#doc .dc-metric-guides .dc-forum-help { border:0; }
#doc .dc-metric-guides summary { cursor:pointer; font-weight:600; }
#doc .dc-score-open { display:block; border:0; background:none; color:var(--die-acc); font:inherit; font-size:11px;
  padding:5px 0; cursor:pointer; text-decoration:underline; text-underline-offset:2px; }
#doc .dc-score-open:focus-visible { outline:2px solid var(--die-acc); outline-offset:2px; }
#doc-score-dialog { width:min(960px,calc(100vw - 32px)); max-height:calc(100vh - 48px); padding:0; border:1px solid #cbd5e1;
  border-radius:14px; color:#334155; background:#fff; box-shadow:0 20px 70px #0f172a40; }
#doc-score-dialog::backdrop { background:#0f172a80; }
#doc-score-dialog .score-dialog-head { display:flex; align-items:center; justify-content:space-between; gap:12px;
  padding:18px 22px; border-bottom:1px solid #e2e8f0; }
#doc-score-dialog h2 { margin:0; font-size:18px; color:#0f172a; }
#doc-score-dialog .score-dialog-body { padding:18px 22px; font-size:13px; }
#doc-score-dialog .score-close { border:1px solid #cbd5e1; border-radius:6px; background:#f8fafc; padding:7px 12px; color:#334155; cursor:pointer; }
#doc-score-dialog .score-table-scroll { overflow-x:auto; margin:16px 0; }
#doc-score-dialog table { width:100%; min-width:720px; border-collapse:collapse; font-size:12px; }
#doc-score-dialog th, #doc-score-dialog td { padding:10px; border:1px solid #e2e8f0; text-align:left; vertical-align:top; }
#doc-score-dialog th { background:#f8fafc; font-weight:700; }
#doc-score-dialog .score-totals { display:flex; flex-wrap:wrap; gap:10px 24px; padding:12px; background:#f0f9ff; border-radius:8px; }
#doc-score-dialog .dc-course-link { color:#0369a1; text-decoration:underline; }
@media (max-width:640px){ #doc .dc-metric-guides { grid-template-columns:1fr; } }
@media (max-width:640px){ #doc .dc-hide-sm { display:none !important; } }
</style>

<!-- Stats -->
<div id="doc_stats" style="display:none">
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 w-full">
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex items-start gap-3">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#e0e7ff">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4338ca" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div class="min-w-0 flex-1"><div class="text-2xl font-extrabold text-slate-900 leading-none" id="ds_total">-</div><div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mt-1 break-words"><?php echo s(get_string('docentes_lbl', 'local_mulima_analytics')); ?></div></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex items-start gap-3">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#dcfce7">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      </div>
      <div class="min-w-0 flex-1"><div class="text-2xl font-extrabold text-slate-900 leading-none" id="ds_active">-</div><div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mt-1 break-words"><?php echo s(get_string('com_actividade', 'local_mulima_analytics')); ?></div></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex items-start gap-3">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#fee2e2">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
      </div>
      <div class="min-w-0 flex-1"><div class="text-2xl font-extrabold text-slate-900 leading-none" id="ds_idle">-</div><div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mt-1 break-words"><?php echo s(get_string('sem_actividade', 'local_mulima_analytics')); ?></div></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex items-start gap-3">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#dbeafe">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      </div>
      <div class="min-w-0 flex-1"><div class="text-2xl font-extrabold text-slate-900 leading-none" id="ds_graded">-</div><div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mt-1 break-words"><?php echo s(get_string('doc_assign_corrected', 'local_mulima_analytics')); ?></div><div class="dc-assign-meta" id="ds_grading_summary"></div></div>
    </div>
  </div>
</div>

<!-- Results -->
<div class="die-card bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-4" id="doc_results" style="display:none">
  <div class="die-card-head flex items-center justify-between gap-2 px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
    <span style="display:flex;align-items:center;gap:8px">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><?php echo s(get_string('docentes_lbl', 'local_mulima_analytics')); ?><span id="doc_count" style="font-weight:600;color:#94a3b8;text-transform:none;letter-spacing:0"></span>
    </span>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <div style="position:relative">
      <svg style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#94a3b8" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="srch_doc" placeholder="<?php echo s(get_string('search_teacher_course', 'local_mulima_analytics')); ?>" oninput="renderDoc()" style="padding:6px 10px 6px 28px;border:1px solid #e2e8f0;border-radius:7px;font-size:11px;width:170px;outline:none;font-weight:400;text-transform:none;letter-spacing:0">
    </div>
    <button class="die-btn inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors cursor-pointer" id="btn_demails" onclick="copyDocEmails()" style="display:none">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><?php echo s(get_string('copiar_emails', 'local_mulima_analytics')); ?></button>
    <button class="die-btn inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold text-white bg-green-600 hover:bg-green-700 transition-colors cursor-pointer border-0" id="btn_xls" onclick="exportXls()" style="display:none">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg><?php echo s(get_string('exportar_xlsx', 'local_mulima_analytics')); ?></button>
    </div>
  </div>
  <div id="doc_loading" class="die-empty text-center py-12 px-6 text-slate-400" style="display:none"><div class="die-spin w-5 h-5 rounded-full"></div><br><?php echo s(get_string('loading_teachers', 'local_mulima_analytics')); ?></div>
  <div id="doc_empty" class="die-empty text-center py-12 px-6 text-slate-400" style="display:none">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    <div><?php echo s(get_string('no_teachers_scope', 'local_mulima_analytics')); ?></div>
  </div>
  <div id="doc_chips" style="display:none;padding:10px 14px;border-bottom:1px solid #f1f5f9;gap:8px;flex-wrap:wrap">
    <span class="dc-fchip on" data-f="" onclick="setDocF(this)"><?php echo s(get_string('todos', 'local_mulima_analytics')); ?> <span class="n" id="dch_t">0</span></span>
    <span class="dc-fchip" data-f="act" onclick="setDocF(this)"><?php echo s(get_string('com_actividade', 'local_mulima_analytics')); ?> <span class="n" id="dch_a">0</span></span>
    <span class="dc-fchip" data-f="idle" onclick="setDocF(this)"><?php echo s(get_string('sem_actividade', 'local_mulima_analytics')); ?> <span class="n" id="dch_i">0</span></span>
  </div>
  <div id="doc_table_wrap" style="display:none">
    <div class="dc-metric-guides">
    <details class="dc-forum-help">
      <summary><?php echo s(get_string('score_section', 'local_mulima_analytics')); ?></summary>
      <p><?php echo s(get_string('score_rules_help', 'local_mulima_analytics')); ?></p>
      <p><?php echo s(get_string('score_logs_help', 'local_mulima_analytics')); ?></p>
      <p id="doc_score_rules"></p>
      <?php if (has_capability('moodle/site:config', context_system::instance())): ?>
      <a href="<?php echo $SCORE_CONFIG; ?>"><?php echo s(get_string('score_configure', 'local_mulima_analytics')); ?></a>
      <?php endif; ?>
    </details>
    <details class="dc-forum-help">
      <summary><?php echo s(get_string('doc_assign_title', 'local_mulima_analytics')); ?></summary>
      <p><?php echo s(get_string('doc_assign_help', 'local_mulima_analytics')); ?></p>
      <p><?php echo s(get_string('doc_assign_groups_help', 'local_mulima_analytics')); ?></p>
      <p id="doc_assign_window"></p>
    </details>
    <details class="dc-forum-help">
      <summary><?php echo s(get_string('doc_forum_title', 'local_mulima_analytics')); ?></summary>
      <p><?php echo s(get_string('doc_forum_help', 'local_mulima_analytics')); ?></p>
      <p id="doc_forum_window"></p>
    </details>
    </div>
    <div class="dc-table-scroll" style="overflow-x:auto">
    <table class="die-t w-full border-collapse text-xs">
      <thead><tr>
        <th style="width:36px">#</th>
        <th class="sortable" data-k="fullname" onclick="sortDoc(this)"><?php echo s(get_string('docente', 'local_mulima_analytics')); ?><span class="arr">▼</span></th>
        <th class="dc-course-cell"><?php echo s(get_string('disciplinas_s', 'local_mulima_analytics')); ?></th>
        <th class="num sortable" data-k="activities" onclick="sortDoc(this)">
          <div><?php echo s(get_string('actividades', 'local_mulima_analytics')); ?></div><div style="font-size:9px;opacity:.7"><?php echo s(get_string('criadas', 'local_mulima_analytics')); ?></div>
        </th>
        <th class="num sortable dc-hide-sm" data-k="resources" onclick="sortDoc(this)">
          <div><?php echo s(get_string('recursos', 'local_mulima_analytics')); ?></div><div style="font-size:9px;opacity:.7"><?php echo s(get_string('publicados', 'local_mulima_analytics')); ?></div>
        </th>
        <th class="num sortable" data-k="grading_percent" onclick="sortDoc(this)">
          <div><?php echo s(get_string('trabalhos', 'local_mulima_analytics')); ?><span class="arr">▼</span></div><div style="font-size:9px;opacity:.7"><?php echo s(get_string('doc_assign_progress', 'local_mulima_analytics')); ?></div>
        </th>
        <th class="num sortable" data-k="forum_posts" onclick="sortDoc(this)">
          <div><?php echo s(get_string('doc_forum_title', 'local_mulima_analytics')); ?><span class="arr">▼</span></div>
          <div style="font-size:9px;opacity:.7"><?php echo s(get_string('doc_forum_messages', 'local_mulima_analytics')); ?></div>
        </th>
        <th class="num sortable dc-hide-sm" data-k="last_ts" onclick="sortDoc(this)"><?php echo s(get_string('ultimo_acesso', 'local_mulima_analytics')); ?><span class="arr">▼</span></th>
        <th class="num dc-hide-sm"><?php echo s(get_string('score_progress', 'local_mulima_analytics')); ?></th>
        <th class="num sortable act" data-k="score" onclick="sortDoc(this)" style="width:90px"><?php echo s(get_string('pontuacao', 'local_mulima_analytics')); ?><span class="arr">▼</span></th>
      </tr></thead>
      <tbody id="doc_tbody"></tbody>
    </table>
    </div>
    <div class="flex flex-wrap gap-4 text-xs text-slate-500 px-4 py-3 border-t border-slate-100">
      <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full" style="background:#16a34a"></span><?php echo s(get_string('high_activity', 'local_mulima_analytics')); ?></span>
      <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full" style="background:#d97706"></span><?php echo s(get_string('moderate_activity', 'local_mulima_analytics')); ?></span>
      <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full" style="background:#dc2626"></span><?php echo s(get_string('low_activity', 'local_mulima_analytics')); ?></span>
      <span id="doc_score_formula" style="margin-left:auto;color:#64748b"></span>
    </div>
  </div>
</div>

</div>

<dialog id="doc-score-dialog" aria-labelledby="doc-score-title">
  <div class="score-dialog-head">
    <h2 id="doc-score-title"><?php echo s(get_string('score_dialog_title', 'local_mulima_analytics')); ?></h2>
    <button class="score-close" type="button" onclick="closeScore()"><?php echo s(get_string('score_close', 'local_mulima_analytics')); ?></button>
  </div>
  <div class="score-dialog-body" id="doc-score-body"></div>
</dialog>

<script>
var AX = '<?php echo $AX; ?>';

var SK = '<?php echo $SK; ?>';
var XLS = '<?php echo $XLS; ?>';
var COURSE_VIEW = <?php echo json_encode($COURSE_VIEW); ?>;
var FORUM_VIEW = <?php echo json_encode($FORUM_VIEW); ?>;
var SCORE_RULES = <?php echo json_encode($scoring); ?>;

var filters;
function G(id){return document.getElementById(id);}
function onPeriod(){filters.periodChanged();}
function onCat(){filters.categoryChanged();}
function onCourse(){filters.refresh();}
function onFilter(){filters.refresh();}
function resetData(){
  closeScore();
  _TEACHERS=[];
  ['doc_results','doc_stats','doc_chips','doc_table_wrap','btn_xls','btn_demails'].forEach(function(id){G(id).style.display='none';});
  G('doc_tbody').innerHTML='';
}
function loadData(selected){
  closeScore();show();loading(true);
  filters.request('report','teachers',{period:selected.period,catid:selected.catid,descendants:selected.descendants,courseid:selected.courseid,range:G('flt_range').value},function(d){
    if(d.scoring) SCORE_RULES=d.scoring;
    loading(false);render(d.teachers||[]);
  },function(){loading(false);},{post:true});
}

var _TEACHERS = [], _DF = '', _SORTK = 'score', _SORTD = -1;

function render(teachers) {
    _TEACHERS = teachers; _DF = ''; _SORTK = 'score'; _SORTD = -1;
    document.querySelectorAll('#doc .dc-fchip').forEach(function(el){
        el.classList.toggle('on', el.getAttribute('data-f') === '');
    });
    document.querySelectorAll('#doc th.sortable').forEach(function(th){
        th.classList.toggle('act', th.getAttribute('data-k') === 'score');
        var arrow = th.querySelector('.arr');
        if (arrow) arrow.textContent = '\u25BC';
    });
    var srch = G('srch_doc'); if (srch) srch.value = '';
    var n = teachers.length;
    G('btn_xls').style.display     = n ? 'inline-flex' : 'none';
    G('btn_demails').style.display = n ? 'inline-flex' : 'none';
    if (!n) { G('doc_empty').style.display='block'; G('doc_stats').style.display='none'; G('doc_chips').style.display='none'; return; }

    // Summary stats
    var active = teachers.filter(hasActivity).length;
    // An assignment shared by two teachers is counted once in the overall card.
    var assignments = {};
    teachers.forEach(function(t){ (t.assignments||[]).forEach(function(a){assignments[a.id]=a;}); });
    var graded = 0, submitted = 0;
    Object.keys(assignments).forEach(function(id){graded+=Number(assignments[id].corrected)||0;submitted+=Number(assignments[id].submitted)||0;});
    G('doc_stats').style.display = 'block';
    G('ds_total').textContent  = n;
    G('ds_active').textContent = active;
    G('ds_idle').textContent   = n - active;
    G('ds_graded').textContent = graded+' / '+submitted;
    var overallPercent = submitted ? (graded===submitted?100:Math.min(99.9,Math.round(1000*graded/submitted)/10)) : null;
    G('ds_grading_summary').textContent = (overallPercent===null?DIE_LANG.doc_assign_no_submissions:overallPercent+'%')
        +' · '+DIE_LANG.doc_assign_pending+': '+(submitted-graded);

    G('doc_chips').style.display = 'flex';
    G('dch_t').textContent = n;
    G('dch_a').textContent = active;
    G('dch_i').textContent = n - active;

    G('doc_score_formula').textContent = scoreFormula();
    G('doc_score_rules').textContent = scoreFormula()+' · '+DIE_LANG.score_moderate+': '+SCORE_RULES.moderate
        +' · '+DIE_LANG.score_high+': '+SCORE_RULES.high;
    G('doc_table_wrap').style.display = 'block';
    G('doc_forum_window').textContent = DIE_LANG.doc_forum_window.replace('{$a}', G('flt_range').value);
    G('doc_assign_window').textContent = DIE_LANG.doc_assign_window.replace('{$a}', G('flt_range').value);
    renderDoc();
}

function setDocF(el){
    _DF = el.getAttribute('data-f');
    document.querySelectorAll('#doc .dc-fchip').forEach(function(x){ x.classList.remove('on'); });
    el.classList.add('on');
    renderDoc();
}

function sortDoc(th){
    var k = th.getAttribute('data-k');
    if (_SORTK === k) { _SORTD = -_SORTD; } else { _SORTK = k; _SORTD = (k === 'fullname') ? 1 : -1; }
    document.querySelectorAll('#doc th.sortable').forEach(function(x){ x.classList.remove('act'); });
    th.classList.add('act');
    var arrow = th.querySelector('.arr');
    if (arrow) arrow.textContent = _SORTD === -1 ? '\u25BC' : '\u25B2';
    renderDoc();
}

function laCls(ts){
    if (!ts) return 'old';
    var d = (Date.now()/1000 - ts) / 86400;
    return d <= 7 ? 'ok' : (d <= 30 ? 'mid' : 'old');
}

function hasActivity(t){
    return t.has_activity===undefined ? t.score>0 : Boolean(t.has_activity);
}
function scoreLevel(t){
    return t.score_level || (!t.score?'none':(t.score>=SCORE_RULES.high?'high':(t.score>=SCORE_RULES.moderate?'moderate':'low')));
}
function scoreFormula(){
    return DIE_LANG.score_activities+' × '+SCORE_RULES.weight_activities+' + '+DIE_LANG.score_resources+' × '+SCORE_RULES.weight_resources
        +' + '+DIE_LANG.score_graded+' × '+SCORE_RULES.weight_grading+' + '+DIE_LANG.score_forum_posts+' × '+SCORE_RULES.weight_forums
        +' · '+DIE_LANG.score_maximum+': '+SCORE_RULES.maximum;
}
function closeScore(){
    var dialog=G('doc-score-dialog');
    if(dialog && dialog.open) dialog.close();
}
function openScore(userid){
    var t=_TEACHERS.find(function(row){return row.userid===Number(userid);});
    if(!t) return;
    var courses=t.course_scores||[], keys=['activities','resources','graded','forum_posts'];
    var html='<p><strong>'+esc(t.fullname)+'</strong><br>'+esc(DIE_LANG.score_period.replace('{$a}',G('flt_range').value))+'</p>'
        +'<div class="score-totals"><span>'+esc(DIE_LANG.score_raw)+': <strong>'+Number(t.score_raw||0)+'</strong></span>'
        +'<span>'+esc(DIE_LANG.score_maximum)+': <strong>'+Number(t.score_max||SCORE_RULES.maximum)+'</strong></span>'
        +'<span>'+esc(DIE_LANG.score_final)+': <strong>'+Number(t.score||0)+'</strong></span></div>'
        +'<div class="score-table-scroll"><table><thead><tr><th scope="col">'+esc(DIE_LANG.score_course)+'</th>';
    keys.forEach(function(key){html+='<th scope="col">'+esc(DIE_LANG['score_'+key])+'</th>';});
    html+='<th scope="col">'+esc(DIE_LANG.score_raw)+'</th><th scope="col">'+esc(DIE_LANG.score_final)+'</th></tr></thead><tbody>';
    courses.forEach(function(c){
        html+='<tr><th scope="row">'+courseLink(c.courseid,c.coursename,false)+'</th>';
        keys.forEach(function(key){
            var part=c.score_components[key];
            html+='<td>'+Number(part.count)+' × '+Number(part.weight)+' = <strong>'+Number(part.points)+'</strong></td>';
        });
        html+='<td>'+Number(c.score_raw)+'</td><td><strong>'+Number(c.score)+'</strong> / '+Number(c.score_max)
            +'<br>'+esc(DIE_LANG['score_level_'+c.score_level])+'</td></tr>';
    });
    html+='</tbody></table></div><p>'+esc(DIE_LANG.score_calculation_hint)+'</p><p>'+esc(DIE_LANG.score_scope_note)+'</p>';
    G('doc-score-body').innerHTML=html;
    G('doc-score-dialog').showModal();
}

function forumCell(t){
    var forums=Array.isArray(t.forums)?t.forums:[], total=Array.isArray(t.forums)?forums.length:(Number(t.forums_total)||0);
    if (!total) return '<div class="dc-forum dc-forum-meta">'+esc(DIE_LANG.doc_forum_none)+'</div>';
    var posts = Number(t.forum_posts) || 0;
    var coverage = DIE_LANG.doc_forum_coverage
        .replace('{$a->participated}', Number(t.forums_participated) || 0)
        .replace('{$a->total}', total);
    var html = '<div class="dc-forum"><strong>'+esc(DIE_LANG.doc_forums_total)+': '+total+'</strong>'
        + '<div class="dc-forum-main"><span class="dc-m'+(posts ? '' : ' z')+'">'+posts+'</span> '+esc(DIE_LANG.doc_forum_messages)+'</div>'
        + '<div class="dc-forum-meta">'+esc(coverage)+'</div>'
        + '<div class="dc-forum-meta">'+esc(DIE_LANG.doc_forum_period.replace('{$a}',G('flt_range').value))+'</div>'
        + '<div class="dc-forum-meta">'+esc(DIE_LANG.doc_forum_topics)+': '+(Number(t.forum_discussions)||0)
        + ' · '+esc(DIE_LANG.doc_forum_replies)+': '+(Number(t.forum_replies)||0)+'</div>';
    if (t.forum_last_post) {
        html += '<details class="dc-forum-meta"><summary>'+esc(DIE_LANG.doc_forum_last)+'</summary><span>'+esc(t.forum_last_post)+'</span></details>';
    } else {
        html += '<div class="dc-forum-meta">'+esc(DIE_LANG.doc_forum_no_posts)+'</div>';
    }
    if (forums.length) {
        html+='<details class="dc-forum-details"><summary>'+esc(DIE_LANG.doc_forum_view)+' ('+total+')</summary><ul class="dc-forum-list">';
        forums.forEach(function(f){
            var active=(Number(f.posts)||0)>0;
            html+='<li><strong>'+reportLink(FORUM_VIEW,f.cmid,f.name,DIE_LANG.doc_forum_open,false)+'</strong>'
                +'<div class="dc-forum-meta">'+courseLink(f.courseid,f.coursename,false)+'</div>';
            if (!f.visible) { html+='<span class="dc-forum-meta">'+esc(DIE_LANG.doc_forum_hidden)+'</span>'; }
            html+='<div class="dc-forum-meta">'+esc(f.topics?DIE_LANG.doc_forum_existing_topics+': '+f.topics:DIE_LANG.doc_forum_empty)+'</div>'
                +'<span class="dc-forum-status'+(active?' active':'')+'">'+esc(active?DIE_LANG.doc_forum_with_participation:DIE_LANG.doc_forum_without_participation)+'</span>'
                +'<div class="dc-forum-meta">'+esc(DIE_LANG.doc_forum_messages)+': '+(Number(f.posts)||0)+'</div>'
                +'<div class="dc-forum-meta">'+esc(DIE_LANG.doc_forum_topics)+': '+(Number(f.discussions)||0)
                +' · '+esc(DIE_LANG.doc_forum_replies)+': '+(Number(f.replies)||0)+'</div>';
            if (f.last_post) { html+='<div class="dc-forum-meta">'+esc(DIE_LANG.doc_forum_last)+': '+esc(f.last_post)+'</div>'; }
            html+='</li>';
        });
        html+='</ul></details>';
    }
    return html+'</div>';
}

function courseLink(id, name, chip){
    return reportLink(COURSE_VIEW,id,name,DIE_LANG.doc_course_open,chip);
}
function reportLink(base, id, name, description, chip){
    var courseid=Number(id), classes='dc-course-link'+(chip?' dc-chip':'');
    if (!Number.isSafeInteger(courseid) || courseid<=0) {
        return '<span'+(chip?' class="dc-chip"':'')+'>'+esc(name)+'</span>';
    }
    var label=esc(description.replace('{$a}',name)).replace(/"/g,'&quot;');
    var href=esc(base+'?id='+courseid).replace(/"/g,'&quot;');
    return '<a class="'+classes+'" href="'+href+'" target="_blank" rel="noopener noreferrer" title="'+label+'" aria-label="'+label+'">'
        +'<span>'+esc(name)+'</span><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M15 3h6v6M10 14 21 3M21 14v7H3V3h7"/></svg></a>';
}
function courseCell(t){
    var courses=t.course_details;
    if (!Array.isArray(courses)) {
        courses=(t.courses||[]).map(function(name){return {id:0,name:name};});
    }
    function links(items){return items.map(function(c){return courseLink(c.id,c.name,true);}).join('');}
    var html='<div class="dc-courses">'+links(courses.slice(0,2));
    if (courses.length>2) {
        html+='<details class="dc-more-courses"><summary>'+esc(DIE_LANG.doc_course_more.replace('{$a}',courses.length-2))
            +'</summary><div>'+links(courses.slice(2))+'</div></details>';
    }
    return html+'</div>';
}

function assignmentLevel(level){
    return DIE_LANG['doc_assign_'+level] || DIE_LANG.doc_assign_no_submissions;
}
function assignmentCoverage(corrected, submitted){
    return DIE_LANG.doc_assign_coverage.replace('{$a->corrected}',corrected).replace('{$a->submitted}',submitted);
}
function assignmentCell(t){
    var assignments=t.assignments||[], total=Number(t.assignments_total)||0;
    if (!total) return '<div class="dc-assign dc-assign-meta">'+esc(DIE_LANG.doc_assign_no_assignments)+'</div>';
    var percent=t.grading_percent, level=t.grading_level||'no_submissions';
    var html='<div class="dc-assign"><strong>'+esc(DIE_LANG.doc_assign_total)+': '+total+'</strong>'
        + '<div class="dc-assign-meta">'+esc(assignmentCoverage(t.submissions_graded||0,t.submissions_total||0))+'</div>';
    if (percent!==null && percent!==undefined) {
        percent=Math.max(0,Math.min(100,Number(percent)||0));
        html+='<div class="dc-assign-progress '+(level==='complete'?'complete':'')+'" role="progressbar" aria-label="'+esc(DIE_LANG.doc_assign_progress)+'" aria-valuemin="0" aria-valuemax="100" aria-valuenow="'+percent+'"><span style="width:'+percent+'%"></span></div>';
    }
    html+='<div class="dc-assign-level '+(level==='complete'?'complete':'')+'">'+esc(assignmentLevel(level))
        +(percent===null||percent===undefined?'':' · '+percent+'%')+'</div>'
        + '<div class="dc-assign-meta">'+esc(DIE_LANG.doc_assign_pending)+': '+(Number(t.submissions_pending)||0)+'</div>'
        + '<div class="dc-assign-meta">'+esc(DIE_LANG.doc_assign_own.replace('{$a}',G('flt_range').value))+': '+(Number(t.graded)||0)+'</div>'
        + '<details><summary>'+esc(DIE_LANG.doc_assign_view)+' ('+total+')</summary><ul class="dc-assign-list">';
    assignments.forEach(function(a){
        html+='<li><strong>'+esc(a.name)+'</strong><div class="dc-assign-meta">'+courseLink(a.courseid,a.coursename,false)
            +' · '+esc(a.offline?DIE_LANG.doc_assign_offline:(a.team?DIE_LANG.doc_assign_team:DIE_LANG.doc_assign_individual))+'</div>'
            +'<div class="dc-assign-meta">'+esc(assignmentCoverage(a.corrected,a.submitted))+'</div>'
            +'<div class="dc-assign-meta">'+esc(DIE_LANG.doc_assign_pending)+': '+a.pending+' · '
            +esc(assignmentLevel(a.level))+(a.percent===null?'':' ('+a.percent+'%)')+'</div></li>';
    });
    return html+'</ul></details></div>';
}

function renderDoc(){
    var q = (G('srch_doc') ? G('srch_doc').value : '').toLowerCase().trim();
    var rows = _TEACHERS.filter(function(t){
        if (_DF === 'act'  && !hasActivity(t)) return false;
        if (_DF === 'idle' && hasActivity(t))  return false;
        if (q && (t.fullname+' '+t.email+' '+(t.courses||[]).join(' ')).toLowerCase().indexOf(q) < 0) return false;
        return true;
    });
    rows.sort(function(a,b){
        var x = a[_SORTK], y = b[_SORTK];
        if (typeof x === 'string') return _SORTD * x.localeCompare(y);
        return _SORTD * ((x||0) - (y||0));
    });
    G('doc_count').textContent = (q || _DF) ? dieText('filtered_count',{shown:rows.length,total:_TEACHERS.length}) : '\u00B7 '+_TEACHERS.length;

    var html = '';
    rows.forEach(function(t,i){
        var pct = Math.min(100,Math.round((t.score/(t.score_max||SCORE_RULES.maximum))*100));
        var level=scoreLevel(t);
        var scoreCls = level==='high'?'dc-score-hi':(level==='moderate'?'dc-score-md':'dc-score-lo');
        var barColor = level==='high'?'#16a34a':(level==='moderate'?'#d97706':(t.score>0?'#94a3b8':'#e2e8f0'));
        var init=(t.fullname||'?').split(' ').map(function(w){return w[0];}).join('').substring(0,2).toUpperCase();
        function m(v, good){ return '<span class="dc-m'+(v>0?'':' z')+'"'+(good&&v>0?' style="color:#16a34a"':'')+'>'+v+'</span>'; }
        html += '<tr>'
            + '<td style="color:#94a3b8;font-size:11px">'+(i+1)+'</td>'
            + '<td><div style="display:flex;align-items:center;gap:10px"><div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#fff;flex-shrink:0;background:'+avColor(t.fullname)+'">'+init+'</div><div style="min-width:0"><div style="font-weight:600;color:#0f172a">'+esc(t.fullname)+'</div><div style="font-size:11px;color:#94a3b8">'+esc(t.email)+'</div></div></div></td>'
            + '<td class="dc-course-cell">'+courseCell(t)+'</td>'
            + '<td class="num">'+m(t.activities)+'</td>'
            + '<td class="num dc-hide-sm">'+m(t.resources)+'</td>'
            + '<td class="num">'+assignmentCell(t)+'</td>'
            + '<td class="num">'+forumCell(t)+'</td>'
            + '<td class="num dc-hide-sm"><span class="dc-la '+laCls(t.last_ts)+'">'+esc(t.last_access||DIE_LANG.risk_no_access_record)+'</span></td>'
            + '<td class="num dc-hide-sm"><div style="height:5px;border-radius:3px;background:#f1f5f9;width:60px;overflow:hidden;margin:0 auto"><div class="die-bar-fill" style="height:100%;border-radius:3px;width:'+pct+'%;background:'+barColor+'"></div></div></td>'
            + '<td class="num"><div style="display:flex;flex-direction:column;align-items:center;gap:3px"><span class="dc-score '+scoreCls+'">'+Number(t.score)+'</span>'
            + '<span class="dc-pill '+(level==='high'?'dc-pill-g':(level==='moderate'?'dc-pill-a':(level==='low'?'dc-pill-r':'')))+'">'+esc(DIE_LANG['score_level_'+level])+'</span>'
            + '<button type="button" class="dc-score-open" onclick="openScore('+Number(t.userid)+')">'+esc(DIE_LANG.score_view)+'</button>'
            + '</div></td>'
            + '</tr>';
    });
    G('doc_tbody').innerHTML = html;
}

function avColor(name){
    var palette = ['#0ea5e9','#8b5cf6','#ec4899','#f59e0b','#10b981','#ef4444','#6366f1','#14b8a6'];
    var h = 0; name = name || '?';
    for (var i=0; i<name.length; i++) h = (h*31 + name.charCodeAt(i)) >>> 0;
    return palette[h % palette.length];
}

function copyDocEmails(){
    var q = (G('srch_doc') ? G('srch_doc').value : '').toLowerCase().trim();
    var emails = {};
    _TEACHERS.forEach(function(t){
        if (_DF === 'act'  && !hasActivity(t)) return;
        if (_DF === 'idle' && hasActivity(t))  return;
        if (q && (t.fullname+' '+t.email+' '+(t.courses||[]).join(' ')).toLowerCase().indexOf(q) < 0) return;
        emails[t.email] = 1;
    });
    var list = Object.keys(emails).join('; ');
    if (!list) { if(window.dieToast) dieToast(DIE_LANG.sem_emails,'info'); return; }
    function done(){ if(window.dieToast) dieToast(dieEmailsCopiados(Object.keys(emails).length),'ok'); }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(list).then(done).catch(function(){ window.prompt(DIE_LANG.copy_emails_prompt, list); });
    } else { window.prompt(DIE_LANG.copy_emails_prompt, list); }
}

function exportXls() {
    var pid=document.getElementById('flt_period').value;
    var catid=document.getElementById('flt_cat').value;
    var cid=(window.DieCourseSearch && DieCourseSearch.getSelectedId()) || '';
    var range=document.getElementById('flt_range').value;
    window.open(XLS+'?sesskey='+SK+'&period='+pid+'&catid='+catid+'&courseid='+cid+'&range='+range+'&descendants='+filters.scope().descendants,'_blank');
}

function show() { document.getElementById('doc_results').style.display='block'; document.getElementById('doc_empty').style.display='none'; document.getElementById('doc_table_wrap').style.display='none'; }
function hide() { document.getElementById('doc_results').style.display='none'; }
function loading(v) { document.getElementById('doc_loading').style.display=v?'block':'none'; }
function esc(s) { var d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }

filters=LearningAnalyticsFilters.create({url:AX,sesskey:SK,withCourses:true,
  onReset:resetData,onChange:loadData,onLoading:function(){show();loading(true);},
  onEmpty:function(){show();loading(false);render([]);}
});
filters.start();
</script>

  <?php \local_mulima_analytics\local\branding::render_footer(); ?>
  </main><!-- content -->
</div><!-- main -->
</div><!-- #die-root -->
<?php
echo $OUTPUT->footer();
?>
