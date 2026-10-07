<?php
// This file is part of Moodle - https://moodle.org/
// Copyright 2026 Joaquim Pascoal Mulima Junior.
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Production migration code tested with SQLite and Moodle API doubles.
 * Run outside Moodle: php tests/regression/legacy_migration.php
 *
 * @package    local_mulima_analytics
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace {
    define('MOODLE_INTERNAL', true);
    define('CONTEXT_SYSTEM', 10);
    define('CAP_ALLOW', 1);
    define('CAP_PREVENT', -1);
    define('CAP_PROHIBIT', -1000);
    $checks = 0;
    $allowed = $sessionvalid = true;
    $busy = false;
    $released = $cacheclears = 0;
    $failfile = false;
    $failmarker = false;

    function check($condition, string $message): void {
        $GLOBALS['checks']++;
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }
    function fails(callable $operation, string $reason): void {
        try {
            $operation();
        } catch (\Throwable $error) {
            check($error->getMessage() === $reason, 'Expected ' . $reason . '; got ' . $error->getMessage());
            return;
        }
        check(false, 'Expected failure: ' . $reason);
    }
    class moodle_exception extends \RuntimeException {
        public function __construct($key, $component = '') {
            parent::__construct($key);
        }
    }
    class context_system {
        public static function instance(): object {
            return (object)['id' => 1];
        }
    }
    class cache_helper {
        public static function invalidate_by_definition($component, $definition): void {
            $GLOBALS['cacheclears']++;
        }
    }
    function require_login(): void {
    }
    function require_capability($capability, $context): void {
        if (!$GLOBALS['allowed'] || $capability !== 'moodle/site:config') {
            throw new moodle_exception('permission');
        }
    }
    function require_sesskey(): void {
        if (!$GLOBALS['sessionvalid']) {
            throw new moodle_exception('sesskey');
        }
    }
    function accesslib_clear_all_caches($force): void {
        $GLOBALS['cacheclears']++;
    }

    /** SQLite database with the production DML signatures used by this feature. */
    class migration_database {
        public $pdo;
        public function __construct() {
            $this->pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $this->pdo->exec('CREATE TABLE config_plugins (id INTEGER PRIMARY KEY, plugin TEXT, name TEXT, value TEXT,
                UNIQUE(plugin, name))');
            $this->pdo->exec('CREATE TABLE capabilities (id INTEGER PRIMARY KEY, name TEXT UNIQUE, component TEXT,
                contextlevel INTEGER, captype TEXT)');
            $this->pdo->exec('CREATE TABLE role_capabilities (id INTEGER PRIMARY KEY, capability TEXT, roleid INTEGER,
                contextid INTEGER, permission INTEGER, UNIQUE(capability,roleid,contextid))');
            $this->pdo->exec('CREATE TABLE config (id INTEGER PRIMARY KEY, name TEXT UNIQUE, value TEXT)');
            $this->pdo->exec('CREATE TABLE files (id INTEGER PRIMARY KEY, component TEXT, contextid INTEGER,
                filearea TEXT, itemid INTEGER, filepath TEXT, filename TEXT, userid INTEGER, content TEXT,
                timecreated INTEGER, timemodified INTEGER,
                UNIQUE(contextid,component,filearea,itemid,filepath,filename))');
        }
        public function query(string $sql, array $params = []): array {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
            return $statement->fetchAll(\PDO::FETCH_OBJ);
        }
        public function where(array $conditions): array {
            return [implode(' AND ', array_map(function($field) {
                return $field . ' = ?';
            }, array_keys($conditions))), array_values($conditions)];
        }
        public function get_records(string $table, array $conditions, string $sort = '', string $fields = '*'): array {
            [$where, $values] = $this->where($conditions);
            $rows = $this->query("SELECT $fields FROM $table WHERE $where" . ($sort ? " ORDER BY $sort" : ''), $values);
            $result = [];
            foreach ($rows as $row) {
                $result[reset($row)] = $row;
            }
            return $result;
        }
        public function get_records_list(string $table, string $field, array $items, string $sort = ''): array {
            $marks = implode(',', array_fill(0, count($items), '?'));
            return $this->query("SELECT * FROM $table WHERE $field IN ($marks)" . ($sort ? " ORDER BY $sort" : ''), $items);
        }
        public function delete_records(string $table, array $conditions): void {
            [$where, $values] = $this->where($conditions);
            $this->query("DELETE FROM $table WHERE $where", $values);
        }
        public function start_delegated_transaction(): migration_transaction {
            return new migration_transaction($this->pdo);
        }
        public function dump(): string {
            $data = [];
            foreach (['config_plugins', 'config', 'capabilities', 'role_capabilities', 'files'] as $table) {
                $data[$table] = $this->query("SELECT * FROM $table ORDER BY id");
            }
            return json_encode($data);
        }
    }
    class migration_transaction {
        private $pdo;
        public function __construct(\PDO $pdo) {
            $this->pdo = $pdo;
            $pdo->beginTransaction();
        }
        public function allow_commit(): void {
            $this->pdo->commit();
        }
        public function rollback(\Throwable $error): void {
            $this->pdo->rollBack();
            throw $error;
        }
    }
    function get_config(string $component, string $name) {
        $rows = $GLOBALS['DB']->query('SELECT value FROM config_plugins WHERE plugin = ? AND name = ?', [$component, $name]);
        return $rows ? $rows[0]->value : false;
    }
    function set_config(string $name, $value, ?string $component = null): bool {
        global $DB, $CFG;
        if ($GLOBALS['failmarker'] && $name === 'legacy_migration_completed') {
            throw new moodle_exception('marker_failure');
        }
        if ($component !== null) {
            $DB->query('INSERT INTO config_plugins (plugin,name,value) VALUES (?,?,?)
                ON CONFLICT(plugin,name) DO UPDATE SET value=excluded.value', [$component, $name, (string)$value]);
        } else {
            $DB->query('INSERT INTO config (name,value) VALUES (?,?)
                ON CONFLICT(name) DO UPDATE SET value=excluded.value', [$name, (string)$value]);
            $CFG->$name = $value;
        }
        return true;
    }
    function assign_capability(string $capability, int $permission, int $roleid, int $contextid, bool $overwrite): bool {
        global $DB;
        $DB->query('INSERT INTO role_capabilities (capability,roleid,contextid,permission) VALUES (?,?,?,?)
            ON CONFLICT(capability,roleid,contextid) DO UPDATE SET permission=excluded.permission',
            [$capability, $roleid, $contextid, $permission]);
        return true;
    }
    class migration_file {
        public $record;
        public function __construct(object $record) {
            $this->record = $record;
        }
        public function __call(string $name, array $args) {
            return $this->record->{substr($name, 4)};
        }
        public function is_directory(): bool {
            return $this->record->filename === '.';
        }
    }
    class migration_storage {
        public function create_directory(int $contextid, string $component, string $filearea, int $itemid,
                string $filepath, $userid): bool {
            $GLOBALS['DB']->query('INSERT INTO files (component,contextid,filearea,itemid,filepath,filename,userid,
                content,timecreated,timemodified) VALUES (?,?,?,?,?,\'.\',?,\'\',300,300)',
                [$component, $contextid, $filearea, $itemid, $filepath, $userid]);
            return true;
        }
        public function get_area_files(int $contextid, string $component, string $filearea, $itemid,
                string $sort, bool $includedirs): array {
            $sql = 'SELECT * FROM files WHERE contextid=? AND component=? AND filearea=?';
            if (!$includedirs) {
                $sql .= " AND filename <> '.'";
            }
            return array_map(function($record) {
                return new migration_file($record);
            }, $GLOBALS['DB']->query($sql . ' ORDER BY ' . $sort, [$contextid, $component, $filearea]));
        }
        public function get_file(int $contextid, string $component, string $filearea, int $itemid,
                string $filepath, string $filename) {
            $rows = $GLOBALS['DB']->query('SELECT * FROM files WHERE contextid=? AND component=? AND filearea=?
                AND itemid=? AND filepath=? AND filename=?', [$contextid, $component, $filearea, $itemid, $filepath, $filename]);
            return $rows ? new migration_file($rows[0]) : false;
        }
        public function create_file_from_storedfile(array $changes, migration_file $source): migration_file {
            if ($GLOBALS['failfile']) {
                throw new moodle_exception('file_failure');
            }
            $row = (array)$source->record;
            unset($row['id']);
            $row = array_merge($row, $changes);
            $keys = implode(',', array_keys($row));
            $marks = implode(',', array_fill(0, count($row), '?'));
            $GLOBALS['DB']->query("INSERT INTO files ($keys) VALUES ($marks)", array_values($row));
            return new migration_file((object)$row);
        }
    }
    function get_file_storage(): migration_storage {
        return new migration_storage();
    }
    function fixture(bool $legacy = true): void {
        global $DB, $CFG, $allowed, $sessionvalid, $busy, $failfile, $failmarker;
        $DB = new migration_database();
        $CFG = (object)['wwwroot' => 'https://moodle.test/campus', 'custommenuitems' =>
            "Learning Analytics|/local/learning_analytics/index.php\r\nCourses|/course/index.php"];
        $allowed = $sessionvalid = true;
        $busy = $failfile = $failmarker = false;
        set_config('custommenuitems', $CFG->custommenuitems);
        foreach (['local_mulima_analytics', 'local_learning_analytics'] as $component) {
            if (!$legacy && $component === 'local_learning_analytics') {
                continue;
            }
            set_config('version', '2026100205', $component);
            foreach (['view', 'viewdashboard', 'viewaccesses', 'viewcoverage', 'viewrisk',
                    'viewteachers', 'viewattendance', 'exportdata', 'manageattendance'] as $suffix) {
                $DB->query('INSERT INTO capabilities (name,component,contextlevel,captype) VALUES (?,?,?,?)',
                    [str_replace('local_', 'local/', $component) . ':' . $suffix, $component, 10,
                    $suffix === 'manageattendance' ? 'write' : 'read']);
            }
        }
        set_config('version', '2026100206', 'local_mulima_analytics');
        set_config('weight_forums', '0', 'local_learning_analytics');
        set_config('footer_note', '', 'local_learning_analytics');
        set_config('institution_name', 'Legacy institution', 'local_learning_analytics');
        set_config('institution_name', 'Destination institution', 'local_mulima_analytics');
        assign_capability('local/learning_analytics:viewteachers', CAP_ALLOW, 2, 1, true);
        assign_capability('local/learning_analytics:exportdata', CAP_PREVENT, 3, 1, true);
        assign_capability('local/learning_analytics:viewrisk', CAP_PROHIBIT, 3, 7, true);
        assign_capability('local/mulima_analytics:viewteachers', CAP_ALLOW, 1, 1, true);
        assign_capability('local/mulima_analytics:viewrisk', CAP_ALLOW, 3, 7, true);
        assign_capability('mod/forum:viewdiscussion', CAP_ALLOW, 3, 1, true);
        foreach ([['logo_pdf', '.', 11, ''], ['logo_pdf', 'logo.png', 11, 'SOURCE'],
                ['logo_excel', '.', null, ''], ['logo_excel', 'legacy-null.png', null, 'NULL OWNER'],
                ['logo_excel', 'legacy-zero.png', 0, 'ZERO OWNER'], ['unrelated', 'other.png', 11, 'OTHER']]
                as [$area, $name, $owner, $content]) {
            $DB->query('INSERT INTO files (component,contextid,filearea,itemid,filepath,filename,userid,content,
                timecreated,timemodified) VALUES (?,1,?,0,\'/\',?,?,?,100,200)',
                ['local_learning_analytics', $area, $name, $owner, $content]);
        }
    }
}
namespace core\lock {
    class lock_config {
        public static function get_lock_factory($component): lock_factory {
            return new lock_factory();
        }
    }
    class lock_factory {
        public function get_lock($key, $timeout) {
            return $GLOBALS['busy'] ? false : new lock();
        }
    }
    class lock {
        public function release(): void {
            $GLOBALS['released']++;
        }
    }
}
namespace {
    require __DIR__ . '/../../classes/local/legacy_migration.php';
    use local_mulima_analytics\local\legacy_migration as migration;
    fixture();
    $before = $DB->dump();
    $summary = migration::summary();
    check($summary['status'] === 'ready', 'Recognises previous author installation');
    check($summary['counts'] === ['settingscopied' => 2, 'settingskept' => 1, 'permissions' => 3,
        'filescopied' => 5, 'fileskept' => 0, 'menulinks' => 1], 'Preview counts empty/zero settings and directories');
    check($DB->dump() === $before, 'Preview is read-only');
    $sourcepermissions = $DB->query("SELECT * FROM role_capabilities WHERE capability LIKE 'local/learning_analytics:%' ORDER BY id");
    $sourcefiles = $DB->query("SELECT * FROM files WHERE component='local_learning_analytics' ORDER BY id");
    $result = migration::migrate();
    check($result === $summary['counts'], 'Execution matches preview');
    check(get_config('local_mulima_analytics', 'version') === '2026100206', 'Current version is never replaced');
    check(get_config('local_mulima_analytics', 'weight_forums') === '0', 'Preserves zero setting');
    check(get_config('local_mulima_analytics', 'footer_note') === '', 'Preserves blank setting');
    check(get_config('local_mulima_analytics', 'institution_name') === 'Destination institution', 'Preserves existing target settings');
    check(json_encode($DB->query("SELECT * FROM role_capabilities WHERE capability LIKE 'local/learning_analytics:%' ORDER BY id")) ===
        json_encode($sourcepermissions), 'Source permissions remain unchanged');
    $targetpermissions = $DB->query("SELECT capability,roleid,contextid,permission FROM role_capabilities
        WHERE capability LIKE 'local/mulima_analytics:%' ORDER BY id");
    check(count($targetpermissions) === 3, 'Default grants absent from source removed');
    foreach ($targetpermissions as $index => $record) {
        check($record->capability === str_replace('local/learning_analytics:', 'local/mulima_analytics:',
            $sourcepermissions[$index]->capability), 'Capability mapping');
        foreach (['roleid', 'contextid', 'permission'] as $field) {
            check($record->$field === $sourcepermissions[$index]->$field, 'Preserves ' . $field);
        }
    }
    check(count($DB->query("SELECT * FROM role_capabilities WHERE capability='mod/forum:viewdiscussion'")) === 1,
        'Unrelated permissions retained');
    $targetfiles = $DB->query("SELECT * FROM files WHERE component='local_mulima_analytics' ORDER BY id");
    check(count($targetfiles) === 5, 'Copies only system logos');
    foreach ($targetfiles as $file) {
        $matching = array_values(array_filter($sourcefiles, function($source) use ($file) {
            return $source->filearea === $file->filearea && $source->filepath === $file->filepath &&
                $source->filename === $file->filename;
        }))[0];
        $fields = ['contextid', 'itemid', 'filepath', 'filename', 'userid', 'content'];
        if ($file->filename !== '.') {
            $fields = array_merge($fields, ['timecreated', 'timemodified']);
        }
        foreach ($fields as $field) {
            check($file->$field === $matching->$field, 'File metadata: ' . $field);
        }
    }
    check(json_encode($DB->query("SELECT * FROM files WHERE component='local_learning_analytics' ORDER BY id")) ===
        json_encode($sourcefiles), 'Source files retained');
    check(strpos($CFG->custommenuitems, '/local/mulima_analytics/index.php') !== false, 'Menu updated');
    check(migration::summary()['status'] === 'completed', 'Completed status');
    set_config('footer_note', 'New edit', 'local_mulima_analytics');
    $completedstate = $DB->dump();
    check(migration::migrate() === $result, 'Retry returns saved result');
    check($DB->dump() === $completedstate, 'Retry does not overwrite new edits or duplicate files');
    check($released === 2, 'Locks released on success and retry');

    fixture();
    $DB->query("INSERT INTO files (component,contextid,filearea,itemid,filepath,filename,userid,content,
        timecreated,timemodified) VALUES ('local_mulima_analytics',1,'logo_pdf',0,'/','new.png',12,'NEW',300,400)");
    $preview = migration::summary();
    check($preview['counts']['filescopied'] === 3 && $preview['counts']['fileskept'] === 2, 'Existing logo area retained');
    migration::migrate();
    $pdffiles = $DB->query("SELECT * FROM files WHERE component='local_mulima_analytics' AND filearea='logo_pdf'");
    check(count($pdffiles) === 1 && $pdffiles[0]->content === 'NEW', 'Existing logo neither overwritten nor mixed');

    foreach (['missing', 'unrelated', 'wrongcontext', 'wrongtype', 'unregisteredtarget'] as $scenario) {
        fixture($scenario !== 'missing');
        if ($scenario === 'unrelated') {
            $DB->query("DELETE FROM capabilities WHERE component='local_learning_analytics'");
            set_config('version', '2026100299', 'local_learning_analytics');
        } else if ($scenario === 'wrongcontext') {
            $DB->query("UPDATE capabilities SET contextlevel=50 WHERE name='local/learning_analytics:viewaccesses'");
        } else if ($scenario === 'wrongtype') {
            $DB->query("UPDATE capabilities SET captype='write' WHERE name='local/learning_analytics:viewaccesses'");
        } else if ($scenario === 'unregisteredtarget') {
            $DB->query("DELETE FROM capabilities WHERE component='local_mulima_analytics'");
        }
        $before = $DB->dump();
        check(migration::summary()['status'] === 'unavailable', 'Guard: ' . $scenario);
        fails(function() { migration::migrate(); }, 'migration_unavailable');
        check($DB->dump() === $before, 'No changes: ' . $scenario);
    }
    fixture();
    foreach (['permission', 'sesskey', 'migration_busy', 'file_failure', 'marker_failure'] as $failure) {
        $allowed = $failure !== 'permission';
        $sessionvalid = $failure !== 'sesskey';
        $busy = $failure === 'migration_busy';
        $failfile = $failure === 'file_failure';
        $failmarker = $failure === 'marker_failure';
        $before = $DB->dump();
        $menu = $CFG->custommenuitems;
        fails(function() { migration::migrate(); }, $failure);
        check($DB->dump() === $before, 'No partial writes: ' . $failure);
        check($CFG->custommenuitems === $menu, 'Menu retained: ' . $failure);
    }
    check($cacheclears > 0, 'Caches invalidated');

    $menu = "-Report|  /local/learning_analytics/acessos.php?catid=3#results  |en\r\n" .
        "Absolute|https://moodle.test/campus/local/learning_analytics/index.php|pt\n" .
        "Foreign|https://other.test/local/learning_analytics/index.php\r" .
        "Similar|/local/learning_analytics_extra/index.php\n" .
        "Description /local/learning_analytics/index.php|/course/index.php\n" .
        "Folder|/local/learning_analytics\nHeading";
    [$rewritten, $count] = migration::rewrite_menu($menu, 'https://moodle.test/campus');
    check($count === 3, 'Rewrites only own relative and absolute URLs');
    check(strpos($rewritten, "  /local/mulima_analytics/acessos.php?catid=3#results  |en\r\n") !== false,
        'Retains query, anchor, whitespace, locale and CRLF');
    foreach (["Foreign|https://other.test/local/learning_analytics/index.php\r", "Similar|/local/learning_analytics_extra/index.php\n",
            "Description /local/learning_analytics/index.php|/course/index.php\n"] as $unchanged) {
        check(strpos($rewritten, $unchanged) !== false, 'Unrelated menu data retained');
    }
    check(migration::rewrite_menu($rewritten, 'https://moodle.test/campus') === [$rewritten, 0], 'Menu rewrite idempotent');
    check(migration::rewrite_menu('', 'https://moodle.test') === ['', 0], 'Empty menu');
    echo 'PASS ' . $checks . " migration checks (SQL, permissions, files, guards, rollback and menus).\n";
}
