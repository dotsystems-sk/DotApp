<?php
namespace Dotsystems\App\Parts;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Databaser;
use Dotsystems\App\Parts\QueryBuilder;
use Dotsystems\App\Parts\Entity;
use Dotsystems\App\Parts\Collection;

class DatabaserMysqliDriver {
    public static function create() {
        $databases = null;
        $databaserSet = false;
        $databaserInstance = new \stdClass();

        $setDatabaser = function (Databaser $databaser) use (&$databases, &$databaserSet, &$databaserInstance) {
            if ($databaserSet === false) {
                $databaserInstance = $databaser;
                $databases = $databaser->getDatabases();
                $databases["mysqli"] = [];
                $databaser->statement['execution_type'] = 0;
                $databaserSet = true;
            }
        };

        $getBindingType = function ($value) {
            if (is_int($value)) return 'i';
            if (is_float($value)) return 'd';
            if (is_string($value)) return 's';
            if (is_null($value)) return 's';
            return 'b';
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

        Databaser::addDriver("mysqli", "select_db", function (Databaser $databaser, $name) use ($setDatabaser) {
            $setDatabaser($databaser);
            $databases = $databaser->getDatabases();
            if (!isset(Databaser::getConnections()['mysqli'][$name])) {
                Databaser::$connections['mysqli'][$name] = mysqli_connect(
                    $databases["mysqli"][$name]['server'],
                    $databases["mysqli"][$name]['username'],
                    $databases["mysqli"][$name]['password']
                );
                if (Databaser::$connections['mysqli'][$name]) {
                    $databaser->activeconnection['mysqli'] = Databaser::$connections['mysqli'][$name];
                    mysqli_report(MYSQLI_REPORT_OFF);
                    mysqli_set_charset($databaser->activeconnection['mysqli'], $databases["mysqli"][$name]['collation']);
                    mysqli_select_db($databaser->activeconnection['mysqli'], $databases["mysqli"][$name]['database']);
                } else {
                    $databaser->activeconnection['mysqli'] = null;
                    throw new \Exception("Nepodarilo sa pripojiť k databáze: " . mysqli_connect_error());
                }
            } else {
                $databaser->activeconnection['mysqli'] = Databaser::$connections['mysqli'][$name];
                mysqli_report(MYSQLI_REPORT_OFF);
            }
        });

        Databaser::addDriver("mysqli", "q", function (Databaser $databaser, $querybuilder) use ($clear_statement, $setDatabaser) {
            $setDatabaser($databaser);
            $clear_statement();
            $newqb = new QueryBuilder($databaser);
            if (is_callable($querybuilder)) {
                $querybuilder($newqb);
            }
            return $newqb;
        });

        Databaser::addDriver("mysqli", "schema", function (Databaser $databaser, callable $callback, $success = null, $error = null) use ($clear_statement, $setDatabaser) {
            $setDatabaser($databaser);
            $clear_statement();
            $databaser->setQB(new QueryBuilder($databaser));
            if (is_callable($callback)) {
                $callback($databaser->getQB());
            }
            $databaser->execute($success, $error);
        });

        Databaser::addDriver("mysqli", "return", function (Databaser $databaser, $type) use ($setDatabaser) {
            $setDatabaser($databaser);
            $databaser->returnType = strtoupper($type);
        });

        Databaser::addDriver("mysqli", "getQuery", function (Databaser $databaser) use ($setDatabaser) {
            $setDatabaser($databaser);
            $queryData = $databaser->getQB()->getQuery();
            return [
                'query' => $queryData['query'],
                'bindings' => $queryData['bindings']
            ];
        });

        Databaser::addDriver("mysqli", "inserted_id", function (Databaser $databaser) use ($setDatabaser) {
            $setDatabaser($databaser);
            if ($databaser->getActiveConnection()['mysqli']) {
                return mysqli_insert_id($databaser->getActiveConnection()['mysqli']);
            }
            return null;
        });

        Databaser::addDriver("mysqli", "affected_rows", function (Databaser $databaser) use ($setDatabaser) {
            $setDatabaser($databaser);
            if ($databaser->getActiveConnection()['mysqli']) {
                return mysqli_affected_rows($databaser->getActiveConnection()['mysqli']);
            }
            return null;
        });

        Databaser::addDriver("mysqli", "fetchArray", function (Databaser $databaser, &$array) use ($setDatabaser) {
            $setDatabaser($databaser);
            return mysqli_fetch_assoc($array);
        });

        Databaser::addDriver("mysqli", "fetchFirst", function (Databaser $databaser, &$array) use ($setDatabaser) {
            $setDatabaser($databaser);
            return $array ? mysqli_fetch_assoc($array) : false;
        });

        Databaser::addDriver("mysqli", "newEntity", function (Databaser $databaser, $row) use ($setDatabaser) {
            $setDatabaser($databaser);
            return new Entity($row, $databaser->di);
        });

        Databaser::addDriver("mysqli", "newCollection", function (Databaser $databaser, $queryOrItems) use ($setDatabaser) {
            $setDatabaser($databaser);
            return new Collection($queryOrItems, $databaser->di);
        });

        Databaser::addDriver("mysqli", "execute", function (Databaser $databaser, $success_callback = null, $error_callback = null) use ($getBindingType, $setDatabaser) {
            $setDatabaser($databaser);
            if (!$databaser->getActiveConnection()[Databaser::getDatabaseDrivers()['driver']]) {
                throw new \Exception("No active connection to database ! Use select_db() !");
            }
            $queryData = $databaser->getQB()->getQuery();
            if (strlen(trim($queryData["query"])) == 0 && isset($queryData['queryParts']['ifNotExistUsed']) && $queryData['queryParts']['ifNotExistUsed'] == true) {
                return;
            }
            $query = $queryData['query'];
            $values = $queryData['bindings'];
            $types = $queryData['types'];

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
                if (is_callable($success_callback)) $success_callback($cached, $databaser, []);
                return $cached;
            }

            $stmt = $databaser->getActiveConnection()['mysqli']->prepare($query);
            if ($stmt === false) {
                $error = ['error' => $databaser->getActiveConnection()['mysqli']->error, 'errno' => $databaser->getActiveConnection()['mysqli']->errno];
                if (is_callable($error_callback)) {
                    DotApp::dotApp()->trigger("dotapp.databaser.execute.error", $error, $execution_data);
                    $error_callback($error, $databaser, $execution_data);
                } else {
                    throw new \Exception("Error: " . $error['error'] . " (errcode: " . $error['errno'] . ")");
                }
                return false;
            }

            if (!empty($values)) {
                $stmt->bind_param($types, ...$values);
            }

            DotApp::dotApp()->trigger("dotapp.databaser.execute", $execution_data);
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
                $databaser->statement['execution_data'] = $execution_data;

                if ($databaser->returnType === "ORM") {
                    if ($result && $result->num_rows > 0) {
                        $rows = [];
                        while ($row = $databaser->fetchArray($result)) {
                            $entity = new Entity($row, $databaser->di);
                            $entity->loadRelations();
                            $rows[] = $entity;
                        }
                        $returnValue = new Collection($rows, $databaser->di);
                    } else {
                        $returnValue = null;
                    }
                    if ($databaser->cacheDriver && $returnValue) {
                        $databaser->cacheDriver->set($cacheKey, $returnValue, 3600);
                    }
                    if (is_callable($success_callback)) {
                        DotApp::dotApp()->trigger("dotapp.databaser.execute.success", $returnValue, $execution_data);
                        $success_callback($returnValue, $databaser, $execution_data);
                    }
                    $stmt->close();
                    $databaser->q(function ($qb) {});
                    return $returnValue;
                } else {
                    $rows = [];
                    while ($row = $databaser->fetchArray($result)) {
                        $rows[] = $row;
                    }
                    if ($databaser->cacheDriver && $rows) {
                        $databaser->cacheDriver->set($cacheKey, $rows, 3600);
                    }
                    DotApp::dotApp()->trigger("dotapp.databaser.execute.success", $rows, $execution_data);
                    if (is_callable($success_callback)) $success_callback($rows, $databaser, $execution_data);
                    $stmt->close();
                    $databaser->q(function ($qb) {});
                    return $result;
                }
            } else {
                $error = ['error' => $stmt->error, 'errno' => $stmt->errno];
                if (is_callable($error_callback)) {
                    DotApp::dotApp()->trigger("dotapp.databaser.execute.error", $error, $execution_data);
                    $error_callback($error, $databaser, $execution_data);
                } else {
                    throw new \Exception("Error: " . $error['error'] . " (errcode: " . $error['errno'] . ")");
                }
                $stmt->close();
                return false;
            }
        });

        Databaser::addDriver("mysqli", "first", function (Databaser $databaser) use ($setDatabaser) {
            $setDatabaser($databaser);
            $result = $databaser->execute();
            if ($databaser->returnType === 'ORM') {
                return $result->getItem(0);
            }
            return $result[0];
        });

        Databaser::addDriver("mysqli", "all", function (Databaser $databaser) use ($setDatabaser) {
            $setDatabaser($databaser);
            if ($databaser->returnType === 'ORM') {
                return new Collection(clone $databaser->di, $databaser->di);
            }
            $result = $databaser->execute();
            $rows = [];
            while ($row = $databaser->fetchArray($result)) {
                $rows[] = $row;
            }
            return $rows;
        });

        Databaser::addDriver("mysqli", "raw", function (Databaser $databaser) use ($setDatabaser) {
            $setDatabaser($databaser);
            return $databaser->execute();
        });

        Databaser::addDriver("mysqli", "transaction", function (Databaser $databaser) use ($setDatabaser) {
            $setDatabaser($databaser);
            $databaser->getActiveConnection()['mysqli']->begin_transaction();
        });

        Databaser::addDriver("mysqli", "transact", function (Databaser $databaser, $operations, $success_callback = null, $error_callback = null) use ($setDatabaser) {
            $setDatabaser($databaser);
            $databaser->getActiveConnection()['mysqli']->begin_transaction();
            $operations($databaser,
                function ($result, $db, $execution_data) use ($databaser, $success_callback) {
                    $databaser->getActiveConnection()['mysqli']->commit();
                    if (is_callable($success_callback)) {
                        $success_callback($result, $databaser, $execution_data);
                    }
                },
                function ($error, $db, $execution_data) use ($databaser, $error_callback) {
                    $databaser->getActiveConnection()['mysqli']->rollback();
                    if (is_callable($error_callback)) {
                        $error_callback($error, $databaser, $execution_data);
                    }
                }
            );
        });

        Databaser::addDriver("mysqli", "commit", function (Databaser $databaser) use ($setDatabaser) {
            $setDatabaser($databaser);
            $databaser->getActiveConnection()['mysqli']->commit();
        });

        Databaser::addDriver("mysqli", "rollback", function (Databaser $databaser) use ($setDatabaser) {
            $setDatabaser($databaser);
            $databaser->getActiveConnection()['mysqli']->rollback();
        });
    }
}