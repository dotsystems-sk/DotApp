<?php

/**
 * CLASS DatabaserMysqliDriver
 *
 * MySQLi database driver implementation for the DotApp framework.
 * Provides MySQLi-specific database operations and ORM support.
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

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Databaser;
use Dotsystems\App\Parts\QueryBuilder;
use Dotsystems\App\Parts\Entity;
use Dotsystems\App\Parts\Collection;

class DatabaserMysqliDriver
{
    public static function create(Databaser $databaser)
    {
        $activeconnection = &$databaser->getActiveConnection();
        $connections = &$databaser->getConnections();
        $databases = &$databaser->getDatabases();
        $database_drivers = &$databaser->getDatabaseDrivers();
        $statement = &$databaser->getStatement();
        $qb = &$databaser->getQueryBuilder();
        $returnType = &$databaser->getReturnType();
        $di = &$databaser->getDI();
        $cacheDriver = &$databaser->getCacheDriver();
        $useCache = &$databaser->getUseCache();

        $databases["mysqli"] = [];
        $statement['execution_type'] = 0;

        // Helper function to determine binding type (MySQLi specific)
        $getBindingType = function ($value) {
            if (is_int($value)) return 'i';
            if (is_float($value)) return 'd';
            if (is_string($value)) return 's';
            if (is_null($value)) return 's'; // NULL as string in MySQLi
            return 'b'; // Blob ako fallback
        };

        // Clean up statement
        $clear_statement = function () use (&$statement) {
            unset($statement);
            $statement = [
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
        $databaser->addDriver("mysqli", "select_db", function ($name) use (&$connections, &$database_drivers, &$databases, &$activeconnection) {
            if (!isset($connections[$database_drivers['driver']][$name])) {
                $connections[$database_drivers['driver']][$name] = mysqli_connect(
                    $databases["mysqli"][$name]['server'],
                    $databases["mysqli"][$name]['username'],
                    $databases["mysqli"][$name]['password']
                );
                if ($connections[$database_drivers['driver']][$name]) {
                    $activeconnection['mysqli'] = $connections[$database_drivers['driver']][$name];
                    mysqli_report(MYSQLI_REPORT_OFF);
                    mysqli_set_charset($activeconnection['mysqli'], $databases["mysqli"][$name]['collation']);
                    mysqli_select_db($activeconnection['mysqli'], $databases["mysqli"][$name]['database']);
                } else {
                    $activeconnection['mysqli'] = null;
                    throw new \Exception("Nepodarilo sa pripojiť k databáze: " . mysqli_connect_error());
                }
            } else {
                $activeconnection['mysqli'] = $connections[$database_drivers['driver']][$name];
                mysqli_report(MYSQLI_REPORT_OFF);
            }
        });

        // Query Builder podpora
        $databaser->addDriver("mysqli", "q", function ($querybuilder) use ($databaser, $clear_statement) {
            $clear_statement();
            $newqb = new QueryBuilder($databaser);
            if (is_callable($querybuilder)) {
                $querybuilder($newqb);
            }
            return $newqb;
        });

        $databaser->addDriver("mysqli", "schema", function (callable $callback, $success = null, $error = null) use ($databaser, $clear_statement) {
            $clear_statement();
            $qb = new QueryBuilder($databaser);
            if (is_callable($callback)) {
                $callback($qb);
            }
            $databaser->execute($success, $error); // Spustí query cez MySQLi
        });

        // Nastavenie typu návratovej hodnoty
        $databaser->addDriver("mysqli", "return", function ($type) use (&$returnType) {
            $returnType = strtoupper($type);
        });

        // Získanie vygenerovaného dotazu
        $databaser->addDriver("mysqli", "getQuery", function () use (&$qb) {
            $queryData = $qb->getQuery();
            return [
                'query' => $queryData['query'],
                'bindings' => $queryData['bindings']
            ];
        });

        $databaser->addDriver("mysqli", "inserted_id", function () use (&$activeconnection) {
            if ($activeconnection['mysqli']) {
                return mysqli_insert_id($activeconnection['mysqli']);
            }
            return null;
        });

        $databaser->addDriver("mysqli", "affected_rows", function () use (&$activeconnection) {
            if ($activeconnection['mysqli']) {
                return mysqli_affected_rows($activeconnection['mysqli']);
            }
            return null;
        });

        // Loading results
        $databaser->addDriver("mysqli", "fetchArray", function (&$array) {
            return mysqli_fetch_assoc($array);
        });

        $databaser->addDriver("mysqli", "fetchFirst", function (&$array) {
            return $array ? mysqli_fetch_assoc($array) : false;
        });

        $databaser->addDriver("mysqli", "newEntity", function ($row) use (&$di) {
            return new Entity($row, $di);
        });

        $databaser->addDriver("mysqli", "newCollection", function ($queryOrItems) use (&$di) {
            return new Collection($queryOrItems, $di);
        });

        // Execute
        $databaser->addDriver("mysqli", "execute", function ($success_callback = null, $error_callback = null) use ($databaser, $getBindingType, &$activeconnection, &$database_drivers, &$qb, &$returnType, &$statement, &$di, &$cacheDriver) {
            if (!$activeconnection[$database_drivers['driver']]) {
                throw new \Exception("No active connection to database ! Use select_db() !");
            }
            $queryData = $qb->getQuery();
            if ((strlen(trim($queryData["query"])) == 0) && isset($queryData['queryParts']['ifNotExistUsed']) && $queryData['queryParts']['ifNotExistUsed'] == true) {
                return;
            }
            $query = $queryData['query'];
            $values = $queryData['bindings'];
            $types = $queryData['types'];

            $table = $statement['table'] ?? 'unknown_table';
            if (isset($queryData['queryParts']['table'])) {
                $table = $queryData['queryParts']['table']; // Pre CREATE TABLE, ALTER TABLE
            } elseif (isset($queryData['queryParts']['from'])) {
                $table = trim(str_replace('FROM', '', $queryData['queryParts']['from']));
            } elseif (isset($queryData['queryParts']['update'])) {
                $table = trim(str_replace('UPDATE', '', $queryData['queryParts']['update']));
            } elseif (isset($queryData['queryParts']['insert'])) {
                $table = trim(preg_replace('/INSERT INTO (\w+).*/', '$1', $queryData['queryParts']['insert']));
            }
            $statement['table'] = $table;

            $cacheKey = "{$table}:{$returnType}:" . md5($query . serialize($values));
            $execution_data = [
                'query' => $query,
                'bindings' => $values
            ];

            if ($cacheDriver && $cached = $cacheDriver->get($cacheKey)) {
                DotApp::dotApp()->trigger("dotapp.databaser.execute.success", $cached, $execution_data);
                if (is_callable($success_callback)) $success_callback($cached, $databaser, []);
                return $cached;
            }

            $stmt = $activeconnection['mysqli']->prepare($query);
            if ($stmt === false) {
                $error = ['error' => $activeconnection['mysqli']->error, 'errno' => $activeconnection['mysqli']->errno];
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
                $statement['execution_data'] = $execution_data;

                if ($returnType === "ORM") {
                    if ($result && $result->num_rows > 0) {
                        $rows = [];
                        while ($row = $databaser->fetchArray($result)) {
                            $entity = new Entity($row, $di);
                            $entity->loadRelations();
                            $rows[] = $entity;
                        }
                        $returnValue = new Collection($rows, $di);
                    } else {
                        $returnValue = null;
                    }
                    if ($cacheDriver && $returnValue) {
                        $cacheDriver->set($cacheKey, $returnValue, 3600);
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
                    if ($cacheDriver && $rows) {
                        $cacheDriver->set($cacheKey, $rows, 3600);
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

        // First
        $databaser->addDriver("mysqli", "first", function () use (&$returnType, $databaser) {
            $result = $databaser->execute();
            if ($returnType === 'ORM') {
                return $result->getItem(0);
            }
            return $result[0];
        });

        // All
        $databaser->addDriver("mysqli", "all", function () use (&$returnType, &$di, $databaser) {
            if ($returnType === 'ORM') {
                return new Collection(clone $di, $di);
            }
            $result = $databaser->execute();
            $rows = [];
            while ($row = $databaser->fetchArray($result)) {
                $rows[] = $row;
            }
            return $rows;
        });

        // Raw
        $databaser->addDriver("mysqli", "raw", function () use ($databaser) {
            return $databaser->execute();
        });

        // Transakcie
        $databaser->addDriver("mysqli", "transaction", function () use (&$activeconnection) {
            $activeconnection['mysqli']->begin_transaction();
        });

        $databaser->addDriver("mysqli", "transact", function ($operations, $success_callback = null, $error_callback = null) use (&$activeconnection, $databaser) {
            $activeconnection['mysqli']->begin_transaction();
            $operations(
                $databaser,
                function ($result, $db, $execution_data) use ($success_callback, &$activeconnection, $databaser) {
                    $activeconnection['mysqli']->commit();
                    if (is_callable($success_callback)) {
                        $success_callback($result, $databaser, $execution_data);
                    }
                },
                function ($error, $db, $execution_data) use ($error_callback, &$activeconnection, $databaser) {
                    $activeconnection['mysqli']->rollback();
                    if (is_callable($error_callback)) {
                        $error_callback($error, $databaser, $execution_data);
                    }
                }
            );
        });

        $databaser->addDriver("mysqli", "commit", function () use (&$activeconnection) {
            $activeconnection['mysqli']->commit();
        });

        $databaser->addDriver("mysqli", "rollback", function () use (&$activeconnection) {
            $activeconnection['mysqli']->rollback();
        });
    }
}
