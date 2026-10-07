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
 * Assessment coverage report.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
\local_mulima_analytics\local\access::require_report('coverage');
\local_mulima_analytics\local\page::setup('cobertura.php', get_string('title_cobertura', 'local_mulima_analytics'));

$SK  = sesskey();
$AX  = (new moodle_url('/local/mulima_analytics/cobertura_ajax.php'))->out(false);
$XLS = (new moodle_url('/local/mulima_analytics/cobertura_export.php'))->out(false);

$root_cats = $DB->get_records_select('course_categories', 'depth = 1', [], 'sortorder', 'id,name');
$maxpoints = (int)(get_config('local_mulima_analytics', 'maxpoints') ?: 800); // same setting used by the Painel

echo $OUTPUT->header();

$DIE_PAGE     = 'cobertura';
$DIE_TITLE    = get_string('title_cobertura', 'local_mulima_analytics');
$DIE_SUBTITLE = get_string('subtitle_cobertura', 'local_mulima_analytics');
require_once(__DIR__.'/die_layout.php');
require_once(__DIR__.'/filter_controls.php');
?>

<div id="cb">
<style>
/* ── Cobertura page ── */
#cb .cb-t { width:100%; border-collapse:collapse; }
#cb .cb-t thead th { background:#f8fafc; color:#64748b; font-size:10px; font-weight:700;
  text-transform:uppercase; letter-spacing:.05em; padding:10px 14px; border-bottom:2px solid #e2e8f0;
  text-align:left; white-space:nowrap; position:sticky; top:0; z-index:2; }
#cb .cb-t thead th.c { text-align:center; }
#cb .cb-t thead th.sortable { cursor:pointer; }
#cb .cb-t thead th.sortable:hover { color:#334155; }
#cb .cb-t thead th .arr { opacity:.35; font-size:8px; margin-left:3px; }
#cb .cb-t thead th.act .arr { opacity:1; color:var(--die-acc); }
#cb .cb-t tbody tr { border-bottom:1px solid #f1f5f9; }
#cb .cb-t tbody tr:last-child { border-bottom:none; }
#cb .cb-t tbody td { padding:11px 14px; vertical-align:middle; font-size:12px; }
#cb .cb-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px;
  font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; white-space:nowrap; }
#cb .cb-pill::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }
#cb .cb-ok   { background:#dcfce7; color:#15803d; }
#cb .cb-bad  { background:#fee2e2; color:#b91c1c; }
#cb .cb-mid  { background:#fef3c7; color:#b45309; }
#cb .cb-grey { background:#f1f5f9; color:#94a3b8; }
#cb .cb-pend { display:inline-flex; align-items:center; justify-content:center; min-width:26px; height:22px;
  padding:0 7px; border-radius:12px; background:#fee2e2; color:#b91c1c; font-size:11px; font-weight:800; }
#cb .cb-pend.z { background:#f1f5f9; color:#cbd5e1; }
#cb .cb-mod { display:inline-block; padding:2px 8px; border-radius:6px; background:#f1f5f9;
  color:#475569; font-size:10px; font-weight:700; text-transform:capitalize; }
#cb .cb-bar { height:6px; border-radius:4px; background:#f1f5f9; overflow:hidden; width:100%; max-width:110px; }
#cb .cb-bar > div { height:100%; border-radius:4px; transition:width .4s; }
#cb .cb-chip { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:20px;
  font-size:11px; font-weight:600; cursor:pointer; border:1.5px solid #e2e8f0; background:#fff;
  color:#64748b; transition:all .15s; user-select:none; }
#cb .cb-chip:hover { border-color:#cbd5e1; }
#cb .cb-chip.on { border-color:var(--die-acc); background:rgba(var(--die-acc-rgb),.08); color:var(--die-acc); }
#cb .cb-chip .n { font-size:10px; font-weight:800; padding:1px 6px; border-radius:10px; background:#f1f5f9; }
#cb .cb-btn { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:7px;
  font-size:11px; font-weight:600; border:1px solid #e2e8f0; background:#fff; color:#475569;
  cursor:pointer; transition:all .15s; white-space:nowrap; text-decoration:none; }
#cb .cb-btn:hover { border-color:var(--die-acc); color:var(--die-acc); }
@media (max-width:640px){ #cb .cb-hide-sm { display:none !important; } }
</style>

<!-- Filters -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm mb-4">
  <div class="flex items-center gap-2 px-4 py-2.5 bg-slate-50 border-b border-slate-100 rounded-t-xl text-[10px] font-bold text-slate-500 uppercase tracking-wider">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
    <?php echo s(get_string('filtros', 'local_mulima_analytics')); ?>
  </div>
  <div class="p-4 die-filters la-report-filters">
    <div class="flex flex-col gap-1.5 flex-1 min-w-36">
      <label for="flt_period" class="text-[10px] font-bold text-slate-500 uppercase tracking-wide"><?php echo s(get_string('periodo_execucao', 'local_mulima_analytics')); ?></label>
      <select class="die-select w-full text-sm px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800" id="flt_period" onchange="onPeriod()">
        <option value=""></option>
        <?php foreach ($root_cats as $rc): ?>
        <option value="<?php echo $rc->id; ?>"><?php echo s($rc->name); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex flex-col gap-1.5 flex-1 min-w-36">
      <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide"><?php echo s(get_string('subcategoria', 'local_mulima_analytics')); ?></label>
      <div id="flt_cat_tree" class="la-category-tree" role="group" aria-label="<?php echo s(get_string('subcategoria', 'local_mulima_analytics')); ?>"></div><input type="hidden" id="flt_cat" value="">
    </div>
    <div class="flex flex-col gap-1.5" style="max-width:150px">
      <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide"><?php echo s(get_string('score_target', 'local_mulima_analytics')); ?></label>
      <div style="display:flex;align-items:center;gap:6px">
        <input type="number" class="die-input w-full text-sm px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800" id="flt_target" value="<?php echo $maxpoints; ?>" min="1" step="10" onchange="renderCov()">
        <span style="font-size:12px;color:#64748b">pts</span>
      </div>
    </div>
  </div>
</div>

<!-- Stats -->
<div id="cb_stats" style="display:none">
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 w-full">
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex items-start gap-3">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#fef3c7">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      </div>
      <div class="min-w-0 flex-1"><div class="text-2xl font-extrabold text-slate-900 leading-none" id="cs_total">-</div><div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mt-1 break-words"><?php echo s(get_string('disciplinas', 'local_mulima_analytics')); ?></div></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex items-start gap-3">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#dcfce7">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      </div>
      <div class="min-w-0 flex-1"><div class="text-2xl font-extrabold text-slate-900 leading-none" id="cs_ok">-</div><div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mt-1 break-words"><?php echo s(get_string('na_meta', 'local_mulima_analytics')); ?></div></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex items-start gap-3">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#fee2e2">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div class="min-w-0 flex-1"><div class="text-2xl font-extrabold text-slate-900 leading-none" id="cs_bad">-</div><div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mt-1 break-words"><?php echo s(get_string('fora_meta', 'local_mulima_analytics')); ?></div></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex items-start gap-3">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#fee2e2">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M12 22a2.1 2.1 0 0 0 2-1.5H10a2.1 2.1 0 0 0 2 1.5zM18 16v-5a6 6 0 1 0-12 0v5l-2 2v1h16v-1z"/></svg>
      </div>
      <div class="min-w-0 flex-1"><div class="text-2xl font-extrabold leading-none" id="cs_pend" style="color:#dc2626">-</div><div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mt-1 break-words"><?php echo s(get_string('por_corrigir', 'local_mulima_analytics')); ?></div></div>
    </div>
  </div>
</div>

<!-- Results -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm mb-4" id="cb_results" style="display:none">
  <div class="flex items-center justify-between gap-2 px-4 py-2.5 bg-slate-50 border-b border-slate-100 rounded-t-xl text-[10px] font-bold text-slate-500 uppercase tracking-wider" style="flex-wrap:wrap">
    <span style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      <button id="btn_back" onclick="backToAll()" style="display:none;align-items:center;gap:4px;padding:4px 10px;border-radius:7px;border:1px solid #e2e8f0;background:#fff;color:#475569;font-size:10px;font-weight:700;cursor:pointer">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="15 18 9 12 15 6"/></svg><?php echo s(get_string('todos', 'local_mulima_analytics')); ?></button>
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      <span id="cb_title"><?php echo s(get_string('assessment_by_course', 'local_mulima_analytics')); ?></span>
      <span id="cur_course" style="display:none;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;background:rgba(var(--die-acc-rgb),.1);color:var(--die-acc);font-size:10px;font-weight:700;text-transform:none;letter-spacing:0;max-width:320px">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        <span id="cur_course_name" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span>
      </span>
      <span id="cb_count" style="font-weight:600;color:#94a3b8;text-transform:none;letter-spacing:0"></span>
    </span>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <div style="position:relative" id="srch_wrap">
        <svg style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#94a3b8" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="srch_cb" placeholder="<?php echo s(get_string('search_course_category', 'local_mulima_analytics')); ?>" oninput="renderCov()" style="padding:6px 10px 6px 28px;border:1px solid #e2e8f0;border-radius:7px;font-size:11px;width:170px;outline:none;font-weight:400;text-transform:none;letter-spacing:0">
      </div>
      <button class="die-btn inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold text-white bg-green-600 hover:bg-green-700 transition-colors cursor-pointer border-0" id="btn_xls" onclick="exportXls()" style="display:none">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg><?php echo s(get_string('exportar_xlsx', 'local_mulima_analytics')); ?></button>
    </div>
  </div>
  <div id="cb_loading" class="text-center py-12 px-6 text-slate-400" style="display:none"><div class="die-spin w-5 h-5 rounded-full" style="margin:0 auto 10px"></div><?php echo s(get_string('calculating', 'local_mulima_analytics')); ?></div>
  <div id="cb_empty" class="text-center py-12 px-6 text-slate-400" style="display:none"><?php echo s(get_string('no_courses_scope', 'local_mulima_analytics')); ?></div>
  <div id="cb_chips" style="display:none;padding:10px 14px;border-bottom:1px solid #f1f5f9;gap:8px;flex-wrap:wrap">
    <span class="cb-chip on" data-f="" onclick="setCbF(this)"><?php echo s(get_string('todas', 'local_mulima_analytics')); ?> <span class="n" id="cbc_t">0</span></span>
    <span class="cb-chip" data-f="ok" onclick="setCbF(this)"><?php echo s(get_string('na_meta', 'local_mulima_analytics')); ?> <span class="n" id="cbc_o">0</span></span>
    <span class="cb-chip" data-f="bad" onclick="setCbF(this)"><?php echo s(get_string('fora_meta', 'local_mulima_analytics')); ?> <span class="n" id="cbc_b">0</span></span>
    <span class="cb-chip" data-f="pend" onclick="setCbF(this)"><?php echo s(get_string('com_pendentes', 'local_mulima_analytics')); ?> <span class="n" id="cbc_p">0</span></span>
  </div>

  <!-- Level 1: disciplines -->
  <div id="cb_table_wrap" style="display:none;overflow-x:auto">
    <table class="cb-t">
      <thead><tr>
        <th style="width:36px">#</th>
        <th class="sortable" data-k="name" onclick="sortCov(this)"><?php echo s(get_string('disciplina', 'local_mulima_analytics')); ?><span class="arr">▼</span></th>
        <th class="cb-hide-sm"><?php echo s(get_string('categoria', 'local_mulima_analytics')); ?></th>
        <th class="c sortable" data-k="n_items" onclick="sortCov(this)"><?php echo s(get_string('avaliaveis', 'local_mulima_analytics')); ?><span class="arr">▼</span></th>
        <th class="c sortable act" data-k="points" onclick="sortCov(this)"><?php echo s(get_string('pontuacao', 'local_mulima_analytics')); ?><span class="arr">▼</span></th>
        <th class="c sortable cb-hide-sm" data-k="graded" onclick="sortCov(this)"><?php echo s(get_string('avaliados', 'local_mulima_analytics')); ?><span class="arr">▼</span></th>
        <th class="c sortable" data-k="pending" onclick="sortCov(this)"><?php echo s(get_string('por_corrigir', 'local_mulima_analytics')); ?><span class="arr">▼</span></th>
        <th class="c" style="width:110px"><?php echo s(get_string('estado', 'local_mulima_analytics')); ?></th>
        <th class="c" style="width:70px"></th>
      </tr></thead>
      <tbody id="cb_tbody"></tbody>
    </table>
  </div>

  <!-- Level 2: activities of one discipline -->
  <div id="cb_acts_wrap" style="display:none;overflow-x:auto">
    <table class="cb-t">
      <thead><tr>
        <th style="width:36px">#</th>
        <th><?php echo s(get_string('actividade', 'local_mulima_analytics')); ?></th>
        <th class="c"><?php echo s(get_string('tipo', 'local_mulima_analytics')); ?></th>
        <th class="c"><?php echo s(get_string('pontos', 'local_mulima_analytics')); ?></th>
        <th class="c"><?php echo s(get_string('submetidos', 'local_mulima_analytics')); ?></th>
        <th class="c cb-hide-sm"><?php echo s(get_string('avaliados', 'local_mulima_analytics')); ?></th>
        <th class="c"><?php echo s(get_string('por_corrigir', 'local_mulima_analytics')); ?></th>
        <th class="c" style="width:120px"><?php echo s(get_string('estado', 'local_mulima_analytics')); ?></th>
        <th class="c" style="width:130px"></th>
      </tr></thead>
      <tbody id="cb_acts_tbody"></tbody>
    </table>
  </div>
</div>

<!-- Modal: submissions of one activity -->
<div id="modal_bg" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:300" onclick="if(event.target===this)closeModal()">
  <div class="die-modal-box" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:820px;max-width:94vw;max-height:88vh;background:#fff;border-radius:16px;display:flex;flex-direction:column;overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:flex-start;justify-content:space-between;gap:10px">
      <div style="min-width:0">
        <div id="m_title" style="font-size:15px;font-weight:800;color:#0f172a"></div>
        <div id="m_sub" style="font-size:11px;color:#94a3b8;margin-top:2px"></div>
      </div>
      <button onclick="closeModal()" style="background:none;border:none;cursor:pointer;color:#94a3b8;font-size:18px;line-height:1;padding:4px">✕</button>
    </div>
    <div id="m_stats" style="display:flex;gap:10px;padding:12px 20px;border-bottom:1px solid #f1f5f9;flex-wrap:wrap"></div>
    <div style="overflow:auto;flex:1">
      <div id="m_filter_errors"></div>
      <div id="m_loading" class="text-center py-10 text-slate-400" style="display:none"><div class="die-spin w-5 h-5 rounded-full" style="margin:0 auto 10px"></div><?php echo s(get_string('loading_submissions', 'local_mulima_analytics')); ?></div>
      <table class="cb-t" id="m_tbl" style="display:none">
        <thead><tr>
          <th style="width:36px">#</th>
          <th><?php echo s(get_string('estudante', 'local_mulima_analytics')); ?></th>
          <th class="c cb-hide-sm"><?php echo s(get_string('submetido_em', 'local_mulima_analytics')); ?></th>
          <th class="c"><?php echo s(get_string('nota', 'local_mulima_analytics')); ?></th>
          <th class="c" style="width:140px"><?php echo s(get_string('estado', 'local_mulima_analytics')); ?></th>
        </tr></thead>
        <tbody id="m_tbody"></tbody>
      </table>
      <div id="m_empty" class="text-center py-10 text-slate-400" style="display:none"><?php echo s(get_string('sem_submissoes_act', 'local_mulima_analytics')); ?></div>
    </div>
    <div style="padding:12px 20px;border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end;gap:8px">
      <a id="m_open" href="#" target="_blank" class="cb-btn" style="display:none">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg><?php echo s(get_string('open_moodle_grade', 'local_mulima_analytics')); ?></a>
    </div>
  </div>
</div>

</div><!-- #cb -->

<script>
var SK='<?php echo $SK; ?>', AX='<?php echo $AX; ?>', XLS='<?php echo $XLS; ?>';
var _COURSES=[], _ACTS=[], _CBF='', _SK2='pending', _SD=-1, CID=0, CNAME='', VIEW='courses', filters;

function G(id){ return document.getElementById(id); }
function esc(s){ var d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }
function target(){ return Math.max(1, parseInt(G('flt_target').value)||800); }

function onPeriod(){filters.periodChanged();}
function onCat(){filters.categoryChanged();}
function resetAll(){
  VIEW='courses';CID=0;CNAME='';_COURSES=[];_ACTS=[];
  closeModal();
  ['cb_results','cb_stats','cb_acts_wrap','cb_table_wrap','cb_chips','btn_back','btn_xls','cur_course'].forEach(function(id){G(id).style.display='none';});
  G('cb_tbody').innerHTML='';G('cb_acts_tbody').innerHTML='';
  G('cb_title').textContent=DIE_LANG.assessment_by_course;
  G('srch_wrap').style.display='block';
  G('cb_empty').textContent=DIE_LANG.no_courses_scope;
}
function loadOverview(){
  var selected=filters.scope();if(!selected.period)return;
  G('cb_results').style.display='block';G('cb_loading').style.display='block';
  ['cb_empty','cb_table_wrap','cb_chips','cb_stats','btn_xls'].forEach(function(id){G(id).style.display='none';});
  filters.request('report','overview',{period:selected.period,catid:selected.catid,descendants:selected.descendants},function(d){
    G('cb_loading').style.display='none';
    _COURSES=d.courses||[];_CBF='';_SK2='pending';_SD=-1;
    G('srch_cb').value='';
    document.querySelectorAll('#cb .cb-chip').forEach(function(x,i){x.classList.toggle('on',i===0);});
    if(!_COURSES.length){G('cb_empty').style.display='block';return;}
    G('btn_xls').style.display='inline-flex';G('cb_chips').style.display='flex';G('cb_table_wrap').style.display='block';renderCov();
  },function(){G('cb_loading').style.display='none';},{post:true});
}

function stOf(co){ return Math.round(co.points) === target() ? 'ok' : 'bad'; }

function setCbF(el){
  _CBF = el.getAttribute('data-f');
  document.querySelectorAll('#cb .cb-chip').forEach(function(x){ x.classList.remove('on'); });
  el.classList.add('on');
  renderCov();
}
function sortCov(th){
  var k = th.getAttribute('data-k');
  if (_SK2===k) { _SD=-_SD; } else { _SK2=k; _SD=(k==='name')?1:-1; }
  document.querySelectorAll('#cb_table_wrap th.sortable').forEach(function(x){ x.classList.remove('act'); });
  th.classList.add('act');
  th.querySelector('.arr').textContent = _SD===-1 ? '\u25BC' : '\u25B2';
  renderCov();
}

function renderCov(){
  if (VIEW!=='courses' || !_COURSES.length) return;
  var t = target();
  var ok=0, pend=0, withPend=0;
  _COURSES.forEach(function(co){
    if (Math.round(co.points)===t) ok++;
    pend += co.pending;
    if (co.pending>0) withPend++;
  });
  G('cb_stats').style.display='block';
  G('cs_total').textContent=_COURSES.length;
  G('cs_ok').textContent=ok;
  G('cs_bad').textContent=_COURSES.length-ok;
  G('cs_pend').textContent=pend;
  G('cbc_t').textContent=_COURSES.length;
  G('cbc_o').textContent=ok;
  G('cbc_b').textContent=_COURSES.length-ok;
  G('cbc_p').textContent=withPend;

  var q=(G('srch_cb')?G('srch_cb').value:'').toLowerCase().trim();
  var rows=_COURSES.filter(function(co){
    if (_CBF==='ok'   && stOf(co)!=='ok')  return false;
    if (_CBF==='bad'  && stOf(co)!=='bad') return false;
    if (_CBF==='pend' && co.pending<=0)    return false;
    if (q && (co.name+' '+co.short+' '+(co.catname||'')).toLowerCase().indexOf(q)<0) return false;
    return true;
  });
  rows.sort(function(a,b){
    var x=a[_SK2], y=b[_SK2];
    if (typeof x==='string') return _SD*x.localeCompare(y);
    return _SD*((x||0)-(y||0));
  });
  G('cb_count').textContent=(q||_CBF)?dieText('filtered_count',{shown:rows.length,total:_COURSES.length}):'\u00B7 '+_COURSES.length;

  var html='';
  rows.forEach(function(co,i){
    var st=stOf(co), pts=Math.round(co.points*10)/10;
    var pcol = st==='ok' ? '#15803d' : (pts>target() ? '#b45309' : '#b91c1c');
    var pbar = Math.min(100, Math.round(pts/target()*100));
    html+='<tr>'
      +'<td style="color:#94a3b8;font-size:11px">'+(i+1)+'</td>'
      +'<td><div style="font-weight:600;color:#0f172a">'+esc(co.name)+'</div><div style="font-size:11px;color:#94a3b8">'+esc(co.short)+'</div></td>'
      +'<td class="cb-hide-sm" style="font-size:11px;color:#94a3b8">'+esc(co.catname)+'</td>'
      +'<td style="text-align:center;font-weight:800;font-size:14px;color:'+(co.n_items?'#0f172a':'#cbd5e1')+'">'+co.n_items+'</td>'
      +'<td style="text-align:center"><div style="display:flex;flex-direction:column;align-items:center;gap:4px"><span style="font-size:14px;font-weight:800;color:'+pcol+'">'+pts+'<span style="font-size:10px;font-weight:600;color:#94a3b8"> / '+target()+'</span></span><div class="cb-bar"><div style="width:'+pbar+'%;background:'+pcol+'"></div></div></div></td>'
      +'<td class="cb-hide-sm" style="text-align:center"><span style="font-weight:800;font-size:13px">'+co.graded+'</span><span style="font-size:10px;color:#94a3b8"> / '+co.submitted+'</span></td>'
      +'<td style="text-align:center"><span class="cb-pend'+(co.pending?'':' z')+'">'+co.pending+'</span></td>'
      +'<td style="text-align:center">'+(st==='ok'
          ? (co.pending? '<span class="cb-pill cb-mid">'+esc(DIE_LANG.meta_ok_corrigir)+'</span>' : '<span class="cb-pill cb-ok">'+esc(DIE_LANG.conforme)+'</span>')
          : '<span class="cb-pill cb-bad">'+esc(pts>target()?DIE_LANG.acima_meta:DIE_LANG.abaixo_meta)+'</span>')+'</td>'
      +'<td style="text-align:center"><button class="cb-btn cb-drill" data-cid="'+co.id+'">'+DIE_LANG.ver+'</button></td>'
      +'</tr>';
  });
  G('cb_tbody').innerHTML=html;
}

// ── Level 2: activities ──────────────────────────────────────────
function drillCourse(id, name){
  filters.cancel('report');closeModal();
  VIEW='acts'; CID=id; CNAME=name;
  G('cb_table_wrap').style.display='none';
  G('cb_chips').style.display='none';
  G('cb_stats').style.display='none';
  G('btn_back').style.display='inline-flex';
  G('cb_title').textContent=DIE_LANG.assessable_activities;
  G('cur_course_name').textContent=name;
  G('cur_course').style.display='inline-flex';
  G('cur_course').title=name;
  G('cb_count').textContent='';
  G('srch_wrap').style.display='none';
  G('cb_loading').style.display='block';
  G('cb_acts_wrap').style.display='none';
  G('cb_empty').style.display='none';
  filters.request('report','acts',{courseid:id},function(d){
      G('cb_loading').style.display='none';
      if (!d.ok) { if(window.dieToast) dieToast(d.error||DIE_LANG.erro_carregar,'err'); return; }
      _ACTS = d.acts||[];
      if (!_ACTS.length) { G('cb_empty').style.display='block'; G('cb_empty').textContent=DIE_LANG.no_assessable_activities; return; }
      renderActs();
      G('cb_acts_wrap').style.display='block';
    },function(){G('cb_loading').style.display='none';});
}
function backToAll(){
  filters.cancel('report');closeModal();G('cb_loading').style.display='none';
  VIEW='courses'; CID=0; CNAME='';
  G('cb_acts_wrap').style.display='none';
  G('btn_back').style.display='none';
  G('cur_course').style.display='none';
  G('cb_title').textContent=DIE_LANG.assessment_by_course;
  G('srch_wrap').style.display='block';
  G('cb_empty').style.display='none';
  G('cb_table_wrap').style.display='block';
  G('cb_chips').style.display='flex';
  renderCov();
}

var MODNAMES=DIE_LANG.module_names;

function renderActs(){
  var total = _ACTS.reduce(function(a,x){ return a+x.grademax; },0);
  G('cb_count').textContent='\u00B7 '+dieText('activities_count',_ACTS.length)+' \u00B7 '+(Math.round(total*10)/10)+' pts';
  var html='';
  _ACTS.forEach(function(a,i){
    var st = a.pending>0 ? 'bad' : (a.submitted>0 ? 'ok' : 'grey');
    html+='<tr>'
      +'<td style="color:#94a3b8;font-size:11px">'+(i+1)+'</td>'
      +'<td style="font-weight:600;color:#0f172a">'+esc(a.name)+'</td>'
      +'<td style="text-align:center"><span class="cb-mod">'+esc(MODNAMES[a.module]||a.module)+'</span></td>'
      +'<td style="text-align:center;font-weight:800;font-size:13px">'+a.grademax+'</td>'
      +'<td style="text-align:center;font-weight:800;font-size:13px;color:'+(a.submitted?'#0f172a':'#cbd5e1')+'">'+a.submitted+'</td>'
      +'<td class="cb-hide-sm" style="text-align:center;font-weight:800;font-size:13px;color:'+(a.graded?'#15803d':'#cbd5e1')+'">'+a.graded+'</td>'
      +'<td style="text-align:center"><span class="cb-pend'+(a.pending?'':' z')+'">'+a.pending+'</span></td>'
      +'<td style="text-align:center">'+(st==='bad'
          ? '<span class="cb-pill cb-bad">'+esc(DIE_LANG.requer_atencao)+'</span>'
          : (st==='ok' ? '<span class="cb-pill cb-ok">'+esc(DIE_LANG.em_dia)+'</span>' : '<span class="cb-pill cb-grey">'+esc(DIE_LANG.sem_submissoes)+'</span>'))+'</td>'
      +'<td style="text-align:center;white-space:nowrap">'
        +(a.submitted>0?'<button class="cb-btn cb-det" data-item="'+a.itemid+'" style="margin-right:4px">'+DIE_LANG.ver+'</button>':'')
        +(a.grade_url?'<a class="cb-btn" href="'+a.grade_url+'" target="_blank" title="'+esc(DIE_LANG.open_in_moodle)+'"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg></a>':'')
      +'</td>'
      +'</tr>';
  });
  G('cb_acts_tbody').innerHTML=html;
}

// ── Level 3: modal with submissions ──────────────────────────────
function openDetail(itemid){
  var act=null;
  for (var i=0;i<_ACTS.length;i++){ if(_ACTS[i].itemid===itemid){ act=_ACTS[i]; break; } }
  G('modal_bg').style.display='block';
  G('m_title').textContent = act?act.name:DIE_LANG.actividade;
  G('m_sub').textContent = CNAME+(act?' \u00B7 '+(MODNAMES[act.module]||act.module)+' \u00B7 '+act.grademax+' pts':'');
  G('m_stats').innerHTML='';
  G('m_tbl').style.display='none';
  G('m_empty').style.display='none';
  G('m_loading').style.display='block';
  var open=G('m_open');
  if (act && act.grade_url){ open.href=act.grade_url; open.style.display='inline-flex'; } else { open.style.display='none'; }
  filters.request('detail','detail',{itemid:itemid},function(d){
      G('m_loading').style.display='none';
      if (!d.ok) { if(window.dieToast) dieToast(d.error||DIE_LANG.erro_carregar,'err'); return; }
      var sts=d.students||[];
      if (!sts.length){ G('m_empty').style.display='block'; return; }
      var graded=sts.filter(function(s){return s.graded;}).length;
      var pend=sts.length-graded;
      var late=sts.filter(function(s){return !s.graded && s.days_wait>7;}).length;
      G('m_stats').innerHTML=
        chip(DIE_LANG.submetidos, sts.length, '#0f172a')
        +chip(DIE_LANG.avaliados, graded, '#15803d')
        +chip(DIE_LANG.por_avaliar, pend, pend?'#b91c1c':'#94a3b8')
        +(late?chip(DIE_LANG.older_than_days, late, '#b45309'):'');
      var html='';
      sts.forEach(function(s,i){
        html+='<tr>'
          +'<td style="color:#94a3b8;font-size:11px">'+(i+1)+'</td>'
          +'<td><div style="font-weight:600;color:#0f172a">'+esc(s.fullname)+'</div><div style="font-size:11px;color:#94a3b8">'+esc(s.email)+'</div></td>'
          +'<td class="cb-hide-sm" style="text-align:center;font-size:12px;color:#64748b">'+(s.submitted_at||'-')+'</td>'
          +'<td style="text-align:center">'+(s.graded
              ? '<span style="font-size:14px;font-weight:800;color:#15803d">'+s.grade+'</span><span style="font-size:10px;color:#94a3b8"> / '+d.grademax+'</span>'
              : '<span style="color:#cbd5e1;font-weight:700">-</span>')+'</td>'
          +'<td style="text-align:center">'+(s.graded
              ? '<span class="cb-pill cb-ok">'+DIE_LANG.avaliado+'</span>'
              : (s.days_wait>7
                  ? '<span class="cb-pill cb-bad">'+DIE_LANG.requer_atencao+' · '+s.days_wait+'d</span>'
                  : '<span class="cb-pill cb-mid">'+DIE_LANG.por_avaliar+'</span>'))+'</td>'
          +'</tr>';
      });
      G('m_tbody').innerHTML=html;
      G('m_tbl').style.display='table';
    },function(){G('m_loading').style.display='none';},{errorHost:'m_filter_errors'});
}
function chip(l,v,c){ return '<div style="display:flex;flex-direction:column;gap:2px;padding:6px 14px;border:1px solid #f1f5f9;border-radius:10px"><span style="font-size:16px;font-weight:800;color:'+c+'">'+v+'</span><span style="font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em">'+l+'</span></div>'; }
function closeModal(){if(filters)filters.cancel('detail');G('modal_bg').style.display='none';}

// Delegation: drill + detail buttons.
document.addEventListener('click', function(e){
  var d=e.target.closest('.cb-drill');
  if (d){ var id=+d.getAttribute('data-cid'); var co=null;
    for (var i=0;i<_COURSES.length;i++){ if(_COURSES[i].id===id){ co=_COURSES[i]; break; } }
    drillCourse(id, co?co.name:''); return; }
  var v=e.target.closest('.cb-det');
  if (v){ openDetail(+v.getAttribute('data-item')); }
});
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeModal(); });

function exportXls(){
  var pid=G('flt_period').value, cat=G('flt_cat').value;
  window.open(XLS+'?sesskey='+SK+'&period='+pid+'&catid='+(cat||'')+'&target='+target()+'&descendants='+filters.scope().descendants,'_blank');
}

filters=LearningAnalyticsFilters.create({url:AX,sesskey:SK,withCourses:false,
  onReset:resetAll,onChange:loadOverview,
  onLoading:function(){G('cb_results').style.display='block';G('cb_empty').style.display='none';G('cb_loading').style.display='block';}
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
