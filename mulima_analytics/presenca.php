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
 * Attendance list report.
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

\local_mulima_analytics\local\page::setup('presenca.php', get_string('title_presenca', 'local_mulima_analytics'));

$SK  = sesskey();
$AX  = (new moodle_url('/local/mulima_analytics/presenca_ajax.php'))->out(false);
$EXP = (new moodle_url('/local/mulima_analytics/presenca_export.php'))->out(false);
$CFGURL = (new moodle_url('/local/mulima_analytics/presenca_config.php'))->out(false);

$cfg_enable_especial = (int)(local_mulima_analytics_attendance_config('enable_especial') ?: 0);
$sysctx = context_system::instance();
$is_manager = has_capability('moodle/site:config', $sysctx) || has_capability('moodle/course:update', $sysctx);

// Root categories = períodos de execução (uniform with other DIE pages).
$root_cats = $DB->get_records_select('course_categories', 'depth = 1', [], 'sortorder', 'id,name');

echo $OUTPUT->header();

$DIE_PAGE     = 'presenca';
$DIE_TITLE    = get_string('title_presenca', 'local_mulima_analytics');
$DIE_SUBTITLE = get_string('subtitle_presenca', 'local_mulima_analytics');
require_once(__DIR__.'/die_layout.php');
require_once(__DIR__.'/filter_controls.php');
?>

<div id="lp">
<style>
/* ── Presenças page ── */
#lp { max-width: 780px; }
#lp .lp-ep { flex:1; min-width:130px; padding:16px 12px; border:1.5px solid #e2e8f0; border-radius:12px;
  text-align:center; cursor:pointer; transition:all .15s; background:#fff; user-select:none; }
#lp .lp-ep:hover { border-color:#cbd5e1; background:#f8fafc; }
#lp .lp-ep.on { border-color:var(--c); background:var(--bg); box-shadow:0 0 0 3px var(--ring); }
#lp .lp-ep-ico { display:flex; align-items:center; justify-content:center; width:36px; height:36px;
  border-radius:10px; background:#f1f5f9; color:#94a3b8; margin:0 auto 8px; transition:all .15s; }
#lp .lp-ep.on .lp-ep-ico { background:var(--c); color:#fff; }
#lp .lp-ep-name { font-size:12px; font-weight:700; color:#334155; }
#lp .lp-ep.on .lp-ep-name { color:var(--c); }
#lp .lp-ep-sub { font-size:10px; color:#94a3b8; margin-top:2px; }
#lp .lp-sc { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:20px;
  font-size:11px; font-weight:600; cursor:pointer; border:1.5px solid #e2e8f0; background:#fff;
  color:#64748b; transition:all .15s; user-select:none; }
#lp .lp-sc:hover { border-color:#cbd5e1; }
#lp .lp-sc.on { border-color:var(--die-acc); background:rgba(var(--die-acc-rgb),.08); color:var(--die-acc); }
#lp .lp-panel { display:none; margin-top:14px; }
#lp .lp-panel.show { display:block; }
#lp .lp-label { display:block; font-size:10px; font-weight:700; color:#64748b; text-transform:uppercase;
  letter-spacing:.04em; margin:12px 0 6px; }
#lp .lp-label:first-child { margin-top:0; }
#lp .lp-hint { font-size:11px; color:#94a3b8; margin-top:10px; display:flex; align-items:flex-start; gap:6px; }
</style>

<!-- Época -->
<div class="die-card bg-white border border-slate-200 rounded-xl shadow-sm mb-4">
  <div class="flex items-center justify-between gap-2 px-4 py-2.5 bg-slate-50 border-b border-slate-100 rounded-t-xl text-[10px] font-bold text-slate-500 uppercase tracking-wider">
    <span>
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><?php echo s(get_string('exam_period', 'local_mulima_analytics')); ?></span>
    <?php if ($is_manager): ?>
    <a href="<?php echo $CFGURL; ?>" style="display:inline-flex;align-items:center;gap:5px;color:#64748b;font-size:10px;font-weight:700;text-decoration:none">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg><?php echo s(get_string('nav_settings', 'local_mulima_analytics')); ?></a>
    <?php endif; ?>
  </div>
  <div class="p-4">
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <div class="lp-ep on" style="--c:#2563eb;--bg:#eff6ff;--ring:rgba(37,99,235,.12)" data-ep="normal" onclick="pickEp(this)">
        <div class="lp-ep-ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
        <div class="lp-ep-name"><?php echo s(get_string('exame_normal', 'local_mulima_analytics')); ?></div>
        <div class="lp-ep-sub"><?php echo s(get_string('normal_all_admitted', 'local_mulima_analytics')); ?></div>
      </div>
      <div class="lp-ep" style="--c:#d97706;--bg:#fffbeb;--ring:rgba(217,119,6,.12)" data-ep="recorrencia" onclick="pickEp(this)">
        <div class="lp-ep-ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg></div>
        <div class="lp-ep-name"><?php echo s(get_string('exame_recorrencia', 'local_mulima_analytics')); ?></div>
        <div class="lp-ep-sub"><?php echo s(get_string('recurrence_failed_average', 'local_mulima_analytics')); ?></div>
      </div>
      <?php if ($cfg_enable_especial): ?>
      <div class="lp-ep" style="--c:#7c3aed;--bg:#f5f3ff;--ring:rgba(124,58,237,.12)" data-ep="especial" onclick="pickEp(this)">
        <div class="lp-ep-ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
        <div class="lp-ep-name"><?php echo s(get_string('exame_especial', 'local_mulima_analytics')); ?></div>
        <div class="lp-ep-sub"><?php echo s(get_string('special_failed_both', 'local_mulima_analytics')); ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Âmbito + Exportação -->
<div class="die-card bg-white border border-slate-200 rounded-xl shadow-sm mb-4">
  <div class="flex items-center gap-2 px-4 py-2.5 bg-slate-50 border-b border-slate-100 rounded-t-xl text-[10px] font-bold text-slate-500 uppercase tracking-wider">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg><?php echo s(get_string('scope_and_export', 'local_mulima_analytics')); ?></div>
  <div class="p-4">
    <div class="die-filters flex flex-wrap gap-3 items-end">
      <div class="flex flex-col gap-1.5 flex-1 min-w-36">
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide"><?php echo s(get_string('periodo_execucao', 'local_mulima_analytics')); ?></label>
        <select class="die-select w-full text-sm px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800" id="flt_period" onchange="onPeriod()">
          <option value=""></option>
          <?php foreach ($root_cats as $rc): ?>
          <option value="<?php echo $rc->id; ?>"><?php echo s($rc->name); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex flex-col gap-1.5 flex-1 min-w-36">
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide"><?php echo s(get_string('subcategoria', 'local_mulima_analytics')); ?></label>
        <div id="flt_cat_tree" class="la-category-tree"></div><input type="hidden" id="flt_cat" value="">
      </div>
      <div class="flex flex-col flex-1 min-w-44" id="dcs_container"></div>
      <div class="flex flex-col gap-1.5 flex-1 min-w-36" id="grp_wrap" style="display:none;max-width:220px">
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide"><?php echo s(get_string('class', 'local_mulima_analytics')); ?></label>
        <select class="die-select w-full text-sm px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-800" id="sel-group">
          <option value=""><?php echo s(get_string('single_list', 'local_mulima_analytics')); ?></option>
        </select>
      </div>
    </div>

    <div class="lp-hint" id="scope_hint">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
      <span id="scope_hint_txt"><?php echo s(get_string('attendance_hint_start', 'local_mulima_analytics')); ?></span>
    </div>

    <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap">
      <button class="die-btn" id="btn_pdf" onclick="go('pdf')" disabled style="flex:1;min-width:170px;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px;border-radius:10px;border:none;font-size:13px;font-weight:700;cursor:pointer;background:var(--die-acc);color:#fff;opacity:.45">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg><?php echo s(get_string('descarregar_pdf', 'local_mulima_analytics')); ?></button>
      <button class="die-btn" id="btn_xls" onclick="go('excel')" disabled style="flex:1;min-width:170px;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px;border-radius:10px;border:1.5px solid #e2e8f0;font-size:13px;font-weight:700;cursor:pointer;background:#fff;color:#15803d;opacity:.45">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></polyline></svg><?php echo s(get_string('export_excel', 'local_mulima_analytics')); ?></button>
    </div>
  </div>
</div>

</div><!-- #lp -->

<script>
var SK='<?php echo $SK; ?>', AX='<?php echo $AX; ?>', EXP='<?php echo $EXP; ?>';
var epoch='normal', CID=0, CNAME='', filters;

function G(id){ return document.getElementById(id); }

function pickEp(el){
  epoch = el.getAttribute('data-ep');
  document.querySelectorAll('#lp .lp-ep').forEach(function(x){ x.classList.remove('on'); });
  el.classList.add('on');
}

// The same blank, explicit category selection used by the report tabs.
function onPeriod(){filters.periodChanged();}
function onCat(){filters.categoryChanged();}
function resetScope(){
  clearCourse();
  G('scope_hint').style.display='none';
  ['btn_pdf','btn_xls'].forEach(function(id){G(id).disabled=true;G(id).style.opacity='.45';});
}
function selectionReady(selected){
  if(selected.courseid)courseChosen(+selected.courseid,DieCourseSearch.getSelectedName());
  else {clearCourse();updateButtons();}
}

function clearCourse(){
  CID = 0; CNAME = '';
  G('grp_wrap').style.display = 'none';
  G('sel-group').innerHTML = '<option value="">'+escH(DIE_LANG.single_list)+'</option>';
  hintUpdate();
}
function courseChosen(id, name){
  CID = id; CNAME = name;
  var sel = G('sel-group');
  sel.innerHTML = '<option value="">'+escH(DIE_LANG.single_list)+'</option>';
  G('grp_wrap').style.display = 'none';
  filters.request('detail','groups',{courseid:id},function(d){
    var gs=d.groups||[];
    if(gs.length){
      var h='<option value="">'+escH(DIE_LANG.single_list)+'</option><option value="0">'+escH(dieText('split_groups',gs.length))+'</option>';
      gs.forEach(function(g){h+='<option value="'+g.id+'">'+escH(g.name)+' ('+g.count+')</option>';});
      sel.innerHTML=h;G('grp_wrap').style.display='flex';
    }
    hintUpdate();
  });
  updateButtons();
}

function hintUpdate(){
  var ready=filters&&filters.isReady();
  G('scope_hint').style.display=ready?'flex':'none';
  if(!ready)return;
  if(CID){
    G('scope_hint_txt').textContent=dieText('attendance_hint_course',CNAME);
  }else{
    var selected=filters.scope();
    G('scope_hint_txt').textContent=selected.descendants
      ? DIE_LANG.attendance_hint_descendants
      : DIE_LANG.attendance_hint_direct;
  }
}
function updateButtons(){
  var ok = !!(filters&&filters.isReady());
  ['btn_pdf','btn_xls'].forEach(function(id){
    var b = G(id); b.disabled = !ok; b.style.opacity = ok ? '1' : '.45';
  });
}

function go(fmt){
  var pid = G('flt_period').value;
  if (!pid || !filters.isReady()) return;
  var u = EXP+'?sesskey='+SK+'&epoch='+epoch+'&format='+fmt+'&descendants='+filters.scope().descendants;
  if (CID) {
    var g = G('sel-group').value;
    if (g === '') { u += '&scope=course&course='+CID; }
    else          { u += '&scope=group&course='+CID+'&group='+g; }
  } else {
    var cat = G('flt_cat').value;
    u += '&scope=category&cat='+(cat || pid);
  }
  window.location.href = u;
}

// helpers
function selEnable(id, html){ var el=G(id); if(!el)return; if(html!==undefined)el.innerHTML=html; el.disabled=false; el.style.opacity='1'; }
function selDisable(id, text){ var el=G(id); if(!el)return; el.innerHTML='<option value="">'+(text||'- -')+'</option>'; el.disabled=true; el.style.opacity='.45'; }
function selSpin(id){ var el=G(id); if(!el)return; el.innerHTML='<option>'+escH(DIE_LANG.a_carregar)+'</option>'; el.disabled=true; el.style.opacity='.6'; }
function escH(s){ var d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }

filters=LearningAnalyticsFilters.create({url:AX,sesskey:SK,withCourses:true,
  onReset:resetScope,onChange:selectionReady
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
