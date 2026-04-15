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
 * @version   1.8 FREE
 * @license   MIT License
 * @date      2014 - 2026
 *
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the 
 * following condition: You **must** retain this header in all copies or 
 * substantial portions of the code, including the author and company information.
 */

namespace Dotsystems\App\Parts;

use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\Config;
use \Dotsystems\App\Parts\DI;
use \Dotsystems\App\Parts\QueryBuilder;
use \Dotsystems\App\Parts\SchemaBuilder;
use \Dotsystems\App\Parts\CacheDriverInterface;

class Databaser
{
    public $cacheDriver = null;
    private $useCache = false;
    private $databases;
    private $connections;
    private $activeconnection;
    private $allDB = [];
    public $statement;
    public $database_drivers; // Type of database we use...
    public $cloned;
    public $driver_helper;
    public $returnType = 'RAW'; // Predvolené správanie pre typ návratovej hodnoty
    private $qb;
    public $di;
    private static $customDrivers = [];

    public function diSet()
    {
        $this->di = DotApp::dotApp()->db;
    }

    function __construct($dotapp = null)
    {
        $this->databases = array();
        $this->database_drivers = array();
        $this->database_drivers['drivers'] = array();
        $this->database_drivers['driver'] = null;
        $this->database_drivers['created'] = array();
        $this->driver_helper = array();
        $this->cloned = false;
        $this->qb = new QueryBuilder($this);
        $this->connections = array();
        $this->activeconnection = array();
        $this->allDB = Config::get("databases");
        $this->useCache = Config::db("cache") ?? false;
        if ($this->useCache === true) $this->setCacheDriver();
        $this->loadDatabases();
    }

    private function loadDatabases()
    {
        if (empty($this->allDB)) return;
        foreach ($this->allDB as $name => $db) {
            $this->driver(strtolower($db["driver"]));
            $this->add($name, $db["host"], $db["username"], $db["password"], $db["database"], $db["charset"], $db["type"]);
        }
        // Loadneme defaultnu DB
        $this->driver(Config::db("driver"));
        $this->selectDb(Config::db("maindb"));
    }

    private function setCacheDriver()
    {
        // Kvoli spatnej kompatibilite pred refaktoringom sposobu akym fungovala cache urobime teraz anonymnu triedu
        // ktora zaruci spatnu kompatibilutu ale bude fungovat nad novymi drivermi
        $cacheObj = Cache::use("databaserCache");
        $setMethod = function ($key, $value, $lifetime) use ($cacheObj) {
            $cacheObj->save($key, $value, $lifetime);
        };
        $getMethod = function ($key) use ($cacheObj) {
            $cacheObj->load($key);
        };
        $this->cacheDriver = new class($setMethod, $getMethod) {
            private $methods = [];

            function __construct($setMethod, $getMethod)
            {
                $methods['set'] = $setMethod;
                $methods['get'] = $getMethod;
            }

            public function set($key, $value, $lifetime)
            {
                $this->methods['set']($key, $value, $lifetime);
            }

            public function get($key)
            {
                return $this->methods['get']($key);
            }
        };
    }

    public function __debugInfo()
    {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }

    // Getters that return references for driver classes
    public function &getActiveConnection()
    {
        return $this->activeconnection;
    }

    public function &getConnections()
    {
        return $this->connections;
    }

    public function &getDatabases()
    {
        return $this->databases;
    }

    public function &getDatabaseDrivers()
    {
        return $this->database_drivers;
    }

    public function &getStatement()
    {
        return $this->statement;
    }

    public function &getQueryBuilder()
    {
        return $this->qb;
    }

    public function &getReturnType()
    {
        return $this->returnType;
    }

    public function &getDI()
    {
        return $this->di;
    }

    public function &getCacheDriver()
    {
        return $this->cacheDriver;
    }

    public function &getUseCache()
    {
        return $this->useCache;
    }


    private function create_mysqli_driver()
    {
        DatabaserMysqliDriver::create($this);
    }

    private function create_pdo_driver()
    {
        DatabaserPdoDriver::create($this);
    }

    /* Set actual driver */
    public function driver($driver)
    {
        // Ak by niekto mal vlastny driver, ale nechce zaberat pamat kym nie je potrebny
        DotApp::DotApp()->trigger("dotapp.db.driver.set", $this, $driver);

        // Vytvorime vstavane drivery az ked su ptorebne...
        if (strtolower($driver) == "pdo" && !isset($this->database_drivers['created']['pdo'])) {
            $this->database_drivers['created']['pdo'] = true;
            $this->create_pdo_driver();
        } else if (strtolower($driver) == "mysqli" && !isset($this->database_drivers['created']['mysqli'])) {
            $this->database_drivers['created']['mysqli'] = true;
            $this->create_mysqli_driver();
        } else {
            // Skusime ci nahodou nie je registrovany vlastny driver
            if (isset(self::$customDrivers[strtolower($driver)])) {
                $customDriverClass = self::$customDrivers[strtolower($driver)];
                if (class_exists($customDriverClass) && method_exists($customDriverClass, 'create')) {
                    $this->database_drivers['created'][strtolower($driver)] = true;
                    $customDriverClass::create($this);
                }
            }
        }

        if (isset($this->database_drivers['drivers'][$driver])) {
            $this->database_drivers['driver'] = $driver;
        }
        return $this;
    }

    public static function customDriver($name, $classname)
    {
        self::$customDrivers = array_merge(self::$customDrivers, [strtolower($name) => $classname]);
    }

    public function addDriver($drivername, $functionname, $driver)
    {
        if (is_callable($driver)) {
            if (!isset($this->database_drivers['drivers'][$drivername])) {
                $this->database_drivers['drivers'][$drivername] = array();
            }
            $this->database_drivers['drivers'][$drivername][$functionname] = $driver;
        }
        return $this;
    }

    public function add($name, $server, $username, $password, $database, $collation = "UTF8", $type = "mysqli")
    {
        // Type - Ak je naprikald PDO, tak moze este specifikovat typ PDO naprikald MYSQL
        if (!isset($this->databases[$this->database_drivers['driver']][$name])) $this->databases[$this->database_drivers['driver']][$name] = [];
        $this->databases[$this->database_drivers['driver']][$name]['type'] = $type;
        $this->databases[$this->database_drivers['driver']][$name]['server'] = $server;
        $this->databases[$this->database_drivers['driver']][$name]['username'] = $username;
        $this->databases[$this->database_drivers['driver']][$name]['password'] = $password;
        $this->databases[$this->database_drivers['driver']][$name]['database'] = $database;
        $this->databases[$this->database_drivers['driver']][$name]['collation'] = $collation;
        return ($this);
    }

    public function select_db($name)
    {
        $this->database_drivers['drivers'][$this->database_drivers['driver']]['select_db']($name);
        return ($this);
    }

    public function selectDb($name)
    {
        // select_db je tu od roku 2014 :D
        // Just alias before releasing to have camelCase
        return $this->select_db($name);
    }

    /* Statements preparation */
    public function return($type)
    {
        $this->database_drivers['drivers'][$this->database_drivers['driver']]['return']($type);
        return $this;
    }

    public function fetchArray(&$array)
    {
        return $this->database_drivers['drivers'][$this->database_drivers['driver']]['fetchArray']($array);
    }

    public function fetchFirst(&$array)
    {
        return $this->database_drivers['drivers'][$this->database_drivers['driver']]['fetchFirst']($array);
    }

    public function cache($driver)
    {
        $this->cacheDriver = $driver;
        return $this;
    }

    public function execute($success = null, $onError = null)
    {
        return ($this->database_drivers['drivers'][$this->database_drivers['driver']]['execute']($success, $onError));
    }

    public function first()
    {
        return $this->database_drivers['drivers'][$this->database_drivers['driver']]['first']();
    }

    public function all()
    {
        return $this->database_drivers['drivers'][$this->database_drivers['driver']]['all']();
    }

    public function raw()
    {
        return $this->database_drivers['drivers'][$this->database_drivers['driver']]['raw']();
    }

    public function transaction()
    {
        $this->database_drivers['drivers'][$this->database_drivers['driver']]['transaction']();
        return ($this);
    }

    public function newEntity($row = [])
    {
        return $this->database_drivers['drivers'][$this->database_drivers['driver']]['newEntity']($row);
    }

    public function newCollection($entities = [])
    {
        return $this->database_drivers['drivers'][$this->database_drivers['driver']]['newCollection']($entities);
    }

    public function getQuery()
    {
        return $this->qb->getQuery();
    }

    // Len ulahcenie transakcii aby netrebalo manualne rollback a commit. Pri uspechu pride proste automaticky commit pri neuspechu rollback.
    // Pozor na pouzitie kedy ma prist commit az niekde ked sa vykonau viacere vnorene prikazy vtedy je nutne ist cez execute a commitovt manualne
    public function transact(callable $operations, $success_callback = null, $error_callback = null)
    {
        $this->database_drivers['drivers'][$this->database_drivers['driver']]['transact']($operations, $success_callback, $error_callback);
        return $this;
    }

    public function commit()
    {
        $this->database_drivers['drivers'][$this->database_drivers['driver']]['commit']();
        return ($this);
    }

    public function rollback()
    {
        $this->database_drivers['drivers'][$this->database_drivers['driver']]['rollback']();
        return ($this);
    }

    public function q(callable $queryBuilder)
    {
        $qbObject = $this->database_drivers['drivers'][$this->database_drivers['driver']]['q']($queryBuilder);
        return (new DI(new QueryObject($qbObject, $this)));
    }

    // Alias na Q kedze predtym to mala byt skratka od Q - query, ale uz pouzivame querybuilder preto QB - takto su zachovane oba.
    public function qb(callable $queryBuilder)
    {
        return $this->q($queryBuilder);
    }

    public function schema(callable $callback, $success = null, $error = null)
    {
        $this->database_drivers['drivers'][$this->database_drivers['driver']]['schema']($callback, $success, $error);
        return $this;
    }

    public function migrate($direction = 'up', $success = null, $error = null)
    {
        $this->database_drivers['drivers'][$this->database_drivers['driver']]['migrate']($direction, $success, $error);
        return $this;
    }

    /*
        BACKWARD COMPATIBILITY WITH MY OLD APPLICATIONS SO I CAN SAFELY UPDATE CORE
    */

    // Zaciatok funkcii spatnej kompatibility

    public function query($query, $ignoreerrors = 0)
    {
        $this->statement['execution_data'] = array();
        mysqli_report(MYSQLI_REPORT_OFF);
        if ($ignoreerrors == 1) {
            return (mysqli_query($this->activeconnection[Config::db('driver')], $query));
        } else {
            $result = mysqli_query($this->activeconnection[Config::db('driver')], $query);
            if ($result) return ($result);
            throw new \Exception(mysqli_error($this->activeconnection[Config::db('driver')]));
        }
    }

    public function query_first($query)
    {
        $this->statement['execution_data'] = array();
        if ($dbreturn = $this->query($query)) {
            $data = mysqli_fetch_array($dbreturn);
            return ($data);
        } else {
            return (array());
        }
    }

    public function insert($table, $data)
    {
        $this->statement['execution_data'] = array();
        $pdata = $this->prepare_query_data($data);
        $query = "INSERT INTO `" . $table . "` (" . implode(",", $pdata['rows']) . ") VALUES (" . implode(",", $pdata['values2']) . ");";
        return (mysqli_query($this->activeconnection[Config::db('driver')], $query));
    }

    public function updateManual($table, $data, $where)
    {
        $this->statement['execution_data'] = array();
        $pdata = $this->prepare_query_data($data);
        $set = array();
        foreach ($pdata['rows'] as $key => $row) {
            $set[] = " " . $row . " = " . $pdata['values2'][$key] . " ";
        }
        $query = "UPDATE `" . $table . "` SET " . implode(",", $set) . " " . $where . ";";
        return (mysqli_query($this->activeconnection[Config::db('driver')], $query));
    }


    public function getDatabaseType()
    {
        if (isset($this->database_drivers['driver'])) {
            $driver = $this->database_drivers['driver'];
            $databases = $this->databases[$driver] ?? [];
            if (!empty($databases)) {
                $dbName = array_key_first($databases);
                return strtolower($databases[$dbName]['type'] ?? 'mysql');
            }
        }
        return 'mysql';
    }

    public function prepare_query_data($data)
    {
        $navrat = array();
        $rows = array();
        $values = array();
        $values2 = array();
        $placeholders = "";
        $vplaceholder = array();
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
        return ($navrat);
    }

    public function insert_multi($table, $data)
    {
        $this->statement['execution_data'] = array();
        $rows = array_keys($data);
        $values = array_map(function ($value) {
            return is_string($value) ? "'$value'" : $value;
        }, array_values($data));
        $query = "INSERT INTO `" . $table . "` (" . implode(',', $rows) . ") VALUES (" . implode(',', $values) . ");";
        return mysqli_query($this->activeconnection['mysqli'], $query);
    }

    public function inserted_id()
    {
        if (isset($this->database_drivers['drivers'][$this->database_drivers['driver']]['inserted_id'])) {
            return $this->database_drivers['drivers'][$this->database_drivers['driver']]['inserted_id']();
        }
        return null;
    }

    public function affected_rows()
    {
        if (isset($this->database_drivers['drivers'][$this->database_drivers['driver']]['affected_rows'])) {
            return $this->database_drivers['drivers'][$this->database_drivers['driver']]['affected_rows']();
        }
        return null;
    }

    // WHERE HAS - filter by relation existence
    public function whereHas($relation, callable $callback = null)
    {
        // Implementation of whereHas - basic version for now
        $this->statement['where_has'][] = [
            'relation' => $relation,
            'callback' => $callback
        ];
        return $this;
    }

    // WHERE DOESNT HAVE - filter by relation non-existence
    public function whereDoesntHave($relation, callable $callback = null)
    {
        $this->statement['where_doesnt_have'][] = [
            'relation' => $relation,
            'callback' => $callback
        ];
        return $this;
    }

    // WITH COUNT - eager loading with count
    public function withCount($relations)
    {
        $relations = is_array($relations) ? $relations : [$relations];
        $this->statement['with_count'] = array_merge($this->statement['with_count'] ?? [], $relations);
        return $this;
    }

    // LOAD MISSING - load missing relations
    public function loadMissing($relations)
    {
        if ($this->statement['return_type'] === 'ORM' && !empty($this->statement['data'])) {
            $relations = is_array($relations) ? $relations : [$relations];
            foreach ($this->statement['data'] as $entity) {
                if ($entity instanceof \Dotsystems\App\Parts\Entity) {
                    $entity->with($relations);
                    $entity->loadRelations();
                }
            }
        }
        return $this;
    }

    // REFRESH - reload all entities
    public function refresh()
    {
        if ($this->statement['return_type'] === 'ORM' && !empty($this->statement['data'])) {
            foreach ($this->statement['data'] as $entity) {
                if ($entity instanceof \Dotsystems\App\Parts\Entity) {
                    $fresh = $entity->fresh();
                    if ($fresh) {
                        $key = array_search($entity, $this->statement['data']);
                        if ($key !== false) {
                            $this->statement['data'][$key] = $fresh;
                        }
                    }
                }
            }
        }
        return $this;
    }

    // EXISTS - whether results exist (optimized)
    public function exists()
    {
        // Klonujeme query pre LIMIT 1 optimalizáciu
        $originalQuery = clone $this->qb;

        $result = false;
        $this->qb->select('1')->limit(1); // SELECT 1 LIMIT 1 is fastest

        $this->execute(function ($data) use (&$result) {
            $result = !empty($data);
        }, null, false); // false = don't trigger callback error handling

        // Restore original query
        $this->qb = $originalQuery;

        return $result;
    }

    // DOESNT EXIST - whether results don't exist
    public function doesntExist()
    {
        return !$this->exists();
    }

    // WITH - eager loading relácií
    public function with($relations)
    {
        $relations = is_array($relations) ? $relations : [$relations];
        $this->statement['with'] = array_merge($this->statement['with'] ?? [], $relations);
        return $this;
    }

    // Koniec spatnej kompatibility

    // Oprava bug pri vnorenych queries sa prepisovala instancia querybuildera.
    public function setQB(QueryBuilder $queryBuilder)
    {
        $this->qb = $queryBuilder;
        return $this;
    }
}

class QueryObject
{
    private $querybuilder;
    private $databaser;

    function __construct(QueryBuilder $qb, Databaser $databaser)
    {
        $this->querybuilder = $qb;
        $this->databaser = $databaser;
    }

    public function execute($success = null, $onError = null)
    {
        $this->databaser->setQB($this->querybuilder);
        return $this->databaser->execute($success, $onError);
    }

    public function getQuery()
    {
        $this->databaser->setQB($this->querybuilder);
        return $this->databaser->getQuery(); // If you have this method in Databaser (from drivers), otherwise add it as wrapper
    }

    public function first()
    {
        $this->databaser->setQB($this->querybuilder);
        return $this->databaser->first();
    }

    public function all()
    {
        $this->databaser->setQB($this->querybuilder);
        return $this->databaser->all();
    }

    public function paginate($perPage = 15, $currentPage = 1, $error_callback = null)
    {
        $offset = ($currentPage - 1) * $perPage;

        // 1. Vytvoríme COUNT query bez limit/offset
        $countQueryBuilder = clone $this->querybuilder;
        $countQueryBuilder->select('COUNT(*) as total');
        $countQueryBuilder->resetLimitOffset();

        $this->databaser->setQB($countQueryBuilder);
        // For count query we use RAW return type
        $originalReturnType = $this->databaser->returnType;
        $this->databaser->returnType = 'RAW';
        $countResult = $this->databaser->execute(function ($result) {
            return $result[0]['total'] ?? 0;
        }, $error_callback);
        $this->databaser->returnType = $originalReturnType;
        $total = is_numeric($countResult) ? $countResult : 0;

        // 2. Aplikujeme LIMIT/OFFSET na pôvodný query
        $this->querybuilder->limit($perPage)->offset($offset);

        // 3. Spustíme query pre stránku
        $this->databaser->setQB($this->querybuilder);
        $result = $this->databaser->execute(function ($result) {
            return $result;
        }, $error_callback);

        // 4. Vrátime paginovaný výsledok
        $data = is_array($result) ? $result : [];

        return [
            'data' => $data,
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, ceil($total / $perPage)),
            'from' => $total > 0 ? $offset + 1 : null,
            'to' => $total > 0 ? min($total, ($currentPage * $perPage)) : null,
            'has_more_pages' => $currentPage < ceil($total / $perPage),
            'prev_page' => $currentPage > 1 ? $currentPage - 1 : null,
            'next_page' => $currentPage < ceil($total / $perPage) ? $currentPage + 1 : null,
        ];
    }

    public function raw()
    {
        $this->databaser->setQB($this->querybuilder);
        return $this->databaser->raw();
    }

    // WHERE HAS - filtre podľa existencie relácie
    public function whereHas($relation, callable $callback = null)
    {
        $this->databaser->whereHas($relation, $callback);
        return $this;
    }

    // WHERE DOESNT HAVE - filter by relation non-existence
    public function whereDoesntHave($relation, callable $callback = null)
    {
        $this->databaser->whereDoesntHave($relation, $callback);
        return $this;
    }

    // WITH COUNT - eager loading with count
    public function withCount($relations)
    {
        $this->databaser->withCount($relations);
        return $this;
    }

    // LOAD MISSING - load missing relations
    public function loadMissing($relations)
    {
        $this->databaser->loadMissing($relations);
        return $this;
    }

    // REFRESH - reload all entities
    public function refresh()
    {
        $this->databaser->refresh();
        return $this;
    }

    // EXISTS - či existujú výsledky
    public function exists()
    {
        return $this->databaser->exists();
    }

    // DOESNT EXIST - whether results don't exist
    public function doesntExist()
    {
        return $this->databaser->doesntExist();
    }

    // Missing methods for ORM compatibility
    public function with($relations)
    {
        $this->databaser->with($relations);
        return $this;
    }
}
