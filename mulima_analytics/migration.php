<?php
// This file is part of Moodle - https://moodle.org/
// Copyright 2026 Joaquim Pascoal Mulima Junior.
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Site administrator's preview and transfer of the previous report settings.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
admin_externalpage_setup('local_mulima_analytics_migration');
require_capability('moodle/site:config', context_system::instance());

$url = new moodle_url('/local/mulima_analytics/migration.php');
$settingsurl = new moodle_url('/local/mulima_analytics/presenca_config.php');
$PAGE->set_heading(get_string('pluginname', 'local_mulima_analytics'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    \local_mulima_analytics\local\legacy_migration::migrate();
    redirect($url, get_string('migration_success', 'local_mulima_analytics'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}
$summary = \local_mulima_analytics\local\legacy_migration::summary();
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('migration_title', 'local_mulima_analytics'));
?>
<div class="card mb-4"><div class="card-body">
<?php if ($summary['status'] === 'unavailable'): ?>
    <p><?php echo s(get_string('migration_unavailable', 'local_mulima_analytics')); ?></p>
<?php else: ?>
    <p><?php echo s(get_string($summary['status'] === 'completed' ? 'migration_complete_help' : 'migration_help', 'local_mulima_analytics')); ?></p>
    <?php if ($summary['counts']): ?>
    <table class="table">
        <caption><?php echo s(get_string('migration_counts', 'local_mulima_analytics')); ?></caption>
        <thead><tr><th scope="col"><?php echo s(get_string('migration_item', 'local_mulima_analytics')); ?></th><th scope="col"><?php echo s(get_string('migration_count', 'local_mulima_analytics')); ?></th></tr></thead>
        <tbody>
        <?php foreach ($summary['counts'] as $key => $count): ?>
        <tr><th scope="row"><?php echo s(get_string('migration_' . $key, 'local_mulima_analytics')); ?></th><td><?php echo (int)$count; ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php if ($summary['status'] === 'ready'): ?>
    <div class="alert alert-info"><?php echo s(get_string('migration_permissions_help', 'local_mulima_analytics')); ?></div>
    <form action="<?php echo $url->out(); ?>" method="post">
        <input type="hidden" name="sesskey" value="<?php echo s(sesskey()); ?>">
        <button type="submit" class="btn btn-primary"><?php echo s(get_string('migration_action', 'local_mulima_analytics')); ?></button>
    </form>
    <?php endif; ?>
<?php endif; ?>
</div></div>
<p><a href="<?php echo $settingsurl->out(); ?>"><?php echo s(get_string('migration_back', 'local_mulima_analytics')); ?></a></p>
<?php echo $OUTPUT->footer(); ?>
