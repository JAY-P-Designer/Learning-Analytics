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
 * Shared report layout and browser controls.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
if (!defined('DIE_LAYOUT_LOADED')) define('DIE_LAYOUT_LOADED', 1);

$_cur = $DIE_PAGE ?? 'index';
$_acc = [
    'index'    => ['hex'=>'#0d9488','rgb'=>'13,148,136','tw'=>'teal'],
    'acessos'  => ['hex'=>'#0369a1','rgb'=>'3,105,161','tw'=>'sky'],
    'cobertura'=> ['hex'=>'#d97706','rgb'=>'217,119,6','tw'=>'amber'],
    'risco'    => ['hex'=>'#dc2626','rgb'=>'220,38,38','tw'=>'red'],
    'docentes' => ['hex'=>'#2563eb','rgb'=>'37,99,235','tw'=>'blue'],
    'presenca' => ['hex'=>'#7c3aed','rgb'=>'124,58,237','tw'=>'violet'],
][$_cur] ?? ['hex'=>'#0d9488','rgb'=>'13,148,136','tw'=>'teal'];

$_nav = [
    'index'    => ['l'=>get_string('nav_index_l','local_mulima_analytics'),    's'=>get_string('nav_index_s','local_mulima_analytics'),    'f'=>'index.php',    'i'=>'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z M9 22V12h6v10'],
    'acessos'  => ['l'=>get_string('nav_acessos_l','local_mulima_analytics'),  's'=>get_string('nav_acessos_s','local_mulima_analytics'),  'f'=>'acessos.php',  'i'=>'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'],
    'cobertura'=> ['l'=>get_string('nav_cobertura_l','local_mulima_analytics'),'s'=>get_string('nav_cobertura_s','local_mulima_analytics'),'f'=>'cobertura.php','i'=>'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
    'risco'    => ['l'=>get_string('nav_risco_l','local_mulima_analytics'),    's'=>get_string('nav_risco_s','local_mulima_analytics'),    'f'=>'risco.php',    'i'=>'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
    'docentes' => ['l'=>get_string('nav_docentes_l','local_mulima_analytics'), 's'=>get_string('nav_docentes_s','local_mulima_analytics'), 'f'=>'docentes.php', 'i'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
    'presenca' => ['l'=>get_string('nav_presenca_l','local_mulima_analytics'), 's'=>get_string('nav_presenca_s','local_mulima_analytics'), 'f'=>'presenca.php', 'i'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
];

$_navreports = [
    'index' => 'dashboard',
    'acessos' => 'accesses',
    'cobertura' => 'coverage',
    'risco' => 'risk',
    'docentes' => 'teachers',
    'presenca' => 'attendance',
];

$_settings_url = (new moodle_url('/local/mulima_analytics/presenca_config.php'))->out(false);
$_home_url     = (new moodle_url('/local/mulima_analytics/index.php'))->out(false);
?>

<style>
/* Hierarchical category filter shared by every report page. */
.la-category-tree{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;width:100%}
.la-category-level{min-width:0}
.la-category-level label{display:block;margin:0 0 6px;color:#64748b;font-size:10px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.la-category-level select{width:100%;min-height:38px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;padding:0 10px;color:#0f172a;font-size:13px;transition:border-color .15s,box-shadow .15s}
.la-category-level select:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12);outline:0}
.la-category-levels{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;width:100%}
@media(max-width:640px){.la-category-levels{grid-template-columns:1fr}}
.die-filters{display:grid!important;grid-template-columns:repeat(12,minmax(0,1fr));gap:14px!important;align-items:end}
.die-filters>div{min-width:0!important;grid-column:span 3}
.die-filters>div:has(.la-category-tree){grid-column:span 6;min-width:0}
.die-filters>#dcs_container{grid-column:span 3;min-width:0}
.die-filters .die-fg[style*="max-width"]{grid-column:span 3}
.die-filters label{line-height:1.2}
@media(max-width:900px){.die-filters>div,.die-filters>div:has(.la-category-tree){grid-column:span 6}}
@media(max-width:640px){.die-filters{grid-template-columns:1fr!important}.die-filters>div,.die-filters>div:has(.la-category-tree){grid-column:span 1}}
/* Accent colour variables (per page) */
#die-root { --die-acc: <?php echo $_acc['hex']; ?>; --die-acc-rgb: <?php echo $_acc['rgb']; ?>; }

/* Use Moodle's page shell instead of rendering a second application shell. */
#die-root > .flex.min-h-screen { display: block; min-height: 0; }
#die-root #die-sidebar,
#die-root #die-overlay,
#die-root header.sticky { display: none !important; }
#die-root > .flex.min-h-screen > .flex-1 {
  display: block;
  min-width: 0;
  background: transparent;
}
#die-root .die-hero {
  border: 1px solid #dee2e6;
  border-radius: .75rem .75rem 0 0;
  box-shadow: 0 .125rem .25rem rgba(0,0,0,.075);
}
#die-root main.flex-1 { padding: 1rem 0 0 !important; }
#die-root .die-moodle-nav {
  display: flex;
  gap: .25rem;
  overflow-x: auto;
  padding: 0 .75rem;
  background: #fff;
  border: 1px solid #dee2e6;
  border-top: 0;
  border-radius: 0 0 .75rem .75rem;
  box-shadow: 0 .125rem .25rem rgba(0,0,0,.075);
}
#die-root .die-moodle-nav a {
  display: inline-flex;
  align-items: center;
  color: #495057;
  font-size: .8125rem;
  font-weight: 600;
  padding: .7rem .8rem;
  text-decoration: none;
  white-space: nowrap;
  border-bottom: 3px solid transparent;
}
#die-root .die-moodle-nav a:hover { color: var(--die-acc); background: rgba(var(--die-acc-rgb), .04); }
#die-root .die-moodle-nav a.die-active { color: var(--die-acc); border-bottom-color: var(--die-acc); }
#die-root .die-moodle-nav .die-settings-link { margin-left: auto; }

/* Sidebar collapse */
#die-sidebar.collapsed { width: 64px !important; }
#die-sidebar.collapsed .die-nav-label,
#die-sidebar.collapsed .die-nav-sub,
#die-sidebar.collapsed .die-brand-text,
#die-sidebar.collapsed .die-foot-label { display: none !important; }
#die-sidebar.collapsed .die-nav-item { justify-content: center; padding: 10px; }

/* Scrollbar hidden */
.die-no-scroll::-webkit-scrollbar { display: none; }
.die-no-scroll { scrollbar-width: none; }

/* Active sidebar accent stripe */
.die-nav-item.die-active::before {
  content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%);
  width: 3px; height: 20px; border-radius: 0 3px 3px 0;
  background: var(--die-acc);
}

/* Shimmer skeleton */
.die-skeleton {
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: die-shimmer 1.4s ease infinite;
}
@keyframes die-shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

/* Spinner */
.die-spin {
  border: 2px solid #e2e8f0;
  border-top-color: var(--die-acc);
  border-radius: 50%;
  animation: die-rotate .7s linear infinite;
}
@keyframes die-rotate { to { transform: rotate(360deg); } }

/* Modal animation */
.die-modal-bg { backdrop-filter: blur(6px); }
.die-modal-box { animation: die-modal-in .22s cubic-bezier(.4,0,.2,1); }
@keyframes die-modal-in { from{opacity:0;transform:scale(.94) translateY(8px)} to{opacity:1;transform:scale(1) translateY(0)} }

/* Toast */
.die-toast { animation: die-toast-up .3s cubic-bezier(.4,0,.2,1); }
@keyframes die-toast-up { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }

/* Topbar accent strip */
.die-hero::before {
  content: ''; display: block;
  height: 3px; background: linear-gradient(90deg, var(--die-acc), transparent);
}

/* Table hover accent */
#die-root table tbody tr:hover td { background: rgba(var(--die-acc-rgb), .04); }

/* Custom select arrow */
#die-root select {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%2394a3b8' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  padding-right: 28px !important;
  -webkit-appearance: none;
  appearance: none;
}

/* Card hover */
.die-card { transition: box-shadow .2s; }
.die-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08), 0 2px 4px rgba(0,0,0,.04); }

/* Focus ring uses accent */
#die-root input:focus, #die-root select:focus, #die-root textarea:focus {
  outline: none;
  box-shadow: 0 0 0 3px rgba(var(--die-acc-rgb), .2);
  border-color: var(--die-acc) !important;
}

/* Bar fill */
.die-bar-fill { transition: width .5s cubic-bezier(.4,0,.2,1); background: var(--die-acc); }

/* Progress bar */
.die-progress-fill { transition: width .6s cubic-bezier(.4,0,.2,1); }

@media (max-width: 768px) {
  #die-sidebar { display: none !important; }
  #die-sidebar.mobile-open { display: flex !important; position: fixed; z-index: 200; top: 0; left: 0; bottom: 0;
    width: 240px !important; box-shadow: 0 0 48px rgba(0,0,0,.4); }
}

/* ── Responsive layer (global) ─────────────────────────────── */
@media (max-width: 640px) {
  #die-root main { padding: 12px 0 0 !important; }
  #die-root .die-hero h1 { font-size: 15px; }
  #die-root .die-hero p { display: none; }
  /* Tables: tighter cells, keep horizontal scroll usable */
  #die-root table td, #die-root table th { padding: 7px 8px !important; font-size: 11px !important; }
  /* Filter groups stack full-width */
  #die-root .flex-wrap > .min-w-36, #die-root .flex-wrap > .min-w-44 { flex: 1 1 100% !important; max-width: none !important; }
  /* Inline search boxes shrink */
  #die-root input[id^="srch_"] { width: 120px !important; }
  /* Generic modal becomes bottom sheet */
  #die-root .die-modal-box { width: 100vw !important; max-width: 100vw !important; max-height: 94vh !important;
    border-radius: 14px 14px 0 0 !important; }
}
</style>
<script>
window.LearningAnalyticsCategoryTree = {
  mount: function(containerid, hiddenid, ajaxurl, sesskey, onselect) {
    var root = document.getElementById(containerid), hidden = document.getElementById(hiddenid);
    if (!root || !hidden) return;
    var generation=0;
    function esc(s){var d=document.createElement('div');d.textContent=String(s||'');return d.innerHTML;}
    function load(parent, level, period, currentGeneration) {
      var url = ajaxurl+'?op=cats&sesskey='+encodeURIComponent(sesskey)+'&period='+period+'&parent='+parent;
      var box=document.createElement('div'); box.className='la-category-level';
      var levelLabel=dieText('categoria_nivel',level+1);
      box.innerHTML='<label>'+esc(levelLabel)+'</label><select class="die-select"><option>'+esc(DIE_LANG.a_carregar)+'</option></select>';
      root.appendChild(box); var select=box.querySelector('select'); select.disabled=true;
      fetch(url,{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(data){
        if(currentGeneration!==generation)return;
        var cats=data.cats||[]; select.innerHTML='';
        var opt=document.createElement('option'); opt.value=String(parent); opt.textContent=cats.length?DIE_LANG.todas_neste_nivel:(DIE_LANG.todas_disciplinas_nivel); select.appendChild(opt);
        cats.forEach(function(c){var o=document.createElement('option');o.value=c.id;o.textContent=(level? '↳ ':'')+c.name+(c.count?' ('+c.count+')':'');select.appendChild(o);});
        select.disabled=false; select.onchange=function(){
          while (box.nextSibling) box.nextSibling.remove();
          hidden.value=select.value;
          if (select.value!==String(parent)) load(select.value,level+1,period,currentGeneration);
          onselect(select.value);
        };
        hidden.value=String(parent); onselect(String(parent));
      }).catch(function(){select.innerHTML='<option>'+esc(DIE_LANG.erro_carregar)+'</option>';});
    }
    this.reset=function(period){generation++;root.innerHTML='';hidden.value='';if(period)load(period,0,period,generation);};
    this.reset(0);
  }
};
</script>

<!-- Toast container -->
<div id="die-toast-wrap" style="position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none"></div>
<!-- Mobile overlay -->
<div id="die-overlay" style="display:none;position:fixed;top:0;right:0;bottom:0;left:0;background:rgba(0,0,0,.45);z-index:45" onclick="closeSidebar()"></div>

<div id="die-root" class="font-sans text-slate-800">
<div class="flex min-h-screen">

<!-- ━━━ SIDEBAR ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<aside id="die-sidebar" class="w-56 bg-slate-900 flex flex-col shrink-0 sticky top-0 h-screen z-50 transition-all duration-300 overflow-hidden die-no-scroll">

  <!-- Brand -->
  <div class="flex items-center gap-2 px-4 py-5 border-b border-white/5 shrink-0">
    <a href="<?php echo $_home_url; ?>" class="flex items-center gap-3 flex-1 min-w-0 no-underline">
      <div style="width:32px;height:32px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:var(--die-acc)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="#fff"><path d="M3 3h18v2H3V3zm0 4h12v2H3V7zm0 4h18v2H3v-2zm0 4h12v2H3v-2z"/></svg>
      </div>
      <div class="die-brand-text overflow-hidden">
        <div class="text-sm font-extrabold text-white tracking-tight leading-none">Learning Analytics</div>
      </div>
    </a>
    <?php if (has_capability('moodle/site:config', context_system::instance())): ?>
    <a href="<?php echo (new moodle_url('/local/mulima_analytics/permissoes.php'))->out(false); ?>"
       class="die-brand-text no-underline" title="<?php echo s(get_string('title_permissoes', 'local_mulima_analytics')); ?>"
       style="display:flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;color:rgba(255,255,255,.45);flex-shrink:0;transition:all .15s"
       onmouseover="this.style.background='rgba(255,255,255,.08)';this.style.color='#fff'"
       onmouseout="this.style.background='transparent';this.style.color='rgba(255,255,255,.45)'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
    </a>
    <?php endif; ?>
  </div>

  <!-- Nav -->
  <nav class="flex-1 py-3 px-2 die-no-scroll overflow-y-auto">
    <div style="font-size:9px;font-weight:700;color:rgba(255,255,255,.25);text-transform:uppercase;letter-spacing:.1em;padding:0 12px;margin-bottom:8px"><?php echo s(get_string('nav_reports', 'local_mulima_analytics')); ?></div>
    <?php foreach ($_nav as $key => $item):
      if (!\local_mulima_analytics\local\access::can_view($_navreports[$key])) { continue; }
      $active = ($key === $_cur);
      $href   = (new moodle_url('/local/mulima_analytics/'.$item['f']))->out(false);
      $item_color  = $active ? 'rgba(255,255,255,1)'    : 'rgba(255,255,255,.55)';
      $item_bg     = $active ? 'rgba(255,255,255,.12)'  : 'transparent';
      $icon_bg     = $active ? 'rgba(255,255,255,.18)'  : 'rgba(255,255,255,.07)';
      $sub_color   = $active ? 'rgba(255,255,255,.5)'   : 'rgba(255,255,255,.3)';
    ?>
    <a href="<?php echo $href; ?>"
       class="die-nav-item<?php echo $active?' die-active':''; ?>"
       style="display:flex;align-items:center;gap:12px;padding:9px 12px;border-radius:8px;margin-bottom:2px;position:relative;text-decoration:none;transition:all .15s;color:<?php echo $item_color; ?>;background:<?php echo $item_bg; ?>"
       onmouseover="if(!this.classList.contains('die-active')){this.style.background='rgba(255,255,255,.06)';this.style.color='rgba(255,255,255,1)';}"
       onmouseout="if(!this.classList.contains('die-active')){this.style.background='transparent';this.style.color='rgba(255,255,255,.55)';}">
      <div style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:<?php echo $icon_bg; ?>;transition:background .15s">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <?php
          // Each icon may have multiple paths separated by space - split and render
          $paths = explode(' M', ' '.$item['i']);
          foreach ($paths as $i => $p) {
            $p = trim($p);
            if (!$p) continue;
            echo '<path d="'.($i > 0 ? 'M' : '').$p.'"/>';
          }
          ?>
        </svg>
      </div>
      <div class="die-nav-label" style="overflow:hidden">
        <span style="display:block;font-size:12px;font-weight:600;line-height:1"><?php echo $item['l']; ?></span>
        <span class="die-nav-sub" style="display:block;font-size:10px;margin-top:2px;color:<?php echo $sub_color; ?>"><?php echo $item['s']; ?></span>
      </div>
    </a>
    <?php endforeach; ?>
  </nav>

  <!-- Footer -->
  <div class="border-t border-white/5 p-2 shrink-0 space-y-1">
    <a href="<?php echo $_settings_url; ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg text-white/40 hover:text-white/70 hover:bg-white/5 transition-colors no-underline">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      <span class="die-foot-label text-xs"><?php echo s(get_string('nav_settings', 'local_mulima_analytics')); ?></span>
    </a>
  </div>
</aside>

<!-- ━━━ MAIN ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div class="flex-1 min-w-0 bg-slate-50 flex flex-col">

  <!-- Topbar -->
  <header class="bg-white border-b border-slate-200 px-4 md:px-6 h-14 flex items-center gap-4 sticky top-0 z-30 shadow-sm">
    <!-- Hamburger -->
    <button onclick="toggleSidebar()" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>

    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-xs text-slate-500 overflow-hidden min-w-0 flex-1">
      <a href="<?php echo $_home_url; ?>" class="hover:text-slate-800 transition-colors whitespace-nowrap"><?php echo s(get_string('title_index', 'local_mulima_analytics')); ?></a>
      <?php if ($_cur !== 'index'): ?>
      <svg width="12" height="12" style="opacity:.4;flex-shrink:0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
      <span class="font-semibold text-slate-800 truncate"><?php echo htmlspecialchars($DIE_TITLE ?? $_nav[$_cur]['l'] ?? ''); ?></span>
      <?php endif; ?>
    </nav>

    <div class="flex items-center gap-2 shrink-0">
      <?php if (!empty($DIE_HEAD_ACTIONS)) echo $DIE_HEAD_ACTIONS; ?>
      <?php if ($_cur === 'index'): ?>
      <a href="<?php echo (new moodle_url('/local/mulima_analytics/index.php',['t'=>time()]))->out(false); ?>"
         class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors no-underline">
        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg><?php echo s(get_string('refresh', 'local_mulima_analytics')); ?></a>
      <?php endif; ?>
    </div>
  </header>

  <!-- Page hero -->
  <div class="die-hero bg-white border-b border-slate-200">
    <div class="px-4 md:px-6 py-4 flex items-start justify-between gap-4 flex-wrap">
      <div class="flex items-center gap-4">
        <div style="width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:rgba(var(--die-acc-rgb),.12);border:1px solid rgba(var(--die-acc-rgb),.2)">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="stroke:var(--die-acc)">
            <?php
            $paths = explode(' M', ' '.($_nav[$_cur]['i'] ?? ''));
            foreach ($paths as $pi => $p) { $p=trim($p); if(!$p) continue; echo '<path d="'.($pi>0?'M':'').$p.'"/>'; }
            ?>
          </svg>
        </div>
        <div>
          <h1 class="text-lg font-extrabold text-slate-900 tracking-tight leading-tight m-0">
            <?php echo htmlspecialchars($DIE_TITLE ?? $_nav[$_cur]['l'] ?? 'Learning Analytics'); ?>
          </h1>
          <?php if (!empty($DIE_SUBTITLE)): ?>
          <p class="text-xs text-slate-500 mt-0.5 m-0"><?php echo htmlspecialchars($DIE_SUBTITLE); ?></p>
          <?php endif; ?>
        </div>
      </div>
      <span class="text-xs text-slate-400 hidden sm:block"><?php echo date('d/m/Y H:i'); ?></span>
    </div>
  </div>

  <!-- Report navigation integrated with the Moodle content area. -->
  <?php
  $navigation = new \local_mulima_analytics\output\report_navigation($_cur, $_nav, $_navreports);
  echo $OUTPUT->render_from_template(
      'local_mulima_analytics/report_navigation',
      $navigation->export_for_template($OUTPUT)
  );
  ?>

  <!-- Content -->
  <main class="flex-1 p-4 md:p-6">

<script>
// Sidebar toggle
function toggleSidebar(){
  var s=document.getElementById('die-sidebar');
  var o=document.getElementById('die-overlay');
  if(window.innerWidth<768){
    var opening = !s.classList.contains('mobile-open');
    s.classList.toggle('mobile-open');
    if(o) o.style.display = opening ? 'block' : 'none';
  } else {
    s.classList.toggle('collapsed');
    localStorage.setItem('die_col', s.classList.contains('collapsed')?'1':'');
  }
}
function closeSidebar(){
  var s=document.getElementById('die-sidebar');
  var o=document.getElementById('die-overlay');
  s.classList.remove('mobile-open');
  if(o) o.style.display = 'none';
}
(function(){
  if(localStorage.getItem('die_col')==='1' && window.innerWidth>=768)
    document.getElementById('die-sidebar').classList.add('collapsed');
})();

// Shared JS localisation dictionary - every page's own <script> (loaded
// after die_layout.php) can read DIE_LANG.xxx instead of hardcoding text,
// so table cells built dynamically in JS stay bilingual too.
<?php
$dieuistrings = [];
foreach ([
    'a_carregar', 'abaixo_meta', 'acima_meta', 'activa', 'actividade',
    'activities_count', 'activities_filtered_count', 'alerta', 'all_courses_count', 'alta',
    'assessable_activities', 'assessment_by_course', 'attendance_hint_course', 'attendance_hint_descendants', 'attendance_hint_direct',
    'avaliado', 'avaliados', 'aviso', 'baixa', 'categoria_nivel',
    'clear', 'conforme', 'contactar_todos', 'copiar_emails', 'copy_emails_prompt',
    'courses_filtered_count', 'critico', 'days', 'disciplina', 'doc_assign_complete',
    'doc_assign_corrected', 'doc_assign_coverage', 'doc_assign_existing', 'doc_assign_groups_help', 'doc_assign_help',
    'doc_assign_in_progress', 'doc_assign_individual', 'doc_assign_name', 'doc_assign_no_assignments', 'doc_assign_no_submissions',
    'doc_assign_not_started', 'doc_assign_offline', 'doc_assign_own', 'doc_assign_own_header', 'doc_assign_pending',
    'doc_assign_percent', 'doc_assign_progress', 'doc_assign_received', 'doc_assign_sheet', 'doc_assign_team',
    'doc_assign_title', 'doc_assign_total', 'doc_assign_type', 'doc_assign_view', 'doc_assign_window',
    'doc_course_more', 'doc_course_open', 'doc_forum_coverage', 'doc_forum_empty', 'doc_forum_existing_topics',
    'doc_forum_hidden', 'doc_forum_last', 'doc_forum_messages', 'doc_forum_no_posts', 'doc_forum_none',
    'doc_forum_open', 'doc_forum_period', 'doc_forum_replies', 'doc_forum_topics', 'doc_forum_view',
    'doc_forum_window', 'doc_forum_with_participation', 'doc_forum_without_participation', 'doc_forums_total', 'em_dia',
    'emails_copiados', 'emails_copied_client', 'erro_carregar', 'erro_carregar_categorias', 'erro_rede',
    'estudantes_limiar', 'exportar_xlsx', 'filtered_count', 'filtered_suffix', 'inactivo',
    'invalid_json_response', 'invalid_response', 'mensagem', 'message_confirm', 'meta_ok_corrigir',
    'moderada', 'mostrar_mais', 'no_assessable_activities', 'no_course_matches', 'no_courses',
    'no_courses_scope', 'no_results', 'no_students_without_access', 'not_available', 'nunca_acedeu',
    'older_than_days', 'open_in_moodle', 'pesquisar_disciplinas', 'por_avaliar', 'records_threshold',
    'refine_search', 'requer_atencao', 'request_failed', 'request_too_long', 'retry',
    'risk_no_access_record', 'risk_scope_courses', 'risk_scope_platform', 'risk_total_range', 'risk_unit_records',
    'risk_unit_students', 'score_activities', 'score_calculation_hint', 'score_course', 'score_final',
    'score_forum_posts', 'score_graded', 'score_high', 'score_level_high', 'score_level_low',
    'score_level_moderate', 'score_level_none', 'score_maximum', 'score_moderate', 'score_period',
    'score_raw', 'score_resources', 'score_scope_note', 'score_view', 'search_course',
    'search_results_for', 'select_period_first', 'sem_emails', 'sem_submissoes', 'sem_submissoes_act',
    'sempre', 'showing_courses', 'since_platform_start', 'single_list', 'split_groups',
    'submetidos', 'todas', 'todas_categorias', 'todas_disciplinas', 'todas_disciplinas_nivel',
    'todas_neste_nivel', 'ver',
] as $diekey) {
    $dieuistrings[$diekey] = get_string($diekey, 'local_mulima_analytics');
}
$dieuistrings['module_names'] = [
    'quiz' => get_string('mod_quiz', 'local_mulima_analytics'),
    'assign' => get_string('mod_assign', 'local_mulima_analytics'),
    'resource' => get_string('activity_file', 'local_mulima_analytics'),
    'url' => get_string('activity_url', 'local_mulima_analytics'),
    'page' => get_string('activity_page', 'local_mulima_analytics'),
    'forum' => get_string('mod_forum', 'local_mulima_analytics'),
    'folder' => get_string('activity_folder', 'local_mulima_analytics'),
    'scorm' => get_string('mod_scorm', 'local_mulima_analytics'),
    'lesson' => get_string('mod_lesson', 'local_mulima_analytics'),
    'wiki' => get_string('activity_wiki', 'local_mulima_analytics'),
    'workshop' => get_string('mod_workshop', 'local_mulima_analytics'),
    'attendance' => get_string('mod_attendance', 'local_mulima_analytics'),
    'h5p' => get_string('mod_h5p', 'local_mulima_analytics'),
    'h5pactivity' => get_string('mod_h5p', 'local_mulima_analytics'),
    'glossary' => get_string('modulename', 'glossary'),
    'data' => get_string('modulename', 'data'),
    'lti' => get_string('modulename', 'lti'),
];
$dieuistrings['locale'] = str_replace('_', '-', current_language());
?>
window.DIE_LANG = <?php echo json_encode($dieuistrings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
function dieText(key, values) {
  var template = DIE_LANG[key] || '';
  if (values && typeof values === 'object') {
    return template.replace(/\{\$a->([a-zA-Z_][a-zA-Z_0-9]*)\}/g, function(match, field) {
      return values[field] === undefined ? match : String(values[field]);
    });
  }
  return template.replace(/\{\$a\}/g, function() { return values === undefined ? '' : String(values); });
}
function dieMostrarMais(n) { return dieText('mostrar_mais', n); }
function dieEmailsCopiados(n) { return dieText('emails_copiados', n); }
function dieEstudantesLimiar(n, d) { return dieText('estudantes_limiar', {n:n, d:d}); }
</script>

<style>
/* Table styles (Tailwind can't target thead/td directly with JIT) */
#die-root table.die-t thead th {
  background: #f8fafc;
  color: #64748b;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
  padding: 10px 14px;
  border-bottom: 2px solid #e2e8f0;
  text-align: left;
  white-space: nowrap;
  position: sticky;
  top: 0;
  z-index: 2;
}
#die-root table.die-t thead th.c { text-align: center; }
#die-root table.die-t tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .1s; }
#die-root table.die-t tbody tr:last-child { border-bottom: none; }
#die-root table.die-t tbody td { padding: 10px 14px; vertical-align: middle; font-size: 12px; }
#die-root table.die-t tbody td.c { text-align: center; }

/* Select/input consistent focus */
#die-root .die-select:disabled { opacity: .45; cursor: not-allowed; background: #f8fafc; }
#die-root .die-select, #die-root .die-input {
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}

/* fg label */
#die-root .die-fg > label:not([class]) {
  font-size: 10px; font-weight: 700; color: #64748b;
  text-transform: uppercase; letter-spacing: .04em; display: block; margin-bottom: 5px;
}

/* Responsive grid helpers */
@media (max-width: 640px) {
  #die-root .die-stats { grid-template-columns: repeat(2, 1fr) !important; }
  #die-root .die-filters { flex-direction: column; }
  #die-root .die-fg { min-width: 100% !important; }
  #die-root .die-modal-box { max-height: 95vh; border-radius: 12px; }
}
@media (max-width: 400px) {
  #die-root .die-stats { grid-template-columns: 1fr !important; }
}
</style>


<script>
/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   DIE COURSE SEARCH - shared autocomplete component
   Each page sets window.onCourseSelected(id, name) and
   window.onCourseAll() for the "all courses" action.
   ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
window.DieCourseSearch = (function(){
  var _list = [];
  var _hiddenId = null; // id of the hidden input that holds selected value

  function G(id){ return document.getElementById(id); }
  function esc(s){ var d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }

  function setList(courses, hiddenInputId){
    _list = courses || [];
    _hiddenId = hiddenInputId || 'flt_course';
    var inp = G('course_search_input');
    if (!inp) return;
    if (!_list.length){
      inp.disabled = true; inp.style.opacity = '0.45';
      inp.placeholder = DIE_LANG.no_courses; return;
    }
    inp.disabled = false; inp.style.opacity = '1';
    inp.placeholder = DIE_LANG.search_course;
    inp.value = '';
    if (G('course_clear_btn')) G('course_clear_btn').style.display = 'none';
    if (G(_hiddenId)) G(_hiddenId).value = '';
  }

  function setLoading(){
    var inp = G('course_search_input');
    if (inp){ inp.disabled=true; inp.style.opacity='0.6'; inp.placeholder=DIE_LANG.a_carregar; }
  }

  function disable(txt){
    var inp = G('course_search_input');
    if (inp){ inp.disabled=true; inp.style.opacity='0.45'; inp.value=''; inp.placeholder=txt||'- -'; }
    close();
    if (G('course_clear_btn')) G('course_clear_btn').style.display = 'none';
    if (G(_hiddenId)) G(_hiddenId).value = '';
    _list = [];
  }

  function filter(){
    var q = (G('course_search_input').value||'').toLowerCase().trim();
    var items = q ? _list.filter(function(c){
      return c.name.toLowerCase().indexOf(q) >= 0 || (c.short||'').toLowerCase().indexOf(q) >= 0;
    }) : _list;
    renderDropdown(items, q);
  }

  function open(){
    if (_list.length) renderDropdown(_list, '');
  }

  function close(){
    var dd = G('course_dropdown');
    if (dd) dd.style.display = 'none';
  }

  function renderDropdown(list, q){
    var dd = G('course_dropdown');
    if (!dd || !_list.length){ if(dd) dd.style.display='none'; return; }
    var html = '<div onclick="DieCourseSearch.selectAll()" '
      + 'style="padding:9px 12px;cursor:pointer;border-bottom:1px solid #e2e8f0;font-size:12px;font-weight:700;color:var(--die-acc);display:flex;align-items:center;gap:6px">'
      + '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 12h6M9 15h4"/></svg>'
      + ' '+esc(dieText('all_courses_count',_list.length))+'</div>';
    list.slice(0, 30).forEach(function(c){
      var n = esc(c.name);
      if (q) {
        var qr = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        n = n.replace(new RegExp('(' + qr + ')', 'gi'), '<mark style="background:#fef3c7;border-radius:2px">$1</mark>');
      }
      html += '<div onclick="DieCourseSearch.select(' + c.id + ',\'' + esc(c.name).replace(/'/g,"\\'") + '\')" '
        + 'style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f8fafc;font-size:12px;display:flex;flex-direction:column;gap:2px">'
        + '<span style="font-weight:600;white-space:normal;overflow-wrap:anywhere">' + n + '</span>'
        + '</div>';
    });
    if (list.length > 30) {
      html += '<div style="padding:8px 12px;font-size:10px;color:#94a3b8;text-align:center">+'
        +esc(dieText('showing_courses',{shown:30,total:list.length}))+'</div>';
    }
    dd.innerHTML = html;
    dd.style.display = 'block';
  }

  function select(id, name){
    if (G('course_search_input')) G('course_search_input').value = name;
    if (G('course_clear_btn')) G('course_clear_btn').style.display = 'flex';
    if (G(_hiddenId)) G(_hiddenId).value = id;
    close();
    if (window.onCourseSelected) window.onCourseSelected(+id, name);
  }

  function selectAll(){
    if (G('course_search_input')) G('course_search_input').value = '';
    if (G('course_clear_btn')) G('course_clear_btn').style.display = 'none';
    if (G(_hiddenId)) G(_hiddenId).value = '';
    close();
    if (window.onCourseAll) window.onCourseAll();
  }

  function clear(){
    if (G('course_search_input')) G('course_search_input').value = '';
    if (G('course_clear_btn')) G('course_clear_btn').style.display = 'none';
    if (G(_hiddenId)) G(_hiddenId).value = '';
    close();
    if (window.onCourseAll) window.onCourseAll();
  }

  return { setList:setList, setLoading:setLoading, disable:disable,
           filter:filter, open:open, close:close, select:select,
           selectAll:selectAll, clear:clear };
})();

// Global bindings used by HTML onXxx attributes
function courseSearchFilter(){ DieCourseSearch.filter(); }
function courseSearchOpen(){   DieCourseSearch.open(); }
function courseSearchClose(){  DieCourseSearch.close(); }
function courseSearchClear(){  DieCourseSearch.clear(); }
</script>

<script>
/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   DieCourseSearch - campo de pesquisa com autocomplete
   para substituir o select de disciplina em todas as páginas

   Uso no HTML:
     DieCourseSearch.render('container_id', {
       onSelect: function(id, name){ ... },   // disciplina escolhida
       onAll   : function(){ ... }            // "Todas as disciplinas"
     });

   Quando os cursos carregam:
     DieCourseSearch.setList([{id,name,short,cat}, ...]);
   ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
window.DieCourseSearch = (function(){
  var _list    = [];   // full list of courses for current cat/period
  var _cfg     = {};
  var _open    = false;
  var _sel_id  = 0;
  var _sel_name= '';

  // ── HTML skeleton (injected once per page) ─────────────────
  var WRAP_ID = 'dcs-wrap';

  function render(containerId, cfg){
    _cfg = cfg || {};
    var c = document.getElementById(containerId);
    if (!c) return;
    c.innerHTML =
      '<div style="position:relative">'
      // Search icon
      +'<svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;z-index:1" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>'
      // Input
      +'<input id="dcs-input" type="text" autocomplete="off" placeholder="'+esc(DIE_LANG.search_course)+'" disabled'
      +' style="width:100%;padding:9px 30px 9px 32px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:12px;font-family:inherit;outline:none;background:#f8fafc;transition:border .15s,background .15s;color:#0f172a"'
      +' oninput="DieCourseSearch._filter()"'
      +' onfocus="DieCourseSearch._open()"'
      +' onkeydown="DieCourseSearch._key(event)">'
      // Clear button
      +'<button id="dcs-clear" onclick="DieCourseSearch.clear()" style="display:none;position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;padding:3px;line-height:0">'
      +'<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>'
      // Dropdown
      +'<div id="dcs-drop" style="display:none;position:absolute;top:calc(100% + 5px);left:0;right:0;background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;box-shadow:0 12px 32px rgba(0,0,0,.13);z-index:9999;max-height:260px;overflow-y:auto"></div>'
      +'</div>'
      // Hidden input for compatibility
      +'<input type="hidden" id="dcs-value" value="">';
  }

  // ── Set course list (called after AJAX loads courses) ──────
  function setList(list){
    _list     = list || [];
    _sel_id   = 0;
    _sel_name = '';
    var inp = document.getElementById('dcs-input');
    if (inp){
      inp.value       = '';
      inp.disabled    = (_list.length === 0);
      inp.style.background    = _list.length ? '#fff' : '#f8fafc';
      inp.style.pointerEvents = _list.length ? 'auto' : 'none';
      inp.style.opacity       = _list.length ? '1' : '0.5';
      inp.placeholder = _list.length
        ? dieText('pesquisar_disciplinas',_list.length)
        : DIE_LANG.no_courses;
    }
    var clr = document.getElementById('dcs-clear');
    if (clr) clr.style.display = 'none';
    _closeDrop();
    // Auto-trigger "all" view
    if (_cfg.onAll && _list.length) _cfg.onAll();
  }

  // ── Filter & show dropdown ─────────────────────────────────
  function _filter(){
    var inp = document.getElementById('dcs-input');
    if (!inp) return;
    var q = inp.value.trim().toLowerCase();
    var clr = document.getElementById('dcs-clear');
    if (clr) clr.style.display = q ? 'block' : 'none';
    // If input cleared → back to "all"
    if (!q){
      _sel_id = 0; _sel_name = '';
      var hid = document.getElementById('dcs-value');
      if (hid) hid.value = '';
      if (_cfg.onAll) _cfg.onAll();
      _closeDrop();
      return;
    }
    var matches = _list.filter(function(c){
      return c.name.toLowerCase().indexOf(q) >= 0
          || (c.short||'').toLowerCase().indexOf(q) >= 0
          || (c.cat||'').toLowerCase().indexOf(q) >= 0;
    }).slice(0, 30);
    _renderDrop(matches, q);
  }

  function _open(){
    var inp = document.getElementById('dcs-input');
    if (!inp || inp.disabled) return;
    inp.style.borderColor   = 'var(--die-acc)';
    inp.style.boxShadow     = '0 0 0 3px rgba(var(--die-acc-rgb),.15)';
    var q = inp.value.trim().toLowerCase();
    if (q){
      _filter();
    } else {
      // Show all first 20 when focused empty
      _renderDrop(_list.slice(0, 20), '');
    }
  }

  function _closeDrop(){
    var d = document.getElementById('dcs-drop');
    if (d) d.style.display = 'none';
    _open = false;
    var inp = document.getElementById('dcs-input');
    if (inp){
      inp.style.borderColor = '#e2e8f0';
      inp.style.boxShadow   = 'none';
    }
  }

  function _renderDrop(items, q){
    var d = document.getElementById('dcs-drop');
    if (!d) return;
    if (!items.length){
      d.innerHTML = '<div style="padding:12px 14px;font-size:12px;color:#94a3b8;text-align:center">' + esc(DIE_LANG.no_results) + '</div>';
      d.style.display = 'block';
      return;
    }
    var html = '';
    // "Todas" option at top
    html += '<div onclick="DieCourseSearch._selectAll()" style="padding:10px 14px;border-bottom:1px solid #f1f5f9;cursor:pointer;font-size:12px;font-weight:700;color:var(--die-acc);display:flex;align-items:center;gap:8px" onmouseover="this.style.background=\'#f8fafc\'" onmouseout="this.style.background=\'\'"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>'+esc(dieText('all_courses_count',_list.length))+'</div>';
    items.forEach(function(c){
      var hi = q ? c.name.replace(new RegExp('('+q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')','gi'),'<mark style="background:#fef3c7;border-radius:2px;padding:0 1px">$1</mark>') : c.name;
      html += '<div onclick="DieCourseSearch._selectItem('+c.id+',\''+c.name.replace(/\\/g,'\\\\').replace(/'/g,"\\'")+'\')"'
        +' style="padding:9px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9" onmouseover="this.style.background=\'#f8fafc\'" onmouseout="this.style.background=\'\'">'
        +'<div style="font-size:12px;font-weight:600;color:#0f172a;white-space:normal;overflow-wrap:anywhere">'+hi+'</div>'
        +'</div>';
    });
    if (items.length === 30) html += '<div style="padding:8px 14px;font-size:10px;color:#94a3b8;text-align:center">'+esc(DIE_LANG.refine_search)+'</div>';
    d.innerHTML = html;
    d.style.display = 'block';
    _open = true;
  }

  function _selectItem(id, name){
    _sel_id = id; _sel_name = name;
    var inp = document.getElementById('dcs-input');
    if (inp){ inp.value = name; inp.style.borderColor='var(--die-acc)'; }
    var hid = document.getElementById('dcs-value');
    if (hid) hid.value = id;
    var clr = document.getElementById('dcs-clear');
    if (clr) clr.style.display = 'block';
    _closeDrop();
    if (_cfg.onSelect) _cfg.onSelect(id, name);
  }

  function _selectAll(){
    clear();
    if (_cfg.onAll) _cfg.onAll();
  }

  function _key(e){
    var d = document.getElementById('dcs-drop');
    if (!d || d.style.display === 'none') return;
    var items = d.querySelectorAll('[onclick*="_selectItem"]');
    if (e.key === 'Escape'){ _closeDrop(); return; }
    if (e.key === 'Enter'){
      var focused = d.querySelector('[data-focused]');
      if (focused) focused.click();
      return;
    }
  }

  function clear(){
    _sel_id = 0; _sel_name = '';
    var inp = document.getElementById('dcs-input');
    if (inp){ inp.value = ''; inp.focus(); }
    var hid = document.getElementById('dcs-value');
    if (hid) hid.value = '';
    var clr = document.getElementById('dcs-clear');
    if (clr) clr.style.display = 'none';
    _closeDrop();
  }

  function disable(msg){
    var inp = document.getElementById('dcs-input');
    if (!inp) return;
    inp.value = '';
    inp.placeholder = msg || DIE_LANG.select_period_first;
    inp.disabled    = true;
    inp.style.background    = '#f8fafc';
    inp.style.opacity       = '0.5';
    inp.style.pointerEvents = 'none';
    inp.style.borderColor   = '#e2e8f0';
    var clr = document.getElementById('dcs-clear');
    if (clr) clr.style.display = 'none';
    _closeDrop();
    _list = [];
  }

  function esc(s){ var d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }

  // Close dropdown when clicking outside
  document.addEventListener('click', function(e){
    var wrap = document.getElementById('dcs-wrap');
    if (wrap && !wrap.contains(e.target)) _closeDrop();
    var inp = document.getElementById('dcs-input');
    if (inp && !inp.contains(e.target)) _closeDrop();
    var d = document.getElementById('dcs-drop');
    if (d && !d.contains(e.target) && e.target !== inp) _closeDrop();
  });

  return {
    render      : render,
    setList     : setList,
    clear       : clear,
    disable     : disable,
    _filter     : _filter,
    _open       : _open,
    _closeDrop  : _closeDrop,
    _selectItem : _selectItem,
    _selectAll  : _selectAll,
    _key        : _key,
    getValue    : function(){ return _sel_id; },
    getName     : function(){ return _sel_name; },
  };
})();
</script>

<style>
/* ── DieCourseSearch ─────────────────────────────────────────── */
.dcs-wrap { position:relative; flex:1; min-width:180px; }
.dcs-input-row { display:flex; align-items:center; gap:0; background:#fff; border:1.5px solid #e2e8f0; border-radius:9px; transition:border .15s, box-shadow .15s; overflow:hidden; }
.dcs-input-row:focus-within { border-color:var(--die-acc); box-shadow:0 0 0 3px rgba(var(--die-acc-rgb),.12); }
.dcs-input-row.disabled { opacity:.45; pointer-events:none; background:#f8fafc; }
.dcs-icon { padding:0 10px; color:#94a3b8; flex-shrink:0; display:flex; align-items:center; }
.dcs-inp { flex:1; padding:9px 4px 9px 0; font-size:12px; border:none; outline:none; background:transparent; font-family:inherit; color:#0f172a; min-width:0; }
.dcs-inp::placeholder { color:#94a3b8; }
.dcs-clear { padding:0 10px; color:#94a3b8; background:none; border:none; cursor:pointer; font-size:14px; line-height:1; display:none; flex-shrink:0; }
.dcs-clear:hover { color:#475569; }
.dcs-badge { font-size:9px; font-weight:700; padding:2px 7px; border-radius:20px; background:rgba(var(--die-acc-rgb),.12); color:var(--die-acc); margin-right:6px; white-space:nowrap; flex-shrink:0; }
.dcs-spin { width:14px; height:14px; border:2px solid #e2e8f0; border-top-color:var(--die-acc); border-radius:50%; animation:die-rotate .7s linear infinite; margin-right:10px; flex-shrink:0; }

/* Dropdown */
.dcs-drop { position:absolute; top:calc(100% + 4px); left:0; right:0; background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow:0 12px 32px rgba(0,0,0,.14); z-index:9000; max-height:min(300px, 45vh); overflow:hidden; display:flex; flex-direction:column; }
.dcs-drop-search { padding:8px 12px 6px; border-bottom:1px solid #f1f5f9; flex-shrink:0; font-size:11px; color:#64748b; }
.dcs-drop-list { overflow-y:auto; flex:1; }
.dcs-drop-item { padding:8px 14px; cursor:pointer; display:flex; align-items:center; gap:10px; transition:background .1s; font-size:12px; border-bottom:1px solid #f8fafc; }
.dcs-drop-item:hover, .dcs-drop-item.active { background:rgba(var(--die-acc-rgb),.06); }
.dcs-drop-item.all-opt { font-weight:700; color:var(--die-acc); border-bottom:1px solid #e2e8f0; }
.dcs-drop-name { flex:1; min-width:0; font-weight:600; color:#0f172a; white-space:normal; overflow-wrap:anywhere; line-height:1.4; }
.dcs-drop-empty { padding:20px; text-align:center; color:#94a3b8; font-size:12px; }
.dcs-drop-count { padding:6px 14px; font-size:10px; color:#94a3b8; border-top:1px solid #f1f5f9; background:#f8fafc; flex-shrink:0; }
</style>

<script>
/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   DieCourseSearch - campo de pesquisa com autocomplete AJAX
   
   Uso:
     DieCourseSearch.mount('container_id', {
       onSelect : function(id, name) { ... },
       onAll    : function() { ... }
     });
     DieCourseSearch.setLoading();
     DieCourseSearch.setList([ {id, name, short, cat}, ... ]);
     DieCourseSearch.disable('placeholder text');
     DieCourseSearch.clear();
   ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
window.DieCourseSearch = (function(){
  var _el      = null;   // container element
  var _inp     = null;   // text input
  var _drop    = null;   // dropdown div
  var _list    = null;   // scrollable list div
  var _badge   = null;   // course count badge
  var _clear   = null;   // clear button
  var _row     = null;   // input row div
  var _spin    = null;   // spinner
  var _courses = [];     // full course list
  var _selectedId   = 0;
  var _selectedName = '';
  var _debounce = null;
  var _cb = { onSelect:null, onAll:null };
  var _open = false;
  var _idx = -1;         // keyboard navigation index

  // ── Build DOM ───────────────────────────────────────────────
  function mount(containerId, opts) {
    _cb = opts || {};
    _el = document.getElementById(containerId);
    if (!_el) return;

    _el.innerHTML =
      '<label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide block mb-1.5">'+esc(DIE_LANG.disciplina)+'</label>'
      + '<div class="dcs-wrap">'
      +   '<div class="dcs-input-row disabled" id="dcs_row">'
      +     '<span class="dcs-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>'
      +     '<input class="dcs-inp" id="dcs_inp" type="text" placeholder="" autocomplete="off" disabled>'
      +     '<span class="dcs-badge" id="dcs_badge" style="display:none"></span>'
      +     '<div class="dcs-spin" id="dcs_spin" style="display:none"></div>'
      +     '<button class="dcs-clear" id="dcs_clear" onclick="DieCourseSearch.clear()" title="'+esc(DIE_LANG.clear)+'">✕</button>'
      +   '</div>'
      +   '<div class="dcs-drop" id="dcs_drop" style="display:none">'
      +     '<div class="dcs-drop-search" id="dcs_drop_hdr"></div>'
      +     '<div class="dcs-drop-list" id="dcs_list"></div>'
      +     '<div class="dcs-drop-count" id="dcs_count" style="display:none"></div>'
      +   '</div>'
      + '</div>';

    _inp   = document.getElementById('dcs_inp');
    _drop  = document.getElementById('dcs_drop');
    _list  = document.getElementById('dcs_list');
    _badge = document.getElementById('dcs_badge');
    _clear = document.getElementById('dcs_clear');
    _row   = document.getElementById('dcs_row');
    _spin  = document.getElementById('dcs_spin');

    // Events
    _inp.addEventListener('input', function(){ clearTimeout(_debounce); _debounce = setTimeout(render, 90); });
    _inp.addEventListener('focus', function(){ if (_courses.length) openDrop(); });
    _inp.addEventListener('keydown', onKey);
    document.addEventListener('mousedown', function(e){ if (_el && !_el.contains(e.target)) closeDrop(); });
  }

  function onKey(e) {
    if (!_open) { if(e.key==='ArrowDown'||e.key==='Enter') openDrop(); return; }
    var items = _list.querySelectorAll('.dcs-drop-item');
    if (e.key === 'ArrowDown')  { e.preventDefault(); _idx = Math.min(_idx+1, items.length-1); highlightItem(items); }
    else if (e.key === 'ArrowUp'){ e.preventDefault(); _idx = Math.max(_idx-1, 0); highlightItem(items); }
    else if (e.key === 'Enter') { e.preventDefault(); if (_idx>=0 && items[_idx]) items[_idx].click(); }
    else if (e.key === 'Escape'){ closeDrop(); }
  }
  function highlightItem(items) {
    items.forEach(function(it,i){ it.classList.toggle('active', i===_idx); });
    if (items[_idx]) items[_idx].scrollIntoView({block:'nearest'});
  }

  // ── Public API ──────────────────────────────────────────────
  function setLoading() {
    _courses = []; _selectedId = 0; _selectedName = '';
    _inp.value = ''; _inp.disabled = true; _inp.placeholder = DIE_LANG.a_carregar;
    _row.classList.add('disabled');
    if(_badge) { _badge.style.display='none'; }
    if(_clear) { _clear.style.display='none'; }
    if(_spin)  { _spin.style.display='block'; }
    closeDrop();
  }

  function setList(courses) {
    _courses = courses || [];
    _selectedId = 0; _selectedName = '';
    _inp.disabled = false;
    _inp.placeholder = _courses.length ? dieText('pesquisar_disciplinas',_courses.length) : DIE_LANG.no_courses;
    _row.classList.remove('disabled');
    if(_spin)  { _spin.style.display='none'; }
    if(_clear) { _clear.style.display='none'; }
    if(_badge) {
      _badge.textContent = _courses.length;
      _badge.style.display = _courses.length ? 'inline-flex' : 'none';
    }
    // Trigger onAll immediately when list is loaded
    if (_cb.onAll && _courses.length) _cb.onAll();
  }

  function disable(placeholder) {
    _courses = []; _selectedId = 0; _selectedName = '';
    _inp.value = ''; _inp.disabled = true;
    _inp.placeholder = placeholder === undefined ? '' : placeholder;
    _row.classList.add('disabled');
    if(_badge) { _badge.style.display='none'; }
    if(_clear) { _clear.style.display='none'; }
    if(_spin)  { _spin.style.display='none'; }
    closeDrop();
  }

  function clear() {
    _selectedId = 0; _selectedName = '';
    _inp.value = '';
    if(_clear) { _clear.style.display='none'; }
    openDrop();
    if (_cb.onAll) _cb.onAll();
  }

  function getSelectedId()   { return _selectedId; }
  function getSelectedName() { return _selectedName; }

  // ── Dropdown ────────────────────────────────────────────────
  function openDrop() {
    if (!_courses.length) return;
    _open = true; _idx = -1;
    _drop.style.display = 'flex';
    render();
  }
  function closeDrop() {
    _open = false;
    if (_drop) _drop.style.display = 'none';
  }

  function render() {
    if (!_open) openDrop();
    var q = (_inp.value || '').toLowerCase().trim();
    var filtered = q
      ? _courses.filter(function(c){ return c.name.toLowerCase().indexOf(q)>=0 || (c.short||'').toLowerCase().indexOf(q)>=0 || (c.cat||'').toLowerCase().indexOf(q)>=0; })
      : _courses;

    var hdr = document.getElementById('dcs_drop_hdr');
    var cnt = document.getElementById('dcs_count');
    if (hdr) hdr.textContent = q ? dieText('search_results_for',q) : DIE_LANG.todas_disciplinas_nivel;

    _list.innerHTML = '';
    _idx = -1;

    // "All" option
    var all = document.createElement('div');
    all.className = 'dcs-drop-item all-opt';
    all.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>'
      + '<span>'+esc(dieText('all_courses_count',_courses.length))+'</span>';
    all.addEventListener('mousedown', function(e){ e.preventDefault(); _inp.value=''; if(_clear)_clear.style.display='none'; _selectedId=0; _selectedName=''; closeDrop(); if(_cb.onAll)_cb.onAll(); });
    _list.appendChild(all);

    if (!filtered.length) {
      var empty = document.createElement('div');
      empty.className = 'dcs-drop-empty';
      empty.textContent = q ? dieText('no_course_matches',q) : DIE_LANG.no_courses;
      _list.appendChild(empty);
    } else {
      filtered.slice(0, 60).forEach(function(c) {
        var item = document.createElement('div');
        item.className = 'dcs-drop-item';
        var name = document.createElement('div');
        name.className = 'dcs-drop-name';
        name.textContent = c.name;
        item.appendChild(name);
        item.addEventListener('mousedown', function(e){
          e.preventDefault();
          _selectedId   = c.id;
          _selectedName = c.name;
          _inp.value    = c.name;
          if(_clear) _clear.style.display = 'block';
          if(_badge) _badge.style.display = 'none';
          closeDrop();
          if(_cb.onSelect) _cb.onSelect(c.id, c.name);
        });
        _list.appendChild(item);
      });
    }

    if (cnt) {
      if (filtered.length > 60) {
        cnt.textContent = dieText('showing_courses',{shown:60,total:filtered.length});
        cnt.style.display = 'block';
      } else {
        cnt.style.display = 'none';
      }
    }
  }

  function esc(s){ var d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }

  function clearSilent() {
    _selectedId = 0; _selectedName = '';
    if (_inp) _inp.value = '';
    if (_clear) _clear.style.display = 'none';
    closeDrop();
  }
  return { mount:mount, setList:setList, setLoading:setLoading, disable:disable, clear:clear, clearSilent:clearSilent, getSelectedId:getSelectedId, getSelectedName:getSelectedName };
})();
</script>
