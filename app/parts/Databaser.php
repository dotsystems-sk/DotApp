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
use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\Config;
use \Dotsystems\App\Parts\DI;
use \Dotsystems\App\Parts\QueryBuilder;
use \Dotsystems\App\Parts\SchemaBuilder;
use \Dotsystems\App\Parts\CacheDriverInterface;

class Databaser {
    public $cacheDriver = null;
    private $useCache=false;
    private $databases;
    private $connections;
    private $activeconnection;
    private $allDB=[];
    public $statement;
    public $database_drivers; // Type of database we use...
    public $cloned;
    public $driver_helper;
    public $returnType = 'RAW'; // Predvolené správanie pre typ návratovej hodnoty
    private $qb;
    public $di;

    public function diSet() {
        $this->di = DotApp::dotApp()->db;
    }

    function __construct($dotapp=null) {
        $this->databases = array();
        $this->database_drivers = array();
        $this->database_drivers['drivers'] = array();
        $this->database_drivers['driver'] = array();
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

    private function loadDatabases() {
        if (empty($this->allDB)) return;
        foreach ($this->allDB as $name => $db) {
            $this->driver(strtolower($db["driver"]));
            $this->add($name, $db["host"], $db["username"], $db["password"], $db["database"], $db["charset"], $db["type"]);
        }
        // Loadneme defaultnu DB
        $this->driver(Config::db("driver"));
        $this->selectDb(Config::db("maindb"));
    }

    private function setCacheDriver() {
        // Kvoli spatnej kompatibilite pred refaktoringom sposobu akym fungovala cache urobime teraz anonymnu triedu
        // ktora zaruci spatnu kompatibilutu ale bude fungovat nad novymi drivermi
        $cacheObj = Cache::use("databaserCache");
        $setMethod = function($key,$value,$lifetime) use ($cacheObj) {
            $cacheObj->save($key,$value,$lifetime);
        };
        $getMethod = function($key) use ($cacheObj) {
            $cacheObj->load($key);
        };
        $this->cacheDriver = new class($setMethod,$getMethod) {
            private $methods = [];

            function __construct($setMethod,$getMethod) {
                $methods['set'] = $setMethod;
                $methods['get'] = $getMethod;
            }

            public function set($key,$value,$lifetime) {
                $this->methods['set']($key,$value,$lifetime);
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
            $newqb = new QueryBuilder($this);
            if (is_callable($querybuilder)) {
                $querybuilder($newqb);
            }
            return $newqb;
        });

        $this->addDriver("mysqli", "schema", function (callable $callback, $success=null, $error=null) use ($clear_statement) {
            $clear_statement();
            $this->qb = new QueryBuilder($this);
            if (is_callable($callback)) {
                $callback($this->qb);
            }
            $this->execute($success,$error); // Spustí query cez MySQLi
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
            if ( (strlen(trim($queryData["query"])) == 0) && isSet($queryData['queryParts']['ifNotExistUsed']) && $queryData['queryParts']['ifNotExistUsed'] == true) {
                return;
            }
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
            $execution_data = [
                'query' => $query,
                'bindings' => $values
            ];

            if ($this->cacheDriver && $cached = $this->cacheDriver->get($cacheKey)) {
                DotApp::dotApp()->trigger("dotapp.databaser.execute.success",$cached,$execution_data);
                if (is_callable($success_callback)) $success_callback($cached, $this, []);
                return $cached;
            }            
        
            $stmt = $this->activeconnection['mysqli']->prepare($query);
            if ($stmt === false) {
                $error = ['error' => $this->activeconnection['mysqli']->error, 'errno' => $this->activeconnection['mysqli']->errno];
                if (is_callable($error_callback)) {
                    DotApp::dotApp()->trigger("dotapp.databaser.execute.error",$error,$execution_data);
                    $error_callback($error, $this, $execution_data);
                } else {
                    throw new \Exception("Error: " . $error['error'] . " (errcode: " . $error['errno'] . ")");
                }
                return false;
            }
        
            if (!empty($values)) {
                $stmt->bind_param($types, ...$values);
            }
            
            DotApp::dotApp()->trigger("dotapp.databaser.execute",$execution_data);
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
                        DotApp::dotApp()->trigger("dotapp.databaser.execute.success",$returnValue,$execution_data);
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
                    DotApp::dotApp()->trigger("dotapp.databaser.execute.success",$rows,$execution_data);
                    if (is_callable($success_callback)) $success_callback($rows, $this, $execution_data);

                    $stmt->close();
                    $this->q(function ($qb) {});
                    return $result;
                }
            } else {
                $error = ['error' => $stmt->error, 'errno' => $stmt->errno];
                if (is_callable($error_callback)) {
                    DotApp::dotApp()->trigger("dotapp.databaser.execute.error",$error,$execution_data);
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
                'table' => 'unknown_table'
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
                    case 'pgsql':
                        $dsn = "pgsql:host={$server};dbname={$database}";
                        break;
                    case 'sqlite':
                        $dsn = "sqlite:{$database}";
                        break;
                    case 'sqlsrv':
                        if (!extension_loaded('pdo_sqlsrv')) {
                            throw new \Exception("PDO SQLSRV extension is not loaded. Please install the pdo_sqlsrv extension.");
                        }
                        $dsn = "sqlsrv:Server={$server};Database={$database}";
                        break;
                    case 'oci':
                        if (!extension_loaded('pdo_oci')) {
                            throw new \Exception("PDO OCI extension is not loaded. Please install the pdo_oci extension.");
                        }
                        // Predpokladáme, že $database obsahuje názov Oracle SID alebo Service Name
                        $dsn = "oci:dbname=//{$server}/{$database}";
                        if (!empty($collation)) {
                            $dsn .= ";charset={$collation}";
                        }
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
            $newqb = new QueryBuilder($this); // Vždy nový QueryBuilder
            if (is_callable($querybuilder)) {
                $querybuilder($newqb);
            }
            return $newqb;
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
            if (is_callable($callback)) {
                $callback($this->qb);
            }
            $this->execute($success,$error); // Spustí query cez PDO
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
            if ( (strlen(trim($queryData["query"])) == 0) && isSet($queryData['queryParts']['ifNotExistUsed']) && $queryData['queryParts']['ifNotExistUsed'] == true) {
                return;
            }
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
            $execution_data = [
                'query' => $query,
                'bindings' => $values
            ];

            if ($this->cacheDriver && $cached = $this->cacheDriver->get($cacheKey)) {
                DotApp::dotApp()->trigger("dotapp.databaser.execute.success",$cached,$execution_data);
                if (is_callable($success_callback)) $success_callback($cached, $this, []);
                return $cached;
            }
        
            try {
                $stmt = $this->activeconnection['pdo']->prepare($query);
                if ($stmt === false) {
                    $error = ['error' => $this->activeconnection['pdo']->errorInfo()[2] ?? 'Failed to prepare statement', 'errno' => $this->activeconnection['pdo']->errorCode()];
                    if (is_callable($error_callback)) {
                        DotApp::dotApp()->trigger("dotapp.databaser.execute.error",$error,$execution_data);
                        $error_callback($error, $this, $execution_data);
                    } else {
                        throw new \Exception("Error: " . $error['error'] . " (errcode: " . $error['errno'] . ")");
                    }
                    return false;
                }
        
                foreach ($values as $index => $value) {
                    $stmt->bindValue($index + 1, $value, $getBindingType($value));
                }
                
                DotApp::dotApp()->trigger("dotapp.databaser.execute",$execution_data);
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
                        DotApp::dotApp()->trigger("dotapp.databaser.execute.success",$returnValue,$execution_data);
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
                        DotApp::dotApp()->trigger("dotapp.databaser.execute.success",$rows,$execution_data);
                        if (is_callable($success_callback)) $success_callback($rows, $this, $execution_data);
                        $stmt->closeCursor();
                        $this->q(function ($qb) {});
                        return $rows;
                    }
                } else {
                    $error = ['error' => $stmt->errorInfo()[2] ?? 'Execution failed', 'errno' => $stmt->errorCode()];
                    if (is_callable($error_callback)) {
                        DotApp::dotApp()->trigger("dotapp.databaser.execute.error",$error,$execution_data);
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
                    DotApp::dotApp()->trigger("dotapp.databaser.execute.error",$error,$execution_data);
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
    public function driver($driver) {
        // Ak by niekto mal vlastny driver, ale nechce zaberat pamat kym nie je potrebny
        DotApp::DotApp()->trigger("dotapp.db.driver.set",$this,$driver);
        
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
        if (!isset($this->databases[$this->database_drivers['driver']][$name])) $this->databases[$this->database_drivers['driver']][$name] = [];
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

    public function getQuery() {
        return $this->qb->getQuery();
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
        $qbObject = $this->database_drivers['drivers'][$this->database_drivers['driver']]['q']($queryBuilder);
        return (new DI( new QueryObject($qbObject,$this)));
    }

    // Alias na Q kedze predtym to mala byt skratka od Q - query, ale uz pouzivame querybuilder preto QB - takto su zachovane oba.
    public function qb(callable $queryBuilder) {
        return $this->q($queryBuilder);
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
            return (mysqli_query($this->activeconnection[Config::db('driver')], $query));
        } else {
            $result = mysqli_query($this->activeconnection[Config::db('driver')], $query);
            if ($result) return ($result);
            throw new \Exception(mysqli_error($this->activeconnection[Config::db('driver')]));
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
        return (mysqli_query($this->activeconnection[Config::db('driver')], $query));
    }

    public function updateManual($table, $data, $where) {
        $this->statement['execution_data'] = array();
        $pdata = $this->prepare_query_data($data);
        $set = array();
        foreach ($pdata['rows'] as $key => $row) {
            $set[] = " " . $row . " = " . $pdata['values2'][$key] . " ";
        }
        $query = "UPDATE `" . $table . "` SET " . implode(",", $set) . " " . $where . ";";
        return (mysqli_query($this->activeconnection[Config::db('driver')], $query));
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
        $reflection = new \ReflectionClass('mysqli_stmt');
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

    // Oprava bug pri vnorenych queries sa prepisovala instancia querybuildera.
    public function setQB(QueryBuilder $queryBuilder) {
        $this->qb = $queryBuilder;
        return $this;
    }
}

class QueryObject {
    private $querybuilder;
    private $databaser;

    function __construct(QueryBuilder $qb, Databaser $databaser) {
        $this->querybuilder = $qb;
        $this->databaser = $databaser;
    }

    public function execute($success = null, $onError = null) {
        $this->databaser->setQB($this->querybuilder);
        return $this->databaser->execute($success, $onError);
    }

    public function getQuery() {
        $this->databaser->setQB($this->querybuilder);
        return $this->databaser->getQuery(); // Ak máš túto metódu v Databaser (z driverov), inak ju pridaj ako wrapper
    }

    public function first() {
        $this->databaser->setQB($this->querybuilder);
        return $this->databaser->first();
    }

    public function all() {
        $this->databaser->setQB($this->querybuilder);
        return $this->databaser->all();
    }

    public function raw() {
        $this->databaser->setQB($this->querybuilder);
        return $this->databaser->raw();
    }

}

?>