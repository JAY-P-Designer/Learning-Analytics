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
 * Report and attendance settings.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
\local_mulima_analytics\local\access::require_manage_attendance();
// Config helper: reads local_mulima_analytics, falls back to local_listas_exame (legacy).
if (!function_exists('local_mulima_analytics_attendance_config')) {
    function local_mulima_analytics_attendance_config($key) {
        return \local_mulima_analytics\local\attendance_config::get($key);
    }
}

$sysctx = context_system::instance();
$PAGE->set_context($sysctx);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/mulima_analytics/presenca_config.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('cfg_page_title', 'local_mulima_analytics'));
$PAGE->set_heading(''); // own .cfg-title/.cfg-sub rendered in the body below

$index_url = new moodle_url('/local/mulima_analytics/presenca.php');
$config_url = new moodle_url('/local/mulima_analytics/presenca_config.php');
$permissions_url = new moodle_url('/local/mulima_analytics/permissoes.php');
$canmanagescore = has_capability('moodle/site:config', $sysctx);
$migration = $canmanagescore ? \local_mulima_analytics\local\legacy_migration::summary() : ['status' => 'unavailable'];
$migrationurl = new moodle_url('/local/mulima_analytics/migration.php');
$scorevalues = \local_mulima_analytics\local\teacher_scoring::settings();
$scoreerrors = [];

// Save.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && optional_param('settingssection', '', PARAM_ALPHA) === 'teacherscore') {
    require_sesskey();
    require_capability('moodle/site:config', $sysctx);
    foreach (\local_mulima_analytics\local\teacher_scoring::defaults() as $key=>$default) {
        $scorevalues[$key] = optional_param($key, '', PARAM_RAW_TRIMMED);
    }
    $scoreerrors = \local_mulima_analytics\local\teacher_scoring::errors($scorevalues);
    if (!$scoreerrors) {
        \local_mulima_analytics\local\teacher_scoring::save($scorevalues);
        $scoreurl = new moodle_url($config_url);
        $scoreurl->set_anchor('teacher-scoring');
        redirect($scoreurl, get_string('cfg_saved_msg', 'local_mulima_analytics'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $fields = [
        'maxpoints' => PARAM_INT,
        'institution_name' => PARAM_TEXT,
        'institution_short' => PARAM_TEXT,
        'periodo_lectivo' => PARAM_TEXT,
        'pass_grade' => PARAM_INT,
        'exam_threshold_pct' => PARAM_INT,
        'fr_keywords' => PARAM_TEXT,
        'footer_left' => PARAM_TEXT,
        'footer_right' => PARAM_TEXT,
        'footer_note' => PARAM_TEXT,
    ];
    foreach ($fields as $key => $type) {
        $val = optional_param($key, '', $type);
        if ($key === 'maxpoints') {
            $val = max(1, (int)$val);
        }
        set_config($key, $val, 'local_mulima_analytics');
    }
    $checks = [
        'enable_especial', 'include_fr', 'show_code', 'show_nota', 'show_signature',
        'show_email', 'show_obs', 'show_discipline', 'show_period', 'show_teacher',
        'show_count', 'show_group', 'show_date',
    ];
    foreach ($checks as $key) {
        $val = optional_param($key, 0, PARAM_INT);
        set_config($key, $val, 'local_mulima_analytics');
    }

    // Logo upload.
    $ctx = context_system::instance();
    $fs = get_file_storage();
    if (optional_param('remove_logo', 0, PARAM_BOOL)) {
        $fs->delete_area_files($ctx->id, 'local_mulima_analytics', 'logo_pdf');
    }
    if (!empty($_FILES['logo_pdf']['tmp_name']) && is_uploaded_file($_FILES['logo_pdf']['tmp_name'])) {
        $upload = $_FILES['logo_pdf'];
        if (!empty($upload['error']) || (int)$upload['size'] > 5 * 1024 * 1024) {
            throw new moodle_exception('error', 'error', '', null, 'Invalid or oversized logo upload.');
        }
        $imageinfo = @getimagesize($upload['tmp_name']);
        $allowedmimes = ['image/png', 'image/jpeg'];
        if (!$imageinfo || !in_array($imageinfo['mime'] ?? '', $allowedmimes, true)) {
            throw new moodle_exception('logo_invalid', 'local_mulima_analytics');
        }
        $fs->delete_area_files($ctx->id, 'local_mulima_analytics', 'logo_pdf');
        $info = [
            'contextid' => $ctx->id, 'component' => 'local_mulima_analytics',
            'filearea' => 'logo_pdf', 'itemid' => 0, 'filepath' => '/',
            'filename' => clean_filename($upload['name']),
            'userid' => (int)$USER->id,
        ];
        $fs->create_file_from_pathname($info, $upload['tmp_name']);
    }

    redirect($config_url, get_string('cfg_saved_msg', 'local_mulima_analytics'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Load current values.
$cfg = [];
$keys = ['maxpoints', 'institution_name', 'institution_short', 'periodo_lectivo', 'pass_grade', 'exam_threshold_pct',
         'fr_keywords', 'footer_left', 'footer_right', 'enable_especial', 'include_fr',
         'show_code', 'show_nota', 'show_signature', 'show_email', 'show_obs',
         'show_discipline', 'show_period', 'show_teacher', 'show_count', 'show_group',
         'show_date', 'footer_note'];
$defaults = [
    'maxpoints' => 800,
    'institution_name' => 'INSTITUTO SUPERIOR DE TRANSPORTES E COMUNICACOES',
    'institution_short' => 'ISUTC', 'periodo_lectivo' => '', 'pass_grade' => 10,
    'exam_threshold_pct' => 50, 'fr_keywords' => 'FR,FRAUDE,FRAUD',
    'footer_left' => get_string('lp_signature_teacher', 'local_mulima_analytics'), 'footer_right' => get_string('lp_signature_head', 'local_mulima_analytics'),
    'enable_especial' => 0, 'include_fr' => 0, 'show_code' => 1, 'show_nota' => 1, 'show_signature' => 1,
    'show_email' => 0, 'show_obs' => 1, 'show_discipline' => 1, 'show_period' => 1,
    'show_teacher' => 1, 'show_count' => 1, 'show_group' => 1, 'show_date' => 1,
    'footer_note' => '',
];
foreach ($keys as $k) {
    $v = local_mulima_analytics_attendance_config($k);
    $cfg[$k] = ($v !== null) ? $v : ($defaults[$k] ?? '');
}

$logo_url = '';
$logo_name = '';
$logo_files = get_file_storage()->get_area_files($sysctx->id, 'local_mulima_analytics', 'logo_pdf', 0, 'id DESC', false);
if ($logo_files) {
    $logo_file = reset($logo_files);
    $logo_name = $logo_file->get_filename();
    $logo_url = moodle_url::make_pluginfile_url(
        $logo_file->get_contextid(),
        $logo_file->get_component(),
        $logo_file->get_filearea(),
        $logo_file->get_itemid(),
        $logo_file->get_filepath(),
        $logo_file->get_filename()
    )->out(false);
}

echo $OUTPUT->header();
?>
<style>
#cfg-wrap{max-width:820px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
.cfg-head{margin-bottom:24px}
.cfg-title{font-size:22px;font-weight:800;color:#0f172a}
.cfg-sub{font-size:12px;color:#94a3b8;margin-top:2px}
.cfg-tabs{display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin-bottom:24px}
.cfg-tab{display:flex;align-items:center;gap:6px;padding:10px 20px;color:#64748b;font-size:13px;font-weight:600;text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s}
.cfg-tab:hover{color:#0f172a;text-decoration:none}
.cfg-tab.active{color:#0d9488;border-bottom-color:#0d9488}
.cfg-section{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;margin-bottom:16px}
.cfg-section-h{font-size:14px;font-weight:700;color:#0f172a;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px}
.cfg-section-h i{color:#0d9488;font-size:15px}
.cfg-row{margin-bottom:14px}
.cfg-label{display:block;font-size:12px;font-weight:600;color:#334155;margin-bottom:4px}
.cfg-hint{font-size:11px;color:#94a3b8;margin-top:2px}
#teacher-scoring .cfg-hint{color:#64748b;line-height:1.5}
.cfg-input{width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;outline:none;transition:border-color .15s;background:#fff;color:#0f172a}
.cfg-input:focus{border-color:#0d9488;box-shadow:0 0 0 3px rgba(13,148,136,.08)}
.cfg-check{display:flex;align-items:center;gap:8px;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;cursor:pointer;transition:all .15s}
.cfg-check:hover{border-color:#cbd5e1;background:#f8fafc}
.cfg-check input{accent-color:#0d9488;width:16px;height:16px}
.cfg-check span{font-size:13px;color:#334155}
.cfg-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.cfg-btn{display:inline-flex;align-items:center;gap:8px;padding:12px 28px;border-radius:10px;border:none;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s}
.cfg-btn-p{background:#0d9488;color:#fff;box-shadow:0 2px 8px rgba(13,148,136,.25)}.cfg-btn-p:hover{background:#0f766e}
.cfg-btn-s{background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0}.cfg-btn-s:hover{background:#e2e8f0}
.cfg-logo-current{display:flex;align-items:center;gap:16px;padding:12px;margin:10px 0;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc}
.cfg-logo-current img{display:block;max-width:180px;max-height:72px;object-fit:contain;background:#fff;border:1px solid #e2e8f0;border-radius:6px;padding:6px}
.cfg-logo-meta{font-size:12px;color:#475569;overflow-wrap:anywhere}
@media (max-width:640px){.cfg-grid{grid-template-columns:1fr}.cfg-tabs{overflow-x:auto}.cfg-tab{white-space:nowrap;padding:10px 12px}.cfg-logo-current{align-items:flex-start;flex-direction:column}}
</style>

<div id="cfg-wrap" class="die-config-shell">

<div class="die-config-header">
  <div>
    <div class="die-config-eyebrow"><?php echo s(get_string('cfg_eyebrow', 'local_mulima_analytics')); ?></div>
    <h1 class="die-config-title"><?php echo s(get_string('cfg_page_title', 'local_mulima_analytics')); ?></h1>
    <p class="die-config-subtitle"><?php echo s(get_string('cfg_page_subtitle', 'local_mulima_analytics')); ?></p>
  </div>
</div>
<nav class="die-config-tabs" aria-label="<?php echo s(get_string('cfg_page_title', 'local_mulima_analytics')); ?>">
    <a href="<?php echo $config_url->out(false); ?>" class="die-config-tab is-active" aria-current="page"><i class="fa fa-cog"></i> <?php echo s(get_string('cfg_tab_general', 'local_mulima_analytics')); ?></a>
    <a href="<?php echo $permissions_url->out(false); ?>" class="die-config-tab"><i class="fa fa-lock"></i> <?php echo s(get_string('cfg_tab_permissions', 'local_mulima_analytics')); ?></a>
    <a href="<?php echo $index_url->out(false); ?>" class="die-config-tab is-return"><i class="fa fa-arrow-left"></i> <?php echo s(get_string('cfg_tab_lists', 'local_mulima_analytics')); ?></a>
</nav>

<?php if ($canmanagescore && $migration['status'] !== 'unavailable'): ?>
<section class="cfg-section" aria-labelledby="migration-title">
  <h2 class="cfg-section-h" id="migration-title"><?php echo s(get_string('migration_title', 'local_mulima_analytics')); ?></h2>
  <p class="cfg-hint"><?php echo s(get_string($migration['status'] === 'completed' ? 'migration_complete_help' : 'migration_help', 'local_mulima_analytics')); ?></p>
  <a class="cfg-btn cfg-btn-s" href="<?php echo $migrationurl->out(); ?>"><?php echo s(get_string('migration_review', 'local_mulima_analytics')); ?></a>
</section>
<?php endif; ?>

<?php if ($canmanagescore): ?>
<section class="cfg-section" id="teacher-scoring" aria-labelledby="score-settings-title">
  <h2 class="cfg-section-h" id="score-settings-title"><i class="fa fa-bar-chart" aria-hidden="true"></i> <?php echo s(get_string('score_section', 'local_mulima_analytics')); ?></h2>
  <p class="cfg-hint" style="color:#475569;margin-bottom:16px"><?php echo s(get_string('score_settings_help', 'local_mulima_analytics')); ?></p>
  <form method="post" action="<?php echo $config_url->out(false); ?>#teacher-scoring">
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
    <input type="hidden" name="settingssection" value="teacherscore">
    <?php if ($scoreerrors): ?>
      <div class="alert alert-danger" role="alert"><?php echo s(get_string('score_correct_errors', 'local_mulima_analytics')); ?></div>
    <?php endif; ?>
    <div class="cfg-grid">
    <?php foreach (\local_mulima_analytics\local\teacher_scoring::defaults() as $key=>$default):
        $isweight = strpos($key, 'weight_') === 0;
    ?>
      <div class="cfg-row">
        <label class="cfg-label" for="score-<?php echo $key; ?>"><?php echo s(get_string('score_'.$key, 'local_mulima_analytics')); ?></label>
        <input class="cfg-input" id="score-<?php echo $key; ?>" name="<?php echo $key; ?>" type="number" step="1" required
          min="<?php echo $isweight ? 0 : ($key === 'maximum' ? 2 : 1); ?>" max="<?php echo $isweight ? 100 : 100000; ?>"
          value="<?php echo s($scorevalues[$key]); ?>" aria-describedby="score-<?php echo $key; ?>-hint"
          <?php if (isset($scoreerrors[$key])) { echo 'aria-invalid="true"'; } ?>>
        <div class="cfg-hint" id="score-<?php echo $key; ?>-hint"<?php if (isset($scoreerrors[$key])) { echo ' style="color:#b91c1c"'; } ?>>
          <?php echo s(get_string($scoreerrors[$key] ?? ($isweight ? 'score_weight_hint' : 'score_threshold_hint'), 'local_mulima_analytics')); ?>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
    <div style="display:flex;justify-content:flex-end">
      <button type="submit" class="cfg-btn cfg-btn-p"><?php echo s(get_string('cfg_guardar', 'local_mulima_analytics')); ?></button>
    </div>
  </form>
</section>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
<input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

<div class="cfg-section">
    <div class="cfg-section-h"><i class="fa fa-line-chart"></i> <?php echo s(get_string('cfg_section_reports', 'local_mulima_analytics')); ?></div>
    <div class="cfg-row">
        <label class="cfg-label" for="id_maxpoints"><?php echo s(get_string('maxpoints', 'local_mulima_analytics')); ?></label>
        <input id="id_maxpoints" class="cfg-input" type="number" name="maxpoints" min="1" step="1" required value="<?php echo (int)$cfg['maxpoints']; ?>">
        <div class="cfg-hint"><?php echo s(get_string('maxpoints_desc', 'local_mulima_analytics')); ?></div>
    </div>
</div>

<div class="cfg-section">
    <div class="cfg-section-h"><i class="fa fa-building"></i> <?php echo s(get_string('cfg_section_identidade', 'local_mulima_analytics')); ?></div>
    <div class="cfg-row">
        <label class="cfg-label"><?php echo s(get_string('cfg_nome_instituicao', 'local_mulima_analytics')); ?></label>
        <input class="cfg-input" name="institution_name" value="<?php echo s($cfg['institution_name']); ?>">
    </div>
    <div class="cfg-grid">
        <div class="cfg-row">
            <label class="cfg-label"><?php echo s(get_string('cfg_sigla', 'local_mulima_analytics')); ?></label>
            <input class="cfg-input" name="institution_short" value="<?php echo s($cfg['institution_short']); ?>">
        </div>
        <div class="cfg-row">
            <label class="cfg-label"><?php echo s(get_string('cfg_periodo_lectivo', 'local_mulima_analytics')); ?></label>
            <input class="cfg-input" name="periodo_lectivo" value="<?php echo s($cfg['periodo_lectivo']); ?>" placeholder="<?php echo s(get_string('cfg_periodo_placeholder', 'local_mulima_analytics')); ?>">
        </div>
    </div>
    <div class="cfg-row">
        <label class="cfg-label"><?php echo s(get_string('cfg_logo_pdf', 'local_mulima_analytics')); ?></label>
        <?php if ($logo_url): ?>
        <div class="cfg-logo-current">
            <img src="<?php echo $logo_url; ?>" alt="<?php echo s(get_string('cfg_current_logo', 'local_mulima_analytics')); ?>">
            <div class="cfg-logo-meta">
                <strong><?php echo s(get_string('cfg_current_logo', 'local_mulima_analytics')); ?></strong><br>
                <?php echo s($logo_name); ?>
                <label class="cfg-check" style="margin-top:8px;padding:7px 10px">
                    <input type="checkbox" name="remove_logo" value="1">
                    <span><?php echo s(get_string('cfg_remove_logo', 'local_mulima_analytics')); ?></span>
                </label>
            </div>
        </div>
        <?php else: ?>
        <div class="cfg-hint" style="margin-bottom:8px"><?php echo s(get_string('cfg_no_logo', 'local_mulima_analytics')); ?></div>
        <?php endif; ?>
        <input type="file" name="logo_pdf" accept=".png,.jpg,.jpeg" class="cfg-input" style="padding:7px">
        <div class="cfg-hint"><?php echo s(get_string('cfg_logo_hint', 'local_mulima_analytics')); ?></div>
    </div>
</div>

<div class="cfg-section">
    <div class="cfg-section-h"><i class="fa fa-graduation-cap"></i> <?php echo s(get_string('cfg_section_avaliacao', 'local_mulima_analytics')); ?></div>
    <div class="cfg-grid">
        <div class="cfg-row">
            <label class="cfg-label"><?php echo s(get_string('cfg_nota_aprovacao', 'local_mulima_analytics')); ?></label>
            <input class="cfg-input" type="number" name="pass_grade" value="<?php echo (int)$cfg['pass_grade']; ?>">
        </div>
        <div class="cfg-row">
            <label class="cfg-label"><?php echo s(get_string('cfg_limiar_exame', 'local_mulima_analytics')); ?></label>
            <input class="cfg-input" type="number" name="exam_threshold_pct" value="<?php echo (int)$cfg['exam_threshold_pct']; ?>">
        </div>
    </div>
    <div class="cfg-row">
        <label class="cfg-label"><?php echo s(get_string('cfg_palavras_fraude', 'local_mulima_analytics')); ?></label>
        <input class="cfg-input" name="fr_keywords" value="<?php echo s($cfg['fr_keywords']); ?>">
        <div class="cfg-hint"><?php echo s(get_string('cfg_palavras_fraude_hint', 'local_mulima_analytics')); ?></div>
    </div>
    <div class="cfg-grid">
        <label class="cfg-check">
            <input type="hidden" name="enable_especial" value="0">
            <input type="checkbox" name="enable_especial" value="1" <?php echo $cfg['enable_especial'] ? 'checked' : ''; ?>>
            <span><?php echo s(get_string('cfg_activar_especial', 'local_mulima_analytics')); ?></span>
        </label>
        <label class="cfg-check">
            <input type="hidden" name="include_fr" value="0">
            <input type="checkbox" name="include_fr" value="1" <?php echo $cfg['include_fr'] ? 'checked' : ''; ?>>
            <span><?php echo s(get_string('cfg_incluir_fr', 'local_mulima_analytics')); ?></span>
        </label>
    </div>
</div>

<div class="cfg-section">
    <div class="cfg-section-h"><i class="fa fa-columns"></i> <?php echo s(get_string('cfg_section_colunas', 'local_mulima_analytics')); ?></div>
    <div class="cfg-grid">
        <label class="cfg-check">
            <input type="hidden" name="show_code" value="0">
            <input type="checkbox" name="show_code" value="1" <?php echo $cfg['show_code'] ? 'checked' : ''; ?>>
            <span><?php echo s(get_string('cfg_codigo_estudante', 'local_mulima_analytics')); ?></span>
        </label>
        <label class="cfg-check">
            <input type="hidden" name="show_nota" value="0">
            <input type="checkbox" name="show_nota" value="1" <?php echo $cfg['show_nota'] ? 'checked' : ''; ?>>
            <span><?php echo s(get_string('nota', 'local_mulima_analytics')); ?></span>
        </label>
        <label class="cfg-check">
            <input type="hidden" name="show_signature" value="0">
            <input type="checkbox" name="show_signature" value="1" <?php echo $cfg['show_signature'] ? 'checked' : ''; ?>>
            <span><?php echo s(get_string('cfg_assinatura', 'local_mulima_analytics')); ?></span>
        </label>
        <label class="cfg-check">
            <input type="hidden" name="show_email" value="0">
            <input type="checkbox" name="show_email" value="1" <?php echo $cfg['show_email'] ? 'checked' : ''; ?>>
            <span><?php echo s(get_string('cfg_email_estudante', 'local_mulima_analytics')); ?></span>
        </label>
        <label class="cfg-check">
            <input type="hidden" name="show_obs" value="0">
            <input type="checkbox" name="show_obs" value="1" <?php echo $cfg['show_obs'] ? 'checked' : ''; ?>>
            <span><?php echo s(get_string('cfg_observacoes', 'local_mulima_analytics')); ?></span>
        </label>
    </div>
</div>

<div class="cfg-section">
    <div class="cfg-section-h"><i class="fa fa-info-circle"></i> <?php echo s(get_string('cfg_section_header_fields', 'local_mulima_analytics')); ?></div>
    <div class="cfg-grid">
        <?php
        $headerchecks = [
            'show_discipline' => 'cfg_show_discipline',
            'show_period' => 'cfg_show_period',
            'show_teacher' => 'cfg_show_teacher',
            'show_count' => 'cfg_show_count',
            'show_group' => 'cfg_show_group',
            'show_date' => 'cfg_show_date',
        ];
        foreach ($headerchecks as $field => $stringkey):
        ?>
        <label class="cfg-check">
            <input type="hidden" name="<?php echo $field; ?>" value="0">
            <input type="checkbox" name="<?php echo $field; ?>" value="1" <?php echo $cfg[$field] ? 'checked' : ''; ?>>
            <span><?php echo s(get_string($stringkey, 'local_mulima_analytics')); ?></span>
        </label>
        <?php endforeach; ?>
    </div>
</div>

<div class="cfg-section">
    <div class="cfg-section-h"><i class="fa fa-pencil"></i> <?php echo s(get_string('cfg_section_rodape', 'local_mulima_analytics')); ?></div>
    <div class="cfg-grid">
        <div class="cfg-row">
            <label class="cfg-label"><?php echo s(get_string('cfg_assinatura_esq', 'local_mulima_analytics')); ?></label>
            <input class="cfg-input" name="footer_left" value="<?php echo s($cfg['footer_left']); ?>">
        </div>
        <div class="cfg-row">
            <label class="cfg-label"><?php echo s(get_string('cfg_assinatura_dir', 'local_mulima_analytics')); ?></label>
            <input class="cfg-input" name="footer_right" value="<?php echo s($cfg['footer_right']); ?>">
        </div>
    </div>
    <div class="cfg-row">
        <label class="cfg-label"><?php echo s(get_string('cfg_footer_note', 'local_mulima_analytics')); ?></label>
        <input class="cfg-input" name="footer_note" value="<?php echo s($cfg['footer_note']); ?>">
        <div class="cfg-hint"><?php echo s(get_string('cfg_footer_note_hint', 'local_mulima_analytics')); ?></div>
    </div>
</div>

<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;margin-bottom:40px">
    <a href="<?php echo $index_url->out(false); ?>" class="cfg-btn cfg-btn-s"><i class="fa fa-arrow-left"></i> <?php echo s(get_string('cfg_cancelar', 'local_mulima_analytics')); ?></a>
    <button type="submit" class="cfg-btn cfg-btn-p"><i class="fa fa-check"></i> <?php echo s(get_string('cfg_guardar', 'local_mulima_analytics')); ?></button>
</div>

</form>
</div>

<?php
\local_mulima_analytics\local\branding::render_footer('settings');
echo $OUTPUT->footer();
?>
