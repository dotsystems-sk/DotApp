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
 * @version   1.6 FREE
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

class Databaser {
    public $cacheDriver = null;
    private $databases;
    private $connections;
    private $activeconnection;
    public $statement;
    public $database_drivers; // Type of database we use...
    public $cloned;
    public $driver_helper;
    public $returnType = 'RAW'; // Predvolené správanie pre typ návratovej hodnoty
    private $qb; // Query builder
    public $di;
    public $dotapp;
    public $dotApp;
    public $DotApp;

    public function diSet() {
        $this->di = $this->dotapp->db;
    }

    function __construct($dotapp=null) {
        $this->dotapp = $dotapp;
        $this->dotApp = $dotapp;
        $this->DotApp = $dotapp;
        $this->databases = array();
        $this->database_drivers = array();
        $this->database_drivers['drivers'] = array();
        $this->database_drivers['created'] = array();
        $this->driver_helper = array();
        $this->cloned = false;
        $this->qb = new QueryBuilder($this); 
        $this->connections = array();
        $this->activeconnection = array();
    }

    public function __debugInfo() {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }

    private function create_mysqli_driver() {
        $this->databases["mysqli"] = [];
        $this->statement['execution_type'] = 0;
    
        // Pomocná funkcia na určenie typu väzby (špecifické pre MySQLi)
        $getBindingType = function ($value) {
            if (is_int($value)) return 'i';
            if (is_float($value)) return 'd';
            if (is_string($value)) return 's';
            if (is_null($value)) return 's'; // NULL ako string v MySQLi
            return 'b'; // Blob ako fallback
        };
    
        // Vyčistenie statementu
        $clear_statement = function () {
            unset($this->statement);
            $this->statement = [
                'execution_type' => 0,
                'query_parts' => [
                    'select' => '',
                    'from' => '',
                    'joins' => [],
                    'where' => [],
                    'group_by' => '',
                    'having' => '',
                    'order_by' => '',
                    'limit' => '',
                    'offset' => ''
                ],
                'bindings' => [],
                'binding_types' => '',
                'values' => [],
                'values_types' => [],
                'execution_data' => [],
                'transaction' => null,
                'table' => 'unknown_table' // Inicializujeme table
            ];
        };
   
        // Pripojenie k databáze
        $this->addDriver("mysqli", "select_db", function ($name) {
            if (!isset($this->connections[$this->database_drivers['driver']][$name])) {
                $this->connections[$this->database_drivers['driver']][$name] = mysqli_connect(
                    $this->databases["mysqli"][$name]['server'],
                    $this->databases["mysqli"][$name]['username'],
                    $this->databases["mysqli"][$name]['password']
                );
                if ($this->connections[$this->database_drivers['driver']][$name]) {
                    $this->activeconnection['mysqli'] = $this->connections[$this->database_drivers['driver']][$name];
                    mysqli_report(MYSQLI_REPORT_OFF);
                    mysqli_set_charset($this->activeconnection['mysqli'], $this->databases["mysqli"][$name]['collation']);
                    mysqli_select_db($this->activeconnection['mysqli'], $this->databases["mysqli"][$name]['database']);
                } else {
                    $this->activeconnection['mysqli'] = null;
                    throw new \Exception("Nepodarilo sa pripojiť k databáze: " . mysqli_connect_error());
                }
            } else {
                $this->activeconnection['mysqli'] = $this->connections[$this->database_drivers['driver']][$name];
                mysqli_report(MYSQLI_REPORT_OFF);
            }
        });
    
        // Query Builder podpora
        $this->addDriver("mysqli", "q", function ($querybuilder) use ($clear_statement) {
            $clear_statement();
            $this->qb = new QueryBuilder($this);
            if (is_callable($querybuilder)) {
                $querybuilder($this->qb);
            }
        });

        $this->addDriver("mysqli", "schema", function (callable $callback, $success=null, $error=null) use ($clear_statement) {
            $clear_statement();
            $this->qb = new QueryBuilder($this);
            $callback($this->qb);
            $this->execute($success,$error); // Spustí query cez MySQLi
        });
    
        $this->addDriver("mysqli", "migrate", function ($direction = 'up', $success=null, $error=null) use ($clear_statement) {
            $clear_statement();
            if ($direction === 'up') {
                $this->qb = new QueryBuilder($this);
                $this->qb->createTable('migrations', function ($table) {
                    $table->id();
                    $table->string('migration');
                    $table->timestamps();
                });
                $this->execute($success, $error);
                // Tu by bola logika pre načítanie a aplikovanie migračných súborov
            } elseif ($direction === 'down') {
                $this->qb = new QueryBuilder($this);
                $this->qb->dropTable('migrations');
                $this->execute($success, $error);
            }
        });
    
        // Nastavenie typu návratovej hodnoty
        $this->addDriver("mysqli", "return", function ($type) {
            $this->returnType = strtoupper($type);
        });
    
        // Získanie vygenerovaného dotazu
        $this->addDriver("mysqli", "getQuery", function () {
            $queryData = $this->qb->getQuery();
            return [
                'query' => $queryData['query'],
                'bindings' => $queryData['bindings']
            ];
        });

        $this->addDriver("mysqli", "inserted_id", function () {
            if ($this->activeconnection['mysqli']) {
                return mysqli_insert_id($this->activeconnection['mysqli']);
            }
            return null;
        });
        
        $this->addDriver("mysqli", "affected_rows", function () {
            if ($this->activeconnection['mysqli']) {
                return mysqli_affected_rows($this->activeconnection['mysqli']);
            }
            return null;
        });
    
        // Načítanie výsledkov
        $this->addDriver("mysqli", "fetchArray", function (&$array) {
            return mysqli_fetch_assoc($array);
        });
    
        $this->addDriver("mysqli", "fetchFirst", function (&$array) {
            return $array ? mysqli_fetch_assoc($array) : false;
        });

        $this->addDriver("mysqli", "newEntity", function ($row) {
            return new Entity($row,$this->di);
        });

        $this->addDriver("mysqli", "newCollection", function ($queryOrItems) {
            return new Collection($queryOrItems, $this->di);
        });
    
        // Execute
        $this->addDriver("mysqli", "execute", function ($success_callback = null, $error_callback = null) use ($getBindingType) {
            if (!$this->activeconnection[$this->database_drivers['driver']]) {
                throw new \Exception("No active connection to database ! Use select_db() !");
            }
            $queryData = $this->qb->getQuery();
            $query = $queryData['query'];
            $values = $queryData['bindings'];
            $types = $queryData['types'];
        
            $table = $this->statement['table'] ?? 'unknown_table';
            if (isset($queryData['queryParts']['table'])) {
                $table = $queryData['queryParts']['table']; // Pre CREATE TABLE, ALTER TABLE
            } elseif (isset($queryData['queryParts']['from'])) {
                $table = trim(str_replace('FROM', '', $queryData['queryParts']['from']));
            } elseif (isset($queryData['queryParts']['update'])) {
                $table = trim(str_replace('UPDATE', '', $queryData['queryParts']['update']));
            } elseif (isset($queryData['queryParts']['insert'])) {
                $table = trim(preg_replace('/INSERT INTO (\w+).*/', '$1', $queryData['queryParts']['insert']));
            }
            $this->statement['table'] = $table;

            $cacheKey = "{$table}:{$this->returnType}:" . md5($query . serialize($values));

            if ($this->cacheDriver && $cached = $this->cacheDriver->get($cacheKey)) {
                if (is_callable($success_callback)) $success_callback($cached, $this, []);
                return $cached;
            }
        
            $execution_data = [
                'query' => $query,
                'bindings' => $values
            ];
        
            $stmt = $this->activeconnection['mysqli']->prepare($query);
            if ($stmt === false) {
                $error = ['error' => $this->activeconnection['mysqli']->error, 'errno' => $this->activeconnection['mysqli']->errno];
                if (is_callable($error_callback)) {
                    $error_callback($error, $this, $execution_data);
                } else {
                    throw new \Exception("Error: " . $error['error'] . " (errcode: " . $error['errno'] . ")");
                }
                return false;
            }
        
            if (!empty($values)) {
                $stmt->bind_param($types, ...$values);
            }
        
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $execution_data = [
                    'affected_rows' => $stmt->affected_rows,
                    'insert_id' => $stmt->insert_id,
                    'num_rows' => $result ? $result->num_rows : 0,
                    'result' => $result,
                    'query' => $query,
                    'bindings' => $values
                ];
                $this->statement['execution_data'] = $execution_data;
        
                if ($this->returnType === "ORM") {
                    if ($result && $result->num_rows > 0) {
                        $rows = [];
                        while ($row = $this->fetchArray($result)) {
                            $entity = new Entity($row, $this->di);
                            $entity->loadRelations();
                            $rows[] = $entity;
                        }
                        $returnValue = new Collection($rows, $this->di);
                    } else {
                        $returnValue = null;
                    }
                    if ($this->cacheDriver && $returnValue) {
                        $this->cacheDriver->set($cacheKey, $returnValue, 3600);
                    }
                    if (is_callable($success_callback)) {
                        $success_callback($returnValue, $this, $execution_data);
                    }
                    $stmt->close();
                    $this->q(function ($qb) {});
                    return $returnValue;
                } else {
                    $rows = [];
                    while ($row = $this->fetchArray($result)) {
                        $rows[] = $row;
                    }
                    if ($this->cacheDriver && $rows) {
                        $this->cacheDriver->set($cacheKey, $rows, 3600);
                    }
                    if (is_callable($success_callback)) $success_callback($rows, $this, $execution_data);

                    $stmt->close();
                    $this->q(function ($qb) {});
                    return $result;
                }
            } else {
                $error = ['error' => $stmt->error, 'errno' => $stmt->errno];
                if (is_callable($error_callback)) {
                    $error_callback($error, $this, $execution_data);
                } else {
                    throw new \Exception("Error: " . $error['error'] . " (errcode: " . $error['errno'] . ")");
                }
                $stmt->close();
                return false;
            }
        });
    
        // First
        $this->addDriver("mysqli", "first", function () {
            $result = $this->execute();
            if ($this->returnType === 'ORM') {
                return $result->getItem(0);
            }
            return $result[0];
        });
    
        // All
        $this->addDriver("mysqli", "all", function () {
            if ($this->returnType === 'ORM') {
                return new Collection(clone $this->di, $this->di);
            }
            $result = $this->execute();
            $rows = [];
            while ($row = $this->fetchArray($result)) {
                $rows[] = $row;
            }
            return $rows;
        });
    
        // Raw
        $this->addDriver("mysqli", "raw", function () {
            return $this->execute();
        });
    
        // Transakcie
        $this->addDriver("mysqli", "transaction", function () {
            $this->activeconnection['mysqli']->begin_transaction();
        });

        $this->addDriver("mysqli", "transact", function ($operations, $success_callback = null, $error_callback = null) {
            $this->activeconnection['mysqli']->begin_transaction();
            $operations($this,
                function ($result, $db, $execution_data) use ($success_callback) {
                    $this->activeconnection['mysqli']->commit();
                    if (is_callable($success_callback)) {
                        $success_callback($result, $this, $execution_data);
                    }
                },
                function ($error, $db, $execution_data) use ($error_callback) {
                    $this->activeconnection['mysqli']->rollback();
                    if (is_callable($error_callback)) {
                        $error_callback($error, $this, $execution_data);
                    }
                }
            );
        });
    
        $this->addDriver("mysqli", "commit", function () {
            $this->activeconnection['mysqli']->commit();
        });
    
        $this->addDriver("mysqli", "rollback", function () {
            $this->activeconnection['mysqli']->rollback();
        });
    }

    private function create_pdo_driver() {
        $this->databases["pdo"] = [];
        $this->statement['execution_type'] = 0;
    
        // Pomocná funkcia na určenie typu väzby
        $getBindingType = function ($value) {
            if (is_int($value)) return \PDO::PARAM_INT;
            if (is_float($value)) return \PDO::PARAM_STR; // PDO nemá explicitný float, použijeme string
            if (is_string($value)) return \PDO::PARAM_STR;
            if (is_null($value)) return \PDO::PARAM_NULL;
            return \PDO::PARAM_LOB; // Blob ako fallback
        };
    
        // Vyčistenie statementu
        $clear_statement = function () {
            unset($this->statement);
            $this->statement = [
                'execution_type' => 0,
                'query_parts' => [
                    'select' => '',
                    'from' => '',
                    'joins' => [],
                    'where' => [],
                    'group_by' => '',
                    'having' => '',
                    'order_by' => '',
                    'limit' => '',
                    'offset' => ''
                ],
                'bindings' => [],
                'binding_types' => '',
                'values' => [],
                'values_types' => [],
                'execution_data' => [],
                'transaction' => null,
                'table' => 'unknown_table' // Pridané
            ];
        };
    
        // Pripojenie k databáze cez PDO s dynamickým DSN
        $this->addDriver("pdo", "select_db", function ($name) {
            if (!isset($this->connections[$this->database_drivers['driver']][$name])) {
                $type = strtolower($this->databases['pdo'][$name]['type']);
                $server = $this->databases['pdo'][$name]['server'];
                $database = $this->databases['pdo'][$name]['database'];
                $collation = $this->databases['pdo'][$name]['collation'];
    
                // Generovanie DSN na základe typu databázy
                switch ($type) {
                    case 'mysql':
                        $dsn = "mysql:host={$server};dbname={$database};charset={$collation}";
                        break;
                    case 'pgsql': // PostgreSQL
                        $dsn = "pgsql:host={$server};dbname={$database}";
                        break;
                    case 'sqlite': // SQLite
                        $dsn = "sqlite:{$database}"; // Pre SQLite je "database" cesta k súboru
                        break;
                    default:
                        throw new \Exception("Nepodporovaný typ databázy: {$type}");
                }
    
                try {
                    $this->connections[$this->database_drivers['driver']][$name] = new \PDO(
                        $dsn,
                        $this->databases['pdo'][$name]['username'],
                        $this->databases['pdo'][$name]['password'],
                        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
                    );
                    $this->activeconnection['pdo'] = $this->connections[$this->database_drivers['driver']][$name];
                } catch (\PDOException $e) {
                    $this->activeconnection['pdo'] = null;
                    throw new \Exception("Nepodarilo sa pripojiť k databáze: " . $e->getMessage());
                }
            } else {
                $this->activeconnection['pdo'] = $this->connections[$this->database_drivers['driver']][$name];
            }
        });
    
        // Query Builder podpora
        $this->addDriver("pdo", "q", function ($querybuilder) use ($clear_statement) {
            $clear_statement();
            $this->qb = new QueryBuilder($this); // Vždy nový QueryBuilder
            if (is_callable($querybuilder)) {
                $querybuilder($this->qb);
            }
        });

        $this->addDriver("pdo", "inserted_id", function () {
            if ($this->activeconnection['pdo']) {
                return $this->activeconnection['pdo']->lastInsertId();
            }
            return null;
        });
        
        $this->addDriver("pdo", "affected_rows", function () {
            if ($this->activeconnection['pdo'] && isset($this->statement['execution_data']['result'])) {
                return $this->statement['execution_data']['result']->rowCount();
            }
            return null;
        });

        $this->addDriver("pdo", "schema", function (callable $callback, $success=null, $error=null) use ($clear_statement) {
            $clear_statement();
            $this->qb = new QueryBuilder($this);
            $callback($this->qb);
            $this->execute($success,$error); // Spustí query cez PDO
        });
    
        $this->addDriver("pdo", "migrate", function ($direction = 'up', $success=null, $error=null) use ($clear_statement) {
            $clear_statement();
            if ($direction === 'up') {
                $this->qb = new QueryBuilder($this);
                $this->qb->createTable('migrations', function ($table) {
                    $table->id();
                    $table->string('migration');
                    $table->timestamps();
                });
                $this->execute($success,$error);
                // Tu by bola logika pre načítanie a aplikovanie migračných súborov
            } elseif ($direction === 'down') {
                $this->qb = new QueryBuilder($this);
                $this->qb->dropTable('migrations');
                $this->execute($success,$error);
            }
        });
    
        // Nastavenie typu návratovej hodnoty
        $this->addDriver("pdo", "return", function ($type) {
            $this->returnType = strtoupper($type);
        });
    
        // Získanie vygenerovaného dotazu
        $this->addDriver("pdo", "getQuery", function () {
            $queryData = $this->qb->getQuery();
            return [
                'query' => $queryData['query'],
                'bindings' => $queryData['bindings']
            ];
        });
    
        // Načítanie výsledkov
        $this->addDriver("pdo", "fetchArray", function (&$stmt) {
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        });
    
        $this->addDriver("pdo", "fetchFirst", function (&$stmt) {
            return $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
        });

        $this->addDriver("pdo", "newEntity", function ($row) {
            return new Entity($row,$this->di);
        });

        $this->addDriver("pdo", "newCollection", function ($queryOrItems) {
            return new Collection($queryOrItems, $this->di);
        });
    
        // Execute
        $this->addDriver("pdo", "execute", function ($success_callback = null, $error_callback = null) use ($getBindingType) {
            if (!$this->activeconnection[$this->database_drivers['driver']]) {
                throw new \Exception("No active connection to database ! Use select_db() !");
            }
            $queryData = $this->qb->getQuery();
            $query = $queryData['query'];
            $values = $queryData['bindings'];
        
            // Nový formát kľúča
            $table = $this->statement['table'] ?? 'unknown_table';
            if (isset($queryData['queryParts']['table'])) {
                $table = $queryData['queryParts']['table']; // Pre CREATE TABLE, ALTER TABLE
            } elseif (isset($queryData['queryParts']['from'])) {
                $table = trim(str_replace('FROM', '', $queryData['queryParts']['from']));
            } elseif (isset($queryData['queryParts']['update'])) {
                $table = trim(str_replace('UPDATE', '', $queryData['queryParts']['update']));
            } elseif (isset($queryData['queryParts']['insert'])) {
                $table = trim(preg_replace('/INSERT INTO (\w+).*/', '$1', $queryData['queryParts']['insert']));
            }
            $this->statement['table'] = $table;

            $cacheKey = "{$table}:{$this->returnType}:" . md5($query . serialize($values));

            if ($this->cacheDriver && $cached = $this->cacheDriver->get($cacheKey)) {
                if (is_callable($success_callback)) $success_callback($cached, $this, []);
                return $cached;
            }
        
            $execution_data = [
                'query' => $query,
                'bindings' => $values
            ];
        
            try {
                $stmt = $this->activeconnection['pdo']->prepare($query);
                if ($stmt === false) {
                    $error = ['error' => $this->activeconnection['pdo']->errorInfo()[2] ?? 'Failed to prepare statement', 'errno' => $this->activeconnection['pdo']->errorCode()];
                    if (is_callable($error_callback)) {
                        $error_callback($error, $this, $execution_data);
                    } else {
                        throw new \Exception("Error: " . $error['error'] . " (errcode: " . $error['errno'] . ")");
                    }
                    return false;
                }
        
                foreach ($values as $index => $value) {
                    $stmt->bindValue($index + 1, $value, $getBindingType($value));
                }
        
                if ($stmt->execute()) {
                    $execution_data = [
                        'affected_rows' => $stmt->rowCount(),
                        'insert_id' => $this->activeconnection['pdo']->lastInsertId(),
                        'num_rows' => $stmt->rowCount(),
                        'result' => $stmt,
                        'query' => $query,
                        'bindings' => $values
                    ];
                    $this->statement['execution_data'] = $execution_data;
        
                    if ($this->returnType === "ORM") {
                        if ($stmt->rowCount() > 0) {
                            $rows = [];
                            while ($row = $this->fetchArray($stmt)) {
                                $entity = new Entity($row, $this->di);
                                $entity->loadRelations();
                                $rows[] = $entity;
                            }
                            $returnValue = new Collection($rows, $this->di);
                        } else {
                            $returnValue = null;
                        }
                        if ($this->cacheDriver && $returnValue) {
                            $this->cacheDriver->set($cacheKey, $returnValue, 3600);
                        }
                        if (is_callable($success_callback)) $success_callback($returnValue, $this, $execution_data);
                        $stmt->closeCursor();
                        $this->q(function ($qb) {});
                        return $returnValue;
                    } else {
                        // Toto bolo niekedy tu na to, aby vratilo rovno pole zo STMT. Ale neskor prerobene aby vratilo STMT result
                        $rows = [];
                        while ($row = $this->fetchArray($stmt)) {
                            $rows[] = $row;
                        }
                        if ($this->cacheDriver && $rows) {
                            $this->cacheDriver->set($cacheKey, $rows, 3600);
                        }
                        if (is_callable($success_callback)) $success_callback($rows, $this, $execution_data);
                        $stmt->closeCursor();
                        $this->q(function ($qb) {});
                        return $rows;
                    }
                } else {
                    $error = ['error' => $stmt->errorInfo()[2] ?? 'Execution failed', 'errno' => $stmt->errorCode()];
                    if (is_callable($error_callback)) {
                        $error_callback($error, $this, $execution_data);
                    } else {
                        throw new \Exception("Error: " . $error['error'] . " (errcode: " . $error['errno'] . ")");
                    }
                    $stmt->closeCursor();
                    return false;
                }
            } catch (\PDOException $e) {
                $error = ['error' => $e->getMessage(), 'errno' => $e->getCode()];
                if (is_callable($error_callback)) {
                    $error_callback($error, $this, $execution_data);
                } else {
                    throw new \Exception("Error: " . $e->getMessage() . " (errcode: " . $e->getCode() . ")");
                }
                return false;
            }
        });
    
        // First
        $this->addDriver("pdo", "first", function () {
            $result = $this->execute();
            if ($this->returnType === 'ORM') {
                return $result->getItem(0);
            }
            return $result[0];
        });
    
        // All
        $this->addDriver("pdo", "all", function () {
            if ($this->returnType === 'ORM') {
                return new Collection(clone $this->di, $this->di);
            }
            $rows = $this->execute();
            return $rows;
        });
    
        // Raw
        $this->addDriver("pdo", "raw", function () {
            return $this->execute();
        });
    
        // Transakcie
        $this->addDriver("pdo", "transaction", function () {
            $this->activeconnection['pdo']->beginTransaction();
        });

        $this->addDriver("pdo", "transact", function ($operations, $success_callback = null, $error_callback = null) {
            $this->activeconnection['pdo']->beginTransaction();
            $operations($this,
                function ($result, $db, $execution_data) use ($success_callback) {
                    $this->activeconnection['pdo']->commit();
                    if (is_callable($success_callback)) {
                        $success_callback($result, $this, $execution_data);
                    }
                },
                function ($error, $db, $execution_data) use ($error_callback) {
                    $this->activeconnection['pdo']->rollback();
                    if (is_callable($error_callback)) {
                        $error_callback($error, $this, $execution_data);
                    }
                }
            );
        });
    
        $this->addDriver("pdo", "commit", function () {
            $this->activeconnection['pdo']->commit();
        });
    
        $this->addDriver("pdo", "rollback", function () {
            $this->activeconnection['pdo']->rollback();
        });
    }

    /* Set actual driver */
    public function driver($driver, DotApp $dotapp) {
        // Ak by niekto mal vlastny driver, ale nechce zaberat pamat kym nie je potrebny
        $dotapp->trigger("dotapp.db.driver.set",$this,$driver);
        
        // Vytvorime vstavane drivery az ked su ptorebne...
        if (strtolower($driver) == "pdo" && !isset($this->database_drivers['created']['pdo'])) {
            $this->database_drivers['created']['pdo'] = true;
            $this->create_pdo_driver();
        } else if (strtolower($driver) == "mysqli" && !isset($this->database_drivers['created']['mysqli'])) {
            $this->database_drivers['created']['mysqli'] = true;
            $this->create_mysqli_driver();
        }        
        
        if (isset($this->database_drivers['drivers'][$driver])) {
            $this->database_drivers['driver'] = $driver;
        }
        return $this;
    }

    public function addDriver($drivername, $functionname, $driver) {
        if (is_callable($driver)) {
            if (!isset($this->database_drivers['drivers'][$drivername])) {
                $this->database_drivers['drivers'][$drivername] = array();
            }
            $this->database_drivers['drivers'][$drivername][$functionname] = $driver;
        }
        return $this;
    }

    public function add($name, $server, $username, $password, $database, $collation = "UTF8", $type="mysqli") {
        // Type - Ak je naprikald PDO, tak moze este specifikovat typ PDO naprikald MYSQL
        $this->databases[$this->database_drivers['driver']][$name]['type'] = $type;
        $this->databases[$this->database_drivers['driver']][$name]['server'] = $server;
        $this->databases[$this->database_drivers['driver']][$name]['username'] = $username;
        $this->databases[$this->database_drivers['driver']][$name]['password'] = $password;
        $this->databases[$this->database_drivers['driver']][$name]['database'] = $database;
        $this->databases[$this->database_drivers['driver']][$name]['collation'] = $collation;
        return ($this);
    }

    public function select_db($name) {
        $this->database_drivers['drivers'][$this->database_drivers['driver']]['select_db']($name);
        return ($this);
    }

    public function selectDb($name) {
        // select_db je tu od roku 2014 :D
        // Just alias before releasing to have camelCase
        return $this->select_db($name);
    }

    /* Statements preparation */
    public function return($type) {
        $this->database_drivers['drivers'][$this->database_drivers['driver']]['return']($type);
        return $this;
    }

    public function fetchArray(&$array) {
        return $this->database_drivers['drivers'][$this->database_drivers['driver']]['fetchArray']($array);
    }

    public function fetchFirst(&$array) {
        return $this->database_drivers['drivers'][$this->database_drivers['driver']]['fetchFirst']($array);
    }

    public function cache($driver) {
        if (!$driver instanceof CacheDriverInterface) {
            throw new \InvalidArgumentException("Cache driver must implement CacheDriverInterface.");
        }
        $this->cacheDriver = $driver;
        return $this;
    }

    public function execute($success = null, $onError = null) {
        return ($this->database_drivers['drivers'][$this->database_drivers['driver']]['execute']($success, $onError));
    }

    public function first() {
        return $this->database_drivers['drivers'][$this->database_drivers['driver']]['first']();
    }

    public function all() {
        return $this->database_drivers['drivers'][$this->database_drivers['driver']]['all']();
    }

    public function raw() {
        return $this->database_drivers['drivers'][$this->database_drivers['driver']]['raw']();
    }

    public function transaction() {
        $this->database_drivers['drivers'][$this->database_drivers['driver']]['transaction']();
        return ($this);
    }

    public function newEntity($row = []) {
        return $this->database_drivers['drivers'][$this->database_drivers['driver']]['newEntity']($row);
    }

    public function newCollection($entities = []) {
        return $this->database_drivers['drivers'][$this->database_drivers['driver']]['newCollection']($entities);
    }

    // Len ulahcenie transakcii aby netrebalo manualne rollback a commit. Pri uspechu pride proste automaticky commit pri neuspechu rollback.
    // Pozor na pouzitie kedy ma prist commit az niekde ked sa vykonau viacere vnorene prikazy vtedy je nutne ist cez execute a commitovt manualne
    public function transact(callable $operations, $success_callback = null, $error_callback = null) {
        $this->database_drivers['drivers'][$this->database_drivers['driver']]['transact']($operations, $success_callback, $error_callback);
        return $this;
    }

    public function commit() {
        $this->database_drivers['drivers'][$this->database_drivers['driver']]['commit']();
        return ($this);
    }

    public function rollback() {
        $this->database_drivers['drivers'][$this->database_drivers['driver']]['rollback']();
        return ($this);
    }

    public function q(callable $queryBuilder) {
        $this->database_drivers['drivers'][$this->database_drivers['driver']]['q']($queryBuilder);
        return ($this);
    }

    // Alias na Q kedze predtym to mala byt skratka od Q - query, ale uz pouzivame querybuilder preto QB - takto su zachovane oba.
    public function qb(callable $queryBuilder) {
        return $this->q;
    }

    public function schema(callable $callback, $success = null, $error = null) {
        $this->database_drivers['drivers'][$this->database_drivers['driver']]['schema']($callback, $success, $error);
        return $this;
    }
    
    public function migrate($direction = 'up', $success = null, $error = null) {
        $this->database_drivers['drivers'][$this->database_drivers['driver']]['migrate']($direction, $success, $error);
        return $this;
    }

    /*
        BACKWARD COMPATIBILITY WITH MY OLD APPLICATIONS SO I CAN SAFELY UPDATE CORE
    */

    // Zaciatok funkcii spatnej kompatibility

    public function query($query, $ignoreerrors = 0) {
        $this->statement['execution_data'] = array();
        mysqli_report(MYSQLI_REPORT_OFF);
        if ($ignoreerrors == 1) {
            return (mysqli_query($this->activeconnection['mysqli'], $query));
        } else {
            $result = mysqli_query($this->activeconnection['mysqli'], $query);
            if ($result) return ($result);
            throw new \Exception(mysqli_error($this->activeconnection['mysqli']));
        }
    }

    public function query_first($query) {
        $this->statement['execution_data'] = array();
        if ($dbreturn = $this->query($query)) {
            $data = mysqli_fetch_array($dbreturn);
            return ($data);
        } else {
            return (array());
        }
    }

    public function insert($table, $data) {
        $this->statement['execution_data'] = array();
        $pdata = $this->prepare_query_data($data);
        $query = "INSERT INTO `" . $table . "` (" . implode(",", $pdata['rows']) . ") VALUES (" . implode(",", $pdata['values2']) . ");";
        return (mysqli_query($this->activeconnection['mysqli'], $query));
    }

    public function updateManual($table, $data, $where) {
        $this->statement['execution_data'] = array();
        $pdata = $this->prepare_query_data($data);
        $set = array();
        foreach ($pdata['rows'] as $key => $row) {
            $set[] = " " . $row . " = " . $pdata['values2'][$key] . " ";
        }
        $query = "UPDATE `" . $table . "` SET " . implode(",", $set) . " " . $where . ";";
        return (mysqli_query($this->activeconnection['mysqli'], $query));
    }

    public function getReturnType() {
        return $this->returnType;
    }

    public function prepare_query_data($data) {
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

    public function autobind_params($stmt, $placeholder, $values) {
        $bindarray = array_unshift($placeholder, $values);
        $reflection = new ReflectionClass('mysqli_stmt');
        $method = $reflection->getMethod('bind_param');
        $method->invokeArgs($stmt, $bindarray);
    }

    public function insert_multi($table, $data) {
        $this->statement['execution_data'] = array();
        $rows = array_keys($data);
        $values = array_map(function ($value) {
            return is_string($value) ? "'$value'" : $value;
        }, array_values($data));
        $query = "INSERT INTO `" . $table . "` (" . implode(',', $rows) . ") VALUES (" . implode(',', $values) . ");";
        return mysqli_query($this->activeconnection['mysqli'], $query);
    }

    public function inserted_id() {
        if (isset($this->database_drivers['drivers'][$this->database_drivers['driver']]['inserted_id'])) {
            return $this->database_drivers['drivers'][$this->database_drivers['driver']]['inserted_id']();
        }
        return null;
    }

    public function affected_rows() {
        if (isset($this->database_drivers['drivers'][$this->database_drivers['driver']]['affected_rows'])) {
            return $this->database_drivers['drivers'][$this->database_drivers['driver']]['affected_rows']();
        }
        return null;
    }

    // Koniec spatnej kompatibility
}

class QueryBuilder {
    private $queryParts;
    private $bindings;
    private $types;
    private $databaser;
    private $dbType;

    public function __construct($databaser = null) {
        $this->databaser = $databaser;
        $this->queryParts = [];
        $this->bindings = [];
        $this->types = '';
        if ($databaser && isset($databaser->database_drivers['driver'])) {
            $driver = $databaser->database_drivers['driver'];
            $name = key($databaser->databases[$driver] ?? []);
            $this->dbType = strtolower($databaser->databases[$driver][$name]['type'] ?? 'mysql');
        } else {
            $this->dbType = 'mysql';
        }
    }

    // SELECT s DISTINCT
    public function select($columns = '*', $table = null, $distinct = false) {
        $this->queryParts['type'] = 'SELECT';
        $distinctClause = $distinct ? "DISTINCT " : "";
        $this->queryParts['select'] = "SELECT $distinctClause" . $this->sanitizeColumns($columns);
        if ($table) {
            $this->from($table);
        }
        return $this;
    }

    public function distinct() {
        if (isset($this->queryParts['select'])) {
            $this->queryParts['select'] = preg_replace('/^SELECT/', 'SELECT DISTINCT', $this->queryParts['select'], 1);
        } else {
            $this->queryParts['select'] = "SELECT DISTINCT *";
        }
        return $this;
    }

    public function from($table) {
        $this->queryParts['from'] = "FROM " . $this->sanitizeTable($table);
        return $this;
    }

    // TRUNCATE
    public function truncate($table) {
        $this->queryParts['type'] = 'TRUNCATE';
        if ($this->dbType === 'sqlite') {
            $this->queryParts['truncate'] = "DELETE FROM " . $this->sanitizeTable($table);
        } else {
            $this->queryParts['truncate'] = "TRUNCATE TABLE " . $this->sanitizeTable($table);
        }
        return $this;
    }

    // WITH (CTE)
    public function with($name, callable $queryCallback) {
        if ($this->dbType === 'sqlite') {
            throw new \Exception("WITH (CTE) nie je podporované v SQLite.");
        }
        $subQueryBuilder = new QueryBuilder($this->databaser);
        $queryCallback($subQueryBuilder);
        $subQuery = $subQueryBuilder->getQuery();
        $this->queryParts['with'][] = $this->sanitizeTable($name) . " AS (" . $subQuery['query'] . ")";
        $this->bindings = array_merge($this->bindings, $subQuery['bindings']);
        $this->types .= $subQuery['types'];
        return $this;
    }

    // UNION, INTERSECT, EXCEPT
    public function union(QueryBuilder $query, $all = false) {
        $subQuery = $query->getQuery();
        $unionType = $all ? "UNION ALL" : "UNION";
        $this->queryParts['union'][] = "$unionType (" . $subQuery['query'] . ")";
        $this->bindings = array_merge($this->bindings, $subQuery['bindings']);
        $this->types .= $subQuery['types'];
        return $this;
    }

    public function intersect(QueryBuilder $query) {
        if ($this->dbType === 'mysql' || $this->dbType === 'sqlite') {
            throw new \Exception("INTERSECT nie je podporované v $this->dbType.");
        }
        $subQuery = $query->getQuery();
        $this->queryParts['union'][] = "INTERSECT (" . $subQuery['query'] . ")";
        $this->bindings = array_merge($this->bindings, $subQuery['bindings']);
        $this->types .= $subQuery['types'];
        return $this;
    }

    public function except(QueryBuilder $query) {
        if ($this->dbType === 'mysql' || $this->dbType === 'sqlite') {
            throw new \Exception("EXCEPT nie je podporované v $this->dbType.");
        }
        $subQuery = $query->getQuery();
        $this->queryParts['union'][] = "EXCEPT (" . $subQuery['query'] . ")";
        $this->bindings = array_merge($this->bindings, $subQuery['bindings']);
        $this->types .= $subQuery['types'];
        return $this;
    }

    // INSERT
    public function insert($table, array $data) {
        $this->queryParts['type'] = 'INSERT';
        $columns = implode(', ', array_map([$this, 'sanitizeColumn'], array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $this->queryParts['insert'] = "INSERT INTO " . $this->sanitizeTable($table) . " ($columns) VALUES ($placeholders)";
        $this->addBindings(array_values($data));
        return $this;
    }

    public function insertGetId($table, array $data, $idColumn = 'id') {
        $this->insert($table, $data);
        if ($this->dbType === 'pgsql') {
            $this->queryParts['insert'] .= " RETURNING " . $this->sanitizeColumn($idColumn);
        } elseif ($this->dbType === 'sqlsrv') {
            $this->queryParts['insert'] .= "; SELECT SCOPE_IDENTITY() AS " . $this->sanitizeColumn($idColumn);
        }
        return $this;
    }

    // ON DUPLICATE KEY UPDATE / ON CONFLICT
    public function onDuplicateKeyUpdate(array $data, $conflictTarget = null) {
        if ($this->dbType === 'mysql') {
            $updates = [];
            foreach ($data as $column => $value) {
                $updates[] = $this->sanitizeColumn($column) . " = ?";
                $this->addBindings([$value]);
            }
            $this->queryParts['insert'] .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
        } elseif ($this->dbType === 'pgsql') {
            $target = $conflictTarget ? " (" . $this->sanitizeColumn($conflictTarget) . ")" : "";
            $updates = [];
            foreach ($data as $column => $value) {
                $updates[] = $this->sanitizeColumn($column) . " = EXCLUDED." . $this->sanitizeColumn($column);
            }
            $this->queryParts['insert'] .= " ON CONFLICT$target DO UPDATE SET " . implode(', ', $updates);
        } elseif ($this->dbType === 'sqlite') {
            $updates = [];
            foreach ($data as $column => $value) {
                $updates[] = $this->sanitizeColumn($column) . " = ?";
                $this->addBindings([$value]);
            }
            $this->queryParts['insert'] .= " ON CONFLICT DO UPDATE SET " . implode(', ', $updates);
        }
        return $this;
    }

    // UPDATE
    public function update($table) {
        $this->queryParts['type'] = 'UPDATE';
        $this->queryParts['update'] = "UPDATE " . $this->sanitizeTable($table);
        return $this;
    }

    public function set(array $data) {
        $setParts = [];
        foreach ($data as $column => $value) {
            $setParts[] = $this->sanitizeColumn($column) . " = ?";
            $this->addBindings([$value]);
        }
        $this->queryParts['set'] = "SET " . implode(', ', $setParts);
        return $this;
    }

    // DELETE
    public function delete($table = null) {
        $this->queryParts['type'] = 'DELETE';
        if ($table) {
            $this->queryParts['delete'] = "DELETE FROM " . $this->sanitizeTable($table);
        }
        return $this;
    }

    // WHERE
    public function where($column, $operator = null, $value = null, $boolean = 'AND') {
        if ($column instanceof \Closure) {
            $groupBuilder = new QueryBuilder($this->databaser);
            $column($groupBuilder);
            $groupQuery = $groupBuilder->getQuery();
            $whereClause = implode(' ', $groupQuery['queryParts']['where']);
            if ($whereClause) {
                $whereClause = trim(preg_replace('/^(AND|OR)\s+/', '', $whereClause));
                $this->queryParts['where'][] = "$boolean ($whereClause)";
                $this->bindings = array_merge($this->bindings, $groupQuery['bindings']);
                $this->types .= $groupQuery['types'];
            }
        } elseif ($value instanceof \Closure) {
            $subQueryBuilder = new QueryBuilder($this->databaser);
            $value($subQueryBuilder);
            $subQuery = $subQueryBuilder->getQuery();
            $this->queryParts['where'][] = "$boolean " . $this->sanitizeColumn($column) . " $operator (" . $subQuery['query'] . ")";
            $this->bindings = array_merge($this->bindings, $subQuery['bindings']);
            $this->types .= $subQuery['types'];
        } elseif ($value === null) {
            $this->queryParts['where'][] = "$boolean $column";
        } else {
            $this->queryParts['where'][] = "$boolean " . $this->sanitizeColumn($column) . " $operator ?";
            $this->addBindings([$value]);
        }
        return $this;
    }

    public function andWhere($column, $operator = null, $value = null) {
        return $this->where($column, $operator, $value, 'AND');
    }

    public function orWhere($column, $operator = null, $value = null) {
        return $this->where($column, $operator, $value, 'OR');
    }

    public function whereIn($column, array $values, $boolean = 'AND') {
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->queryParts['where'][] = "$boolean " . $this->sanitizeColumn($column) . " IN ($placeholders)";
        $this->addBindings($values);
        return $this;
    }

    public function orWhereIn($column, array $values) {
        return $this->whereIn($column, $values, 'OR');
    }

    // JOIN
    public function join($table, $first, $operator = null, $second = null, $type = 'INNER') {
        if ($table instanceof QueryBuilder) {
            $subQuery = $table->getQuery();
            $this->queryParts['join'][] = "$type JOIN (" . $subQuery['query'] . ") AS subquery ON " . $this->sanitizeColumn($first) . " $operator " . $this->sanitizeColumn($second);
            $this->bindings = array_merge($this->bindings, $subQuery['bindings']);
            $this->types .= $subQuery['types'];
        } else {
            $this->queryParts['join'][] = "$type JOIN " . $this->sanitizeTable($table) . " ON " . $this->sanitizeColumn($first) . " $operator " . $this->sanitizeColumn($second);
        }
        return $this;
    }

    public function leftJoin($table, $first, $operator = null, $second = null) {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin($table, $first, $operator = null, $second = null) {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    public function fullJoin($table, $first, $operator = null, $second = null) {
        if ($this->dbType === 'mysql' || $this->dbType === 'sqlite') {
            throw new \Exception("FULL OUTER JOIN nie je podporovaný v $this->dbType.");
        }
        return $this->join($table, $first, $operator, $second, 'FULL OUTER');
    }

    // GROUP BY
    public function groupBy($columns) {
        $this->queryParts['groupBy'] = "GROUP BY " . $this->sanitizeColumns($columns);
        return $this;
    }

    // HAVING s agregátmi
    public function having($column, $operator = null, $value = null, $aggregate = null) {
        if ($aggregate) {
            $aggregate = strtoupper($aggregate);
            if (!in_array($aggregate, ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'])) {
                throw new \Exception("Nepodporovaná agregátna funkcia: $aggregate.");
            }
            $this->queryParts['having'] = "HAVING $aggregate(" . $this->sanitizeColumn($column) . ") $operator ?";
            $this->addBindings([$value]);
        } else {
            $this->queryParts['having'] = "HAVING " . $this->sanitizeColumn($column) . " $operator ?";
            $this->addBindings([$value]);
        }
        return $this;
    }

    // ORDER BY
    public function orderBy($column, $direction = 'ASC') {
        $this->queryParts['orderBy'][] = $this->sanitizeColumns($column) . " " . strtoupper($direction);
        return $this;
    }

    // LIMIT a OFFSET
    public function limit($limit) {
        if ($this->dbType === 'sqlsrv') {
            if (!isset($this->queryParts['select'])) {
                throw new \Exception("LIMIT (TOP) môže byť použité len s SELECT v SQL Server.");
            }
            $this->queryParts['select'] = preg_replace('/^SELECT/', "SELECT TOP ? ", $this->queryParts['select'], 1);
            $this->addBindings([(int) $limit]);
        } elseif ($this->dbType === 'oci') {
            $this->queryParts['limit'] = "FETCH FIRST ? ROWS ONLY";
            $this->addBindings([(int) $limit]);
        } else {
            $this->queryParts['limit'] = "LIMIT ?";
            $this->addBindings([(int) $limit]);
        }
        return $this;
    }

    public function offset($offset) {
        if ($this->dbType === 'sqlsrv') {
            $this->queryParts['offset'] = "OFFSET ? ROWS";
            $this->addBindings([(int) $offset]);
            if (!isset($this->queryParts['limit'])) {
                $this->queryParts['fetch'] = "FETCH NEXT 18446744073709551615 ROWS ONLY";
            } else {
                $this->queryParts['fetch'] = "FETCH NEXT ? ROWS ONLY";
            }
        } elseif ($this->dbType === 'oci') {
            $this->queryParts['offset'] = "OFFSET ? ROWS";
            $this->addBindings([(int) $offset]);
        } else {
            $this->queryParts['offset'] = "OFFSET ?";
            $this->addBindings([(int) $offset]);
        }
        return $this;
    }

    // Subquery
    public function subQuery() {
        return new self($this->databaser);
    }

    // Zostavenie dotazu
    public function getQuery() {
        $query = '';

        if (!empty($this->queryParts['with'])) {
            $query .= "WITH " . implode(', ', $this->queryParts['with']) . " ";
        }

        if ($this->queryParts['type'] === 'TRUNCATE') {
            $query .= $this->queryParts['truncate'];
        } elseif ($this->queryParts['type'] === 'CREATE_TABLE') {
            $query .= $this->queryParts['create'];
        } elseif ($this->queryParts['type'] === 'ALTER_TABLE') {
            $query .= $this->queryParts['alter'];
        } elseif ($this->queryParts['type'] === 'DROP_TABLE') {
            $query .= $this->queryParts['drop'];
        } elseif ($this->queryParts['type'] === 'SELECT') {
            $query .= $this->queryParts['select'] . " " . $this->queryParts['from'];
        } elseif ($this->queryParts['type'] === 'INSERT') {
            $query .= $this->queryParts['insert'];
        } elseif ($this->queryParts['type'] === 'UPDATE') {
            $query .= $this->queryParts['update'] . " " . $this->queryParts['set'];
        } elseif ($this->queryParts['type'] === 'DELETE') {
            $query .= $this->queryParts['delete'];
        } elseif ($this->queryParts['type'] === 'RAW') {
            $query .= $this->queryParts['raw'];
        }

        if (!empty($this->queryParts['join'])) {
            $query .= " " . implode(" ", $this->queryParts['join']);
        }

        if (!empty($this->queryParts['where'])) {
            $whereParts = $this->queryParts['where'];
            $firstWhere = array_shift($whereParts);
            $query .= " WHERE " . ltrim($firstWhere, "ANDOR ");
            $query .= " " . implode(" ", $whereParts);
        }

        if (!empty($this->queryParts['groupBy'])) {
            $query .= " " . $this->queryParts['groupBy'];
        }

        if (!empty($this->queryParts['having'])) {
            $query .= " " . $this->queryParts['having'];
        }

        if (!empty($this->queryParts['orderBy'])) {
            $query .= " ORDER BY " . implode(", ", $this->queryParts['orderBy']);
        }

        if ($this->dbType === 'sqlsrv') {
            if (!empty($this->queryParts['offset'])) {
                $query .= " " . $this->queryParts['offset'];
                if (!empty($this->queryParts['fetch'])) {
                    $query .= " " . $this->queryParts['fetch'];
                    if (isset($this->queryParts['limit'])) {
                        $limitIndex = array_search($this->queryParts['limit'], array_map('strval', $this->bindings));
                        $this->bindings[] = (int) $this->bindings[$limitIndex];
                    }
                }
            }
        } elseif ($this->dbType === 'oci') {
            if (!empty($this->queryParts['offset'])) {
                $query .= " " . $this->queryParts['offset'];
            }
            if (!empty($this->queryParts['limit'])) {
                $query .= " " . $this->queryParts['limit'];
            }
        } else {
            if (!empty($this->queryParts['limit'])) {
                $query .= " " . $this->queryParts['limit'];
            }
            if (!empty($this->queryParts['offset'])) {
                $query .= " " . $this->queryParts['offset'];
            }
        }

        if (!empty($this->queryParts['union'])) {
            $query .= " " . implode(" ", $this->queryParts['union']);
        }

        return [
            'query' => $query,
            'bindings' => $this->bindings,
            'types' => $this->types,
            'queryParts' => $this->queryParts
        ];
    }

    // Pomocné metódy
    private function addBindings(array $values) {
        foreach ($values as $value) {
            if ($value instanceof \Closure) {
                $subQueryBuilder = new QueryBuilder($this->databaser);
                $value($subQueryBuilder);
                $subQuery = $subQueryBuilder->getQuery();
                $this->bindings = array_merge($this->bindings, $subQuery['bindings']);
                $this->types .= $subQuery['types'];
            } else {
                $this->bindings[] = $this->sanitizeValue($value);
                $this->types .= $this->getBindingType($value);
            }
        }
    }

    private function getBindingType($value) {
        if (is_int($value)) return 'i';
        if (is_float($value)) return 'd';
        if (is_string($value)) return 's';
        return 's'; // Default pre null alebo iné
    }

    private function sanitizeColumns($columns) {
        if (is_array($columns)) {
            return implode(', ', array_map([$this, 'sanitizeColumn'], $columns));
        }
        return $this->sanitizeColumn($columns);
    }

    private function sanitizeColumn($column) {
        // Ak je vstup len '*', vráť ho bez úprav
        if ($column === '*') {
            return '*';
        }
        
        // Povolené znaky: a-z, A-Z, 0-9 a podčiarkovník (_), ale nie hviezdička (*)
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        
        if ($this->dbType === 'sqlsrv') {
            return "[$column]";
        } elseif ($this->dbType === 'pgsql' || $this->dbType === 'oci') {
            return "\"$column\"";
        }
        return "`$column`";
    }
    

    private function sanitizeTable($table) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($this->dbType === 'sqlsrv') {
            return "[$table]";
        } elseif ($this->dbType === 'pgsql' || $this->dbType === 'oci') {
            return "\"$table\"";
        }
        return "`$table`";
    }

    private function sanitizeValue($value) {
        if ($value instanceof \Closure) {
            $subQueryBuilder = new QueryBuilder($this->databaser);
            $value($subQueryBuilder);
            $subQuery = $subQueryBuilder->getQuery();
            return '(' . $subQuery['query'] . ')';
        }
        if (is_int($value)) return (int) $value;
        if (is_float($value)) return (float) $value;
        if (is_bool($value)) return (int) $value;
        if (is_null($value)) return null;
        return (string) $value;
    }

    public function raw($sql, array $bindings = []) {
        $this->queryParts['type'] = 'RAW';
        $hasNamedParams = preg_match('/:([a-zA-Z0-9_]+)/', $sql);
        $hasQuestionMarks = strpos($sql, '?') !== false;

        if ($hasNamedParams && $hasQuestionMarks) {
            throw new \Exception("RAW SQL nemôže kombinovať pomenované premenné (:name) a otázniky (?) naraz.");
        }

        if ($hasNamedParams) {
            if (preg_match_all('/:([a-zA-Z0-9_]+)/', $sql, $matches)) {
                $placeholders = $matches[0];
                $paramNames = $matches[1];
                $orderedBindings = [];
                $types = '';
                foreach ($paramNames as $name) {
                    if (array_key_exists($name, $bindings)) {
                        $orderedBindings[] = $bindings[$name];
                        $types .= $this->getBindingType($bindings[$name]);
                    } else {
                        throw new \Exception("Chýba hodnota pre parameter :$name v bindings.");
                    }
                }
                $sql = preg_replace('/:([a-zA-Z0-9_]+)/', '?', $sql);
                $this->queryParts['raw'] = $sql;
                $this->bindings = $orderedBindings;
                $this->types = $types;
            }
        } else {
            $questionMarkCount = substr_count($sql, '?');
            if ($questionMarkCount > 0 && count($bindings) !== $questionMarkCount) {
                throw new \Exception("Počet otáznikov (?) v SQL ($questionMarkCount) nesúhlasí s počtom hodnôt v bindings (" . count($bindings) . ").");
            }
            $this->queryParts['raw'] = $sql;
            $this->bindings = $bindings;
            $this->types = $this->generateBindingTypes($bindings);
        }
        return $this;
    }

    private function generateBindingTypes(array $bindings) {
        $types = '';
        foreach ($bindings as $value) {
            $types .= $this->getBindingType($value);
        }
        return $types;
    }

    public function createTable($table, callable $callback) {
        $this->queryParts['type'] = 'CREATE_TABLE';
        $this->queryParts['table'] = $this->sanitizeTable($table);
        $schema = new SchemaBuilder($this->databaser);
        $callback($schema);
        $this->queryParts['create'] = "CREATE TABLE {$this->queryParts['table']} (" . $schema->getDefinition() . ")";
        return $this;
    }

    public function alterTable($table, callable $callback) {
        $this->queryParts['type'] = 'ALTER_TABLE';
        $this->queryParts['table'] = $this->sanitizeTable($table);
        $schema = new SchemaBuilder($this->databaser);
        $callback($schema);
        $this->queryParts['alter'] = "ALTER TABLE {$this->queryParts['table']} " . $schema->getAlterDefinition();
        return $this;
    }

    public function dropTable($table) {
        $this->queryParts['type'] = 'DROP_TABLE';
        $this->queryParts['drop'] = "DROP TABLE " . $this->sanitizeTable($table);
        return $this;
    }
}

class SchemaBuilder {
    private $columns = [];
    private $indexes = [];
    private $foreignKeys = [];
    private $alterActions = [];
    private $dbType; // Typ databázy (mysql, pgsql, sqlite, oci, sqlsrv atď.)

    public function __construct($databaser = null) {
        if ($databaser && isset($databaser->database_drivers['driver'])) {
            $driver = $databaser->database_drivers['driver'];
            $name = key($databaser->databases[$driver] ?? []);
            $this->dbType = strtolower($databaser->databases[$driver][$name]['type'] ?? 'mysql');
        } else {
            $this->dbType = 'mysql'; // Default na MySQL
        }
    }

    // Primárny kľúč s automatickým inkrementom
    public function id($name = 'id') {
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" SERIAL PRIMARY KEY";
                break;
            case 'sqlite':
                $this->columns[] = "`$name` INTEGER PRIMARY KEY AUTOINCREMENT";
                break;
            case 'oci': // Oracle
                $this->columns[] = "\"$name\" NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY";
                break;
            case 'sqlsrv': // MS SQL Server
                $this->columns[] = "[$name] BIGINT IDENTITY(1,1) PRIMARY KEY";
                break;
        }
        return $this;
    }

    // Reťazec (VARCHAR/TEXT)
    public function string($name, $length = 255, $nullable = false, $default = null) {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT '$default'" : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` VARCHAR($length) $null$defaultStr";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" VARCHAR($length) $null$defaultStr";
                break;
            case 'sqlite':
                $this->columns[] = "`$name` TEXT $null$defaultStr"; // SQLite nemá dĺžku pre VARCHAR
                break;
            case 'oci':
                $this->columns[] = "\"$name\" VARCHAR2($length) $null$defaultStr";
                break;
            case 'sqlsrv':
                $this->columns[] = "[$name] NVARCHAR($length) $null$defaultStr";
                break;
        }
        return $this;
    }

    // Celé číslo (INTEGER)
    public function integer($name, $nullable = false, $default = null) {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT $default" : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` INT $null$defaultStr";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" INTEGER $null$defaultStr";
                break;
            case 'sqlite':
                $this->columns[] = "`$name` INTEGER $null$defaultStr";
                break;
            case 'oci':
                $this->columns[] = "\"$name\" NUMBER(10) $null$defaultStr";
                break;
            case 'sqlsrv':
                $this->columns[] = "[$name] INT $null$defaultStr";
                break;
        }
        return $this;
    }

    // Veľké celé číslo (BIGINT)
    public function bigInteger($name, $nullable = false, $default = null) {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT $default" : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` BIGINT $null$defaultStr";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" BIGINT $null$defaultStr";
                break;
            case 'sqlite':
                $this->columns[] = "`$name` INTEGER $null$defaultStr"; // SQLite nemá BIGINT
                break;
            case 'oci':
                $this->columns[] = "\"$name\" NUMBER(19) $null$defaultStr";
                break;
            case 'sqlsrv':
                $this->columns[] = "[$name] BIGINT $null$defaultStr";
                break;
        }
        return $this;
    }

    // Boolean
    public function boolean($name, $nullable = false, $default = null) {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT " . ($default ? '1' : '0') : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` TINYINT(1) $null$defaultStr";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" BOOLEAN $null" . ($default !== null ? " DEFAULT " . ($default ? 'TRUE' : 'FALSE') : '');
                break;
            case 'sqlite':
                $this->columns[] = "`$name` INTEGER $null$defaultStr"; // SQLite používa 0/1
                break;
            case 'oci':
                $this->columns[] = "\"$name\" NUMBER(1) $null$defaultStr";
                break;
            case 'sqlsrv':
                $this->columns[] = "[$name] BIT $null" . ($default !== null ? " DEFAULT " . ($default ? '1' : '0') : '');
                break;
        }
        return $this;
    }

    // Desatinné číslo (DECIMAL)
    public function decimal($name, $precision = 10, $scale = 2, $nullable = false, $default = null) {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT $default" : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` DECIMAL($precision,$scale) $null$defaultStr";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" NUMERIC($precision,$scale) $null$defaultStr";
                break;
            case 'sqlite':
                $this->columns[] = "`$name` REAL $null$defaultStr"; // SQLite nemá presné DECIMAL
                break;
            case 'oci':
                $this->columns[] = "\"$name\" NUMBER($precision,$scale) $null$defaultStr";
                break;
            case 'sqlsrv':
                $this->columns[] = "[$name] DECIMAL($precision,$scale) $null$defaultStr";
                break;
        }
        return $this;
    }

    // Časové značky (timestamps)
    public function timestamps() {
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                $this->columns[] = "`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
                break;
            case 'pgsql':
                $this->columns[] = "\"created_at\" TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                $this->columns[] = "\"updated_at\" TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                // Pre ON UPDATE by bolo potrebné trigger
                break;
            case 'sqlite':
                $this->columns[] = "`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP";
                $this->columns[] = "`updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP";
                // Pre ON UPDATE by bolo potrebné trigger
                break;
            case 'oci':
                $this->columns[] = "\"CREATED_AT\" TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                $this->columns[] = "\"UPDATED_AT\" TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                break;
            case 'sqlsrv':
                $this->columns[] = "[created_at] DATETIME2 DEFAULT CURRENT_TIMESTAMP";
                $this->columns[] = "[updated_at] DATETIME2 DEFAULT CURRENT_TIMESTAMP";
                // Pre ON UPDATE by bolo potrebné trigger
                break;
        }
        return $this;
    }

    // Dátum
    public function date($name, $nullable = false, $default = null) {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT '$default'" : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` DATE $null$defaultStr";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" DATE $null$defaultStr";
                break;
            case 'sqlite':
                $this->columns[] = "`$name` TEXT $null$defaultStr"; // SQLite ukladá dátum ako text
                break;
            case 'oci':
                $this->columns[] = "\"$name\" DATE $null$defaultStr";
                break;
            case 'sqlsrv':
                $this->columns[] = "[$name] DATE $null$defaultStr";
                break;
        }
        return $this;
    }

    // Cudzí kľúč
    public function foreign($column, $references = 'id', $on = null, $onDelete = 'CASCADE', $onUpdate = 'NO ACTION') {
        $on = $on ?? $this->guessTableName($column);
        switch ($this->dbType) {
            case 'mysql':
                $this->foreignKeys[] = "FOREIGN KEY (`$column`) REFERENCES `$on` (`$references`) ON DELETE $onDelete ON UPDATE $onUpdate";
                break;
            case 'pgsql':
                $this->foreignKeys[] = "FOREIGN KEY (\"$column\") REFERENCES \"$on\" (\"$references\") ON DELETE $onDelete ON UPDATE $onUpdate";
                break;
            case 'sqlite':
                $this->foreignKeys[] = "FOREIGN KEY (`$column`) REFERENCES `$on` (`$references`) ON DELETE $onDelete ON UPDATE $onUpdate";
                break;
            case 'oci':
                $this->foreignKeys[] = "FOREIGN KEY (\"$column\") REFERENCES \"$on\" (\"$references\") ON DELETE $onDelete";
                // Oracle nepodporuje ON UPDATE priamo v definícii
                break;
            case 'sqlsrv':
                $this->foreignKeys[] = "FOREIGN KEY ([$column]) REFERENCES [$on] ([$references]) ON DELETE $onDelete ON UPDATE $onUpdate";
                break;
        }
        return $this;
    }

    // Index
    public function index($column, $unique = false) {
        $indexType = $unique ? 'UNIQUE INDEX' : 'INDEX';
        switch ($this->dbType) {
            case 'mysql':
                $this->indexes[] = "$indexType (`$column`)";
                break;
            case 'pgsql':
                $this->indexes[] = "$indexType (\"$column\")";
                break;
            case 'sqlite':
                $this->indexes[] = "$indexType (`$column`)";
                break;
            case 'oci':
                $this->indexes[] = "$indexType (\"$column\")";
                break;
            case 'sqlsrv':
                $this->indexes[] = "$indexType ([$column])";
                break;
        }
        return $this;
    }

    public function unique($column) {
        return $this->index($column, true);
    }

    // Pre ALTER TABLE
    public function addColumn($name, $type, $length = null, $nullable = false, $default = null) {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $lengthStr = $length ? "($length)" : '';
        $defaultStr = $default !== null ? " DEFAULT " . (is_string($default) ? "'$default'" : $default) : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->alterActions[] = "ADD `$name` $type$lengthStr $null$defaultStr";
                break;
            case 'pgsql':
                $type = $this->convertTypeForPg($type);
                $this->alterActions[] = "ADD \"$name\" $type$lengthStr $null$defaultStr";
                break;
            case 'sqlite':
                $type = $this->convertTypeForSQLite($type);
                $this->alterActions[] = "ADD `$name` $type $null$defaultStr"; // SQLite ignoruje dĺžku
                break;
            case 'oci':
                $type = $this->convertTypeForOracle($type);
                $this->alterActions[] = "ADD \"$name\" $type$lengthStr $null$defaultStr";
                break;
            case 'sqlsrv':
                $type = $this->convertTypeForSqlSrv($type);
                $this->alterActions[] = "ADD [$name] $type$lengthStr $null$defaultStr";
                break;
        }
        return $this;
    }

    public function dropColumn($name) {
        switch ($this->dbType) {
            case 'mysql':
                $this->alterActions[] = "DROP COLUMN `$name`";
                break;
            case 'pgsql':
                $this->alterActions[] = "DROP COLUMN \"$name\"";
                break;
            case 'sqlite':
                // SQLite nepodporuje DROP COLUMN priamo, placeholder
                $this->alterActions[] = "DROP COLUMN `$name`";
                break;
            case 'oci':
                $this->alterActions[] = "DROP COLUMN \"$name\"";
                break;
            case 'sqlsrv':
                $this->alterActions[] = "DROP COLUMN [$name]";
                break;
        }
        return $this;
    }

    public function getDefinition() {
        return implode(', ', array_merge($this->columns, $this->indexes, $this->foreignKeys));
    }

    public function getAlterDefinition() {
        return implode(', ', $this->alterActions);
    }

    private function guessTableName($column) {
        return rtrim($column, '_id');
    }

    // Konverzia typov pre PostgreSQL
    private function convertTypeForPg($type) {
        $type = strtoupper($type);
        switch ($type) {
            case 'VARCHAR':
                return 'VARCHAR';
            case 'INT':
                return 'INTEGER';
            case 'BIGINT':
                return 'BIGINT';
            case 'TINYINT':
                return 'SMALLINT';
            case 'DECIMAL':
                return 'NUMERIC';
            case 'TIMESTAMP':
                return 'TIMESTAMP';
            default:
                return $type;
        }
    }

    // Konverzia typov pre SQLite
    private function convertTypeForSQLite($type) {
        $type = strtoupper($type);
        switch ($type) {
            case 'VARCHAR':
                return 'TEXT';
            case 'INT':
                return 'INTEGER';
            case 'BIGINT':
                return 'INTEGER';
            case 'TINYINT':
                return 'INTEGER';
            case 'DECIMAL':
                return 'REAL';
            case 'TIMESTAMP':
                return 'DATETIME';
            default:
                return $type;
        }
    }

    // Konverzia typov pre Oracle
    private function convertTypeForOracle($type) {
        $type = strtoupper($type);
        switch ($type) {
            case 'VARCHAR':
                return 'VARCHAR2';
            case 'INT':
                return 'NUMBER(10)';
            case 'BIGINT':
                return 'NUMBER(19)';
            case 'TINYINT':
                return 'NUMBER(3)';
            case 'DECIMAL':
                return 'NUMBER';
            case 'TIMESTAMP':
                return 'TIMESTAMP';
            default:
                return $type;
        }
    }

    // Konverzia typov pre SQL Server
    private function convertTypeForSqlSrv($type) {
        $type = strtoupper($type);
        switch ($type) {
            case 'VARCHAR':
                return 'NVARCHAR';
            case 'INT':
                return 'INT';
            case 'BIGINT':
                return 'BIGINT';
            case 'TINYINT':
                return 'TINYINT';
            case 'DECIMAL':
                return 'DECIMAL';
            case 'TIMESTAMP':
                return 'DATETIME2';
            default:
                return $type;
        }
    }
}

// Ak chceme cachedriver pouzivat, musime implementovat tieto funkcie.
// Logiku si musi uzivatl poriesit sam.
interface CacheDriverInterface {
    public function get($key);
    public function set($key, $value, $ttl = null);
    public function delete($key);
    public function deleteKeys($pattern);
}



?>