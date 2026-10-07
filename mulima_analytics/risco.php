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
 * Student inactivity report.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
\local_mulima_analytics\local\access::require_report('risk');
\local_mulima_analytics\local\page::setup('risco.php', get_string('title_risco', 'local_mulima_analytics'));

$SK  = sesskey();
$AX  = (new moodle_url('/local/mulima_analytics/risco_ajax.php'))->out(false);
$XLS = (new moodle_url('/local/mulima_analytics/risco_export.php'))->out(false);
$MSG = (new moodle_url('/message/index.php'))->out(false);

$root_cats = $DB->get_records_select('course_categories', 'depth = 1', [], 'sortorder', 'id,name');

echo $OUTPUT->header();
?>
<?php
$DIE_PAGE     = 'risco';
$DIE_TITLE    = get_string('title_risco', 'local_mulima_analytics');
$DIE_SUBTITLE = get_string('subtitle_risco', 'local_mulima_analytics');
require_once(__DIR__.'/die_layout.php');
require_once(__DIR__.'/filter_controls.php');
?>

<div id="rsk">

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
      <div class="die-fg flex flex-col gap-1.5 flex-1 min-w-36">
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide"><?php echo s(get_string('scope_access_label', 'local_mulima_analytics')); ?></label>
        <select class="die-select w-full text-sm px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800" id="flt_scope" onchange="onFilter()">
          <option value="platform"><?php echo s(get_string('scope_platform_label', 'local_mulima_analytics')); ?></option>
          <option value="courses"><?php echo s(get_string('scope_courses_label', 'local_mulima_analytics')); ?></option>
        </select>
      </div>
      <div class="flex-1 min-w-44" id="dcs_container"></div>
      <div class="die-fg flex flex-col gap-1.5 flex-1 min-w-36" style="max-width:150px">
        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1.5"><?php echo s(get_string('no_access_more_than', 'local_mulima_analytics')); ?></label>
        <div style="display:flex;align-items:center;gap:6px">
          <input type="number" class="die-input w-full text-sm px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800 transition-colors" id="flt_days" value="7" min="1" max="365" onchange="onFilter()">
          <span style="font-size:13px;color:#64748b;white-space:nowrap"><?php echo s(get_string('days', 'local_mulima_analytics')); ?></span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Stats -->
<div id="rsk_stats" style="display:none">
  <div class="rk-management">
    <h2><?php echo s(get_string('risk_management_title', 'local_mulima_analytics')); ?></h2>
    <p><?php echo s(get_string('risk_management_help', 'local_mulima_analytics')); ?></p>
    <p id="rsk_scope_note"></p>
  </div>
  <div class="die-stats grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex items-start gap-3">
      <div class="die-stat-icon w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#fecaca">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#991b1b" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div class="rk-stat-body"><div class="die-stat-v text-2xl font-extrabold text-slate-900 leading-none" id="st_critical">-</div><div class="rk-count-unit"></div><div class="rk-stat-label"><?php echo s(get_string('risk_critical_label', 'local_mulima_analytics')); ?></div><div class="rk-stat-detail" id="st_critical_detail"><?php echo s(get_string('risk_critical_range', 'local_mulima_analytics')); ?></div></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex items-start gap-3">
      <div class="die-stat-icon w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#fef3c7">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      </div>
      <div class="rk-stat-body"><div class="die-stat-v text-2xl font-extrabold text-slate-900 leading-none" id="st_alert">-</div><div class="rk-count-unit"></div><div class="rk-stat-label"><?php echo s(get_string('risk_alert_label', 'local_mulima_analytics')); ?></div><div class="rk-stat-detail" id="st_alert_detail"><?php echo s(get_string('risk_alert_range', 'local_mulima_analytics')); ?></div></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex items-start gap-3">
      <div class="die-stat-icon w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#dbeafe">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" stroke-width="2"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
      </div>
      <div class="rk-stat-body"><div class="die-stat-v text-2xl font-extrabold text-slate-900 leading-none" id="st_warn">-</div><div class="rk-count-unit"></div><div class="rk-stat-label"><?php echo s(get_string('risk_warn_label', 'local_mulima_analytics')); ?></div><div class="rk-stat-detail" id="st_warn_detail"><?php echo s(get_string('risk_warn_range', 'local_mulima_analytics')); ?></div></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex items-start gap-3">
      <div class="die-stat-icon w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#dcfce7">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      </div>
      <div class="rk-stat-body"><div class="die-stat-v text-2xl font-extrabold text-slate-900 leading-none" id="st_total">-</div><div class="rk-count-unit"></div><div class="rk-stat-label"><?php echo s(get_string('risk_total_label', 'local_mulima_analytics')); ?></div><div class="rk-stat-detail" id="st_total_detail"></div></div>
    </div>
  </div>
</div>

<style>
/* ── Risco page styles ── */
#rsk .rk-management { margin:0 0 14px; padding:16px 18px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; }
#rsk .rk-management h2 { margin:0 0 5px; font-size:15px; line-height:1.4; font-weight:700; color:#0f172a; }
#rsk .rk-management p { margin:4px 0 0; font-size:12px; line-height:1.5; color:#475569; }
#rsk .rk-management #rsk_scope_note { color:#64748b; }
#rsk .rk-stat-body { min-width:0; }
#rsk .rk-count-unit { margin-top:4px; font-size:11px; line-height:1.4; color:#64748b; }
#rsk .rk-stat-label { margin-top:9px; font-size:12px; line-height:1.4; font-weight:700; color:#334155; }
#rsk .rk-stat-detail { margin-top:4px; font-size:11px; line-height:1.5; color:#64748b; overflow-wrap:anywhere; }
#rsk .rk-t { width:100%; border-collapse:collapse; }
#rsk .rk-t thead th { background:#f8fafc; color:#64748b; font-size:10px; font-weight:700;
  text-transform:uppercase; letter-spacing:.05em; padding:10px 14px; border-bottom:2px solid #e2e8f0;
  text-align:left; white-space:nowrap; position:sticky; top:0; z-index:2; }
#rsk .rk-t thead th.c { text-align:center; }
#rsk .rk-t tbody tr { border-bottom:1px solid #f1f5f9; transition:background .1s; }
#rsk .rk-t tbody tr:last-child { border-bottom:none; }
#rsk .rk-t tbody td { padding:10px 14px; vertical-align:middle; font-size:12px; }
#rsk .rk-lvl { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px;
  font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; white-space:nowrap; }
#rsk .rk-lvl::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }
#rsk .rk-lvl-c { background:#fee2e2; color:#b91c1c; }
#rsk .rk-lvl-a { background:#fef3c7; color:#b45309; }
#rsk .rk-lvl-w { background:#dbeafe; color:#1d4ed8; }
#rsk .rk-never { display:inline-block; padding:2px 8px; border-radius:6px; background:#fef2f2;
  color:#dc2626; font-size:11px; font-weight:600; }
#rsk .rk-chip { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:20px;
  font-size:11px; font-weight:600; cursor:pointer; border:1.5px solid #e2e8f0; background:#fff;
  color:#64748b; transition:all .15s; user-select:none; }
#rsk .rk-chip:hover { border-color:#cbd5e1; }
#rsk .rk-chip.on { border-color:var(--die-acc); background:rgba(var(--die-acc-rgb),.08); color:var(--die-acc); }
#rsk .rk-chip .n { font-size:10px; font-weight:800; padding:1px 6px; border-radius:10px; background:#f1f5f9; }
#rsk .rk-chip.on .n { background:rgba(var(--die-acc-rgb),.15); }
#rsk .rk-msg-btn { display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:7px;
  font-size:11px; font-weight:600; border:1px solid #e2e8f0; background:#fff; color:#475569;
  cursor:pointer; transition:all .15s; white-space:nowrap; }
#rsk .rk-msg-btn:hover { border-color:var(--die-acc); color:var(--die-acc); }
#rsk .rk-more { display:block; width:100%; padding:11px; border:none; border-top:1px solid #f1f5f9;
  background:#f8fafc; color:#475569; font-size:12px; font-weight:700; cursor:pointer; }
#rsk .rk-more:hover { background:#f1f5f9; }
@media (max-width:640px){ #rsk .rk-hide-sm { display:none !important; } }
</style>

<!-- Distribution bar -->
<div id="rsk_dist" class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 mb-4" style="display:none">
  <div class="flex items-center justify-between mb-2">
    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider"><?php echo s(get_string('distribuicao_nivel_risco', 'local_mulima_analytics')); ?></span>
    <span class="text-[11px] text-slate-400" id="rsk_dist_lbl"></span>
  </div>
  <div style="display:flex;height:10px;border-radius:6px;overflow:hidden;background:#f1f5f9">
    <div id="db_c" style="background:#dc2626;width:0%;transition:width .5s"></div>
    <div id="db_a" style="background:#f59e0b;width:0%;transition:width .5s"></div>
    <div id="db_w" style="background:#3b82f6;width:0%;transition:width .5s"></div>
  </div>
  <div style="display:flex;gap:16px;margin-top:8px;flex-wrap:wrap">
    <span style="font-size:11px;color:#64748b;display:flex;align-items:center;gap:5px"><span style="width:8px;height:8px;border-radius:2px;background:#dc2626;display:inline-block"></span><?php echo s(get_string('critico', 'local_mulima_analytics')); ?><strong id="dl_c"></strong></span>
    <span style="font-size:11px;color:#64748b;display:flex;align-items:center;gap:5px"><span style="width:8px;height:8px;border-radius:2px;background:#f59e0b;display:inline-block"></span><?php echo s(get_string('alerta', 'local_mulima_analytics')); ?><strong id="dl_a"></strong></span>
    <span style="font-size:11px;color:#64748b;display:flex;align-items:center;gap:5px"><span style="width:8px;height:8px;border-radius:2px;background:#3b82f6;display:inline-block"></span><?php echo s(get_string('aviso', 'local_mulima_analytics')); ?><strong id="dl_w"></strong></span>
  </div>
</div>

<!-- Results -->
<div class="die-card bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-4" id="rsk_results" style="display:none">
  <div class="die-card-head flex items-center justify-between gap-2 px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
    <span style="display:flex;align-items:center;gap:8px">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      <?php echo s(get_string('estudantes_sem_acesso', 'local_mulima_analytics')); ?>
      <span id="rsk_count" style="font-weight:600;color:#94a3b8;text-transform:none;letter-spacing:0"></span>
    </span>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <div style="position:relative">
        <svg style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#94a3b8" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="srch_rsk" placeholder="<?php echo s(get_string('search_student_course', 'local_mulima_analytics')); ?>" oninput="renderTable()" style="padding:6px 10px 6px 28px;border:1px solid #e2e8f0;border-radius:7px;font-size:11px;width:170px;outline:none;font-weight:400;text-transform:none;letter-spacing:0">
      </div>
      <button class="die-btn inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors cursor-pointer" id="btn_emails" onclick="copyEmails()" style="display:none">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><?php echo s(get_string('copiar_emails', 'local_mulima_analytics')); ?></button>
      <button class="die-btn inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold text-white bg-green-600 hover:bg-green-700 transition-colors cursor-pointer border-0" id="btn_xls" onclick="exportXls()" style="display:none">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg><?php echo s(get_string('exportar_xlsx', 'local_mulima_analytics')); ?></button>
      <button class="die-btn inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold text-white bg-teal-600 hover:bg-teal-700 transition-colors cursor-pointer border-0" id="btn_msg_all" onclick="msgAll()" style="display:none">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13.6 19.79 19.79 0 0 1 1.61 5a2 2 0 0 1 1.99-2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 10.9a16 16 0 0 0 6 6l.92-.92a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 18.92z"/></svg><?php echo s(get_string('contactar_todos', 'local_mulima_analytics')); ?></button>
    </div>
  </div>
  <div style="padding:0">
    <div id="rsk_loading" class="die-empty text-center py-12 px-6 text-slate-400" style="display:none"><div class="die-spin w-5 h-5 rounded-full"></div><br><?php echo s(get_string('loading_risk', 'local_mulima_analytics')); ?></div>
    <div id="rsk_good" class="die-empty text-center py-12 px-6 text-slate-400" style="display:none">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      <div style="font-size:16px;font-weight:700;margin-top:10px"><?php echo s(get_string('all_students_recent', 'local_mulima_analytics')); ?></div>
      <div style="font-size:13px;color:#94a3b8;margin-top:6px"><span id="good_days"></span></div>
    </div>
    <div id="rsk_chips" style="display:none;padding:10px 14px;border-bottom:1px solid #f1f5f9;gap:8px;flex-wrap:wrap">
      <span class="rk-chip on" data-lvl="" onclick="setLvl(this)"><?php echo s(get_string('todos', 'local_mulima_analytics')); ?> <span class="n" id="ch_t">0</span></span>
      <span class="rk-chip" data-lvl="c" onclick="setLvl(this)"><?php echo s(get_string('critico', 'local_mulima_analytics')); ?> <span class="n" id="ch_c">0</span></span>
      <span class="rk-chip" data-lvl="a" onclick="setLvl(this)"><?php echo s(get_string('alerta', 'local_mulima_analytics')); ?> <span class="n" id="ch_a">0</span></span>
      <span class="rk-chip" data-lvl="w" onclick="setLvl(this)"><?php echo s(get_string('aviso', 'local_mulima_analytics')); ?> <span class="n" id="ch_w">0</span></span>
    </div>
    <div id="rsk_table_wrap" style="display:none;overflow-x:auto">
      <table class="rk-t">
        <thead><tr>
          <th style="width:36px">#</th>
          <th><?php echo s(get_string('estudante', 'local_mulima_analytics')); ?></th>
          <th><?php echo s(get_string('disciplina', 'local_mulima_analytics')); ?></th>
          <th class="rk-hide-sm"><?php echo s(get_string('categoria', 'local_mulima_analytics')); ?></th>
          <th class="rk-hide-sm"><?php echo s(get_string('contacto', 'local_mulima_analytics')); ?></th>
          <th class="c rk-hide-sm"><?php echo s(get_string('ultimo_acesso', 'local_mulima_analytics')); ?></th>
          <th class="c"><?php echo s(get_string('sem_acesso', 'local_mulima_analytics')); ?></th>
          <th class="c" style="width:90px"><?php echo s(get_string('risk_level', 'local_mulima_analytics')); ?></th>
          <th class="c" style="width:110px"><?php echo s(get_string('accao', 'local_mulima_analytics')); ?></th>
        </tr></thead>
        <tbody id="rsk_tbody"></tbody>
      </table>
    </div>
  </div>
</div>

</div><!-- #rsk -->

<script>
var AX = '<?php echo $AX; ?>';

var SK = '<?php echo $SK; ?>';
var XLS = '<?php echo $XLS; ?>';
var MSG = '<?php echo $MSG; ?>';
var _msgIds = [];

var filters;
function G(id){return document.getElementById(id);}
function onPeriod(){filters.periodChanged();}
function onCat(){filters.categoryChanged();}
function onCourse(){filters.refresh();}
function onFilter(){G('flt_days').value=Math.max(1,Math.min(365,parseInt(G('flt_days').value)||7));filters.refresh();}
function resetData(){
  _STUDENTS=[];_msgIds=[];
  ['rsk_results','rsk_stats','rsk_dist','btn_xls','btn_msg_all','btn_emails','rsk_chips','rsk_table_wrap'].forEach(function(id){G(id).style.display='none';});
  G('rsk_tbody').innerHTML='';
}
function loadRisk(selected){
  var days=Math.max(1,Math.min(365,parseInt(G('flt_days').value)||7));
  G('flt_days').value=days;showLoading();
  filters.request('report','risk',{period:selected.period,catid:selected.catid,descendants:selected.descendants,courseid:selected.courseid,days:days,scope:G('flt_scope').value},function(d){
    hideLoading();renderRisk(d.students||[],d.stats,days);
  },hideLoading,{post:true});
}

var _STUDENTS = [], _LVL = '', _SHOWN = 100;

function renderRisk(students, stats, days) {
    _STUDENTS = students; _LVL = ''; _SHOWN = 100;
    var srch = G('srch_rsk'); if (srch) srch.value = '';
    var total = students.length;

    // Stat cards + percentages
    G('rsk_stats').style.display = 'block';
    G('st_critical').textContent = stats.critical;
    G('st_alert').textContent    = stats.alert;
    G('st_warn').textContent     = stats.warn;
    G('st_total').textContent    = total;
    var courseScope=G('flt_scope').value==='courses';
    var unit=courseScope?DIE_LANG.risk_unit_records:DIE_LANG.risk_unit_students;
    document.querySelectorAll('#rsk_stats .rk-count-unit').forEach(function(el){el.textContent=unit;});
    G('rsk_scope_note').textContent=courseScope?DIE_LANG.risk_scope_courses:DIE_LANG.risk_scope_platform;
    G('st_total_detail').textContent=DIE_LANG.risk_total_range.replace('{$a}',days);

    // Distribution bar
    var dist = G('rsk_dist');
    if (dist) {
        dist.style.display = total ? 'block' : 'none';
        if (total) {
            var pc = Math.round(stats.critical/total*100),
                pa = Math.round(stats.alert/total*100),
                pw = 100 - pc - pa;
            G('db_c').style.width = pc+'%'; G('db_a').style.width = pa+'%'; G('db_w').style.width = pw+'%';
            G('dl_c').textContent = stats.critical+' ('+pc+'%)';
            G('dl_a').textContent = stats.alert+' ('+pa+'%)';
            G('dl_w').textContent = stats.warn+' ('+Math.max(0,pw)+'%)';
            G('rsk_dist_lbl').textContent = G('flt_scope').value==='courses'
                ? dieText('records_threshold',{n:total,d:days}) : dieEstudantesLimiar(total, days);
        }
    }

    G('rsk_results').style.display = 'block';
    G('btn_xls').style.display     = total ? 'inline-flex' : 'none';
    G('btn_msg_all').style.display = total ? 'inline-flex' : 'none';
    G('btn_emails').style.display  = total ? 'inline-flex' : 'none';
    _msgIds = students.map(function(s){ return s.userid; });

    if (!total) {
        G('rsk_good').style.display = 'block';
        G('good_days').textContent = dieText('no_students_without_access',days);
        G('rsk_table_wrap').style.display = 'none';
        G('rsk_chips').style.display = 'none';
        return;
    }
    G('rsk_good').style.display = 'none';
    G('rsk_chips').style.display = 'flex';
    G('ch_t').textContent = total;
    G('ch_c').textContent = stats.critical;
    G('ch_a').textContent = stats.alert;
    G('ch_w').textContent = stats.warn;
    G('rsk_table_wrap').style.display = 'block';
    renderTable();
}

function lvlOf(s){ return s.days_since > 30 ? 'c' : (s.days_since > 15 ? 'a' : 'w'); }

function setLvl(el){
    _LVL = el.getAttribute('data-lvl'); _SHOWN = 100;
    document.querySelectorAll('#rsk .rk-chip').forEach(function(x){ x.classList.remove('on'); });
    el.classList.add('on');
    renderTable();
}

function avColor(name){
    var palette = ['#0ea5e9','#8b5cf6','#ec4899','#f59e0b','#10b981','#ef4444','#6366f1','#14b8a6'];
    var h = 0; name = name || '?';
    for (var i=0; i<name.length; i++) h = (h*31 + name.charCodeAt(i)) >>> 0;
    return palette[h % palette.length];
}

function renderTable(){
    var q = (G('srch_rsk') ? G('srch_rsk').value : '').toLowerCase().trim();
    var rows = _STUDENTS.filter(function(s){
        if (_LVL && lvlOf(s) !== _LVL) return false;
        if (q && (s.fullname+' '+s.email+' '+(s.phone||'')+' '+s.coursename+' '+(s.catname||'')).toLowerCase().indexOf(q) < 0) return false;
        return true;
    });
    G('rsk_count').textContent = q || _LVL ? dieText('filtered_count',{shown:rows.length,total:_STUDENTS.length}) : '· '+_STUDENTS.length;

    var html = '';
    rows.slice(0, _SHOWN).forEach(function(s, i){
        var lv = lvlOf(s);
        var lvlCls = lv==='c' ? 'rk-lvl-c' : (lv==='a' ? 'rk-lvl-a' : 'rk-lvl-w');
        var lvlTxt = lv==='c' ? DIE_LANG.critico  : (lv==='a' ? DIE_LANG.alerta  : DIE_LANG.aviso);
        var init = (s.fullname||'?').split(' ').map(function(w){return w[0];}).join('').substring(0,2).toUpperCase();
        html += '<tr>'
            + '<td style="color:#94a3b8;font-size:11px">' + (i+1) + '</td>'
            + '<td><div style="display:flex;align-items:center;gap:10px"><div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#fff;flex-shrink:0;background:'+avColor(s.fullname)+'">'+init+'</div><div style="min-width:0"><div style="font-weight:600;color:#0f172a">'+esc(s.fullname)+'</div><div style="font-size:11px;color:#94a3b8">'+esc(s.email)+'</div></div></div></td>'
            + '<td style="font-size:12px;font-weight:500">'+esc(s.coursename)+'</td>'
            + '<td class="rk-hide-sm" style="font-size:11px;color:#94a3b8">'+esc(s.catname)+'</td>'
            + '<td class="rk-hide-sm" style="font-size:12px;color:#334155">'+(s.phone?esc(s.phone):'<span style="color:#cbd5e1">-</span>')+'</td>'
            + '<td class="rk-hide-sm" style="text-align:center;font-size:12px;color:#64748b">'+(s.last_access||'<span class="rk-never">'+esc(DIE_LANG.risk_no_access_record)+'</span>')+'</td>'
            + '<td style="text-align:center"><strong style="font-size:15px;color:'+(lv==='c'?'#dc2626':lv==='a'?'#d97706':'#2563eb')+'">'+(s.days_since>=9999?DIE_LANG.not_available:s.days_since)+'</strong><span style="font-size:10px;color:#94a3b8">'+(s.days_since>=9999?'':' '+esc(DIE_LANG.days))+'</span></td>'
            + '<td style="text-align:center"><span class="rk-lvl '+lvlCls+'">'+lvlTxt+'</span></td>'
            + '<td style="text-align:center"><button class="rk-msg-btn" data-uid="'+s.userid+'"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg> '+DIE_LANG.mensagem+'</button></td>'
            + '</tr>';
    });
    G('rsk_tbody').innerHTML = html;

    // "Show more" control
    var wrap = G('rsk_table_wrap');
    var more = G('rsk_more');
    if (more) more.remove();
    if (rows.length > _SHOWN) {
        more = document.createElement('button');
        more.id = 'rsk_more'; more.className = 'rk-more';
        more.textContent = dieMostrarMais(rows.length - _SHOWN);
        more.onclick = function(){ _SHOWN += 100; renderTable(); };
        wrap.appendChild(more);
    }
}

// Delegated click for message buttons (avoids quote-escaping in names)
document.addEventListener('click', function(e){
    var b = e.target.closest('.rk-msg-btn');
    if (b) msgUser(+b.getAttribute('data-uid'));
});

function copyEmails(){
    var q = (G('srch_rsk') ? G('srch_rsk').value : '').toLowerCase().trim();
    var emails = {};
    _STUDENTS.forEach(function(s){
        if (_LVL && lvlOf(s) !== _LVL) return;
        if (q && (s.fullname+' '+s.email+' '+s.coursename).toLowerCase().indexOf(q) < 0) return;
        emails[s.email] = 1;
    });
    var list = Object.keys(emails).join('; ');
    if (!list) { if(window.dieToast) dieToast(DIE_LANG.sem_emails,'info'); return; }
    function done(){ if(window.dieToast) dieToast(dieText('emails_copied_client',Object.keys(emails).length),'ok'); }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(list).then(done).catch(function(){ window.prompt(DIE_LANG.copy_emails_prompt, list); });
    } else { window.prompt(DIE_LANG.copy_emails_prompt, list); }
}

function msgUser(userid, name) {
    window.open(MSG + '?id=' + userid, '_blank');
}
function msgAll() {
    if (!_msgIds.length) return;
    // Open message page for first user - Moodle doesn't support bulk message from URL
    // but we can open multiple in sequence or show a list
    if (confirm(dieText('message_confirm',_msgIds.length))) {
        window.open(MSG + '?id=' + _msgIds[0], '_blank');
    }
}
function exportXls() {
    var pid   = G('flt_period') ? G('flt_period').value : '';
    var catid = G('flt_cat') ? G('flt_cat').value : '';
    var cid   = (window.DieCourseSearch && DieCourseSearch.getSelectedId()) || '';
    var days  = G('flt_days') ? G('flt_days').value : '7';
    window.open(XLS+'?sesskey='+SK+'&period='+pid+'&catid='+catid+'&courseid='+cid+'&days='+days+'&scope='+encodeURIComponent(G('flt_scope').value)+'&descendants='+filters.scope().descendants,'_blank');
}

function showLoading() {
    document.getElementById('rsk_results').style.display = 'block';
    document.getElementById('rsk_loading').style.display = 'block';
    document.getElementById('rsk_good').style.display = 'none';
    document.getElementById('rsk_table_wrap').style.display = 'none';
    document.getElementById('rsk_stats').style.display = 'none';
}
function hideLoading() {
    document.getElementById('rsk_loading').style.display = 'none';
}
function resetResults() {
    document.getElementById('rsk_results').style.display = 'none';
    document.getElementById('rsk_stats').style.display = 'none';
}
function esc(s) { var d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }

filters=LearningAnalyticsFilters.create({url:AX,sesskey:SK,withCourses:true,
  onReset:resetData,onChange:loadRisk,onLoading:showLoading,
  onEmpty:function(){hideLoading();renderRisk([],{critical:0,alert:0,warn:0},Math.max(1,parseInt(G('flt_days').value)||7));}
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
