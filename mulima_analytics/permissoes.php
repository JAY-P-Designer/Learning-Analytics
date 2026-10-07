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
 * Report permission settings.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/mulima_analytics/permissoes.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('title_permissoes', 'local_mulima_analytics'));
$PAGE->set_heading(''); // own back-link + card layout rendered below

$SK  = sesskey();
$AX  = (new moodle_url('/local/mulima_analytics/permissoes_ajax.php'))->out(false);
$REPORT_URL   = (new moodle_url('/local/mulima_analytics/index.php'))->out(false);
$CONFIG_URL   = (new moodle_url('/local/mulima_analytics/presenca_config.php'))->out(false);
$PERMISSIONS_URL = (new moodle_url('/local/mulima_analytics/permissoes.php'))->out(false);
// admin/search.php is stable across every Moodle version/theme - safer than
// guessing a theme settings section id, which changed in Moodle 4.4.
$SEARCH_URL = (new moodle_url('/admin/search.php', ['query'=>'custommenuitems']))->out(false);

echo $OUTPUT->header();
?>
<style>
#qap { max-width: 100%; margin: 0; font-family: inherit; }
#qap .qap-back { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:#64748b; text-decoration:none; margin-bottom:16px; }
#qap .qap-back:hover { color:#334155; }
#qap .qap-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; box-shadow:0 1px 3px rgba(0,0,0,.04); margin-bottom:16px; overflow:hidden; }
#qap .qap-head { padding:16px 20px; border-bottom:1px solid #f1f5f9; display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
#qap .qap-head h2 { font-size:14px; font-weight:800; color:#0f172a; margin:0 0 3px; display:flex; align-items:center; gap:8px; }
#qap .qap-head p { font-size:12px; color:#94a3b8; margin:0; line-height:1.5; }
#qap .qap-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:20px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.03em; white-space:nowrap; flex-shrink:0; }
#qap .qap-badge-on { background:#dcfce7; color:#15803d; }
#qap .qap-badge-off { background:#f1f5f9; color:#94a3b8; }
#qap .qap-badge-legacy { background:#fef3c7; color:#b45309; }
#qap .qap-body { padding:8px 20px; }
#qap .qap-row { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 0; border-bottom:1px solid #f8fafc; }
#qap .qap-row:last-child { border-bottom:none; }
#qap .qap-row-label { font-size:13px; font-weight:600; color:#1e293b; }
#qap .qap-row-sub { font-size:11px; color:#94a3b8; margin-top:1px; }
#qap .qap-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700; background:#f1f5f9; color:#94a3b8; }
#qap .qap-sw { position:relative; display:inline-block; width:42px; height:24px; flex-shrink:0; cursor:pointer; }
#qap .qap-sw input { opacity:0; width:0; height:0; }
#qap .qap-sw .qap-sw-track { position:absolute; inset:0; background:#e2e8f0; border-radius:24px; transition:.2s; }
#qap .qap-sw .qap-sw-track::before { content:''; position:absolute; width:18px; height:18px; left:3px; top:3px; background:#fff; border-radius:50%; transition:.2s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
#qap .qap-sw input:checked + .qap-sw-track { background:#2563eb; }
#qap .qap-sw input:checked + .qap-sw-track::before { transform: translateX(18px); }
#qap .qap-sw.disabled { opacity:.4; cursor:not-allowed; }
#qap .qap-navlabel { display:flex; align-items:center; gap:8px; padding:12px 0 16px; }
#qap .qap-navlabel label { font-size:11px; font-weight:700; color:#64748b; white-space:nowrap; }
#qap .qap-navlabel input { flex:1; padding:8px 11px; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; outline:none; color:#1e293b; }
#qap .qap-navlabel input:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.1); }
#qap .qap-loading { text-align:center; padding:40px; color:#94a3b8; font-size:12px; }
.qap-toast { position:fixed; bottom:24px; right:24px; z-index:9999; display:inline-flex; align-items:center;
  gap:10px; padding:12px 18px 12px 14px; border-radius:12px; color:#fff; font-size:13px; font-weight:600;
  box-shadow:0 10px 30px rgba(0,0,0,.22); opacity:0; transform:translateY(10px) scale(.97); transition:opacity .22s ease,transform .22s ease;
  pointer-events:none; max-width:340px; }
.qap-toast.show { opacity:1; transform:translateY(0) scale(1); }
.qap-toast .qap-toast-ico { display:flex; align-items:center; justify-content:center; width:20px; height:20px;
  border-radius:50%; background:rgba(255,255,255,.22); flex-shrink:0; }
#qap .qap-menucard-body { padding:16px 20px 18px; }
#qap .qap-codebox { display:block; width:100%; box-sizing:border-box; padding:11px 13px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:9px; font-size:12.5px; color:#1e293b; word-break:break-all; font-family:ui-monospace,Menlo,Consolas,monospace; }
#qap .qap-btnrow { display:flex; gap:8px; margin-top:10px; }
#qap .qap-btn-primary { flex:1.3; padding:10px; border-radius:9px; border:none; background:#2563eb; color:#fff; font-size:12.5px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; transition:background .15s; }
#qap .qap-btn-primary:hover { background:#1d4ed8; }
#qap .qap-btn-primary:disabled { opacity:.55; cursor:not-allowed; }
#qap .qap-btn-secondary { flex:1; padding:10px; border-radius:9px; border:1px solid #e2e8f0; background:#fff; color:#334155; font-size:12.5px; font-weight:700; cursor:pointer; text-decoration:none; display:flex; align-items:center; justify-content:center; gap:6px; }
#qap .qap-btn-secondary:hover { border-color:#cbd5e1; background:#f8fafc; }
#qap .qap-hint { font-size:11px; color:#94a3b8; margin-top:10px; line-height:1.5; }
#qap .qap-hint a { color:#2563eb; text-decoration:none; }
#qap .qap-hint a:hover { text-decoration:underline; }
</style>

<div class="die-config-shell">
  <div class="die-config-header"><div>
    <div class="die-config-eyebrow"><?php echo s(get_string('cfg_eyebrow', 'local_mulima_analytics')); ?></div>
    <h1 class="die-config-title"><?php echo s(get_string('cfg_page_title', 'local_mulima_analytics')); ?></h1>
    <p class="die-config-subtitle"><?php echo s(get_string('cfg_permissions_subtitle', 'local_mulima_analytics')); ?></p>
  </div></div>
  <nav class="die-config-tabs" aria-label="<?php echo s(get_string('cfg_page_title', 'local_mulima_analytics')); ?>">
    <a class="die-config-tab" href="<?php echo $CONFIG_URL; ?>"><i class="fa fa-cog"></i> <?php echo s(get_string('cfg_tab_general', 'local_mulima_analytics')); ?></a>
    <a class="die-config-tab is-active" aria-current="page" href="<?php echo $PERMISSIONS_URL; ?>"><i class="fa fa-lock"></i> <?php echo s(get_string('cfg_tab_permissions', 'local_mulima_analytics')); ?></a>
    <a class="die-config-tab is-return" href="<?php echo $REPORT_URL; ?>"><i class="fa fa-arrow-left"></i> <?php echo s(get_string('cfg_tab_lists', 'local_mulima_analytics')); ?></a>
  </nav>
<div id="qap">

  <!-- Menu do tema - via oficial Moodle 4+, primeiro porque é a que resulta -->
  <div class="qap-card">
    <div class="qap-head">
      <h2>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.5"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        <?php echo s(get_string('qap_menu_title', 'local_mulima_analytics')); ?>
      </h2>
      <span class="qap-badge qap-badge-off" id="menu_badge"><?php echo s(get_string('qap_verifying', 'local_mulima_analytics')); ?></span>
    </div>
    <div class="qap-menucard-body">
      <p style="font-size:12px;color:#94a3b8;margin:0 0 12px;line-height:1.5">
        <?php echo s(get_string('qap_menu_desc', 'local_mulima_analytics')); ?>
      </p>
      <div class="qap-navlabel" style="padding-top:0">
        <label for="navlabel"><?php echo s(get_string('qap_menu_name', 'local_mulima_analytics')); ?></label>
        <input type="text" id="navlabel" placeholder="<?php echo s(get_string('pluginname', 'local_mulima_analytics')); ?>" onchange="onNavLabel()">
      </div>
      <code class="qap-codebox" id="menuline_box"><?php echo s(get_string('qap_generating', 'local_mulima_analytics')); ?></code>
      <div class="qap-btnrow">
        <button type="button" class="qap-btn-primary" id="btn_addmenu" onclick="addToMenu()">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
          <?php echo s(get_string('qap_menu_add', 'local_mulima_analytics')); ?>
        </button>
        <button type="button" class="qap-btn-secondary" onclick="copyMenuLine()"><?php echo s(get_string('qap_menu_copy', 'local_mulima_analytics')); ?></button>
      </div>
      <div class="qap-hint">
        <?php echo get_string('qap_menu_hint', 'local_mulima_analytics', '<a href="'.$SEARCH_URL.'" target="_blank">'.s(get_string('qap_menu_hint_link', 'local_mulima_analytics')).'</a>'); ?>
      </div>
    </div>
  </div>

  <div class="qap-card">
    <div class="qap-head">
      <h2><?php echo s(get_string('qap_roles_title', 'local_mulima_analytics')); ?></h2>
      <p></p>
    </div>
    <div class="qap-body" id="roles_body">
      <div class="qap-loading"><?php echo s(get_string('qap_roles_loading', 'local_mulima_analytics')); ?></div>
    </div>
  </div>

  <?php if (false): // Removed legacy duplicate link. ?>
  <a class="qap-advanced" href="#">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
    <?php echo s(get_string('qap_advanced', 'local_mulima_analytics')); ?>
  </a>
  <?php endif; ?>

  <div class="qap-toast" id="qap_toast"><span class="qap-toast-ico" id="qap_toast_ico"></span><span id="qap_toast_msg"></span></div>
</div>
</div>

<script>
var SK = '<?php echo $SK; ?>', AX = '<?php echo $AX; ?>';
var QAP_LANG = <?php echo json_encode([
    'protected'   => get_string('qap_role_protected', 'local_mulima_analytics'),
    'sempre'      => get_string('sempre', 'local_mulima_analytics'),
    'granted'     => get_string('qap_access_granted', 'local_mulima_analytics'),
    'removed'     => get_string('qap_access_removed', 'local_mulima_analytics'),
    'updated'     => get_string('qap_name_updated', 'local_mulima_analytics'),
    'menuadded'   => get_string('qap_menu_added', 'local_mulima_analytics'),
    'linecopied'  => get_string('qap_line_copied', 'local_mulima_analytics'),
    'menu_add'    => get_string('qap_menu_add', 'local_mulima_analytics'),
    'menu_update' => get_string('qap_menu_update', 'local_mulima_analytics'),
    'menu_inmenu' => get_string('qap_menu_inmenu', 'local_mulima_analytics'),
    'menu_notinmenu' => get_string('qap_menu_notinmenu', 'local_mulima_analytics'),
    'roles_none'  => get_string('qap_roles_none', 'local_mulima_analytics'),
    'invalid_response' => get_string('invalid_response', 'local_mulima_analytics'),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function b64EncodeUtf8(str) {
  return window.btoa(unescape(encodeURIComponent(str || '')));
}
function b64DecodeUtf8(str) {
  try { return decodeURIComponent(escape(window.atob(str || ''))); }
  catch (e) { return ''; }
}

function qapFetch(url, opts) {
  return fetch(url, opts).then(function(r) {
    return r.text().then(function(txt) {
      var data;
      try { data = JSON.parse(txt); }
      catch (e) {
        var snippet = txt.replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim().substring(0,160);
        throw new Error('HTTP ' + r.status + (snippet ? ': ' + snippet : ''));
      }
      if (data && data.ok === false) throw new Error(data.error || QAP_LANG.invalid_response);
      return data;
    });
  });
}
function qapPost(op, params) {
  var body = new URLSearchParams(Object.assign({op: op, sesskey: SK}, params || {}));
  return qapFetch(AX, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
    body: body.toString()
  });
}
function qapToast(msg, type) {
  var t = document.getElementById('qap_toast');
  var ico = document.getElementById('qap_toast_ico');
  document.getElementById('qap_toast_msg').textContent = msg;
  var ok = type !== 'err';
  t.style.background = ok ? '#16a34a' : '#dc2626';
  ico.innerHTML = ok
    ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>'
    : '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
  t.classList.add('show');
  clearTimeout(window._qapT);
  window._qapT = setTimeout(function(){ t.classList.remove('show'); }, 2600);
}

function loadState() {
  qapFetch(AX + '?op=state&sesskey=' + SK)
    .then(function(d) {
      document.getElementById('navlabel').value = b64DecodeUtf8(d.navlabel);
      document.getElementById('menuline_box').textContent = d.menuline || '';
      setMenuBadge(d.inmenu);
      renderRoles(d.roles);
    })
    .catch(function(e) { qapToast(e.message, 'err'); });
}

function setMenuBadge(inmenu) {
  var b = document.getElementById('menu_badge');
  var btn = document.getElementById('btn_addmenu');
  if (inmenu) {
    b.textContent = QAP_LANG.menu_inmenu; b.className = 'qap-badge qap-badge-on';
    btn.innerHTML = QAP_LANG.menu_update;
  } else {
    b.textContent = QAP_LANG.menu_notinmenu; b.className = 'qap-badge qap-badge-off';
    btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg> ' + QAP_LANG.menu_add;
  }
}

function renderRoles(roles) {
  var html = '';
  roles.forEach(function(r) {
    if (r.manager) {
      html += '<div class="qap-row"><div><div class="qap-row-label">' + esc(r.name) + '</div>'
        + '<div class="qap-row-sub">'+QAP_LANG.protected+'</div></div>'
        + '<span class="qap-pill">'+QAP_LANG.sempre+'</span></div>';
    } else {
      html += '<div class="qap-row"><div><div class="qap-row-label">' + esc(r.name) + '</div></div>'
        + '<label class="qap-sw"><input type="checkbox" data-roleid="' + r.id + '"' + (r.allowed ? ' checked' : '') + ' onchange="onRoleToggle(this)"><span class="qap-sw-track"></span></label></div>';
    }
  });
  document.getElementById('roles_body').innerHTML = html || '<div class="qap-loading">'+QAP_LANG.roles_none+'</div>';
}

function onRoleToggle(el) {
  var roleid = el.getAttribute('data-roleid');
  var on = el.checked ? 1 : 0;
  el.disabled = true;
  qapPost('toggle_role', {roleid: roleid, on: on})
    .then(function() { qapToast(on ? QAP_LANG.granted : QAP_LANG.removed, 'ok'); })
    .catch(function(e) { el.checked = !el.checked; qapToast(e.message, 'err'); })
    .finally(function() { el.disabled = false; });
}

function onNavLabel() {
  var label64 = b64EncodeUtf8(document.getElementById('navlabel').value);
  qapPost('set_nav', {navlabel_b64: label64})
    .then(function(d) {
      qapToast(QAP_LANG.updated, 'ok');
      if (d && d.menuline) document.getElementById('menuline_box').textContent = d.menuline;
    })
    .catch(function(e) { qapToast(e.message, 'err'); });
}
function addToMenu() {
  var btn = document.getElementById('btn_addmenu');
  btn.disabled = true;
  var label64 = b64EncodeUtf8(document.getElementById('navlabel').value);
  qapPost('add_to_menu', {navlabel_b64: label64})
    .then(function() { qapToast(QAP_LANG.menuadded, 'ok'); setMenuBadge(true); })
    .catch(function(e) { qapToast(e.message, 'err'); })
    .finally(function() { btn.disabled = false; });
}
function copyMenuLine() {
  var text = document.getElementById('menuline_box').textContent;
  if (!text) return;
  function done(){ qapToast(QAP_LANG.linecopied, 'ok'); }
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(done).catch(function(){ window.prompt('Copie a linha:', text); });
  } else { window.prompt('Copie a linha:', text); }
}
function esc(s) { var d = document.createElement('div'); d.textContent = String(s||''); return d.innerHTML; }

loadState();
</script>
<?php
\local_mulima_analytics\local\branding::render_footer('permissions');
echo $OUTPUT->footer();
?>
