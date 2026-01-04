<?php
/**
 * CLASS DatabaserPdoDriver
 *
 * PDO database driver implementation for the DotApp framework.
 * Provides PDO-specific database operations and ORM support for all databases.
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

class DatabaserPdoDriver
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

        $databases["pdo"] = [];
        $statement['execution_type'] = 0;

        // Pomocná funkcia na určenie typu väzby
        $getBindingType = function ($value) {
            if (is_int($value)) return \PDO::PARAM_INT;
            if (is_float($value)) return \PDO::PARAM_STR; // PDO nemá explicitný float, použijeme string
            if (is_string($value)) return \PDO::PARAM_STR;
            if (is_null($value)) return \PDO::PARAM_NULL;
            return \PDO::PARAM_LOB; // Blob ako fallback
        };

        // Vyčistenie statementu
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
                'table' => 'unknown_table'
            ];
        };

        // Pripojenie k databáze cez PDO s dynamickým DSN
        $databaser->addDriver("pdo", "select_db", function ($name) use (&$connections, &$database_drivers, &$databases, &$activeconnection) {
            if (!isset($connections[$database_drivers['driver']][$name])) {
                $type = strtolower($databases['pdo'][$name]['type']);
                $server = $databases['pdo'][$name]['server'];
                $database = $databases['pdo'][$name]['database'];
                $collation = $databases['pdo'][$name]['collation'];

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
                    $connections[$database_drivers['driver']][$name] = new \PDO(
                        $dsn,
                        $databases['pdo'][$name]['username'],
                        $databases['pdo'][$name]['password'],
                        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
                    );
                    $activeconnection['pdo'] = $connections[$database_drivers['driver']][$name];
                } catch (\PDOException $e) {
                    $activeconnection['pdo'] = null;
                    throw new \Exception("Nepodarilo sa pripojiť k databáze: " . $e->getMessage());
                }
            } else {
                $activeconnection['pdo'] = $connections[$database_drivers['driver']][$name];
            }
        });

        // Query Builder podpora
        $databaser->addDriver("pdo", "q", function ($querybuilder) use ($databaser, $clear_statement) {
            $clear_statement();
            $newqb = new QueryBuilder($databaser); // Vždy nový QueryBuilder
            if (is_callable($querybuilder)) {
                $querybuilder($newqb);
            }
            return $newqb;
        });

        $databaser->addDriver("pdo", "inserted_id", function () use (&$activeconnection) {
            if ($activeconnection['pdo']) {
                return $activeconnection['pdo']->lastInsertId();
            }
            return null;
        });

        $databaser->addDriver("pdo", "affected_rows", function () use (&$activeconnection, &$statement) {
            if ($activeconnection['pdo'] && isset($statement['execution_data']['result'])) {
                return $statement['execution_data']['result']->rowCount();
            }
            return null;
        });

        $databaser->addDriver("pdo", "schema", function (callable $callback, $success = null, $error = null) use ($databaser, $clear_statement) {
            $clear_statement();
            $qb = new QueryBuilder($databaser);
            if (is_callable($callback)) {
                $callback($qb);
            }
            $databaser->execute($success, $error); // Spustí query cez PDO
        });

        // Nastavenie typu návratovej hodnoty
        $databaser->addDriver("pdo", "return", function ($type) use (&$returnType) {
            $returnType = strtoupper($type);
        });

        // Získanie vygenerovaného dotazu
        $databaser->addDriver("pdo", "getQuery", function () use (&$qb) {
            $queryData = $qb->getQuery();
            return [
                'query' => $queryData['query'],
                'bindings' => $queryData['bindings']
            ];
        });

        // Načítanie výsledkov
        $databaser->addDriver("pdo", "fetchArray", function (&$stmt) {
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        });

        $databaser->addDriver("pdo", "fetchFirst", function (&$stmt) {
            return $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
        });

        $databaser->addDriver("pdo", "newEntity", function ($row) use (&$di) {
            return new Entity($row, $di);
        });

        $databaser->addDriver("pdo", "newCollection", function ($queryOrItems) use (&$di) {
            return new Collection($queryOrItems, $di);
        });

        // Execute
        $databaser->addDriver("pdo", "execute", function ($success_callback = null, $error_callback = null) use ($databaser, $getBindingType, &$activeconnection, &$database_drivers, &$qb, &$returnType, &$statement, &$di, &$cacheDriver) {
            if (!$activeconnection[$database_drivers['driver']]) {
                throw new \Exception("No active connection to database ! Use select_db() !");
            }
            $queryData = $qb->getQuery();
            if ((strlen(trim($queryData["query"])) == 0) && isset($queryData['queryParts']['ifNotExistUsed']) && $queryData['queryParts']['ifNotExistUsed'] == true) {
                return;
            }
            $query = $queryData['query'];
            $values = $queryData['bindings'];

            // Nový formát kľúča
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

            try {
                $stmt = $activeconnection['pdo']->prepare($query);
                if ($stmt === false) {
                    $error = ['error' => $activeconnection['pdo']->errorInfo()[2] ?? 'Failed to prepare statement', 'errno' => $activeconnection['pdo']->errorCode()];
                    if (is_callable($error_callback)) {
                        DotApp::dotApp()->trigger("dotapp.databaser.execute.error", $error, $execution_data);
                        $error_callback($error, $databaser, $execution_data);
                    } else {
                        throw new \Exception("Error: " . $error['error'] . " (errcode: " . $error['errno'] . ")");
                    }
                    return false;
                }

                foreach ($values as $index => $value) {
                    $stmt->bindValue($index + 1, $value, $getBindingType($value));
                }

                DotApp::dotApp()->trigger("dotapp.databaser.execute", $execution_data);
                if ($stmt->execute()) {
                    $execution_data = [
                        'affected_rows' => $stmt->rowCount(),
                        'insert_id' => $activeconnection['pdo']->lastInsertId(),
                        'num_rows' => $stmt->rowCount(),
                        'result' => $stmt,
                        'query' => $query,
                        'bindings' => $values
                    ];
                    $statement['execution_data'] = $execution_data;

                    if ($returnType === "ORM") {
                        if ($stmt->rowCount() > 0) {
                            $rows = [];
                            while ($row = $databaser->fetchArray($stmt)) {
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
                        DotApp::dotApp()->trigger("dotapp.databaser.execute.success", $returnValue, $execution_data);
                        if (is_callable($success_callback)) $success_callback($returnValue, $databaser, $execution_data);
                        $stmt->closeCursor();
                        $databaser->q(function ($qb) {});
                        return $returnValue;
                    } else {
                        // Toto bolo niekedy tu na to, aby vratilo rovno pole zo STMT. Ale neskor prerobene aby vratilo STMT result
                        $rows = [];
                        while ($row = $databaser->fetchArray($stmt)) {
                            $rows[] = $row;
                        }
                        if ($cacheDriver && $rows) {
                            $cacheDriver->set($cacheKey, $rows, 3600);
                        }
                        DotApp::dotApp()->trigger("dotapp.databaser.execute.success", $rows, $execution_data);
                        if (is_callable($success_callback)) $success_callback($rows, $databaser, $execution_data);
                        $stmt->closeCursor();
                        $databaser->q(function ($qb) {});
                        return $rows;
                    }
                } else {
                    $error = ['error' => $stmt->errorInfo()[2] ?? 'Execution failed', 'errno' => $stmt->errorCode()];
                    if (is_callable($error_callback)) {
                        DotApp::dotApp()->trigger("dotapp.databaser.execute.error", $error, $execution_data);
                        $error_callback($error, $databaser, $execution_data);
                    } else {
                        throw new \Exception("Error: " . $error['error'] . " (errcode: " . $error['errno'] . ")");
                    }
                    $stmt->closeCursor();
                    return false;
                }
            } catch (\PDOException $e) {
                $error = ['error' => $e->getMessage(), 'errno' => $e->getCode()];
                if (is_callable($error_callback)) {
                    DotApp::dotApp()->trigger("dotapp.databaser.execute.error", $error, $execution_data);
                    $error_callback($error, $databaser, $execution_data);
                } else {
                    throw new \Exception("Error: " . $e->getMessage() . " (errcode: " . $e->getCode() . ")");
                }
                return false;
            }
        });

        // First
        $databaser->addDriver("pdo", "first", function () use (&$returnType, $databaser) {
            $result = $databaser->execute();
            if ($returnType === 'ORM') {
                return $result->getItem(0);
            }
            return $result[0];
        });

        // All
        $databaser->addDriver("pdo", "all", function () use (&$returnType, &$di, $databaser) {
            if ($returnType === 'ORM') {
                return new Collection(clone $di, $di);
            }
            $rows = $databaser->execute();
            return $rows;
        });

        // Raw
        $databaser->addDriver("pdo", "raw", function () use ($databaser) {
            return $databaser->execute();
        });

        // Transakcie
        $databaser->addDriver("pdo", "transaction", function () use (&$activeconnection) {
            $activeconnection['pdo']->beginTransaction();
        });

        $databaser->addDriver("pdo", "transact", function ($operations, $success_callback = null, $error_callback = null) use (&$activeconnection, $databaser) {
            $activeconnection['pdo']->beginTransaction();
            $operations(
                $databaser,
                function ($result, $db, $execution_data) use ($success_callback, &$activeconnection, $databaser) {
                    $activeconnection['pdo']->commit();
                    if (is_callable($success_callback)) {
                        $success_callback($result, $databaser, $execution_data);
                    }
                },
                function ($error, $db, $execution_data) use ($error_callback, &$activeconnection, $databaser) {
                    $activeconnection['pdo']->rollback();
                    if (is_callable($error_callback)) {
                        $error_callback($error, $databaser, $execution_data);
                    }
                }
            );
        });

        $databaser->addDriver("pdo", "commit", function () use (&$activeconnection) {
            $activeconnection['pdo']->commit();
        });

        $databaser->addDriver("pdo", "rollback", function () use (&$activeconnection) {
            $activeconnection['pdo']->rollback();
        });
    }
}
