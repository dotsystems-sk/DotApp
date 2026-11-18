<?php

namespace Dotsystems\App\Parts;
use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\Databaser;
use \Dotsystems\App\Parts\SchemaBuilder;
use \Dotsystems\App\Parts\DB;

class QueryBuilder {
    private $queryParts;
    private $databaser;
    private $dbType;

    // Bindingy su ulozene po castiach dotazu (with, insert, set, join, where, having, union, raw).
    // getQuery() ich poskladava v rovnakom poradi, v akom idu jednotlive casti do SQL, takze nezalezi
    // na tom, v akom poradi boli volane metody (napr. limit() pred where() alebo where() pred set()).
    private $bindingParts;

    // Operatory, ktore mozu ist do where(), having() a join(). Operator sa vklada priamo do SQL,
    // preto musi byt z tohto zoznamu.
    private static $allowedOperators = [
        '=', '!=', '<>', '<', '>', '<=', '>=', '<=>',
        'LIKE', 'NOT LIKE', 'ILIKE', 'NOT ILIKE', 'LIKE BINARY', 'NOT LIKE BINARY',
        'RLIKE', 'NOT RLIKE', 'REGEXP', 'NOT REGEXP', 'SIMILAR TO', 'NOT SIMILAR TO',
        'IN', 'NOT IN', 'IS', 'IS NOT',
        '~', '~*', '!~', '!~*', '&', '|', '^', '<<', '>>',
    ];

    public function __construct($databaser = null) {
        $this->databaser = $databaser;
        $this->queryParts = [];
        $this->bindingParts = [];

        if ($databaser instanceof Databaser) {
            // If $databaser is a Databaser instance, use existing logic
            if (isset($databaser->database_drivers['driver'])) {
                $driver = $databaser->database_drivers['driver'];
                $databazy = $databaser->getDatabases();
                $name = key($databazy[$driver] ?? []);
                $this->dbType = strtolower($databazy[$driver][$name]['type'] ?? 'mysql');
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
            // DISTINCT pridavame iba raz, aj ked uz bol zadany cez select(..., true).
            if (!preg_match('/^SELECT DISTINCT\b/', $this->queryParts['select'])) {
                $this->queryParts['select'] = preg_replace('/^SELECT/', 'SELECT DISTINCT', $this->queryParts['select'], 1);
            }
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
    public function with($name, $queryCallback) {
        if ($this->dbType === 'sqlite') {
            throw new \Exception("WITH (CTE) nie je podporované v SQLite.");
        }

        if ($queryCallback instanceof QueryBuilder) {
            // If it's already a QueryBuilder object, use it directly
            $subQuery = $queryCallback->getQuery();
        } elseif (is_callable($queryCallback)) {
            // Ak je callable, zavoláme ho na novom QueryBuilder
            $subQueryBuilder = new QueryBuilder($this->databaser);
            $queryCallback($subQueryBuilder);
            $subQuery = $subQueryBuilder->getQuery();
        } else {
            throw new \InvalidArgumentException("Parameter queryCallback must be callable or QueryBuilder instance");
        }

        $this->queryParts['with'][] = $this->sanitizeTable($name) . " AS (" . $subQuery['query'] . ")";
        $this->mergeBindings($subQuery, 'with');
        return $this;
    }

    // UNION, INTERSECT, EXCEPT
    public function union(QueryBuilder $query, $all = false) {
        $subQuery = $query->getQuery();
        $unionType = $all ? "UNION ALL" : "UNION";
        $this->queryParts['union'][] = "$unionType (" . $subQuery['query'] . ")";
        $this->mergeBindings($subQuery, 'union');
        return $this;
    }

    public function intersect(QueryBuilder $query) {
        if (in_array($this->dbType, ['mysql', 'sqlite'])) {
            throw new \Exception("INTERSECT nie je podporované v {$this->dbType}.");
        }
        $subQuery = $query->getQuery();
        $this->queryParts['union'][] = "INTERSECT (" . $subQuery['query'] . ")";
        $this->mergeBindings($subQuery, 'union');
        return $this;
    }

    public function except(QueryBuilder $query) {
        if (in_array($this->dbType, ['mysql', 'sqlite'])) {
            throw new \Exception("EXCEPT nie je podporované v {$this->dbType}.");
        }
        $subQuery = $query->getQuery();
        $this->queryParts['union'][] = "EXCEPT (" . $subQuery['query'] . ")";
        $this->mergeBindings($subQuery, 'union');
        return $this;
    }

    // INSERT
    public function insert($table, array $data) {
        // Prazdne pole by vytvorilo "INSERT INTO t () VALUES ()", co vlozi riadok iba s predvolenymi hodnotami.
        if (count($data) === 0) {
            throw new \InvalidArgumentException("insert() needs at least one column.");
        }
        $this->queryParts['type'] = 'INSERT';
        $columns = implode(', ', array_map([$this, 'sanitizeColumn'], array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $this->queryParts['insert'] = "INSERT INTO " . $this->sanitizeTable($table) . " ($columns) VALUES ($placeholders)";
        // Novy insert() nahradza predchadzajuci aj s jeho bindingmi.
        $this->bindingParts['insert'] = [];
        $this->addBindings(array_values($data), 'insert');
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
        if (!isset($this->queryParts['insert'])) {
            throw new \LogicException("onDuplicateKeyUpdate() must be called after insert().");
        }
        if ($this->dbType === 'mysql') {
            $updates = [];
            foreach ($data as $column => $value) {
                $updates[] = $this->sanitizeColumn($column) . " = ?";
                $this->addBindings([$value], 'insert');
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
                $this->addBindings([$value], 'insert');
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
        // Prazdne pole by vytvorilo "SET " bez stlpcov.
        if (count($data) === 0) {
            throw new \InvalidArgumentException("set() needs at least one column.");
        }
        $setParts = [];
        // Nove set() nahradza predchadzajuce aj s jeho bindingmi.
        $this->bindingParts['set'] = [];
        foreach ($data as $column => $value) {
            $setParts[] = $this->sanitizeColumn($column) . " = ?";
            $this->addBindings([$value], 'set');
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
        return $this->addWhere(func_num_args(), $column, $operator, $value, $boolean);
    }

    public function andWhere($column, $operator = null, $value = null) {
        return $this->addWhere(func_num_args(), $column, $operator, $value, 'AND');
    }

    public function orWhere($column, $operator = null, $value = null) {
        return $this->addWhere(func_num_args(), $column, $operator, $value, 'OR');
    }

    /*
        Spolocna logika pre where(), andWhere() a orWhere().

        $argc je pocet parametrov, s ktorymi bola metoda zavolana. Podla neho rozlisujeme:
            where('a > b')                  - 1 parameter: surova podmienka
            where('stlpec', $hodnota)       - 2 parametre: skrateny zapis pre stlpec = hodnota
            where('stlpec', '=', $hodnota)  - 3 a viac parametrov: stlpec, operator, hodnota

        Pri skratenom zapise je druhy parameter VZDY hodnota. Prazdny retazec, null ani text, ktory vyzera
        ako operator ('like', '='), sa preto nikdy nevlozia do SQL ako surova podmienka alebo operator.
    */
    private function addWhere($argc, $column, $operator, $value, $boolean) {
        $boolean = $this->normalizeBoolean($boolean);

        if ($column instanceof \Closure) {
            $groupBuilder = new QueryBuilder($this->databaser);
            $groupBuilder->queryParts['type'] = 'SELECT'; // Set default type for sub-query
            $column($groupBuilder);
            $groupQuery = $groupBuilder->getQuery();

            // Safe check for 'where' field existence
            $whereClause = '';
            if (!empty($groupQuery['queryParts']['where'])) {
                $whereClause = implode(' ', $groupQuery['queryParts']['where']);
            }

            if ($whereClause) {
                $whereClause = trim(preg_replace('/^(AND|OR)\s+/', '', $whereClause));
                $this->queryParts['where'][] = "$boolean ($whereClause)";
                // Zo skupiny berieme iba bindingy jej podmienok, v poradi v akom su v zatvorke.
                $this->mergeBindingList($groupBuilder->bindingParts['where'] ?? [], 'where');
            }
            return $this;
        }

        if ($value instanceof \Closure) {
            $operator = $this->normalizeOperator($operator);
            $subQueryBuilder = new QueryBuilder($this->databaser);
            $subQueryBuilder->queryParts['type'] = 'SELECT'; // Set default type for sub-query
            $value($subQueryBuilder);
            $subQuery = $subQueryBuilder->getQuery();
            $this->queryParts['where'][] = "$boolean " . $this->sanitizeColumn($column) . " $operator (" . $subQuery['query'] . ")";
            $this->mergeBindings($subQuery, 'where');
            return $this;
        }

        if ($argc <= 1) {
            // Surova podmienka zapisana v prvom parametri, napriklad where('a > b').
            if (!is_string($column) || trim($column) === '') {
                throw new \InvalidArgumentException("where() needs a column or a condition.");
            }
            $this->queryParts['where'][] = "$boolean $column";
            return $this;
        }

        if ($argc === 2) {
            // Skrateny zapis where('stlpec', $hodnota).
            return $this->addWhereValue($column, '=', $operator, $boolean);
        }

        if ($value === null) {
            $operatorText = is_string($operator) ? strtoupper(trim(preg_replace('/\s+/', ' ', $operator))) : $operator;
            if ($operator === null || $operatorText === '') {
                // where('a > b', null, null, 'OR') - surova podmienka s urcenou spojkou.
                if (!is_string($column) || trim($column) === '') {
                    throw new \InvalidArgumentException("where() needs a column or a condition.");
                }
                $this->queryParts['where'][] = "$boolean $column";
            } elseif ($operatorText === '=' || $operatorText === 'IS') {
                $this->queryParts['where'][] = "$boolean " . $this->sanitizeColumn($column) . " IS NULL";
            } elseif ($operatorText === '!=' || $operatorText === '<>' || $operatorText === 'IS NOT') {
                $this->queryParts['where'][] = "$boolean " . $this->sanitizeColumn($column) . " IS NOT NULL";
            } elseif (is_string($operator) && in_array($operatorText, self::$allowedOperators, true)) {
                // Porovnanie s NULL nevrati v SQL ziadny riadok.
                $this->queryParts['where'][] = "$boolean " . $this->sanitizeColumn($column) . " $operatorText NULL";
            } else {
                // where('stlpec', $hodnota, null, 'OR') - druhy parameter je hodnota, nie operator.
                $this->addWhereValue($column, '=', $operator, $boolean);
            }
            return $this;
        }

        return $this->addWhereValue($column, $operator, $value, $boolean);
    }

    // Jedna podmienka stlpec OPERATOR hodnota. Hodnota ide vzdy cez binding.
    private function addWhereValue($column, $operator, $value, $boolean) {
        $operator = $this->normalizeOperator($operator);

        if ($value === null) {
            // stlpec = NULL nevyhovuje v SQL ziadnemu riadku, preto pouzijeme IS NULL / IS NOT NULL.
            if ($operator === '=' || $operator === 'IS') {
                $this->queryParts['where'][] = "$boolean " . $this->sanitizeColumn($column) . " IS NULL";
            } elseif ($operator === '!=' || $operator === '<>' || $operator === 'IS NOT') {
                $this->queryParts['where'][] = "$boolean " . $this->sanitizeColumn($column) . " IS NOT NULL";
            } else {
                $this->queryParts['where'][] = "$boolean " . $this->sanitizeColumn($column) . " $operator NULL";
            }
            return $this;
        }

        if (is_array($value)) {
            // Pole hodnot dava zmysel iba so zoznamom: where('id', 'IN', [1, 2]) alebo where('id', [1, 2]).
            if ($operator === '=' || $operator === 'IN') {
                return $this->whereIn($column, $value, $boolean);
            }
            if ($operator === '!=' || $operator === '<>' || $operator === 'NOT IN') {
                return $this->addWhereList($column, $value, $boolean, true);
            }
            throw new \InvalidArgumentException("where() got an array for operator $operator. Use whereIn() for a list of values.");
        }

        if ($value instanceof QueryBuilder) {
            $subQuery = $value->getQuery();
            $this->queryParts['where'][] = "$boolean " . $this->sanitizeColumn($column) . " $operator (" . $subQuery['query'] . ")";
            $this->mergeBindings($subQuery, 'where');
            return $this;
        }

        if (is_object($value) && !method_exists($value, '__toString')) {
            throw new \InvalidArgumentException("where() got a value that cannot be bound.");
        }

        $this->queryParts['where'][] = "$boolean " . $this->sanitizeColumn($column) . " $operator ?";
        $this->addBindings([$value], 'where');
        return $this;
    }

    public function whereIn($column, $values, $boolean = 'AND') {
        $boolean = $this->normalizeBoolean($boolean);
        if ($values instanceof \Closure) {
            $subQueryBuilder = new QueryBuilder($this->databaser);
            $subQueryBuilder->queryParts['type'] = 'SELECT';
            $values($subQueryBuilder);
            $subQuery = $subQueryBuilder->getQuery();
            $this->queryParts['where'][] = "$boolean " . $this->sanitizeColumn($column) . " IN (" . $subQuery['query'] . ")";
            $this->mergeBindings($subQuery, 'where');
        } elseif ($values instanceof QueryBuilder) {
            $subQuery = $values->getQuery();
            $this->queryParts['where'][] = "$boolean " . $this->sanitizeColumn($column) . " IN (" . $subQuery['query'] . ")";
            $this->mergeBindings($subQuery, 'where');
        } else {
            $this->addWhereList($column, $values, $boolean, false);
        }
        return $this;
    }

    // IN (...) / NOT IN (...) so zoznamom hodnot.
    private function addWhereList($column, $values, $boolean, $not) {
        if (!is_array($values)) {
            throw new \InvalidArgumentException("whereIn() needs an array of values, a Closure or a QueryBuilder.");
        }
        $values = array_values($values);
        if (count($values) === 0) {
            // Prazdny zoznam: IN nevyhovuje ziadnemu riadku, NOT IN vyhovuje kazdemu. "IN ()" je syntakticka chyba.
            $this->queryParts['where'][] = $not ? "$boolean 1 = 1" : "$boolean 1 = 0";
            return $this;
        }
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->queryParts['where'][] = "$boolean " . $this->sanitizeColumn($column) . ($not ? " NOT IN" : " IN") . " ($placeholders)";
        $this->addBindings($values, 'where');
        return $this;
    }

    public function orWhereIn($column, $values) {
        return $this->whereIn($column, $values, 'OR');
    }

    public function whereBetween($column, array $values, $boolean = 'AND') {
        $boolean = $this->normalizeBoolean($boolean);
        $values = array_values($values);
        // BETWEEN potrebuje presne dve hranice. S jednou by sa druha bindovala ako NULL a podmienka by ticho nevratila nic.
        if (count($values) !== 2) {
            throw new \InvalidArgumentException("whereBetween() needs exactly two values.");
        }
        $this->queryParts['where'][] = "$boolean " . $this->sanitizeColumn($column) . " BETWEEN ? AND ?";
        $this->addBindings([$values[0], $values[1]], 'where');
        return $this;
    }

    public function orWhereBetween($column, array $values) {
        return $this->whereBetween($column, $values, 'OR');
    }

    public function whereNull($column, $boolean = 'AND') {
        $boolean = $this->normalizeBoolean($boolean);
        $this->queryParts['where'][] = "$boolean " . $this->sanitizeColumn($column) . " IS NULL";
        return $this;
    }

    public function whereNotNull($column, $boolean = 'AND') {
        $boolean = $this->normalizeBoolean($boolean);
        $this->queryParts['where'][] = "$boolean " . $this->sanitizeColumn($column) . " IS NOT NULL";
        return $this;
    }

    public function orWhereNull($column) {
        return $this->whereNull($column, 'OR');
    }

    public function orWhereNotNull($column) {
        return $this->whereNotNull($column, 'OR');
    }

    // JOIN
    public function join($table, $first, $operator = null, $second = null, $type = 'INNER') {
        // join('t', 'a.id', 'b.id') - operator vynechany, plati rovnost.
        if ($second === null && $operator !== null) {
            $second = $operator;
            $operator = '=';
        }
        $operator = $this->normalizeOperator($operator);
        $type = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $type)));
        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT', 'LEFT OUTER', 'RIGHT OUTER', 'FULL OUTER', 'CROSS'], true)) {
            throw new \InvalidArgumentException("Unsupported join type: $type.");
        }
        if ($table instanceof QueryBuilder) {
            $subQuery = $table->getQuery();
            $this->queryParts['join'][] = "$type JOIN (" . $subQuery['query'] . ") AS subquery ON " . $this->sanitizeColumn($first) . " $operator " . $this->sanitizeColumn($second);
            $this->mergeBindings($subQuery, 'join');
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
        $operator = $this->normalizeOperator($operator);
        if ($aggregate) {
            $aggregate = strtoupper($aggregate);
            if (!in_array($aggregate, ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'])) {
                throw new \Exception("Nepodporovaná agregátna funkcia: $aggregate.");
            }
            $havingCondition = "$aggregate(" . $this->sanitizeColumn($column) . ") $operator ?";
        } else {
            $havingCondition = $this->sanitizeColumn($column) . " $operator ?";
        }
        // Dalsiu podmienku pripajame cez AND, aby pocet otaznikov sedel s poctom bindingov.
        if (!empty($this->queryParts['having'])) {
            $this->queryParts['having'] .= " AND " . $havingCondition;
        } else {
            $this->queryParts['having'] = "HAVING " . $havingCondition;
        }
        $this->addBindings([$value], 'having');
        return $this;
    }

    // ORDER BY
    public function orderBy($column, $direction = 'ASC') {
        // Smer sa vklada priamo do SQL, preto povolujeme iba ASC a DESC.
        $direction = strtoupper(trim((string) $direction));
        if ($direction === '') {
            $direction = 'ASC';
        }
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            throw new \InvalidArgumentException("orderBy() direction must be ASC or DESC.");
        }
        $this->queryParts['orderBy'][] = $this->sanitizeColumns($column) . " " . $direction;
        return $this;
    }

    // LIMIT a OFFSET
    // Ukladame iba cisla. SQL aj bindingy pre LIMIT a OFFSET vznikaju az v getQuery(), takze opakovane
    // volanie limit() alebo offset() iba prepise hodnotu a poradie volani nema vplyv na dotaz.
    public function limit($limit) {
        // limit('10') z requestu sa musi bindovat ako cislo, inak vznikne LIMIT '10'.
        $limit = (int) $limit;
        if ($limit < 0) {
            $limit = 0;
        }
        $this->queryParts['limit_value'] = $limit;
        return $this;
    }

    public function offset($offset) {
        $offset = (int) $offset;
        if ($offset < 0) {
            $offset = 0;
        }
        $this->queryParts['offset_value'] = $offset;
        return $this;
    }

    public function resetLimitOffset() {
        unset($this->queryParts['limit']);
        unset($this->queryParts['limit_value']);
        unset($this->queryParts['offset']);
        unset($this->queryParts['offset_value']);
        unset($this->queryParts['fetch']);
        return $this;
    }

    // Subquery
    public function subQuery() {
        return new self($this->databaser);
    }

    // Zostavenie dotazu
    public function getQuery() {
        $parts = $this->queryParts;
        $type = $parts['type'] ?? '';
        $query = '';
        // Poradie casti bindingov musi presne kopirovat poradie, v akom sa casti pridavaju do $query.
        $order = [];
        $extra = []; // bindingy pre LIMIT / OFFSET / TOP, ktore vznikaju az tu

        $hasLimit = isset($parts['limit_value']);
        $hasOffset = isset($parts['offset_value']);
        unset($parts['limit'], $parts['offset'], $parts['fetch']);

        if (!empty($parts['with'])) {
            $query .= "WITH " . implode(', ', $parts['with']) . " ";
            $order[] = 'with';
        }

        $joinDone = false;
        if ($type === 'TRUNCATE') {
            $query .= $parts['truncate'] ?? '';
        } elseif ($type === 'CREATE_TABLE') {
            $query .= $parts['create'] ?? '';
        } elseif ($type === 'ALTER_TABLE') {
            $query .= $parts['alter'] ?? '';
        } elseif ($type === 'DROP_TABLE') {
            $query .= $parts['drop'] ?? '';
        } elseif ($type === 'SELECT') {
            $select = $parts['select'] ?? '';
            // SQL Server: bez OFFSET sa pocet riadkov obmedzuje cez TOP hned za SELECT.
            if ($this->dbType === 'sqlsrv' && $hasLimit && !$hasOffset) {
                if ($select === '') {
                    throw new \Exception("LIMIT (TOP) can only be used with SELECT in SQL Server.");
                }
                $select = preg_replace('/^SELECT( DISTINCT)?/', 'SELECT$1 TOP (?)', $select, 1);
                $extra['top'] = [$parts['limit_value']];
                $order[] = 'top';
            }
            $from = $parts['from'] ?? '';
            $query .= $select . ($select && $from ? " " : "") . $from;
        } elseif ($type === 'INSERT') {
            $query .= $parts['insert'] ?? '';
            $order[] = 'insert';
        } elseif ($type === 'UPDATE') {
            $query .= $parts['update'] ?? '';
            // MySQL chce JOIN medzi nazvom tabulky a SET.
            if ($this->dbType === 'mysql' && !empty($parts['join'])) {
                $query .= " " . implode(" ", $parts['join']);
                $order[] = 'join';
                $joinDone = true;
            }
            $query .= " " . ($parts['set'] ?? '');
            $order[] = 'set';
        } elseif ($type === 'DELETE') {
            if (isset($parts['delete'])) {
                $query .= $parts['delete'];
            } elseif (isset($parts['from'])) {
                // delete()->from('tabulka')
                $query .= "DELETE " . $parts['from'];
            }
        } elseif ($type === 'RAW') {
            $query .= $parts['raw'] ?? '';
            $order[] = 'raw';
        }

        if (!$joinDone && !empty($parts['join'])) {
            $query .= " " . implode(" ", $parts['join']);
            $order[] = 'join';
        }

        if (!empty($parts['where'])) {
            $whereParts = $parts['where'];
            $firstWhere = array_shift($whereParts);
            // ltrim s druhym parametrom neodstranuje slovo AND/OR ale vsetky znaky A,N,D,O,R a medzeru zlava.
            // where('DATE(created)', ...) by potom skoncilo ako "WHERE TE(created)". Odstranujeme iba uvodnu spojku.
            $query .= " WHERE " . preg_replace('/^(AND|OR)\s+/', '', ltrim($firstWhere));
            if (!empty($whereParts)) {
                $query .= " " . implode(" ", $whereParts);
            }
            $order[] = 'where';
        }

        if (!empty($parts['groupBy'])) {
            $query .= " " . $parts['groupBy'];
        }

        if (!empty($parts['having'])) {
            $query .= " " . $parts['having'];
            $order[] = 'having';
        }

        if (!empty($parts['orderBy'])) {
            $query .= " ORDER BY " . implode(", ", $parts['orderBy']);
        }

        if ($this->dbType === 'sqlsrv') {
            if ($hasOffset) {
                $parts['offset'] = "OFFSET ? ROWS";
                $query .= " " . $parts['offset'];
                $extra['offset'] = [$parts['offset_value']];
                $order[] = 'offset';
                if ($hasLimit) {
                    $parts['fetch'] = "FETCH NEXT ? ROWS ONLY";
                    $query .= " " . $parts['fetch'];
                    $extra['limit'] = [$parts['limit_value']];
                    $order[] = 'limit';
                }
            }
        } elseif ($this->dbType === 'oci') {
            if ($hasOffset) {
                $parts['offset'] = "OFFSET ? ROWS";
                $query .= " " . $parts['offset'];
                $extra['offset'] = [$parts['offset_value']];
                $order[] = 'offset';
            }
            if ($hasLimit) {
                $parts['limit'] = "FETCH FIRST ? ROWS ONLY";
                $query .= " " . $parts['limit'];
                $extra['limit'] = [$parts['limit_value']];
                $order[] = 'limit';
            }
        } else {
            if ($hasLimit) {
                $parts['limit'] = "LIMIT ?";
                $query .= " " . $parts['limit'];
                $extra['limit'] = [$parts['limit_value']];
                $order[] = 'limit';
            } elseif ($hasOffset && $this->dbType !== 'pgsql') {
                // MySQL a SQLite nepoznaju OFFSET bez LIMIT. Pouzijeme "bez obmedzenia".
                $parts['limit'] = $this->dbType === 'sqlite' ? "LIMIT -1" : "LIMIT 18446744073709551615";
                $query .= " " . $parts['limit'];
            }
            if ($hasOffset) {
                $parts['offset'] = "OFFSET ?";
                $query .= " " . $parts['offset'];
                $extra['offset'] = [$parts['offset_value']];
                $order[] = 'offset';
            }
        }

        if (!empty($parts['union'])) {
            $query .= " " . implode(" ", $parts['union']);
            $order[] = 'union';
        }

        $bindings = [];
        $types = '';
        foreach ($order as $bucket) {
            if (isset($extra[$bucket])) {
                foreach ($extra[$bucket] as $number) {
                    $bindings[] = (int) $number;
                    $types .= 'i';
                }
                continue;
            }
            foreach ($this->bindingParts[$bucket] ?? [] as $item) {
                $bindings[] = $item[0];
                $types .= $item[1];
            }
        }

        return [
            'query' => $query,
            'bindings' => $bindings,
            'types' => $types,
            'queryParts' => $parts
        ];
    }

    // Pomocné metódy
    private function addBindings(array $values, $bucket) {
        foreach ($values as $value) {
            if ($value instanceof \Closure) {
                $subQueryBuilder = new QueryBuilder($this->databaser);
                $value($subQueryBuilder);
                $subQuery = $subQueryBuilder->getQuery();
                $this->mergeBindings($subQuery, $bucket);
            } else {
                $this->bindingParts[$bucket][] = [$this->sanitizeValue($value), $this->getBindingType($value)];
            }
        }
    }

    // Pripoji bindingy hotoveho poddotazu (vysledok getQuery()) do danej casti.
    private function mergeBindings(array $subQuery, $bucket) {
        $values = array_values($subQuery['bindings'] ?? []);
        $types = (string) ($subQuery['types'] ?? '');
        foreach ($values as $index => $value) {
            $this->bindingParts[$bucket][] = [$value, isset($types[$index]) ? $types[$index] : $this->getBindingType($value)];
        }
    }

    // Pripoji uz pripravene polozky [hodnota, typ] do danej casti.
    private function mergeBindingList(array $items, $bucket) {
        foreach ($items as $item) {
            $this->bindingParts[$bucket][] = $item;
        }
    }

    private function getBindingType($value) {
        if (is_int($value)) return 'i';
        if (is_float($value)) return 'd';
        if (is_string($value)) return 's';
        return 's'; // Default pre null alebo iné
    }

    // Spojka medzi podmienkami sa vklada priamo do SQL, preto povolujeme iba AND a OR.
    private function normalizeBoolean($boolean) {
        $boolean = strtoupper(trim((string) $boolean));
        if ($boolean !== 'AND' && $boolean !== 'OR') {
            throw new \InvalidArgumentException("Condition must be joined with AND or OR.");
        }
        return $boolean;
    }

    // Operator sa vklada priamo do SQL, preto musi byt zo zoznamu povolenych.
    private function normalizeOperator($operator) {
        if (!is_string($operator)) {
            throw new \InvalidArgumentException("Operator must be a string.");
        }
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $operator)));
        if (!in_array($normalized, self::$allowedOperators, true)) {
            throw new \InvalidArgumentException("Unsupported operator: $operator");
        }
        return $normalized;
    }

    private function sanitizeColumns($columns) {
        if (is_array($columns)) {
            $sanitized = array_map(function ($column) {
                if (!$this->isValidColumn($column)) {
                    throw new \InvalidArgumentException("Invalid column name or expression: $column");
                }
                return $this->sanitizeColumn($column);
            }, $columns);
            return implode(', ', $sanitized);
        }
        if (!$this->isValidColumn($columns)) {
            throw new \InvalidArgumentException("Invalid column name or expression: $columns");
        }
        return $this->sanitizeColumn($columns);
    }

    private function sanitizeColumn($column) {
        if (!is_string($column) && !is_int($column) && !is_float($column)) {
            throw new \InvalidArgumentException("Column name must be a string.");
        }
        $column = (string) $column;

        // Keep SQL expressions and asterisk unchanged
        if ($this->isSqlExpression($column)) {
            return $column;
        }

        // Znak, ktorym sa nazov uzatvara do uvodzoviek, v nazve zdvojime. Nazov tak nemoze uvodzovky ukoncit
        // a pokracovat vlastnym SQL.
        if ($this->dbType === 'sqlsrv') {
            $open = '[';
            $close = ']';
            $escapedClose = ']]';
        } elseif ($this->dbType === 'pgsql' || $this->dbType === 'oci') {
            $open = '"';
            $close = '"';
            $escapedClose = '""';
        } else {
            $open = '`';
            $close = '`';
            $escapedClose = '``';
        }

        // Split qualified name (e.g. table.column)
        $parts = explode('.', $column);
        $sanitizedParts = array_map(function ($part) {
            $part = trim($part);
            if ($part === '') {
                throw new \InvalidArgumentException("Empty column or table name part");
            }
            return $part;
        }, $parts);

        // 't.*' - hviezdicku na konci nechavame bez uvodzoviek, inak by vzniklo `t`.`*`.
        $trailingStar = '';
        if (count($sanitizedParts) > 1 && end($sanitizedParts) === '*') {
            array_pop($sanitizedParts);
            $trailingStar = '.*';
        }

        $quoted = array_map(function ($part) use ($open, $close, $escapedClose) {
            return $open . str_replace($close, $escapedClose, $part) . $close;
        }, $sanitizedParts);

        return implode('.', $quoted) . $trailingStar;
    }

    private function isSqlExpression($column) {
        // Allow asterisk, SQL functions, aliases and literal values
        return $column === '*' ||
            preg_match('/^[A-Z]+\(\*\)$/i', $column) || // Napr. COUNT(*)
            preg_match('/^[A-Z]+\(.*\)$/i', $column) || // Napr. SUM(column)
            preg_match('/.*\s+(as|AS)\s+\w+/i', $column) || // column AS alias (case insensitive)
            strpos($column, ' AS ') !== false || // Maintain backward compatibility
            is_numeric($column) || // Numeric literals, e.g. 1, 42.5
            in_array(strtoupper($column), ['CURRENT_TIMESTAMP', 'NULL', 'TRUE', 'FALSE']); // SQL keywords
    }

    private function isValidColumn($column) {
        // Základná validácia vstupu
        if (empty($column) || !is_string($column)) {
            return false;
        }

        // Allow SQL expressions
        if ($this->isSqlExpression($column)) {
            return true;
        }

        // Validate column names (allowed: a-z, A-Z, 0-9, _, spaces, special characters, dot)
        return preg_match('/^[a-zA-Z0-9_\s\.\-@#$%*]+$/i', $column) &&
               strpos($column, ';') === false &&
               strpos($column, '--') === false &&
               strpos($column, '/*') === false;
    }

    private function sanitizeTable($table) {
        // Split into table name and alias (e.g. "users u" -> ["users", "u"])
        $parts = preg_split('/\s+/', trim($table), 2);
        $tableName = $parts[0];
        $alias = $parts[1] ?? null;

        // "users AS u" - slovo AS nie je sucastou aliasu.
        if ($alias !== null) {
            $alias = preg_replace('/^AS\s+/i', '', trim($alias));
        }

        // Process table name (can contain schema.table)
        $tableParts = explode('.', $tableName);
        $sanitizedParts = array_map(function ($part) {
            // Sanitize each part (allowed: a-z, A-Z, 0-9, _)
            $part = preg_replace('/[^a-zA-Z0-9_]/', '', trim($part));
            if (empty($part)) {
                throw new \InvalidArgumentException("Empty table or schema name part");
            }
            return $part;
        }, $tableParts);

        // Quote table name based on database type
        if ($this->dbType === 'sqlsrv') {
            $sanitizedTable = '[' . implode('].[', $sanitizedParts) . ']';
        } elseif ($this->dbType === 'pgsql' || $this->dbType === 'oci') {
            $sanitizedTable = '"' . implode('"."', $sanitizedParts) . '"';
        } else {
            $sanitizedTable = '`' . implode('`.`', $sanitizedParts) . '`';
        }

        // Add alias if exists (without quoting)
        if ($alias) {
            $sanitizedAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
            if ($sanitizedAlias !== '') {
                $sanitizedTable .= " {$sanitizedAlias}";
            }
        }

        return $sanitizedTable;
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
        // Pole sa neda bindovat ako jedna hodnota. (string) by z neho urobil text "Array".
        if (is_array($value)) {
            throw new \InvalidArgumentException("An array cannot be bound as a single value.");
        }
        return (string) $value;
    }

    public function raw($sql, array $bindings = []) {
        $this->queryParts['type'] = 'RAW';
        // Pomenovane parametre hladame iba mimo textovych literalov a komentarov.
        // '10:30' alebo 'a:b' v SQL preto nie je parameter a :: (pretypovanie v PostgreSQL) tiez nie.
        $named = $this->findNamedParams($sql);
        $hasNamedParams = count($named) > 0;
        $hasQuestionMarks = strpos($sql, '?') !== false;

        if ($hasNamedParams && $hasQuestionMarks) {
            throw new \Exception("RAW SQL cannot combine named variables (:name) and question marks (?) at the same time.");
        }

        $this->bindingParts['raw'] = [];

        if ($hasNamedParams) {
            // Nahradzame od konca, aby sa pozicie predchadzajucich parametrov neposunuli.
            $ordered = [];
            foreach ($named as $param) {
                $name = $param[1];
                if (!array_key_exists($name, $bindings)) {
                    throw new \Exception("Chýba hodnota pre parameter :$name v bindings.");
                }
                $ordered[] = [$bindings[$name], $this->getBindingType($bindings[$name])];
            }
            for ($i = count($named) - 1; $i >= 0; $i--) {
                $sql = substr($sql, 0, $named[$i][0]) . '?' . substr($sql, $named[$i][0] + strlen($named[$i][1]) + 1);
            }
            $this->queryParts['raw'] = $sql;
            $this->bindingParts['raw'] = $ordered;
        } else {
            $questionMarkCount = substr_count($sql, '?');
            if ($questionMarkCount > 0 && count($bindings) !== $questionMarkCount) {
                throw new \Exception("Number of question marks (?) in SQL ($questionMarkCount) doesn't match number of values in bindings (" . count($bindings) . ").");
            }
            $this->queryParts['raw'] = $sql;
            foreach ($bindings as $value) {
                $this->bindingParts['raw'][] = [$value, $this->getBindingType($value)];
            }
        }
        return $this;
    }

    /*
        Najde pomenovane parametre (:nazov) v SQL a vrati ich ako zoznam [pozicia dvojbodky, nazov].
        Preskakuje textove literaly ('...', "..."), nazvy v spatnych uvodzovkach (`...`) a komentare
        (-- ..., # ..., a blokove komentare), pretoze dvojbodka v nich nie je parameter.
    */
    private function findNamedParams($sql) {
        $found = [];
        $length = strlen($sql);
        $i = 0;
        while ($i < $length) {
            $char = $sql[$i];

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $i++;
                while ($i < $length) {
                    if ($sql[$i] === '\\' && $quote !== '`') {
                        $i += 2;
                        continue;
                    }
                    if ($sql[$i] === $quote) {
                        // Zdvojena uvodzovka je sucastou literalu.
                        if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                            $i += 2;
                            continue;
                        }
                        break;
                    }
                    $i++;
                }
                $i++;
                continue;
            }

            if ($char === '-' && $i + 1 < $length && $sql[$i + 1] === '-') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end + 1;
                continue;
            }

            if ($char === '#') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end + 1;
                continue;
            }

            if ($char === '/' && $i + 1 < $length && $sql[$i + 1] === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $length : $end + 2;
                continue;
            }

            if ($char === ':') {
                // "::" je pretypovanie, nie parameter.
                if ($i + 1 < $length && $sql[$i + 1] === ':') {
                    $i += 2;
                    continue;
                }
                $previous = $i > 0 ? $sql[$i - 1] : '';
                if ($previous !== ':' && !preg_match('/[A-Za-z0-9_]/', $previous) && preg_match('/\G:([a-zA-Z0-9_]+)/', $sql, $match, 0, $i)) {
                    $found[] = [$i, $match[1]];
                    $i += strlen($match[0]);
                    continue;
                }
            }

            $i++;
        }
        return $found;
    }

    public function createTable($table, callable $callback) {
        $this->queryParts['ifNotExistUsed'] = false;
        $this->queryParts['type'] = 'CREATE_TABLE';
        $this->queryParts['table'] = $this->sanitizeTable($table);
        $schema = new SchemaBuilder($this->databaser);
        $callback($schema);
        $this->queryParts['create'] = "CREATE TABLE {$this->queryParts['table']}". $schema->getDefinition();
        return $this;
    }

    public function createTableIfNotExist($table, callable $callback) {
        $this->queryParts['ifNotExistUsed'] = true;
        if (DB::schemaBuilder()->tableExists($table)) {
            return $this;
        } else {
            $this->queryParts['type'] = 'CREATE_TABLE';
            $this->queryParts['table'] = $this->sanitizeTable($table);
            $schema = new SchemaBuilder($this->databaser);
            $callback($schema);
            $this->queryParts['create'] = "CREATE TABLE {$this->queryParts['table']}". $schema->getDefinition();
            return $this;
        }
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
