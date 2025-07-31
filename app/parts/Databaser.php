<?php
/**
 * CLASS Databaser
 *
 * This class manages database interactions within the DotApp framework,
 * providing a flexible and secure way to create and execute queries,
 * manage connections, and support custom database drivers.
 *
 * Key Features:
 * - Easy creation and execution of SQL queries with prepared statements.
 * - Multiple database connection management with credential storage.
 * - Custom driver support for flexible database interactions.
 * - Optional ORM in `mysqli` and `pdo` drivers with `Entity` (single row) and `Collection` (row sets).
 * - Lazy loading and relationships (hasOne, hasMany, morphOne, morphMany) with customizable queries via callbacks.
 * - Enhanced relationship handling with optional callback parameter to modify QueryBuilder (e.g., limit, orderBy, where).
 * - Validation and bulk operations in ORM.
 * - Built-in drivers: `mysqli` (legacy and modern with ORM) and `pdo` (modern with ORM).
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.7 FREE
 * @license   MIT License
 * @date      2014 - 2025
 *
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the
 * following condition: You **must** retain this header in all copies or
 * substantial portions of the code, including the author and company information.
 */

namespace Dotsystems\App\Parts;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Config;
use Dotsystems\App\Parts\DI;
use Dotsystems\App\Parts\QueryBuilder;
use Dotsystems\App\Parts\CacheDriverInterface;
use Dotsystems\App\Parts\DatabaserQueryObject;
use Dotsystems\App\Parts\DatabaserMysqliDriver;
use Dotsystems\App\Parts\DatabaserPdoDriver;

class Databaser {
    private static $counter;
    private $cisloInstancie;
    public $cacheDriver = null;
    private $useCache = false;
    private $databases;
    public static $connections = [];
    public $activeconnection;
    private $allDB = [];
    public $statement;
    private static $database_drivers = ['drivers' => [], 'driver' => [], 'created' => []];
    public $cloned;
    public $driver_helper;
    public $returnType = 'RAW';
    private $qb;
    public $di;
    private $selectedDatabase = null;

    function __construct($dotapp = null) {
        $this->databases = [];
        $this->cloned = false;
        $this->qb = new QueryBuilder($this);
        $this->activeconnection = [];
        $this->allDB = Config::get("databases");
        $this->useCache = Config::db("cache") ?? false;
        if ($this->useCache === true) $this->setCacheDriver();
        $this->loadDatabases();
        $this->di = new DI($this);
        self::$counter++;
        $this->cisloInstancie = self::$counter;
        //echo "Nova instancia DATABASERU ".self::$counter."<br>";
    }

    private function loadDatabases() {
        if (empty($this->allDB)) return;
        foreach ($this->allDB as $name => $db) {
            $this->add($name, $db["host"], $db["username"], $db["password"], $db["database"], $db["charset"], $db["type"]);
        }
    }

    private function setCacheDriver() {
        $cacheObj = Cache::use("databaserCache");
        $setMethod = function($key, $value, $lifetime) use ($cacheObj) {
            $cacheObj->save($key, $value, $lifetime);
        };
        $getMethod = function($key) use ($cacheObj) {
            return $cacheObj->load($key);
        };
        $this->cacheDriver = new class($setMethod, $getMethod) {
            private $methods = [];

            function __construct($setMethod, $getMethod) {
                $this->methods['set'] = $setMethod;
                $this->methods['get'] = $getMethod;
            }

            public function set($key, $value, $lifetime) {
                $this->methods['set']($key, $value, $lifetime);
            }

            public function get($key) {
                return $this->methods['get']($key);
            }
        };
    }

    public function __debugInfo() {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }

    public static function getConnections() {
        return self::$connections;
    }

    public static function getDatabaseDrivers() {
        return self::$database_drivers;
    }

    public function getActiveConnection() {
        return $this->activeconnection;
    }

    public function getDatabases() {
        return $this->databases;
    }

    public function getStatement() {
        return $this->statement;
    }

    public function getQB() {
        return $this->qb;
    }

    public function getSelectedDatabase() {
        return $this->selectedDatabase;
    }

    public function setQB(QueryBuilder $queryBuilder) {
        $this->qb = $queryBuilder;
        return $this;
    }

    public static function addDriver($drivername, $functionname, $driver) {
        if (is_callable($driver)) {
            if (!isset(self::$database_drivers['drivers'][$drivername])) {
                self::$database_drivers['drivers'][$drivername] = [];
            }
            self::$database_drivers['drivers'][$drivername][$functionname] = $driver;
        }
    }

    public function add($name, $server, $username, $password, $database, $collation = "UTF8", $type = "mysqli") {
        $driver = $this->allDB[$name]['driver'] ?? 'mysqli';
        if (!isset($this->databases[$driver][$name])) $this->databases[$driver][$name] = [];
        $this->databases[$driver][$name]['type'] = $type;
        $this->databases[$driver][$name]['server'] = $server;
        $this->databases[$driver][$name]['username'] = $username;
        $this->databases[$driver][$name]['password'] = $password;
        $this->databases[$driver][$name]['database'] = $database;
        $this->databases[$driver][$name]['collation'] = $collation;
        return $this;
    }

    public function driver($driver) {
        DotApp::DotApp()->trigger("dotapp.db.driver.set", $this, $driver);

        $driver = strtolower($driver);
        if ($driver == "pdo" && !isset(self::$database_drivers['created']['pdo'])) {
            self::$database_drivers['created']['pdo'] = true;
            DatabaserPdoDriver::create();
        } elseif ($driver == "mysqli" && !isset(self::$database_drivers['created']['mysqli'])) {
            self::$database_drivers['created']['mysqli'] = true;
            DatabaserMysqliDriver::create();
        }

        if (isset(self::$database_drivers['drivers'][$driver])) {
            self::$database_drivers['driver'] = $driver;
        }
        return $this;
    }

    public function select_db($name) {
        // Load the driver for the selected database if not already loaded
        if (isset($this->allDB[$name]['driver'])) {
            $driver = strtolower($this->allDB[$name]['driver']);
            if (!isset(self::$database_drivers['created'][$driver])) {
                $this->driver($driver);
            }
            self::$database_drivers['driver'] = $driver;
        } else {
            throw new \Exception("Database '$name' not found or driver not specified.");
        }

        $this->selectedDatabase = $name; // Set the selected database
        self::$database_drivers['drivers'][self::$database_drivers['driver']]['select_db']($this, $name);
        return $this;
    }

    public function selectDb($name) {
        return $this->select_db($name);
    }

    public function return($type) {
        self::$database_drivers['drivers'][self::$database_drivers['driver']]['return']($this, $type);
        return $this;
    }

    public function fetchArray(&$array) {
        return self::$database_drivers['drivers'][self::$database_drivers['driver']]['fetchArray']($this, $array);
    }

    public function fetchFirst(&$array) {
        return self::$database_drivers['drivers'][self::$database_drivers['driver']]['fetchFirst']($this, $array);
    }

    public function cache($driver) {
        if (!$driver instanceof CacheDriverInterface) {
            throw new \InvalidArgumentException("Cache driver must implement CacheDriverInterface.");
        }
        $this->cacheDriver = $driver;
        return $this;
    }

    public function execute($success = null, $onError = null) {
        return self::$database_drivers['drivers'][self::$database_drivers['driver']]['execute']($this, $success, $onError);
    }

    public function first() {
        return self::$database_drivers['drivers'][self::$database_drivers['driver']]['first']($this);
    }

    public function all() {
        return self::$database_drivers['drivers'][self::$database_drivers['driver']]['all']($this);
    }

    public function raw() {
        return self::$database_drivers['drivers'][self::$database_drivers['driver']]['raw']($this);
    }

    public function transaction() {
        self::$database_drivers['drivers'][self::$database_drivers['driver']]['transaction']($this);
        return $this;
    }

    public function newEntity($row = []) {
        return self::$database_drivers['drivers'][self::$database_drivers['driver']]['newEntity']($this, $row);
    }

    public function newCollection($entities = []) {
        return self::$database_drivers['drivers'][self::$database_drivers['driver']]['newCollection']($this, $entities);
    }

    public function getQuery() {
        return self::$database_drivers['drivers'][self::$database_drivers['driver']]['getQuery']($this);
    }

    public function transact(callable $operations, $success_callback = null, $error_callback = null) {
        self::$database_drivers['drivers'][self::$database_drivers['driver']]['transact']($this, $operations, $success_callback, $error_callback);
        return $this;
    }

    public function commit() {
        self::$database_drivers['drivers'][self::$database_drivers['driver']]['commit']($this);
        return $this;
    }

    public function rollback() {
        self::$database_drivers['drivers'][self::$database_drivers['driver']]['rollback']($this);
        return $this;
    }

    public function q(callable $queryBuilder) {
        $qbObject = self::$database_drivers['drivers'][self::$database_drivers['driver']]['q']($this, $queryBuilder);
        return new DI(new DatabaserQueryObject($qbObject, $this));
    }

    public function qb(callable $queryBuilder) {
        return $this->q($queryBuilder);
    }

    public function schema(callable $callback, $success = null, $error = null) {
        self::$database_drivers['drivers'][self::$database_drivers['driver']]['schema']($this, $callback, $success, $error);
        return $this;
    }

    public function migrate($direction = 'up', $success = null, $error = null) {
        self::$database_drivers['drivers'][self::$database_drivers['driver']]['migrate']($this, $direction, $success, $error);
        return $this;
    }

    // Backward compatibility methods
    public function query($query, $ignoreerrors = 0) {
        $this->statement['execution_data'] = [];
        mysqli_report(MYSQLI_REPORT_OFF);
        if ($ignoreerrors == 1) {
            return mysqli_query($this->getActiveConnection()['mysqli'], $query);
        } else {
            $result = mysqli_query($this->getActiveConnection()['mysqli'], $query);
            if ($result) return $result;
            throw new \Exception(mysqli_error($this->getActiveConnection()['mysqli']));
        }
    }

    public function query_first($query) {
        $this->statement['execution_data'] = [];
        if ($dbreturn = $this->query($query)) {
            $data = mysqli_fetch_array($dbreturn);
            return $data;
        }
        return [];
    }

    public function insert($table, $data) {
        $this->statement['execution_data'] = [];
        $pdata = $this->prepare_query_data($data);
        $query = "INSERT INTO `" . $table . "` (" . implode(",", $pdata['rows']) . ") VALUES (" . implode(",", $pdata['values2']) . ");";
        return mysqli_query($this->getActiveConnection()['mysqli'], $query);
    }

    public function updateManual($table, $data, $where) {
        $this->statement['execution_data'] = [];
        $pdata = $this->prepare_query_data($data);
        $set = [];
        foreach ($pdata['rows'] as $key => $row) {
            $set[] = " " . $row . " = " . $pdata['values2'][$key] . " ";
        }
        $query = "UPDATE `" . $table . "` SET " . implode(",", $set) . " " . $where . ";";
        return mysqli_query($this->getActiveConnection()['mysqli'], $query);
    }

    public function getReturnType() {
        return $this->returnType;
    }

    public function prepare_query_data($data) {
        $navrat = [];
        $rows = [];
        $values = [];
        $values2 = [];
        $placeholders = "";
        $vplaceholder = [];
        $i = 0;
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($value['type'] == "NULL") {
                    $placeholders .= "s";
                    $vplaceholder[] = "?";
                    $values[$i] = null;
                    $values2[$i] = "NULL";
                }
                if ($value['type'] == "INT") {
                    $placeholders .= "i";
                    $vplaceholder[] = "?";
                    $values[$i] = intval($value['val']);
                    $values2[$i] = $values[$i];
                }
                if ($value['type'] == "FLOAT") {
                    $placeholders .= "d";
                    $vplaceholder[] = "?";
                    $values[$i] = floatval($value['val']);
                    $values2[$i] = $values[$i];
                }
                if ($value['type'] == "BOOLEAN") {
                    $placeholders .= "i";
                    $vplaceholder[] = "?";
                    if ($value['val']) $values[$i] = 1;
                    if (!$value['val']) $values[$i] = 0;
                    $values2[$i] = $values[$i];
                }
                if ($value['type'] == "STRING") {
                    $placeholders .= "s";
                    $vplaceholder[] = "?";
                    $values[$i] = $value['val'];
                    $values2[$i] = "'" . $values[$i] . "'";
                }
            } else {
                if ($value === NULL) {
                    $placeholders .= "s";
                    $values[$i] = null;
                    $values2[$i] = "NULL";
                    $vplaceholder[] = "?";
                } elseif (filter_var($value, FILTER_VALIDATE_INT) !== false) {
                    $placeholders .= "i";
                    $values[$i] = intval($value);
                    $values2[$i] = $values[$i];
                    $vplaceholder[] = "?";
                } elseif (filter_var($value, FILTER_VALIDATE_FLOAT) !== false) {
                    $placeholders .= "d";
                    $values[$i] = floatval($value);
                    $values2[$i] = $values[$i];
                    $vplaceholder[] = "?";
                } else if (filter_var($value, FILTER_VALIDATE_BOOLEAN) !== false) {
                    $placeholders .= "i";
                    if ($value) $values[$i] = 1;
                    if (!$value) $values[$i] = 0;
                    $values2[$i] = $values[$i];
                    $vplaceholder[] = "?";
                } else {
                    $placeholders .= "s";
                    $values[$i] = $value;
                    $vplaceholder[] = "?";
                    $values2[$i] = "'" . $values[$i] . "'";
                }
            }
            $rows[$i] = "`" . $key . "`";
            $i++;
        }
        $navrat['rows'] = $rows;
        $navrat['values'] = $values;
        $navrat['values2'] = $values2;
        $navrat['flaceholder'] = $placeholders;
        $navrat['qplaceholder'] = implode(",", $vplaceholder);
        return $navrat;
    }

    public function autobind_params($stmt, $placeholder, $values) {
        $bindarray = array_merge([$placeholder], $values);
        $reflection = new ReflectionClass('mysqli_stmt');
        $method = $reflection->getMethod('bind_param');
        $method->invokeArgs($stmt, $bindarray);
    }

    public function insert_multi($table, $data) {
        $this->statement['execution_data'] = [];
        $rows = array_keys($data);
        $values = array_map(function ($value) {
            return is_string($value) ? "'$value'" : $value;
        }, array_values($data));
        $query = "INSERT INTO `" . $table . "` (" . implode(',', $rows) . ") VALUES (" . implode(',', $values) . ");";
        return mysqli_query($this->getActiveConnection()['mysqli'], $query);
    }

    public function inserted_id() {
        if (isset(self::$database_drivers['drivers'][self::$database_drivers['driver']]['inserted_id'])) {
            return self::$database_drivers['drivers'][self::$database_drivers['driver']]['inserted_id']($this);
        }
        return null;
    }

    public function affected_rows() {
        if (isset(self::$database_drivers['drivers'][self::$database_drivers['driver']]['affected_rows'])) {
            return self::$database_drivers['drivers'][self::$database_drivers['driver']]['affected_rows']($this);
        }
        return null;
    }
}