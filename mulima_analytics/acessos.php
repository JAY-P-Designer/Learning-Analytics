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
 * Activity access report.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
\local_mulima_analytics\local\access::require_report('accesses');
\local_mulima_analytics\local\page::setup('acessos.php', get_string('title_acessos', 'local_mulima_analytics'));

$SK  = sesskey();
$AX  = (new moodle_url('/local/mulima_analytics/acessos_ajax.php'))->out(false);
$XLS = (new moodle_url('/local/mulima_analytics/acessos_export.php'))->out(false);

$root_cats = $DB->get_records_select('course_categories', 'depth = 1', [], 'sortorder', 'id,name');

echo $OUTPUT->header();
$DIE_PAGE     = 'acessos';
$DIE_TITLE    = get_string('title_acessos', 'local_mulima_analytics');
$DIE_SUBTITLE = get_string('subtitle_acessos', 'local_mulima_analytics');
require_once(__DIR__ . '/die_layout.php');
?>

<!-- ── FILTERS ─────────────────────────────────────────────────── -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm mb-4">
  <div class="flex items-center gap-2 px-4 py-2.5 bg-slate-50 border-b border-slate-100 rounded-t-xl text-[10px] font-bold text-slate-500 uppercase tracking-wider">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
    <?php echo s(get_string('filtros', 'local_mulima_analytics')); ?>
  </div>
  <div class="die-filters p-4" id="access_filters">
    <div class="flex flex-col gap-1.5 flex-1 min-w-36">
      <label for="sel_period" class="text-[10px] font-bold text-slate-500 uppercase tracking-wide"><?php echo s(get_string('periodo_execucao', 'local_mulima_analytics')); ?></label>
      <select class="die-select w-full text-sm px-3 py-2 border border-slate-200 rounded-lg bg-white outline-none" id="sel_period" onchange="onPeriod()">
        <option value=""></option>
        <?php foreach ($root_cats as $rc): ?>
        <option value="<?php echo (int)$rc->id; ?>"><?php echo s($rc->name); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex flex-col gap-1.5 flex-1 min-w-36">
      <span id="access_category_label" class="text-[10px] font-bold text-slate-500 uppercase tracking-wide"><?php echo s(get_string('subcategoria', 'local_mulima_analytics')); ?></span>
      <div id="sel_cat_tree" class="la-category-tree" role="group" aria-labelledby="access_category_label"></div>
      <input type="hidden" id="sel_cat" value="">
    </div>
    <div class="flex flex-col flex-1 min-w-36">
      <div id="dcs_container"></div>
    </div>
  </div>
</div>
<div id="access_error" role="alert" style="display:none;margin:0 0 16px;padding:12px 16px;border:1px solid #fca5a5;border-radius:8px;background:#fef2f2;color:#991b1b">
  <span id="access_error_text"></span>
  <button type="button" id="access_retry" class="btn btn-secondary btn-sm"><?php echo s(get_string('retry', 'local_mulima_analytics')); ?></button>
</div>

<style>
/* acessos.php - mobile fixes */
#div_stats .grid > div { min-width: 0; }
#access_filters > div { min-width:0; }
#access_filters .la-category-tree { grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); }
#access_filters select { min-height:38px; }
@media (max-width: 640px) {
  .col-bar { display: none !important; }
  #div_stats .text-2xl { font-size: 1.35rem; }
  #modal_bg > div { width: 100vw !important; max-width: 100vw !important; max-height: 94vh !important; border-radius: 14px 14px 0 0 !important; top: auto !important; bottom: 0 !important; left: 0 !important; transform: none !important; }
}
</style>
<!-- ── STATS ───────────────────────────────────────────────────── -->
<div id="div_stats" style="display:none;margin-bottom:16px">
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 w-full">
    <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-start gap-3 shadow-sm">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#eff6ff">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 12h6M9 15h4"/></svg>
      </div>
      <div class="min-w-0 flex-1"><div class="text-2xl font-extrabold leading-none" id="st_act">-</div><div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mt-1 break-words"><?php echo s(get_string('actividades', 'local_mulima_analytics')); ?></div></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-start gap-3 shadow-sm">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#fef3c7">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
      </div>
      <div class="min-w-0 flex-1"><div class="text-2xl font-extrabold leading-none" id="st_ev">-</div><div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mt-1 break-words"><?php echo s(get_string('visualizacoes', 'local_mulima_analytics')); ?></div></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-start gap-3 shadow-sm col-span-2 sm:col-span-1">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#dcfce7">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
      </div>
      <div style="flex:1">
        <div style="display:flex;justify-content:space-between;align-items:baseline">
          <div class="text-2xl font-extrabold leading-none"><span id="st_stu">-</span><span style="font-size:13px;font-weight:400;color:#64748b"> / <span id="st_enr">-</span></span></div>
          <span style="font-size:13px;font-weight:800;color:var(--die-acc)" id="st_pct">-</span>
        </div>
        <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mt-1 break-words"><?php echo s(get_string('access_enrolled', 'local_mulima_analytics')); ?></div>
        <div style="height:5px;background:#e2e8f0;border-radius:3px;overflow:hidden;margin-top:5px"><div id="st_bar" style="height:100%;border-radius:3px;width:0%;transition:width .6s"></div></div>
      </div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-start gap-3 shadow-sm">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#fce7f3">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#be185d" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      </div>
      <div class="min-w-0 flex-1"><div class="text-2xl font-extrabold leading-none" id="st_avg">-</div><div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mt-1 break-words"><?php echo s(get_string('average_per_student', 'local_mulima_analytics')); ?></div></div>
    </div>
  </div>
</div>

<!-- ── PANORAMIC: all courses ──────────────────────────────────── -->
<div id="div_courses" style="display:none;margin-bottom:16px">
  <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="flex items-center justify-between gap-2 px-4 py-2.5 bg-slate-50 border-b border-slate-100 flex-wrap gap-2">
      <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-2">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg><?php echo s(get_string('todas_disciplinas_nivel', 'local_mulima_analytics')); ?></div>
      <div style="display:flex;gap:8px;align-items:center">
        <div style="position:relative">
          <svg style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#94a3b8" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="srch_courses" placeholder="<?php echo s(get_string('search', 'local_mulima_analytics')); ?>" oninput="renderCourses()" style="padding:6px 10px 6px 28px;border:1px solid #e2e8f0;border-radius:7px;font-size:11px;width:160px;outline:none">
        </div>
        <button id="btn_xls_all" onclick="exportXlsAll()" style="display:none;align-items:center;gap:5px;padding:6px 12px;border-radius:7px;font-size:11px;font-weight:700;background:#16a34a;color:#fff;border:none;cursor:pointer">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg><?php echo s(get_string('export', 'local_mulima_analytics')); ?></button>
      </div>
    </div>
    <div id="div_courses_loading" style="display:none;text-align:center;padding:32px;color:#94a3b8">
      <div class="die-spin" style="width:20px;height:20px;margin:0 auto 10px"></div>
      <div style="font-size:12px"><?php echo s(get_string('loading_all_courses', 'local_mulima_analytics')); ?></div>
    </div>
    <div id="div_courses_empty" style="display:none;text-align:center;padding:40px;color:#94a3b8;font-size:13px"><?php echo s(get_string('no_view_data_period', 'local_mulima_analytics')); ?></div>
    <div id="div_courses_tbl" style="overflow-x:auto;display:none">
      <table style="width:100%;border-collapse:collapse;font-size:12px">
        <thead>
          <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0">
            <th style="padding:9px 14px;text-align:left;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px">#</th>
            <th style="padding:9px 14px;text-align:left;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px"><?php echo s(get_string('disciplina', 'local_mulima_analytics')); ?></th>
            <th style="padding:9px 14px;text-align:left;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;display:none" class="sm-show"><?php echo s(get_string('categoria', 'local_mulima_analytics')); ?></th>
            <th style="padding:9px 14px;text-align:center;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px"><?php echo s(get_string('estudantes', 'local_mulima_analytics')); ?></th>
            <th style="padding:9px 14px;text-align:center;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px"><?php echo s(get_string('visualizacoes', 'local_mulima_analytics')); ?></th>
            <th class="col-bar" style="padding:9px 14px;width:90px;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px"><?php echo s(get_string('bar', 'local_mulima_analytics')); ?></th>
            <th style="padding:9px 14px;text-align:center;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px"><?php echo s(get_string('coverage_short', 'local_mulima_analytics')); ?></th>
            <th style="padding:9px 14px;text-align:center;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px"></th>
          </tr>
        </thead>
        <tbody id="courses_body"></tbody>
      </table>
      <div style="padding:8px 14px;font-size:11px;color:#94a3b8;border-top:1px solid #f1f5f9" id="courses_count"></div>
    </div>
  </div>
</div>

<!-- ── ACTIVITIES TABLE ────────────────────────────────────────── -->
<div id="div_table" style="display:none">
  <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="flex items-center justify-between gap-2 px-4 py-2.5 bg-slate-50 border-b border-slate-100 flex-wrap" style="gap:8px">
      <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-2">
        <button id="btn_back" onclick="backToAll()" style="display:none;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;background:transparent;border:1px solid #e2e8f0;color:#64748b;font-size:10px;font-weight:600;cursor:pointer">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg><?php echo s(get_string('todos', 'local_mulima_analytics')); ?></button>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 12h6M9 15h4"/></svg><?php echo s(get_string('actividades', 'local_mulima_analytics')); ?><span id="cur_course" style="display:none;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;background:rgba(var(--die-acc-rgb),.1);color:var(--die-acc);font-size:10px;font-weight:700;text-transform:none;letter-spacing:0;max-width:320px">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
          <span id="cur_course_name" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span>
        </span>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <div style="position:relative">
          <svg style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#94a3b8" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="srch_act" placeholder="<?php echo s(get_string('filter', 'local_mulima_analytics')); ?>" oninput="renderActs()" style="padding:6px 10px 6px 28px;border:1px solid #e2e8f0;border-radius:7px;font-size:11px;width:150px;outline:none">
        </div>
        <select id="flt_type" onchange="renderActs()" style="padding:6px 10px;border:1px solid #e2e8f0;border-radius:7px;font-size:11px;background:#fff;outline:none;max-width:130px">
          <option value=""><?php echo s(get_string('all_types', 'local_mulima_analytics')); ?></option>
          <option value="assign"><?php echo s(get_string('trabalhos', 'local_mulima_analytics')); ?></option>
          <option value="quiz"><?php echo s(get_string('activity_questionnaires', 'local_mulima_analytics')); ?></option>
          <option value="resource"><?php echo s(get_string('activity_files', 'local_mulima_analytics')); ?></option>
          <option value="forum"><?php echo s(get_string('activity_forums', 'local_mulima_analytics')); ?></option>
          <option value="url">URLs</option>
          <option value="page"><?php echo s(get_string('activity_pages', 'local_mulima_analytics')); ?></option>
        </select>
        <button id="btn_xls" onclick="exportXls()" style="display:none;align-items:center;gap:5px;padding:6px 12px;border-radius:7px;font-size:11px;font-weight:700;background:#16a34a;color:#fff;border:none;cursor:pointer">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg><?php echo s(get_string('export', 'local_mulima_analytics')); ?></button>
      </div>
    </div>
    <div id="div_loading" style="display:none;text-align:center;padding:32px;color:#94a3b8">
      <div class="die-spin" style="width:20px;height:20px;margin:0 auto 10px"></div>
      <div style="font-size:12px"><?php echo s(get_string('a_carregar', 'local_mulima_analytics')); ?></div>
    </div>
    <div id="div_empty" style="display:none;text-align:center;padding:40px;color:#94a3b8">
      <div style="font-size:14px;font-weight:600;margin-bottom:6px"><?php echo s(get_string('no_view_data', 'local_mulima_analytics')); ?></div>
      <div style="font-size:12px"><?php echo s(get_string('no_interaction_log', 'local_mulima_analytics')); ?></div>
    </div>
    <div id="div_tbl" style="overflow-x:auto;display:none">
      <table style="width:100%;border-collapse:collapse;font-size:12px" id="ac_main_table">
        <thead>
          <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0">
            <th style="padding:9px 14px;text-align:left;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;width:32px">#</th>
            <th style="padding:9px 14px;text-align:left;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase"><?php echo s(get_string('actividade', 'local_mulima_analytics')); ?></th>
            <th style="padding:9px 14px;text-align:left;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;width:110px"><?php echo s(get_string('tipo', 'local_mulima_analytics')); ?></th>
            <th class="ac-th-s" data-col="unique_students" onclick="sortActs(this)" style="padding:9px 14px;text-align:center;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;cursor:pointer"><?php echo s(get_string('users', 'local_mulima_analytics')); ?></th>
            <th class="ac-th-s desc" data-col="total_views" onclick="sortActs(this)" style="padding:9px 14px;text-align:center;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;cursor:pointer"><?php echo s(get_string('visualizacoes', 'local_mulima_analytics')); ?></th>
            <th class="col-bar" style="padding:9px 14px;width:76px;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase"><?php echo s(get_string('bar', 'local_mulima_analytics')); ?></th>
            <th style="padding:9px 14px;text-align:center;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;width:70px"><?php echo s(get_string('coverage_short', 'local_mulima_analytics')); ?></th>
            <th style="width:70px"></th>
          </tr>
        </thead>
        <tbody id="act_body"></tbody>
      </table>
      <div style="padding:8px 14px;font-size:11px;color:#94a3b8;border-top:1px solid #f1f5f9" id="act_count"></div>
    </div>
  </div>
</div>

<!-- ── MODAL ───────────────────────────────────────────────────── -->
<div id="modal_bg" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9990;backdrop-filter:blur(6px)" onclick="if(event.target===this)closeModal()">
  <div style="background:#fff;border-radius:16px;width:840px;max-width:95vw;max-height:88vh;display:flex;flex-direction:column;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);box-shadow:0 24px 64px rgba(0,0,0,.18)">
    <div style="padding:16px 22px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:12px">
      <div style="flex:1;min-width:0">
        <div style="font-size:14px;font-weight:800;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" id="m_title"></div>
        <div style="font-size:11px;color:#94a3b8;margin-top:2px" id="m_sub"></div>
      </div>
      <button onclick="closeModal()" style="width:30px;height:30px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:7px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#64748b">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div style="overflow-y:auto;padding:18px 22px;flex:1" id="m_body">
      <div id="m_error" role="alert" style="display:none;padding:12px;background:#fef2f2;color:#991b1b;border-radius:8px">
        <span id="m_error_text"></span>
        <button type="button" id="m_retry" class="btn btn-secondary btn-sm"><?php echo s(get_string('retry', 'local_mulima_analytics')); ?></button>
      </div>
      <div id="m_loading" style="text-align:center;padding:24px;color:#94a3b8">
        <div class="die-spin" style="width:20px;height:20px;margin:0 auto 10px"></div>
      </div>
      <div id="m_content" style="display:none">
        <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap">
          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 16px;flex:1;min-width:90px"><div style="font-size:20px;font-weight:800" id="m_s_stu">-</div><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.4px;margin-top:2px"><?php echo s(get_string('estudantes', 'local_mulima_analytics')); ?></div></div>
          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 16px;flex:1;min-width:90px"><div style="font-size:20px;font-weight:800" id="m_s_ev">-</div><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.4px;margin-top:2px"><?php echo s(get_string('visualizacoes', 'local_mulima_analytics')); ?></div></div>
          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 16px;flex:1;min-width:90px"><div style="font-size:20px;font-weight:800;color:var(--die-acc)" id="m_s_avg">-</div><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.4px;margin-top:2px"><?php echo s(get_string('average_per_student', 'local_mulima_analytics')); ?></div></div>
          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 16px;flex:1;min-width:90px"><div style="font-size:15px;font-weight:800" id="m_s_first">-</div><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.4px;margin-top:2px"><?php echo s(get_string('primeiro_acesso', 'local_mulima_analytics')); ?></div></div>
        </div>
        <div style="margin-bottom:10px;position:relative">
          <svg style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#94a3b8" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="m_srch" placeholder="<?php echo s(get_string('search_student', 'local_mulima_analytics')); ?>" oninput="renderStu()" style="width:100%;padding:8px 10px 8px 28px;border:1px solid #e2e8f0;border-radius:7px;font-size:12px;outline:none">
        </div>
        <div style="overflow-y:auto;max-height:300px">
          <table style="width:100%;border-collapse:collapse;font-size:12px">
            <thead style="position:sticky;top:0">
              <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0">
                <th style="padding:8px 12px;text-align:left;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;width:32px">#</th>
                <th style="padding:8px 12px;text-align:left;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase"><?php echo s(get_string('estudante', 'local_mulima_analytics')); ?></th>
                <th style="padding:8px 12px;text-align:center;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;width:76px"><?php echo s(get_string('visualizacoes', 'local_mulima_analytics')); ?></th>
                <th style="width:70px"></th>
                <th style="padding:8px 12px;text-align:left;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;width:140px"><?php echo s(get_string('first', 'local_mulima_analytics')); ?></th>
                <th style="padding:8px 12px;text-align:left;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;width:140px"><?php echo s(get_string('last', 'local_mulima_analytics')); ?></th>
              </tr>
            </thead>
            <tbody id="m_body_tbl"></tbody>
          </table>
        </div>
      </div>
      <div id="m_empty" style="display:none;text-align:center;padding:28px;color:#94a3b8;font-size:12px"><?php echo s(get_string('no_activity_access', 'local_mulima_analytics')); ?></div>
    </div>
    <div style="padding:12px 22px;border-top:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:#f8fafc">
      <div style="font-size:11px;color:#94a3b8" id="m_info"></div>
      <div style="display:flex;gap:8px">
        <button onclick="exportDtl()" style="display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:7px;font-size:11px;font-weight:700;background:#16a34a;color:#fff;border:none;cursor:pointer">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg><?php echo s(get_string('exportar_xlsx', 'local_mulima_analytics')); ?></button>
        <button onclick="closeModal()" style="padding:7px 14px;border-radius:7px;font-size:11px;font-weight:600;background:transparent;border:1px solid #e2e8f0;color:#64748b;cursor:pointer"><?php echo s(get_string('close', 'local_mulima_analytics')); ?></button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
'use strict';
var AX='<?php echo $AX; ?>', SK='<?php echo $SK; ?>', XLS='<?php echo $XLS; ?>';
var CID=0, CNAME='', CMID=0, REQUESTS={}, PANORAMA_TIMER=null, DESCENDANTS=0, COURSES_READY=false;
var ACTS=[], STUS=[], ALL_COURSES=[];
var SORT={col:'total_views',dir:'desc'}, MAXV=1;
var COLORS=['#0d9488','#2563eb','#7c3aed','#d97706','#dc2626','#16a34a','#0891b2','#be185d'];

function G(id){return document.getElementById(id);}
function show(id,d){var e=G(id);if(e)e.style.display=d||'block';}
function hide(id){var e=G(id);if(e)e.style.display='none';}
function fmt(n){return Number(n||0).toLocaleString(DIE_LANG.locale);}
function esc(s){var d=document.createElement('div');d.textContent=String(s||'');return d.innerHTML;}

// Cancelled or superseded requests must never update the current selection.
function cancelRequest(key){
  var request=REQUESTS[key];
  if(request){delete REQUESTS[key];clearTimeout(request.timer);request.controller.abort();}
}
function clearError(){['access','m'].forEach(function(prefix){hide(prefix+'_error');G(prefix+'_retry').onclick=null;});}
function gfetch(url,cb,eb,key){
  key=key||new URL(url,window.location.href).searchParams.get('op');
  cancelRequest(key);
  var request={controller:new AbortController(),timedout:false};
  REQUESTS[key]=request;
  request.timer=setTimeout(function(){request.timedout=true;request.controller.abort();},60000);
  fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'},signal:request.controller.signal})
    .then(function(r){
      if(!r.ok||r.redirected)throw new Error(DIE_LANG.request_failed);
      return r.json();
    }).then(function(d){
      if(REQUESTS[key]!==request)return;
      if(!d||d.ok!==true)throw new Error(d&&d.error||DIE_LANG.invalid_response);
      cb(d);
    }).catch(function(e){
      if(REQUESTS[key]!==request)return;
      if(eb)eb();
      var prefix=key==='detail'?'m':'access';
      G(prefix+'_error_text').textContent=request.timedout?DIE_LANG.request_too_long:
        (e instanceof SyntaxError?DIE_LANG.invalid_json_response:e.message);
      G(prefix+'_retry').onclick=function(){hide(prefix+'_error');gfetch(url,cb,eb,key);};
      show(prefix+'_error');
    }).finally(function(){
      clearTimeout(request.timer);
      if(REQUESTS[key]===request)delete REQUESTS[key];
    });
}

// Blank category selections show only courses stored directly at the parent.
// Including descendants requires an explicit "Todas neste nível" selection.
function categoryBox(level,text){
  var box=document.createElement('div');box.className='la-category-level';
  var label=document.createElement('label'),select=document.createElement('select');
  select.id='access_category_'+(level+1);select.className='die-select';select.disabled=true;
  label.htmlFor=select.id;label.textContent=DIE_LANG.categoria_nivel.replace('{$a}',level+1);
  select.add(new Option(text,''));box.appendChild(label);box.appendChild(select);G('sel_cat_tree').appendChild(box);
  return box;
}
function loadCategories(parent,level,period){
  var box=categoryBox(level,DIE_LANG.a_carregar),select=box.querySelector('select');
  gfetch(AX+'?op=cats&sesskey='+SK+'&period='+encodeURIComponent(period)+'&parent='+encodeURIComponent(parent),function(d){
    var cats=d.cats||[];
    if(!cats.length){
      if(level){box.remove();}else{select.options[0].textContent='';}
      return;
    }
    select.innerHTML='';select.add(new Option('',''));
    select.add(new Option(DIE_LANG.todas_neste_nivel,String(parent)));
    cats.forEach(function(c){select.add(new Option(c.name+' ('+Number(c.count||0)+')',String(c.id)));});
    select.disabled=false;
    select.onchange=function(){
      cancelRequest('cats');
      while(box.nextSibling)box.nextSibling.remove();
      var chosen=select.value||String(parent);
      G('sel_cat').value=chosen===String(period)?'':chosen;
      DESCENDANTS=select.value===String(parent)?1:0;
      onCat();
      var selected=cats.find(function(c){return String(c.id)===select.value;});
      if(selected&&selected.haschildren!==false)loadCategories(selected.id,level+1,period);
    };
  },function(){select.options[0].textContent=DIE_LANG.erro_carregar;});
}
function onPeriod(){
  cancelRequest('cats');cancelRequest('courses');clearError();
  var pid=G('sel_period').value;
  DESCENDANTS=0;COURSES_READY=false;
  G('sel_cat').value='';G('sel_cat_tree').innerHTML='';
  DieCourseSearch.disable('');resetAll();
  if(!pid){categoryBox(0,'');return;}
  loadCategories(pid,0,pid);
  loadCourses(pid,'');
}
function onCat(){
  cancelRequest('courses');clearError();
  var pid=G('sel_period').value, cat=G('sel_cat').value;
  resetAll();
  if(pid)loadCourses(pid,cat);
}
function loadCourses(pid, catid){
  COURSES_READY=false;DieCourseSearch.setLoading();
  gfetch(AX+'?op=courses&sesskey='+SK+'&period='+encodeURIComponent(pid)+'&descendants='+DESCENDANTS+(catid?'&catid='+encodeURIComponent(catid):''), function(d){
    var list = (d.courses||[]).map(function(co){ return {id:co.id, name:co.name, short:co.short||'', cat:co.cat||''}; });
    COURSES_READY=list.length>0;
    if(!COURSES_READY){DieCourseSearch.disable('');return;}
    // setList invokes onAll once for a non-empty list; do not request the report twice.
    DieCourseSearch.setList(list);
  }, function(){DieCourseSearch.disable(DIE_LANG.erro_carregar);});
}
window.onPeriod=onPeriod;window.onCat=onCat;

function queuePanorama(){
  resetAll();
  var pid=G('sel_period').value,cat=G('sel_cat').value;
  if(pid&&COURSES_READY){
    show('div_courses');show('div_courses_loading');hide('div_courses_empty');hide('div_courses_tbl');
    PANORAMA_TIMER=setTimeout(function(){loadAllCourses(pid,cat);},180);
  }
}

// ── Panoramic ───────────────────────────────────────────────────
function loadAllCourses(pid,catid){
  show('div_courses');show('div_courses_loading');
  ['div_courses_tbl','div_courses_empty','btn_xls_all','div_stats'].forEach(hide);
  hide('div_table');
  gfetch(AX+'?op=panoramic&sesskey='+SK+'&period='+encodeURIComponent(pid||'')+'&descendants='+DESCENDANTS+(catid?'&catid='+encodeURIComponent(catid):''),function(d){
    hide('div_courses_loading');
    if(!d.courses||!d.courses.length){show('div_courses_empty');return;}
    ALL_COURSES=d.courses;
    renderCourses();
    show('div_courses_tbl');
    show('btn_xls_all','inline-flex');
  },function(){hide('div_courses_loading');},'report');
}
function renderCourses(){
  var srch=(G('srch_courses').value||'').toLowerCase();
  var list=ALL_COURSES.filter(function(c){return !srch||c.name.toLowerCase().indexOf(srch)>=0||(c.cat||'').toLowerCase().indexOf(srch)>=0;});
  var maxV=Math.max.apply(null,list.map(function(c){return c.views;}))||1;
  var tb=G('courses_body');tb.innerHTML='';
  list.forEach(function(c,i){
    var pct=Math.round(c.views/maxV*100);
    var cov=c.enrolled?Math.round(c.students/c.enrolled*100):null;
    var covH=cov!==null?'<span style="font-size:11px;font-weight:700;color:'+(cov>=70?'#16a34a':cov>=40?'#d97706':'#dc2626')+'">'+cov+'%</span>':'<span style="color:#94a3b8">-</span>';
    tb.innerHTML+='<tr style="border-bottom:1px solid #f1f5f9">'
      +'<td style="padding:9px 14px;color:#94a3b8;font-size:11px">'+(i+1)+'</td>'
      +'<td style="padding:9px 14px"><div style="font-weight:600;font-size:12px">'+esc(c.name)+'</div><div style="font-size:10px;color:#94a3b8">'+esc(c.short||'')+'</div></td>'
      +'<td style="padding:9px 14px;font-size:11px;color:#64748b;display:none" class="sm-show">'+esc(c.cat||'')+'</td>'
      +'<td style="padding:9px 14px;text-align:center"><strong>'+c.students+'</strong><span style="font-size:10px;color:#94a3b8"> / '+c.enrolled+'</span></td>'
      +'<td style="padding:9px 14px;text-align:center"><strong style="color:var(--die-acc)">'+fmt(c.views)+'</strong></td>'
      +'<td class="col-bar" style="padding:9px 14px"><div style="height:5px;background:#f1f5f9;border-radius:3px;overflow:hidden;width:70px"><div style="height:100%;border-radius:3px;background:var(--die-acc);width:'+pct+'%"></div></div></td>'
      +'<td style="padding:9px 14px;text-align:center">'+covH+'</td>'
      +'<td style="padding:9px 14px;text-align:center"><button class="crs-ver" data-cid="'+c.id+'" style="padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;border:1px solid #e2e8f0;background:#fff;cursor:pointer;color:#475569">'+DIE_LANG.ver+'</button></td>'
      +'</tr>';
  });
  G('courses_count').textContent=dieText('courses_filtered_count',{shown:list.length,total:ALL_COURSES.length})+(srch?DIE_LANG.filtered_suffix:'');
}
window.renderCourses=renderCourses;
function drillCourse(id,name){clearError();resetAll();CID=id;CNAME=name;show('btn_back','inline-flex');loadActivities();}
window.drillCourse=drillCourse;
function backToAll(){clearError();DieCourseSearch.clearSilent();queuePanorama();}
window.backToAll=backToAll;

// ── Activities ──────────────────────────────────────────────────
function setCourseBadge(){var b=G('cur_course'),n=G('cur_course_name');if(!b)return;if(CID&&CNAME){n.textContent=CNAME;b.style.display='inline-flex';b.title=CNAME;}else{b.style.display='none';}}
function loadActivities(){setCourseBadge();
  show('div_table');show('div_loading');
  ['div_tbl','div_empty','btn_xls','div_stats'].forEach(hide);
  hide('div_courses');
  gfetch(AX+'?op=activities&sesskey='+SK+'&courseid='+CID,function(d){
    hide('div_loading');
    if(!d.activities||!d.activities.length){show('div_empty');return;}
    ACTS=d.activities;MAXV=Math.max.apply(null,ACTS.map(function(a){return a.total_views;}))||1;
    var st=d.stats||{},enr=+st.enrolled||0,acc=+st.students||0,pct=enr?Math.round(acc/enr*100):0;
    G('st_act').textContent=ACTS.length;G('st_ev').textContent=fmt(st.total_views||0);
    G('st_stu').textContent=acc;G('st_enr').textContent=enr;G('st_pct').textContent=pct+'%';
    G('st_avg').textContent=acc?(st.total_views/acc).toFixed(1):'0';
    var bar=G('st_bar');bar.style.width=pct+'%';bar.style.background=pct>=70?'#16a34a':pct>=40?'#d97706':'#dc2626';
    show('div_stats');
    renderActs();show('div_tbl');show('btn_xls','inline-flex');
  },function(){hide('div_loading');},'report');
}
function renderActs(){
  var srch=(G('srch_act').value||'').toLowerCase(),typ=G('flt_type').value;
  var list=ACTS.filter(function(a){return(!srch||(a.name.toLowerCase().indexOf(srch)>=0||modLabel(a.modname).toLowerCase().indexOf(srch)>=0))&&(!typ||a.modname===typ);});
  list.sort(function(a,b){var va=+(a[SORT.col]||0),vb=+(b[SORT.col]||0);return SORT.dir==='asc'?va-vb:vb-va;});
  var tb=G('act_body');tb.innerHTML='';
  list.forEach(function(a,i){
    var pct=Math.round(a.total_views/MAXV*100),cov=a.enrolled?Math.round(a.unique_students/a.enrolled*100):null;
    var cls=a.total_views===0?'#94a3b8':a.total_views===MAXV?'#15803d':'#1e40af';
    var bg=a.total_views===0?'#f1f5f9':a.total_views===MAXV?'#dcfce7':'#dbeafe';
    var ck=a.total_views>0?'class="ac-ver" data-cmid="'+a.cmid+'"':'';
    var covH=cov!==null?'<span style="font-size:11px;font-weight:700;color:'+(cov>=70?'#16a34a':cov>=40?'#d97706':'#dc2626')+'">'+cov+'%</span>':'<span style="color:#94a3b8">-</span>';
    tb.innerHTML+='<tr style="border-bottom:1px solid #f1f5f9">'
      +'<td style="padding:9px 14px;color:#94a3b8;font-size:11px">'+(i+1)+'</td>'
      +'<td style="padding:9px 14px"><div style="display:flex;align-items:center;gap:9px">'+modIcon(a.modname)+'<div><div style="font-weight:600">'+esc(a.name)+'</div><div style="font-size:10px;color:#94a3b8">'+esc(a.section_name||'')+'</div></div></div></td>'
      +'<td style="padding:9px 14px"><span style="padding:2px 8px;border-radius:4px;background:#f1f5f9;color:#64748b;font-size:10px">'+modLabel(a.modname)+'</span></td>'
      +'<td style="padding:9px 14px;text-align:center"><strong>'+a.unique_students+'</strong></td>'
      +'<td style="padding:9px 14px;text-align:center"><button '+ck+' style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:'+bg+';color:'+cls+';border:none;cursor:'+(a.total_views>0?'pointer':'default')+'">'+fmt(a.total_views)+'</button></td>'
      +'<td class="col-bar" style="padding:9px 14px"><div style="height:5px;background:#f1f5f9;border-radius:3px;overflow:hidden;width:70px"><div style="height:100%;border-radius:3px;background:var(--die-acc);width:'+pct+'%"></div></div></td>'
      +'<td style="padding:9px 14px;text-align:center">'+covH+'</td>'
      +'<td style="padding:9px 14px;text-align:center">'+(a.total_views>0?'<button class="ac-ver" data-cmid="'+a.cmid+'" style="padding:4px 9px;border-radius:6px;font-size:11px;border:1px solid #e2e8f0;background:#fff;cursor:pointer;color:#475569">'+DIE_LANG.ver+'</button>':'<span style="color:#94a3b8">-</span>')+'</td>'
      +'</tr>';
  });
  G('act_count').textContent=dieText('activities_filtered_count',{shown:list.length,total:ACTS.length})+(srch||typ?DIE_LANG.filtered_suffix:'');
}
window.renderActs=renderActs;
function sortActs(th){var col=th.getAttribute('data-col');SORT.dir=SORT.col===col?(SORT.dir==='asc'?'desc':'asc'):'desc';SORT.col=col;document.querySelectorAll('.ac-th-s').forEach(function(t){t.style.color='';});th.style.color='var(--die-acc)';renderActs();}

// ── Detail modal ────────────────────────────────────────────────
function openDetail(cmid,cmname){
  clearError();
  CMID=cmid;G('m_title').textContent=cmname;G('m_sub').textContent=CNAME;G('m_srch').value='';
  show('m_loading');hide('m_content');hide('m_empty');G('m_info').textContent='';
  show('modal_bg');document.body.style.overflow='hidden';
  gfetch(AX+'?op=detail&sesskey='+SK+'&cmid='+cmid+'&courseid='+CID,function(d){
    hide('m_loading');
    if(!d.students||!d.students.length){show('m_empty');return;}
    STUS=d.students;
    var tot=d.total_views||0,avg=STUS.length?(tot/STUS.length).toFixed(1):'0';
    G('m_s_stu').textContent=STUS.length;G('m_s_ev').textContent=fmt(tot);G('m_s_avg').textContent=avg;
    G('m_s_first').textContent=d.first_overall||'-';
    renderStu();show('m_content');
    G('m_info').textContent=DIE_LANG.since_platform_start;
  },function(){hide('m_loading');});
}
function renderStu(){
  var srch=(G('m_srch').value||'').toLowerCase();
  var list=STUS.filter(function(s){return !srch||s.fullname.toLowerCase().indexOf(srch)>=0||s.email.toLowerCase().indexOf(srch)>=0;});
  var mx=Math.max.apply(null,STUS.map(function(s){return s.views;}))||1;
  var tb=G('m_body_tbl');tb.innerHTML='';
  list.forEach(function(s,i){
    var init=(s.fullname||'?').split(' ').map(function(w){return w[0]||'';}).join('').substring(0,2).toUpperCase();
    var col=COLORS[i%COLORS.length],pct=Math.round(s.views/mx*100);
    tb.innerHTML+='<tr style="border-bottom:1px solid #f1f5f9">'
      +'<td style="padding:8px 12px;color:#94a3b8;font-size:11px">'+(i+1)+'</td>'
      +'<td style="padding:8px 12px"><div style="display:flex;align-items:center;gap:9px"><div style="width:28px;height:28px;border-radius:50%;background:'+col+';display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#fff;flex-shrink:0">'+esc(init)+'</div><div><div style="font-weight:600">'+esc(s.fullname)+'</div><div style="font-size:10px;color:#94a3b8">'+esc(s.email)+'</div></div></div></td>'
      +'<td style="padding:8px 12px;text-align:center"><strong style="font-size:14px;color:var(--die-acc)">'+s.views+'</strong></td>'
      +'<td style="padding:8px 12px"><div style="height:5px;background:#f1f5f9;border-radius:3px;overflow:hidden;width:60px"><div style="height:100%;border-radius:3px;background:'+col+';width:'+pct+'%"></div></div></td>'
      +'<td style="padding:8px 12px;font-size:11px;color:#64748b">'+esc(s.first_access)+'</td>'
      +'<td style="padding:8px 12px;font-size:11px;color:#64748b">'+esc(s.last_access)+'</td>'
      +'</tr>';
  });
}
window.renderStu=renderStu;
function closeModal(){cancelRequest('detail');G('modal_bg').style.display='none';document.body.style.overflow='';STUS=[];CMID=0;}
window.closeModal=closeModal;
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeModal();});

// ── Export ──────────────────────────────────────────────────────
function exportXls(){window.open(XLS+'?sesskey='+SK+'&op=activities&courseid='+CID,'_blank');}
function exportDtl(){window.open(XLS+'?sesskey='+SK+'&op=detail&cmid='+CMID+'&courseid='+CID,'_blank');}
function exportXlsAll(){var pid=G('sel_period').value,cat=G('sel_cat').value;window.open(XLS+'?sesskey='+SK+'&op=panoramic&period='+encodeURIComponent(pid||'')+'&descendants='+DESCENDANTS+(cat?'&catid='+encodeURIComponent(cat):''),'_blank');}
window.exportXls=exportXls;window.exportDtl=exportDtl;window.exportXlsAll=exportXlsAll;

// ── Missing exports + event delegation ─────────────────────────
window.openDetail=openDetail;window.sortActs=sortActs;
document.addEventListener('click',function(e){
  var b=e.target.closest('.ac-ver');
  if(b){var cm=+b.getAttribute('data-cmid');var a=null;for(var i=0;i<ACTS.length;i++){if(ACTS[i].cmid===cm){a=ACTS[i];break;}}openDetail(cm,a?a.name:'');return;}
  var cv=e.target.closest('.crs-ver');
  if(cv){var id=+cv.getAttribute('data-cid');var co=null;for(var j=0;j<ALL_COURSES.length;j++){if(+ALL_COURSES[j].id===id){co=ALL_COURSES[j];break;}}drillCourse(id,co?co.name:'');return;}
});

// ── Helpers ─────────────────────────────────────────────────────
function resetAll(){
  cancelRequest('report');closeModal();clearTimeout(PANORAMA_TIMER);
  CID=0;CNAME='';ACTS=[];ALL_COURSES=[];setCourseBadge();
  ['div_stats','div_courses','div_table','btn_xls','btn_xls_all'].forEach(hide);
  ['srch_courses','srch_act','flt_type'].forEach(function(id){G(id).value='';});
  G('courses_body').innerHTML='';G('act_body').innerHTML='';
  var bb=G('btn_back');if(bb)bb.style.display='none';
}
function modLabel(m){return DIE_LANG.module_names[m]||m;}
function modIcon(m){var c=({quiz:'#7c3aed',assign:'#2563eb',resource:'#dc2626',url:'#0891b2',page:'#16a34a',forum:'#d97706'})[m]||'#94a3b8';var p=({quiz:'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="12" x2="15" y2="12"/>',assign:'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/>',resource:'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><polyline points="14 2 14 8 20 8"/>',url:'<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/>',forum:'<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',page:'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><polyline points="14 2 14 8 20 8"/>'})[m]||'<rect x="3" y="3" width="18" height="18" rx="2"/>';return '<div style="width:28px;height:28px;border-radius:6px;background:'+c+'18;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="'+c+'" stroke-width="2">'+p+'</svg></div>';}
// Mount inside this closure: AX and SK are deliberately private to Acessos.
DieCourseSearch.mount('dcs_container',{
  onSelect:function(id,name){drillCourse(id,name);},
  onAll:function(){queuePanorama();}
});
G('dcs_inp').setAttribute('aria-label',DIE_LANG.disciplina);
onPeriod();
})();
</script>

  <?php \local_mulima_analytics\local\branding::render_footer(); ?>
  </main><!-- content -->
</div><!-- main -->
</div><!-- #die-root -->
<?php
echo $OUTPUT->footer();
?>
