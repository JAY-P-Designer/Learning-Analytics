<?php
// This file is part of Moodle - https://moodle.org/
// Copyright 2026 Joaquim Pascoal Mulima Junior.
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Standalone language regression checks and production PHP template fixtures.
 * Run: php tests/regression/localization.php
 * Set LANGUAGE_FIXTURE_DIR to render the fixtures used by the browser tests.
 *
 * @package local_mulima_analytics
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('MOODLE_INTERNAL', true);
$root = dirname(__DIR__, 2);
$checks = 0;
function check($condition, $message) {
    $GLOBALS['checks']++;
    if (!$condition) { throw new RuntimeException($message); }
}
function language_pack($language) {
    $string = [];
    require($GLOBALS['root'].'/lang/'.$language.'/local_mulima_analytics.php');
    return $string;
}
function get_string($key, $component = '', $a = null) {
    if ($component === 'local_mulima_analytics' || $component === '') {
        $value = $GLOBALS['strings'][$key] ?? '[['.$key.']]';
    } else if ($component === 'langconfig') {
        $value = '%d/%m/%Y %H:%M';
    } else {
        $value = $key === 'modulename' ? ucfirst($component) : $key;
    }
    if (is_object($a) || is_array($a)) {
        foreach ((array)$a as $field=>$data) { $value = str_replace('{$a->'.$field.'}', (string)$data, $value); }
    } else if ($a !== null) { $value = str_replace('{$a}', (string)$a, $value); }
    return $value;
}
function s($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function current_language() { return $GLOBALS['language']; }
function get_config($component, $key = '') { return false; }
function has_capability($capability, $context) { return true; }
function sesskey() { return 'test'; }
function userdate($time, $format = '') { return '02/10/2026 16:58'; }
class context_system { public static function instance() { return (object)['id'=>1]; } }
class moodle_url {
    private $path;
    public function __construct($path, $params = []) { $this->path = $path.($params ? '?'.http_build_query($params) : ''); }
    public function out($escaped = true) { return $escaped ? s($this->path) : $this->path; }
    public function __toString() { return $this->out(); }
}
require($root.'/classes/local/branding.php');
require($root.'/classes/local/teacher_scoring.php');

$english = language_pack('en'); $portuguese = language_pack('pt');
$enkeys = array_keys($english); $ptkeys = array_keys($portuguese);
sort($enkeys); sort($ptkeys);
check($enkeys === $ptkeys, 'English and Portuguese keys differ');
foreach ($enkeys as $key) {
    preg_match_all('/\{\$a(?:->[a-zA-Z_0-9]+)?\}/', $english[$key], $enargs);
    preg_match_all('/\{\$a(?:->[a-zA-Z_0-9]+)?\}/', $portuguese[$key], $ptargs);
    sort($enargs[0]); sort($ptargs[0]);
    check($enargs[0] === $ptargs[0], 'Different interpolation fields in '.$key);
}
foreach (['en', 'pt'] as $language) {
    $source = file_get_contents($root.'/lang/'.$language.'/local_mulima_analytics.php');
    preg_match_all('/\$string\[\x27([^\x27]+)\x27\]/', $source, $keys);
    check(count($keys[1]) === count(array_unique($keys[1])), 'Duplicate '.$language.' language key');
}
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php' || strpos($file->getPathname(), '/tests/') !== false) { continue; }
    $source = file_get_contents($file->getPathname());
    token_get_all($source, TOKEN_PARSE);
    preg_match_all('/get_string\(\s*\x27([^\x27]+)\x27\s*,\s*\x27local_mulima_analytics\x27/', $source, $keys);
    foreach ($keys[1] as $key) { check(isset($english[$key]), 'Undefined language key '.$key.' in '.$file->getFilename()); }
}

$layout = file_get_contents($root.'/die_layout.php');
preg_match('/<\?php\n\$dieuistrings = \[\];(.*?)\?>/s', $layout, $dictionary);
check(isset($dictionary[1]), 'Shared localisation dictionary was not found');
$fixturepath = getenv('LANGUAGE_FIXTURE_DIR');
if ($fixturepath && !is_dir($fixturepath)) { mkdir($fixturepath, 0777, true); }
$OUTPUT = new class { public function footer() { return ''; } };
$root_cats = [(object)['id'=>1, 'name'=>'ANO LECTIVO 2026'], (object)['id'=>2, 'name'=>'ANO LECTIVO 2025']];
$SK = 'test'; $maxpoints = 800; $cfg_enable_especial = 1; $is_manager = true;
$scoring = \local_mulima_analytics\local\teacher_scoring::settings();
$COURSE_VIEW = 'http://learning.test/course/view.php'; $FORUM_VIEW = 'http://learning.test/mod/forum/view.php';
$CFGURL = $SCORE_CONFIG = $CONFIG_URL = $config_url = new moodle_url('/presenca_config.php');
$REPORT_URL = new moodle_url('/index.php'); $PERMISSIONS_URL = $permissions_url = new moodle_url('/permissoes.php');
$SEARCH_URL = new moodle_url('/admin/search.php'); $index_url = new moodle_url('/presenca.php');
$migration = ['status' => 'unavailable']; $migrationurl = new moodle_url('/migration.php');

foreach (['en'=>$english, 'pt'=>$portuguese] as $language=>$strings) {
    eval('$dieuistrings = [];'.$dictionary[1]);
    check($dieuistrings['a_carregar'] === ($language === 'en' ? 'Loading...' : 'A carregar...'), 'Wrong loading language');
    check($dieuistrings['disciplina'] === ($language === 'en' ? 'Course' : 'Disciplina'), 'Wrong picker label');
    if ($fixturepath) { file_put_contents($fixturepath.'/dictionary-'.$language.'.json', json_encode($dieuistrings)); }
    foreach (['acessos','cobertura','risco','docentes','presenca','permissoes','presenca_config'] as $tab) {
        $AX = '/'.$tab.'_ajax.php'; $XLS = $EXP = '/'.$tab.'_export.php'; $MSG = '/message.php';
        $source = file_get_contents($root.'/'.$tab.'.php');
        if ($tab === 'presenca_config') {
            preg_match('/\$defaults = \[(.*?)\n\];/s', $source, $defaults);
            eval('$cfg = ['.$defaults[1].'];');
            $canmanagescore = true; $scorevalues = $scoring; $scoreerrors = []; $logo_url = $logo_name = '';
            $body = substr($source, strpos($source, '<style>'));
        } else {
            $body = substr($source, strpos($source, '?>') + 2);
            $body = preg_replace('/<\?php\s*\$DIE_PAGE.*?\?>/s', '', $body);
        }
        ob_start(); eval('?>'.$body); $rendered = ob_get_clean();
        check(strpos($rendered, '[[') === false, 'Missing translation in '.$tab.' '.$language);
        $visible = preg_replace('/<script.*?<\/script>|<style.*?<\/style>/s', '', $rendered);
        check(strpos($visible, '{$a') === false, 'Unresolved placeholder in '.$tab.' '.$language);
        if ($fixturepath) { file_put_contents($fixturepath.'/'.$tab.'-'.$language.'.html', $rendered); }
    }
    $source = file_get_contents($root.'/migration.php');
    $body = substr($source, strpos($source, '?>') + 2);
    $url = new moodle_url('/local/mulima_analytics/migration.php');
    $settingsurl = new moodle_url('/local/mulima_analytics/presenca_config.php');
    foreach (['ready','completed','unavailable'] as $status) {
        $summary = ['status' => $status, 'counts' => $status === 'unavailable' ? [] : [
            'settingscopied' => 12, 'settingskept' => 2, 'permissions' => 9,
            'filescopied' => 3, 'fileskept' => 0, 'menulinks' => 1,
        ]];
        ob_start(); eval('?>'.$body); $rendered = ob_get_clean();
        check(strpos($rendered, '[[') === false && strpos($rendered, '{$a') === false,
            'Migration screen has complete translations: '.$status.' '.$language);
        check((strpos($rendered, '<form') !== false) === ($status === 'ready'),
            'Transfer form only shown for a compatible source');
        if ($status === 'ready') {
            check(strpos($rendered, 'method="post"') !== false && strpos($rendered, 'name="sesskey"') !== false,
                'Transfer action uses POST and a session key');
        }
        if ($fixturepath) { file_put_contents($fixturepath.'/migration-'.$status.'-'.$language.'.html', $rendered); }
    }
}
echo 'PASS '.$checks." localisation checks, with seven report/settings templates and three migration states in EN/PT.\n";
