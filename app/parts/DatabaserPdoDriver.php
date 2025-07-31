<?php
namespace Dotsystems\App\Parts;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Databaser;
use Dotsystems\App\Parts\QueryBuilder;
use Dotsystems\App\Parts\Entity;
use Dotsystems\App\Parts\Collection;

class DatabaserPdoDriver {
    public static function create() {
        $databases = null;
        $databaserSet = false;
        $databaserInstance = new \stdClass();

        $setDatabaser = function (Databaser $databaser) use (&$databases, &$databaserSet, &$databaserInstance) {
            if ($databaserSet === false) {
                $databaserInstance = $databaser;
                $databases = $databaser->getDatabases();
                $databases["pdo"] = [];
                $databaser->statement['execution_type'] = 0;
                $databaserSet = true;
            }
        };

        $getBindingType = function ($value) {
            if (is_int($value)) return \PDO::PARAM_INT;
            if (is_float($value)) return \PDO::PARAM_STR;
            if (is_string($value)) return \PDO::PARAM_STR;
            if (is_null($value)) return \PDO::PARAM_NULL;
            return \PDO::PARAM_LOB;
        };

        $clear_statement = function () use (&$databaserInstance) {
            $databaserInstance->statement = [
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

        Databaser::addDriver("pdo", "select_db", function (Databaser $databaser, $name) use ($setDatabaser) {
            $setDatabaser($databaser);
            $databases = $databaser->getDatabases();
            if (!isset(Databaser::getConnections()['pdo'][$name])) {
                if (!isset($databases['pdo'][$name])) {
                    throw new \Exception("Database configuration for '$name' not found.");
                }
                $type = strtolower($databases['pdo'][$name]['type']);
                $server = $databases['pdo'][$name]['server'];
                $database = $databases['pdo'][$name]['database'];
                $collation = $databases['pdo'][$name]['collation'] ?? 'utf8';

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
                        $dsn = "oci:dbname=//{$server}/{$database}";
                        if (!empty($collation)) {
                            $dsn .= ";charset={$collation}";
                        }
                        break;
                    default:
                        throw new \Exception("Unsupported database type: {$type}");
                }

                try {
                    Databaser::$connections['pdo'][$name] = new \PDO(
                        $dsn,
                        $databases['pdo'][$name]['username'] ?? '',
                        $databases['pdo'][$name]['password'] ?? '',
                        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
                    );
                    $databaser->activeconnection['pdo'] = Databaser::$connections['pdo'][$name];
                } catch (\PDOException $e) {
                    $databaser->activeconnection['pdo'] = null;
                    throw new \Exception("Failed to connect to database '$name': " . $e->getMessage());
                }
            } else {
                $databaser->activeconnection['pdo'] = Databaser::$connections['pdo'][$name];
            }
        });

        Databaser::addDriver("pdo", "q", function (Databaser $databaser, $querybuilder) use ($clear_statement, $setDatabaser) {
            $setDatabaser($databaser);
            $clear_statement();
            $newqb = new QueryBuilder($databaser);
            if (is_callable($querybuilder)) {
                $querybuilder($newqb);
            }
            return $newqb;
        });

        Databaser::addDriver("pdo", "inserted_id", function (Databaser $databaser) use ($setDatabaser) {
            $setDatabaser($databaser);
            if ($databaser->getActiveConnection()['pdo']) {
                return $databaser->getActiveConnection()['pdo']->lastInsertId();
            }
            return null;
        });

        Databaser::addDriver("pdo", "affected_rows", function (Databaser $databaser) use ($setDatabaser) {
            $setDatabaser($databaser);
            if ($databaser->getActiveConnection()['pdo'] && isset($databaser->getStatement()['execution_data']['result'])) {
                return $databaser->getStatement()['execution_data']['result']->rowCount();
            }
            return 0;
        });

        Databaser::addDriver("pdo", "schema", function (Databaser $databaser, callable $callback, $success = null, $error = null) use ($clear_statement, $setDatabaser) {
            $setDatabaser($databaser);
            $clear_statement();
            $databaser->setQB(new QueryBuilder($databaser));
            if (is_callable($callback)) {
                $callback($databaser->getQB());
            }
            $databaser->execute($success, $error);
        });

        Databaser::addDriver("pdo", "return", function (Databaser $databaser, $type) use ($setDatabaser) {
            $setDatabaser($databaser);
            $databaser->returnType = strtoupper($type);
        });

        Databaser::addDriver("pdo", "getQuery", function (Databaser $databaser) use ($setDatabaser) {
            $setDatabaser($databaser);
            $queryData = $databaser->getQB()->getQuery();
            return [
                'query' => $queryData['query'],
                'bindings' => $queryData['bindings']
            ];
        });

        Databaser::addDriver("pdo", "fetchArray", function (Databaser $databaser, &$stmt) use ($setDatabaser) {
            $setDatabaser($databaser);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: false;
        });

        Databaser::addDriver("pdo", "fetchFirst", function (Databaser $databaser, &$stmt) use ($setDatabaser) {
            $setDatabaser($databaser);
            return $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
        });

        Databaser::addDriver("pdo", "newEntity", function (Databaser $databaser, $row) use ($setDatabaser) {
            $setDatabaser($databaser);
            return new Entity($row, $databaser->di);
        });

        Databaser::addDriver("pdo", "newCollection", function (Databaser $databaser, $queryOrItems) use ($setDatabaser) {
            $setDatabaser($databaser);
            return new Collection($queryOrItems, $databaser->di);
        });

        Databaser::addDriver("pdo", "execute", function (Databaser $databaser, $success_callback = null, $error_callback = null) use ($getBindingType, $setDatabaser) {
            $setDatabaser($databaser);
            $pdo = $databaser->getActiveConnection()['pdo'] ?? null;
            if (!$pdo) {
                $error = ['error' => 'No active database connection. Use select_db() to establish a connection.', 'errno' => 'NO_CONNECTION'];
                if (is_callable($error_callback)) {
                    DotApp::dotApp()->trigger("dotapp.databaser.execute.error", $error, []);
                    $error_callback($error, $databaser, []);
                } else {
                    throw new \Exception($error['error']);
                }
                return false;
            }

            $queryData = $databaser->getQB()->getQuery();
            if (empty(trim($queryData["query"])) && isset($queryData['queryParts']['ifNotExistUsed']) && $queryData['queryParts']['ifNotExistUsed'] === true) {
                return null;
            }

            $query = $queryData['query'];
            $values = $queryData['bindings'] ?? [];
            if (empty(trim($query))) {
                $error = ['error' => 'Empty query provided.', 'errno' => 'EMPTY_QUERY'];
                if (is_callable($error_callback)) {
                    DotApp::dotApp()->trigger("dotapp.databaser.execute.error", $error, []);
                    $error_callback($error, $databaser, []);
                } else {
                    throw new \Exception($error['error']);
                }
                return false;
            }

            $table = $databaser->getStatement()['table'] ?? 'unknown_table';
            if (isset($queryData['queryParts']['table'])) {
                $table = $queryData['queryParts']['table'];
            } elseif (isset($queryData['queryParts']['from'])) {
                $table = trim(str_replace('FROM', '', $queryData['queryParts']['from']));
            } elseif (isset($queryData['queryParts']['update'])) {
                $table = trim(str_replace('UPDATE', '', $queryData['queryParts']['update']));
            } elseif (isset($queryData['queryParts']['insert'])) {
                $table = trim(preg_replace('/INSERT INTO (\w+).*/', '$1', $queryData['queryParts']['insert']));
            }
            $databaser->statement['table'] = $table;

            $cacheKey = "{$table}:{$databaser->returnType}:" . md5($query . serialize($values));
            $execution_data = [
                'query' => $query,
                'bindings' => $values
            ];

            if ($databaser->cacheDriver && $cached = $databaser->cacheDriver->get($cacheKey)) {
                DotApp::dotApp()->trigger("dotapp.databaser.execute.success", $cached, $execution_data);
                if (is_callable($success_callback)) {
                    $success_callback($cached, $databaser, $execution_data);
                }
                return $cached;
            }

            try {
                $stmt = $pdo->prepare($query);
                if ($stmt === false) {
                    $error = ['error' => $pdo->errorInfo()[2] ?? 'Failed to prepare statement', 'errno' => $pdo->errorCode()];
                    DotApp::dotApp()->trigger("dotapp.databaser.execute.error", $error, $execution_data);
                    if (is_callable($error_callback)) {
                        $error_callback($error, $databaser, $execution_data);
                    } else {
                        throw new \Exception("Query preparation failed: {$error['error']} (errcode: {$error['errno']})");
                    }
                    return false;
                }

                foreach ($values as $index => $value) {
                    $stmt->bindValue($index + 1, $value, $getBindingType($value));
                }

                DotApp::dotApp()->trigger("dotapp.databaser.execute", $execution_data);
                if (!$stmt->execute()) {
                    $error = ['error' => $stmt->errorInfo()[2] ?? 'Execution failed', 'errno' => $stmt->errorCode()];
                    DotApp::dotApp()->trigger("dotapp.databaser.execute.error", $error, $execution_data);
                    if (is_callable($error_callback)) {
                        $error_callback($error, $databaser, $execution_data);
                    } else {
                        throw new \Exception("Query execution failed: {$error['error']} (errcode: {$error['errno']})");
                    }
                    $stmt->closeCursor();
                    return false;
                }

                $execution_data = [
                    'affected_rows' => $stmt->rowCount(),
                    'insert_id' => $pdo->lastInsertId(),
                    'num_rows' => $stmt->rowCount(),
                    'result' => $stmt,
                    'query' => $query,
                    'bindings' => $values
                ];
                $databaser->statement['execution_data'] = $execution_data;

                if ($databaser->returnType === "ORM") {
                    $rows = [];
                    if ($stmt->rowCount() > 0) {
                        while ($row = $databaser->fetchArray($stmt)) {
                            $entity = new Entity($row, $databaser->di);
                            $entity->loadRelations();
                            $rows[] = $entity;
                        }
                    }
                    $returnValue = $rows ? new Collection($rows, $databaser->di) : null;
                    if ($databaser->cacheDriver && $returnValue) {
                        $databaser->cacheDriver->set($cacheKey, $returnValue, 3600);
                    }
                    DotApp::dotApp()->trigger("dotapp.databaser.execute.success", $returnValue, $execution_data);
                    if (is_callable($success_callback)) {
                        $success_callback($returnValue, $databaser, $execution_data);
                    }
                    $stmt->closeCursor();
                    $databaser->q(function ($qb) {});
                    return $returnValue;
                }

                $rows = [];
                if ($stmt->rowCount() > 0) {
                    while ($row = $databaser->fetchArray($stmt)) {
                        $rows[] = $row;
                    }
                }
                if ($databaser->cacheDriver && $rows) {
                    $databaser->cacheDriver->set($cacheKey, $rows, 3600);
                }
                DotApp::dotApp()->trigger("dotapp.databaser.execute.success", $rows, $execution_data);
                if (is_callable($success_callback)) {
                    $success_callback($rows, $databaser, $execution_data);
                }
                $stmt->closeCursor();
                $databaser->q(function ($qb) {});
                return $rows;

            } catch (\PDOException $e) {
                $error = ['error' => $e->getMessage(), 'errno' => $e->getCode()];
                DotApp::dotApp()->trigger("dotapp.databaser.execute.error", $error, $execution_data);
                if (is_callable($error_callback)) {
                    $error_callback($error, $databaser, $execution_data);
                } else {
                    throw new \Exception("Query execution error: {$e->getMessage()} (errcode: {$e->getCode()})");
                }
                if (isset($stmt)) {
                    $stmt->closeCursor();
                }
                return false;
            }
        });

        Databaser::addDriver("pdo", "first", function (Databaser $databaser) use ($setDatabaser) {
            $setDatabaser($databaser);
            $result = $databaser->execute();
            if ($databaser->returnType === 'ORM') {
                return $result ? $result->getItem(0) : null;
            }
            return $result[0] ?? null;
        });

        Databaser::addDriver("pdo", "all", function (Databaser $databaser) use ($setDatabaser) {
            $setDatabaser($databaser);
            if ($databaser->returnType === 'ORM') {
                $result = $databaser->execute();
                return $result ?: new Collection([], $databaser->di);
            }
            return $databaser->execute() ?: [];
        });

        Databaser::addDriver("pdo", "raw", function (Databaser $databaser) use ($setDatabaser) {
            $setDatabaser($databaser);
            return $databaser->execute();
        });

        Databaser::addDriver("pdo", "transaction", function (Databaser $databaser) use ($setDatabaser) {
            $setDatabaser($databaser);
            $pdo = $databaser->getActiveConnection()['pdo'] ?? null;
            if (!$pdo) {
                throw new \Exception("No active database connection to start a transaction.");
            }
            $pdo->beginTransaction();
        });

        Databaser::addDriver("pdo", "transact", function (Databaser $databaser, $operations, $success_callback = null, $error_callback = null) use ($setDatabaser) {
            $setDatabaser($databaser);
            $pdo = $databaser->getActiveConnection()['pdo'] ?? null;
            if (!$pdo) {
                $error = ['error' => 'No active database connection to start a transaction.', 'errno' => 'NO_CONNECTION'];
                if (is_callable($error_callback)) {
                    DotApp::dotApp()->trigger("dotapp.databaser.execute.error", $error, []);
                    $error_callback($error, $databaser, []);
                } else {
                    throw new \Exception($error['error']);
                }
                return false;
            }

            try {
                $pdo->beginTransaction();
                $operations($databaser,
                    function ($result, $db, $execution_data) use ($pdo, $success_callback) {
                        $pdo->commit();
                        if (is_callable($success_callback)) {
                            $success_callback($result, $db, $execution_data);
                        }
                    },
                    function ($error, $db, $execution_data) use ($pdo, $error_callback) {
                        $pdo->rollback();
                        if (is_callable($error_callback)) {
                            $error_callback($error, $db, $execution_data);
                        }
                    }
                );
            } catch (\PDOException $e) {
                $pdo->rollback();
                $error = ['error' => $e->getMessage(), 'errno' => $e->getCode()];
                if (is_callable($error_callback)) {
                    DotApp::dotApp()->trigger("dotapp.databaser.execute.error", $error, []);
                    $error_callback($error, $databaser, []);
                } else {
                    throw new \Exception("Transaction error: {$e->getMessage()} (errcode: {$e->getCode()})");
                }
            }
        });

        Databaser::addDriver("pdo", "commit", function (Databaser $databaser) use ($setDatabaser) {
            $setDatabaser($databaser);
            $pdo = $databaser->getActiveConnection()['pdo'] ?? null;
            if (!$pdo) {
                throw new \Exception("No active database connection to commit.");
            }
            $pdo->commit();
        });

        Databaser::addDriver("pdo", "rollback", function (Databaser $databaser) use ($setDatabaser) {
            $setDatabaser($databaser);
            $pdo = $databaser->getActiveConnection()['pdo'] ?? null;
            if (!$pdo) {
                throw new \Exception("No active database connection to rollback.");
            }
            $pdo->rollback();
        });
    }
}