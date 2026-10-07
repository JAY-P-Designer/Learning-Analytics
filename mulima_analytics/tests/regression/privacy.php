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
 * Standalone privacy regression checks with SQLite and Moodle API test doubles.
 *
 * Run separately from Moodle: php tests/regression/privacy.php
 * This is not a replacement for tests/privacy_provider_test.php on Moodle.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace {
    if (defined('MOODLE_INTERNAL')) {
        throw new \RuntimeException('Run this standalone fixture outside Moodle.');
    }
    define('MOODLE_INTERNAL', true);
    define('SQL_PARAMS_NAMED', 2);

    /** Standalone context stub. */
    class context {
        public $id;
        public function __construct(int $id) {
            $this->id = $id;
        }
    }
    /** Standalone system-context stub. */
    class context_system extends context {
        public static function instance(): self {
            return new self(1);
        }
    }
    /** Standalone course-context stub. */
    class context_course extends context {
    }

    /** SQL-backed fixture recordset tracks resource cleanup. */
    class privacy_recordset extends \ArrayIterator {
        public static $open = 0;
        public function __construct(array $rows) {
            parent::__construct($rows);
            self::$open++;
        }
        public function close(): void {
            self::$open--;
        }
    }

    /** Executes production SQL against controlled SQLite records. */
    class privacy_database {
        public $pdo;
        public function __construct() {
            $this->pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $this->pdo->exec('CREATE TABLE files (id INTEGER PRIMARY KEY, contextid INTEGER, component TEXT,
                filearea TEXT, itemid INTEGER, filepath TEXT, filename TEXT, userid INTEGER,
                filesize INTEGER, mimetype TEXT, source TEXT, author TEXT, license TEXT,
                timecreated INTEGER, timemodified INTEGER)');
            $this->pdo->exec('CREATE TABLE user (id INTEGER PRIMARY KEY, firstname TEXT)');
            $this->pdo->exec("INSERT INTO user VALUES (11, 'Uploader A'), (12, 'Uploader B'), (13, 'Directory owner')");
            $this->pdo->exec('CREATE TABLE source_data (userid INTEGER, value TEXT)');
            $this->pdo->exec("INSERT INTO source_data VALUES (11, 'Retained core record')");
            $component = 'local_mulima_analytics';
            $rows = [
                [1, 1, $component, 'logo_pdf', 11, '/', 'a.png'],
                [2, 1, $component, 'logo_pdf', 12, '/', 'b.png'],
                [3, 1, $component, 'logo_excel', 11, '/', 'a.png'],
                [4, 1, $component, 'logo_excel', 12, '/', 'b.png'],
                [5, 1, $component, 'logo_pdf', null, '/', 'legacy-null.png'],
                [6, 1, $component, 'logo_excel', 0, '/', 'legacy-zero.png'],
                [7, 1, 'local_listas_exame', 'logo_pdf', 11, '/', 'legacy-plugin.png'],
                [8, 1, $component, 'unrelated', 11, '/', 'other-area.png'],
                [9, 22, $component, 'logo_pdf', 11, '/', 'course.png'],
                [10, 1, $component, 'logo_pdf', 11, '/', '.'],
                [11, 1, $component, 'logo_pdf', 12, '/b/', 'a.png'],
                [12, 1, $component, 'logo_excel', 13, '/empty/', '.'],
            ];
            $stmt = $this->pdo->prepare('INSERT INTO files VALUES (?, ?, ?, ?, 0, ?, ?, ?, 20,
                ?, ?, ?, ?, 100, 200)');
            foreach ($rows as [$id, $context, $comp, $area, $owner, $path, $name]) {
                $stmt->execute([$id, $context, $comp, $area, $path, $name, $owner,
                    'image/png', 'upload', 'Fictional author', 'allrightsreserved']);
            }
        }
        public function get_in_or_equal(array $items, $type, string $prefix): array {
            if (!$items) {
                throw new \RuntimeException('An empty owner list must never reach a file query.');
            }
            $params = [];
            foreach (array_values($items) as $i => $value) {
                $params[$prefix . $i] = $value;
            }
            $names = array_map(function($key) {
                return ':' . $key;
            }, array_keys($params));
            return [count($items) === 1 ? '= ' . $names[0] : 'IN (' . implode(',', $names) . ')', $params];
        }
        public function get_records_sql(string $sql, array $params = []): array {
            $sql = preg_replace('/\{([a-z_]+)\}/', '$1', $sql);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        }
        public function get_recordset_select(string $table, string $where, array $params, string $sort = ''): privacy_recordset {
            return new privacy_recordset($this->get_records_sql("SELECT * FROM $table WHERE $where" .
                ($sort ? " ORDER BY $sort" : ''), $params));
        }
        public function ids(): array {
            return array_map(function($row) {
                return (int)$row->id;
            }, $this->get_records_sql('SELECT id FROM files ORDER BY id'));
        }
    }

    /** File stub delegates record deletion to the fixture only. */
    class stored_file {
        private $record;
        public function __construct(\stdClass $record) {
            $this->record = $record;
        }
        public function __call(string $method, array $arguments) {
            if (strpos($method, 'get_') !== 0 || !property_exists($this->record, substr($method, 4))) {
                throw new \RuntimeException('Unknown file getter: ' . $method);
            }
            return $this->record->{substr($method, 4)};
        }
        public function is_directory(): bool {
            return $this->record->filename === '.';
        }
        public function delete(): void {
            global $DB;
            $DB->get_records_sql('DELETE FROM files WHERE id = :id', ['id' => $this->record->id]);
        }
    }

    /** Files API test double. */
    class privacy_storage {
        public function get_file_by_id(int $id) {
            global $DB;
            $rows = $DB->get_records_sql('SELECT * FROM files WHERE id = :id', ['id' => $id]);
            return $rows ? new stored_file($rows[0]) : false;
        }
        public function delete_area_files(int $contextid, string $component, string $filearea): void {
            global $DB;
            $DB->get_records_sql('DELETE FROM files WHERE contextid = :contextid AND component = :component
                AND filearea = :filearea', compact('contextid', 'component', 'filearea'));
        }
    }
    function get_file_storage(): privacy_storage {
        return new privacy_storage();
    }
    function get_string(string $key, string $component): string {
        $string = [];
        require __DIR__ . '/../../lang/en/local_mulima_analytics.php';
        if (!array_key_exists($key, $string)) {
            throw new \RuntimeException('Missing language key ' . $key);
        }
        return $string[$key];
    }
}

namespace core_privacy\local\metadata {
    interface provider {
        public static function get_metadata(collection $collection): collection;
    }
    /** Retains the declarations for assertions. */
    class collection {
        public $items = [];
        public function add_subsystem_link($name, array $fields = [], $summary = ''): self {
            $this->items[$name] = compact('fields', 'summary');
            return $this;
        }
        public function add_plugintype_link($name, array $fields = [], $summary = ''): self {
            $this->items[$name] = compact('fields', 'summary');
            return $this;
        }
    }
}
namespace core_privacy\local\request {
    interface core_user_data_provider {
        public static function get_contexts_for_userid(int $userid): contextlist;
        public static function export_user_data(approved_contextlist $contextlist);
        public static function delete_data_for_all_users_in_context(\context $context);
        public static function delete_data_for_user(approved_contextlist $contextlist);
    }
    interface core_userlist_provider {
        public static function get_users_in_context(userlist $userlist);
        public static function delete_data_for_users(approved_userlist $userlist);
    }
    class contextlist {
        private $ids = [];
        public function add_from_sql(string $sql, array $params): void {
            global $DB;
            $this->ids = array_map(function($row) {
                return (int)$row->contextid;
            }, $DB->get_records_sql($sql, $params));
        }
        public function get_contextids(): array {
            return $this->ids;
        }
    }
    class approved_contextlist {
        private $user;
        private $component;
        private $ids;
        public function __construct(\stdClass $user, string $component, array $ids) {
            $this->user = $user;
            $this->component = $component;
            $this->ids = $ids;
        }
        public function get_user(): \stdClass { return $this->user; }
        public function get_component(): string { return $this->component; }
        public function get_contextids(): array { return $this->ids; }
    }
    class userlist {
        protected $context;
        protected $component;
        protected $ids = [];
        public function __construct(\context $context, string $component) {
            $this->context = $context;
            $this->component = $component;
        }
        public function get_context(): \context { return $this->context; }
        public function get_component(): string { return $this->component; }
        public function get_userids(): array { return $this->ids; }
        public function add_from_sql(string $field, string $sql, array $params): void {
            global $DB;
            $sql = "SELECT DISTINCT u.id FROM {user} u JOIN ($sql) target ON u.id = target.$field";
            $this->ids = array_map(function($row) {
                return (int)$row->id;
            }, $DB->get_records_sql($sql, $params));
        }
    }
    class approved_userlist extends userlist {
        public function __construct(\context $context, string $component, array $ids) {
            parent::__construct($context, $component);
            $this->ids = $ids;
        }
    }
    /** Captures data passed to Moodle's export writer. */
    class writer {
        public $files = [];
        public $data = [];
        private static $instance = null;
        public static function with_context(\context $context): self {
            return self::$instance ?? (self::$instance = new self());
        }
        public static function reset(): void { self::$instance = null; }
        public function export_file(array $path, \stored_file $file): self {
            $this->files[$file->get_id()] = $path;
            return $this;
        }
        public function export_data(array $path, \stdClass $data): self {
            $this->data[implode('/', $path)] = $data;
            return $this;
        }
    }
}
namespace core_privacy\local\request\plugin {
    interface provider extends \core_privacy\local\request\core_user_data_provider {
    }
}

namespace {
    use core_privacy\local\metadata\collection;
    use core_privacy\local\request\approved_contextlist;
    use core_privacy\local\request\approved_userlist;
    use core_privacy\local\request\userlist;
    use core_privacy\local\request\writer;
    use local_mulima_analytics\privacy\provider;

    require __DIR__ . '/../../classes/privacy/provider.php';
    $checks = 0;
    function same($expected, $actual, string $message): void {
        global $checks;
        $checks++;
        if ($expected !== $actual) {
            throw new \RuntimeException($message . ': expected ' . json_encode($expected) . ', got ' . json_encode($actual));
        }
    }
    function fresh(): void {
        global $DB;
        same(0, privacy_recordset::$open, 'Every recordset is closed');
        $DB = new privacy_database();
        writer::reset();
    }
    function request(int $userid, array $contexts = [1], string $component = 'local_mulima_analytics'): approved_contextlist {
        return new approved_contextlist((object)['id' => $userid], $component, $contexts);
    }

    $component = 'local_mulima_analytics';
    $system = context_system::instance();
    $course = new context_course(22);
    fresh();
    $metadata = provider::get_metadata(new collection());
    same(['core_files', 'logstore'], array_keys($metadata->items), 'Files and logs are declared');
    foreach (['en', 'pt'] as $language) {
        $string = [];
        require __DIR__ . '/../../lang/' . $language . '/local_mulima_analytics.php';
        foreach ($metadata->items as $item) {
            foreach (array_merge([$item['summary']], array_values($item['fields'])) as $key) {
                same(true, isset($string[$key]) && $string[$key] !== '', $language . ' metadata key ' . $key);
            }
        }
    }
    same([1], provider::get_contexts_for_userid(11)->get_contextids(), 'Owner system context');
    same([1], provider::get_contexts_for_userid(13)->get_contextids(), 'Directory-only owner context');
    same([], provider::get_contexts_for_userid(99)->get_contextids(), 'User without files');
    same([], provider::get_contexts_for_userid(0)->get_contextids(), 'Unattributed files have no user request');
    $users = new userlist($system, $component);
    provider::get_users_in_context($users);
    $ids = $users->get_userids();
    sort($ids);
    same([11, 12, 13], $ids, 'Only positive known owners');
    foreach ([new userlist($course, $component), new userlist($system, 'other_plugin')] as $list) {
        provider::get_users_in_context($list);
        same([], $list->get_userids(), 'Unrelated context/component discovery');
    }

    foreach ([request(11, []), request(11, [22]), request(0), request(11, [1], 'other_plugin'), request(99)] as $invalid) {
        provider::export_user_data($invalid);
        same([], writer::with_context($system)->files, 'Invalid/empty request exports no files');
        same([], writer::with_context($system)->data, 'Invalid/empty request exports no metadata');
    }
    provider::export_user_data(request(11));
    $export = writer::with_context($system);
    same([1, 3], array_keys($export->files), 'Only owner files exported across both logo areas');
    $data = reset($export->data);
    same([1, 3, 10], array_map(function($file) {
        return (int)$file->id;
    }, $data->files), 'Directory metadata included');
    foreach ($data->files as $file) {
        same(11, (int)$file->userid, 'No metadata for another user');
    }
    writer::reset();
    provider::export_user_data(request(13));
    same([], writer::with_context($system)->files, 'Directory is not exported as a binary file');
    $data = array_values(writer::with_context($system)->data)[0];
    same([12], array_map(function($file) {
        return (int)$file->id;
    }, $data->files), 'Directory-only user gets metadata');

    fresh();
    foreach ([request(11, []), request(11, [22]), request(0), request(-1), request(11, [1], 'other_plugin')] as $invalid) {
        provider::delete_data_for_user($invalid);
        same(range(1, 12), $DB->ids(), 'Unapproved individual deletion changes nothing');
    }
    provider::delete_data_for_user(request(11));
    same([2, 4, 5, 6, 7, 8, 9, 11, 12], $DB->ids(), 'One-owner deletion keeps other data');
    same([], provider::get_contexts_for_userid(11)->get_contextids(), 'Deleted owner no longer discovered');
    provider::delete_data_for_user(request(11));
    same([2, 4, 5, 6, 7, 8, 9, 11, 12], $DB->ids(), 'Individual deletion is idempotent');
    same('Retained core record', $DB->get_records_sql('SELECT value FROM source_data')[0]->value, 'Core records preserved');

    fresh();
    foreach ([new approved_userlist($course, $component, [11]), new approved_userlist($system, $component, []),
            new approved_userlist($system, $component, [0, -1]), new approved_userlist($system, 'other_plugin', [11])] as $list) {
        provider::delete_data_for_users($list);
        same(range(1, 12), $DB->ids(), 'Invalid bulk deletion is a no-op');
    }
    provider::delete_data_for_users(new approved_userlist($system, $component, [12]));
    same([1, 3, 5, 6, 7, 8, 9, 10, 12], $DB->ids(), 'Bulk deletion limited to approved owner');
    provider::delete_data_for_users(new approved_userlist($system, $component, [11, 12, 0]));
    same([5, 6, 7, 8, 9, 12], $DB->ids(), 'Multiple approved owners; legacy and other components preserved');
    provider::delete_data_for_users(new approved_userlist($system, $component, [13]));
    same([5, 6, 7, 8, 9], $DB->ids(), 'Directory-only owner removed');

    fresh();
    provider::delete_data_for_all_users_in_context($course);
    same(range(1, 12), $DB->ids(), 'All-users request ignores course context');
    provider::delete_data_for_all_users_in_context($system);
    same([7, 8, 9], $DB->ids(), 'All-users deletion limited to system-context plugin logos');
    same('Retained core record', $DB->get_records_sql('SELECT value FROM source_data')[0]->value, 'Source records remain');
    same(0, privacy_recordset::$open, 'No leaked recordsets');
    echo "Privacy regression: $checks assertions passed.\n";
}
