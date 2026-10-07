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
 * Learning Analytics regression checks: access scope.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/** Standalone SQL regression test: php tests/regression/access_scope.php (PDO SQLite required). */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('MOODLE_INTERNAL', true);
define('SITEID', 1);
define('SQL_PARAMS_NAMED', 2);
class invalid_parameter_exception extends Exception {}

/** Minimal Moodle DML adapter; the production SQL is executed against real SQLite tables. */
final class access_test_db {
    public $pdo;
    public $queries = 0;
    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE course_categories (id INTEGER PRIMARY KEY,parent INTEGER,path TEXT,name TEXT,sortorder INTEGER)');
        $this->pdo->exec('CREATE TABLE course (id INTEGER PRIMARY KEY,category INTEGER,fullname TEXT,shortname TEXT)');
        $categories = [[1,0,'/1'],[2,0,'/2'],[10,1,'/1/10'],[11,1,'/1/11'],[20,10,'/1/10/20'],
            [21,10,'/1/10/21'],[30,20,'/1/10/20/30'],[31,20,'/1/10/20/31'],[40,30,'/1/10/20/30/40']];
        $stmt = $this->pdo->prepare('INSERT INTO course_categories VALUES (?,?,?,?,?)');
        foreach ($categories as [$id,$parent,$path]) {
            $stmt->execute([$id,$parent,$path,'Category '.$id,$id]);
        }
        $stmt = $this->pdo->prepare('INSERT INTO course VALUES (?,?,?,?)');
        foreach ([[1,1],[101,1],[102,10],[103,20],[104,30],[105,40],[106,31],[107,21],[108,2]] as [$id,$category]) {
            $stmt->execute([$id,$category,'Course '.$id,'C'.$id]);
        }
    }
    public function sql_like($field, $param) { return "$field LIKE $param"; }
    public function sql_concat(...$fields) { return '(' . implode(' || ', $fields) . ')'; }
    public function get_field($table, $field, $conditions) {
        $where = implode(' AND ', array_map(function($key) {
            return "$key = :$key";
        }, array_keys($conditions)));
        $records = $this->get_records_sql("SELECT $field FROM $table WHERE $where", $conditions);
        $record = reset($records);
        return $record ? $record->$field : false;
    }
    public function get_record($table, $conditions, $fields) {
        $rows = $this->get_records_select($table, 'id = :id', $conditions, '', $fields);
        return reset($rows);
    }
    public function get_records_select($table, $where, $params, $sort = '', $fields = '*') {
        return $this->get_records_sql("SELECT $fields FROM $table WHERE $where" . ($sort ? " ORDER BY $sort" : ''), $params);
    }
    public function get_records_sql($sql, $params) {
        $this->queries++;
        $sql = preg_replace('/\{([a-z_]+)\}/', '$1', $sql);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $record) {
            $first = array_values((array)$record)[0];
            $result[$first] = $record;
        }
        return $result;
    }
    public function get_fieldset_select($table, $field, $where, $params) {
        return array_keys($this->get_records_select($table, $where, $params, '', $field));
    }
    public function get_in_or_equal($ids, $unused, $prefix) {
        $params = [];
        foreach (array_values($ids) as $index => $id) {
            $params[$prefix.$index] = $id;
        }
        return ['IN (:'.implode(',:', array_keys($params)).')', $params];
    }
}
require_once(__DIR__.'/../../classes/local/access_filters.php');
use local_mulima_analytics\local\access_filters;
$DB = new access_test_db();
$passed = 0;
function same($expected, $actual, string $name): void {
    global $passed;
    if ($expected !== $actual) {
        throw new RuntimeException($name . ': '.json_encode(['expected'=>$expected,'actual'=>$actual]));
    }
    $passed++;
    echo "PASS $name\n";
}
function courseids(int $period, int $category): array {
    $ids = array_keys(access_filters::courses($period, $category));
    sort($ids);
    return $ids;
}
same([101,102,103,104,105,106,107], courseids(1,0), 'Period includes its complete subtree, excluding the site course');
same([102,103,104,105,106,107], courseids(1,10), 'Category 1 includes courses stored at its own level');
same([103,104,105,106], courseids(1,20), 'Category 2 excludes sibling DTM');
same([104,105], courseids(1,30), 'Category 3 includes direct and descendant courses only');
same([105], courseids(1,40), 'No arbitrary hierarchy-depth limit');
same([], courseids(1,11), 'Empty category does not fall back to the whole period');
same([108], courseids(2,0), 'Other periods are isolated');
same([104], access_filters::course_ids(1,30,104), 'Selected discipline is validated within the category');
try {
    access_filters::course_ids(1,30,107);
    throw new RuntimeException('Out-of-category course unexpectedly accepted');
} catch (invalid_parameter_exception $e) {
    same(true,true,'Reject a discipline from a sibling category');
}
$DB->queries = 0;
$children = access_filters::children(1,10);
same([20,21], array_column($children,'id'), 'Only direct child categories are listed');
same([4,1], array_column($children,'count'), 'Counts use the same descendant scope as course results');
same([true,false], array_column($children,'haschildren'), 'Leaves are distinguished from intermediate categories');
same(4, $DB->queries, 'Category counts use four queries, independent of descendant count');
same([], access_filters::children(1,40), 'A leaf category has no artificial next category');
foreach ([[1,2],[2,30],[1,999],[0,0],[20,30]] as [$period,$category]) {
    try {
        access_filters::courses($period, $category);
        throw new RuntimeException('Invalid scope unexpectedly accepted');
    } catch (invalid_parameter_exception $e) {
        same(true, true, "Reject invalid scope $period/$category");
    }
}
same([101], access_filters::course_ids(1,0,0,false), 'Blank category includes only courses directly in the period');
same([102], access_filters::course_ids(1,10,0,false), 'Intermediate category does not implicitly include descendants');
same([104], access_filters::course_ids(1,30,0,false), 'Category 3 direct courses exclude unselected Category 4');
same([105], access_filters::course_ids(1,40,0,false), 'Leaf category returns all of its own courses');
same([], access_filters::course_ids(1,11,0,false), 'Empty direct scope remains empty');
try {
    access_filters::course_ids(1,30,105,false);
    throw new RuntimeException('Unselected descendant course unexpectedly accepted');
} catch (invalid_parameter_exception $e) {
    same(true,true,'Direct scope rejects a course in an unselected child');
}
$DB->pdo->exec('DELETE FROM course WHERE id IN (101,102,103)');
same([], access_filters::course_ids(1,0,0,false), 'Period without direct disciplines does not collect descendants');
same([], access_filters::course_ids(1,10,0,false), 'Intermediate category without direct disciplines remains empty');
$stmt = $DB->pdo->prepare('INSERT INTO course VALUES (?,?,?,?)');
foreach ([[101,1],[102,10],[103,20]] as [$id,$category]) {
    $stmt->execute([$id,$category,'Course '.$id,'C'.$id]);
}
echo "RESULT $passed SQL assertions passed.\n";
