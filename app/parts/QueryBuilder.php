<?php

namespace Dotsystems\App\Parts;
use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\Databaser;
use \Dotsystems\App\Parts\SchemaBuilder;

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
        
        if ($databaser instanceof Databaser) {
            // Ak je $databaser inštancia Databaser, použi existujúcu logiku
            if (isset($databaser->database_drivers['driver'])) {
                $driver = $databaser->database_drivers['driver'];
                $name = key($databaser->databases[$driver] ?? []);
                $this->dbType = strtolower($databaser->databases[$driver][$name]['type'] ?? 'mysql');
            } else {
                $this->dbType = 'mysql';
            }
        } elseif (is_string($databaser) && in_array(strtolower($databaser), ['mysql', 'pgsql', 'sqlite', 'oci', 'sqlsrv'])) {
            // Ak je $databaser string a obsahuje podporovaný typ databázy
            $this->dbType = strtolower($databaser);
        } else {
            // Default hodnota
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

    public function insertInto($table, array $data) {
        return $this->insert($table, $data);
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

    public function deleteFrom($table = null) {
        return $this->delete($table);
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
            $sanitized = array_map(function ($column) {
                if (!$this->isValidColumn($column)) {
                    throw new InvalidArgumentException("Invalid column name or expression: $column");
                }
                return $this->sanitizeColumn($column);
            }, $columns);
            return implode(', ', $sanitized);
        }
        if (!$this->isValidColumn($columns)) {
            throw new InvalidArgumentException("Invalid column name or expression: $columns");
        }
        return $this->sanitizeColumn($columns);
    }

    private function sanitizeColumn($column) {
        // Ponechať SQL výrazy a hviezdičku bez úprav
        if ($this->isSqlExpression($column)) {
            return $column;
        }

        // Rozdeliť kvalifikovaný názov (napr. table.column)
        $parts = explode('.', $column);
        $sanitizedParts = array_map(function ($part) {
            // Sanitácia názvu (povolené: a-z, A-Z, 0-9, _, medzery, špeciálne znaky)
            $part = trim($part);
            if (empty($part)) {
                throw new InvalidArgumentException("Empty column or table name part");
            }
            return $part; // Zachovať pôvodné znaky, ohraničenie sa postará o bezpečnosť
        }, $parts);

        // Zložiť späť názov
        $sanitizedColumn = implode('.', $sanitizedParts);

        // Ohraničenie podľa typu databázy
        if ($this->dbType === 'sqlsrv') {
            return str_replace('.', '].[', "[$sanitizedColumn]");
        } elseif ($this->dbType === 'pgsql' || $this->dbType === 'oci') {
            return str_replace('.', '"."', "\"$sanitizedColumn\"");
        }
        return str_replace('.', '`.`', "`$sanitizedColumn`");
    }

    private function isSqlExpression($column) {
        // Povoliť hviezdičku, SQL funkcie a aliasy
        return $column === '*' ||
               preg_match('/^[A-Z]+\(\*\)$/i', $column) || // Napr. COUNT(*)
               preg_match('/^[A-Z]+\(.*\)$/i', $column) || // Napr. SUM(column)
               strpos($column, ' AS ') !== false; // Napr. column AS alias
    }

    private function isValidColumn($column) {
        // Základná validácia vstupu
        if (empty($column) || !is_string($column)) {
            return false;
        }

        // Povoliť SQL výrazy
        if ($this->isSqlExpression($column)) {
            return true;
        }

        // Validácia názvov stĺpcov (povolené: a-z, A-Z, 0-9, _, medzery, špeciálne znaky, bodka)
        return preg_match('/^[a-zA-Z0-9_\s\.\-@#$%*]+$/i', $column) &&
               strpos($column, ';') === false &&
               strpos($column, '--') === false &&
               strpos($column, '/*') === false;
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

    /**
     * Alters an existing table in the database.
     *
     * This method allows you to modify the structure of an existing table by providing a callback function.
     * The callback function should accept an instance of SchemaBuilder, which provides methods to define
     * alterations to the table.
     *
     * @param string $table The name of the table to be altered.
     * @param callable $callback A callback function that accepts an instance of SchemaBuilder.
     *
     * @return self
     *
     * @throws Exception If the table does not exist.
     */
    public function alterTable($table, callable $callback) {
        $this->queryParts['type'] = 'ALTER_TABLE';
        $this->queryParts['table'] = $this->sanitizeTable($table);
        $schema = new SchemaBuilder($this->databaser);
        $callback($schema);
        $this->queryParts['alter'] = "ALTER TABLE {$this->queryParts['table']} " . $schema->getAlterDefinition();
        return $this;
    }

    /**
     * Drops a table from the database.
     *
     * @param string $table The name of the table to be dropped.
     *
     * @return self
     *
     * @throws Exception If the table does not exist.
     */
    public function dropTable($table) {
        $this->queryParts['type'] = 'DROP_TABLE';
        $this->queryParts['drop'] = "DROP TABLE " . $this->sanitizeTable($table);
        return $this;
    }
}

?>