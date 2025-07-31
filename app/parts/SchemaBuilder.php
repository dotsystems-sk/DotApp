<?php

namespace Dotsystems\App\Parts;

use InvalidArgumentException;

/**
 * Interface for database-specific schema adapters.
 */
interface SchemaAdapter {
    public function formatColumn(ColumnDefinition $column);
    public function formatIndex(array $columns, $unique, $name, $comment = null);
    public function formatForeignKey($column, $references, $on, $onDelete, $onUpdate, $name, $comment = null);
    public function tableExists($table);
    public function columnExists($table, $column);
    public function indexExists($table, $indexName);
    public function foreignKeyExists($table, $constraintName);
    public function formatAlterAction($action);
    public function getAutoIncrementClause($type);
    public function getCharsetAndCollationClause($charset, $collation);
}

/**
 * Represents a column definition with chainable properties.
 */
class ColumnDefinition {
    private $schemaBuilder;
    private $name;
    private $type;
    private $properties = [];

    public function __construct(SchemaBuilder $schemaBuilder, $name, $type) {
        $this->schemaBuilder = $schemaBuilder;
        $this->name = $name;
        $this->type = strtoupper($type);
        $this->properties['nullable'] = false;
    }

    public function nullable($nullable = true) {
        $this->properties['nullable'] = $nullable;
        return $this;
    }

    public function default($value) {
        $this->properties['default'] = $value;
        return $this;
    }

    public function comment($comment) {
        $this->properties['comment'] = $comment;
        return $this;
    }

    public function autoIncrement() {
        if (!in_array($this->type, ['INT', 'BIGINT', 'INTEGER'])) {
            throw new InvalidArgumentException("Auto-increment is only supported for INTEGER or BIGINT types.");
        }
        $this->properties['autoIncrement'] = true;
        return $this;
    }

    public function unsigned() {
        if ($this->schemaBuilder->getDbType() !== 'mysql') {
            throw new InvalidArgumentException("UNSIGNED is only supported in MySQL.");
        }
        $this->properties['unsigned'] = true;
        return $this;
    }

    public function length($length) {
        if (!is_int($length) || $length <= 0) {
            throw new InvalidArgumentException("Column length must be a positive integer.");
        }
        $this->properties['length'] = $length;
        return $this;
    }

    public function precision($precision) {
        if (!is_int($precision) || $precision <= 0) {
            throw new InvalidArgumentException("Precision must be a positive integer.");
        }
        $this->properties['precision'] = $precision;
        return $this;
    }

    public function scale($scale) {
        if (!is_int($scale) || $scale < 0 || (isset($this->properties['precision']) && $scale > $this->properties['precision'])) {
            throw new InvalidArgumentException("Invalid scale for DECIMAL.");
        }
        $this->properties['scale'] = $scale;
        return $this;
    }

    public function onUpdateCurrentTimestamp() {
        if ($this->schemaBuilder->getDbType() === 'mysql' && $this->type === 'TIMESTAMP') {
            $this->properties['onUpdateCurrent'] = true;
        }
        return $this;
    }

    public function getName() {
        return $this->name;
    }

    public function getType() {
        return $this->type;
    }

    public function getProperties() {
        return $this->properties;
    }
}

/**
 * Represents a constraint definition with chainable properties.
 */
/**
 * Represents a constraint definition with chainable properties.
 */
class ConstraintDefinition {
    private $schemaBuilder;
    private $type;
    private $name;
    private $properties = [];
    private $foreignKeyData = [];
    private $dbType;
    private $isManualName = false; // Track if name was manually set

    public function __construct(SchemaBuilder $schemaBuilder, $type, $name, $column = null) {
        $this->schemaBuilder = $schemaBuilder;
        $this->type = strtoupper($type);
        $this->name = $name;
        $this->dbType = $schemaBuilder->getDbType();
        $this->isManualName = ($name !== null); // Mark if name was manually provided
        if ($column !== null) {
            $this->foreignKeyData['column'] = $this->schemaBuilder->sanitizeName($column);
        }
    }

    public function comment($comment) {
        if ($this->dbType === 'sqlite' && $this->type !== 'CHECK') {
            throw new InvalidArgumentException("SQLite only supports comments on CHECK constraints.");
        }
        $this->properties['comment'] = $comment;
        return $this;
    }

    public function references($column) {
        if ($this->type !== 'FOREIGN_KEY') {
            throw new InvalidArgumentException("The 'references' method is only valid for FOREIGN_KEY constraints.");
        }
        if ($this->dbType === 'sqlite' && !$this->isForeignKeySupported()) {
            throw new InvalidArgumentException("SQLite has limited support for FOREIGN KEY constraints; ensure they are enabled.");
        }
        $this->foreignKeyData['references'] = $this->schemaBuilder->sanitizeName($column);
        return $this;
    }

    public function on($table) {
        if ($this->type !== 'FOREIGN_KEY') {
            throw new InvalidArgumentException("The 'on' method is only valid for FOREIGN_KEY constraints.");
        }
        if ($this->dbType === 'sqlite' && !$this->isForeignKeySupported()) {
            throw new InvalidArgumentException("SQLite has limited support for FOREIGN KEY constraints; ensure they are enabled.");
        }
        $this->foreignKeyData['on'] = $this->schemaBuilder->sanitizeName($table);
        // Only update name if it wasn't manually set
        if (!$this->isManualName) {
            $this->name = $this->generateForeignKeyName();
        }
        return $this;
    }

    public function onDelete($action) {
        if ($this->type !== 'FOREIGN_KEY') {
            throw new InvalidArgumentException("The 'onDelete' method is only valid for FOREIGN_KEY constraints.");
        }
        if ($this->dbType === 'sqlite' && !$this->isForeignKeySupported()) {
            throw new InvalidArgumentException("SQLite has limited support for FOREIGN KEY constraints; ensure they are enabled.");
        }
        $validActions = $this->getValidActions();
        if (!in_array(strtoupper($action), $validActions)) {
            throw new InvalidArgumentException("Invalid ON DELETE action for {$this->dbType}: $action. Valid actions: " . implode(', ', $validActions));
        }
        $this->foreignKeyData['onDelete'] = strtoupper($action);
        return $this;
    }

    public function onUpdate($action) {
        if ($this->type !== 'FOREIGN_KEY') {
            throw new InvalidArgumentException("The 'onUpdate' method is only valid for FOREIGN_KEY constraints.");
        }
        if ($this->dbType === 'sqlite' && !$this->isForeignKeySupported()) {
            throw new InvalidArgumentException("SQLite has limited support for FOREIGN KEY constraints; ensure they are enabled.");
        }
        if ($this->dbType === 'oci' && strtoupper($action) !== 'NO ACTION') {
            throw new InvalidArgumentException("Oracle only supports 'NO ACTION' for ON UPDATE in FOREIGN KEY constraints.");
        }
        $validActions = $this->getValidActions();
        if (!in_array(strtoupper($action), $validActions)) {
            throw new InvalidArgumentException("Invalid ON UPDATE action for {$this->dbType}: $action. Valid actions: " . implode(', ', $validActions));
        }
        $this->foreignKeyData['onUpdate'] = strtoupper($action);
        return $this;
    }

    public function getType() {
        return $this->type;
    }

    public function getName() {
        return $this->name;
    }

    public function setName($name) {
        $this->name = $this->schemaBuilder->sanitizeName($name);
        $this->isManualName = true; // Mark name as manually set
        return $this;
    }

    public function getProperties() {
        return $this->properties;
    }

    public function getForeignKeyData() {
        return $this->foreignKeyData;
    }

    private function getValidActions() {
        switch ($this->dbType) {
            case 'mysql':
            case 'pgsql':
            case 'sqlsrv':
                return ['CASCADE', 'RESTRICT', 'SET NULL', 'NO ACTION'];
            case 'sqlite':
                return ['CASCADE', 'RESTRICT', 'SET NULL', 'NO ACTION'];
            case 'oci':
                return ['CASCADE', 'SET NULL', 'NO ACTION'];
            default:
                return ['NO ACTION'];
        }
    }

    private function isForeignKeySupported() {
        return true;
    }

    private function generateForeignKeyName() {
        if (isset($this->foreignKeyData['on'], $this->foreignKeyData['column'])) {
            return "fk_{$this->foreignKeyData['column']}_{$this->foreignKeyData['on']}";
        }
        return $this->name;
    }
}

/**
 * MySQL-specific schema adapter.
 */
class MySqlSchemaAdapter implements SchemaAdapter {
    public function formatColumn(ColumnDefinition $column) {
        $name = $column->getName();
        $type = $column->getType();
        $props = $column->getProperties();
        $length = isset($props['length']) ? "({$props['length']})" : '';
        if ($type === 'DECIMAL' && isset($props['precision'], $props['scale'])) {
            $length = "({$props['precision']},{$props['scale']})";
        }
        $null = $props['nullable'] ? 'NULL' : 'NOT NULL';
        $default = isset($props['default']) ? " DEFAULT " . $this->sanitizeDefault($props['default'], $type) : '';
        $comment = isset($props['comment']) ? " COMMENT '" . str_replace("'", "''", $props['comment']) . "'" : '';
        $unsigned = isset($props['unsigned']) && $props['unsigned'] ? ' UNSIGNED' : '';
        $autoIncrement = isset($props['autoIncrement']) && $props['autoIncrement'] ? ' AUTO_INCREMENT' : '';
        $onUpdate = isset($props['onUpdateCurrent']) && $props['onUpdateCurrent'] ? ' ON UPDATE CURRENT_TIMESTAMP' : '';
        return "`$name` $type$length$unsigned $null$default$autoIncrement$onUpdate$comment";
    }

    public function formatIndex(array $columns, $unique, $name, $comment = null) {
        $indexType = $unique ? 'UNIQUE INDEX' : 'INDEX';
        $columnList = '`' . implode('`,`', $columns) . '`';
        $commentClause = $comment ? " COMMENT '" . str_replace("'", "''", $comment) . "'" : '';
        return "$indexType `$name` ($columnList)$commentClause";
    }

    public function formatForeignKey($column, $references, $on, $onDelete, $onUpdate, $name, $comment = null) {
        $fk = "CONSTRAINT `$name` FOREIGN KEY (`$column`) REFERENCES `$on` (`$references`) ON DELETE $onDelete ON UPDATE $onUpdate";
        $commentClause = $comment ? " COMMENT '" . str_replace("'", "''", $comment) . "'" : '';
        return $fk . $commentClause;
    }

    public function tableExists($table) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table) {
                $qb->select('1')
                   ->from($table)
                   ->limit(1); // Minimalizujeme dopad dotazu
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = true;
                },
                function ($error, $db, $debug) {
                    $result = false;
                }
            );

        return $result;
    }

    public function columnExists($table, $column) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table, $column) {
                $qb->select('1')
                   ->from('information_schema.columns')
                   ->where('table_name', '=', $table)
                   ->where('column_name', '=', $column);
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking column existence: {$error['error']} (code: {$error['errno']})");
                }
            );

        return $result;
    }

    public function indexExists($table, $indexName) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table, $indexName) {
                $qb->select('1')
                   ->from('information_schema.statistics')
                   ->where('table_name', '=', $table)
                   ->where('index_name', '=', $indexName);
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking index existence: {$error['error']} (code: {$error['errno']})");
                }
            );

        return $result;
    }

    public function foreignKeyExists($table, $constraintName) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table, $constraintName) {
                $qb->select('1')
                   ->from('information_schema.table_constraints')
                   ->where('table_name', '=', $table)
                   ->where('constraint_name', '=', $constraintName)
                   ->where('constraint_type', '=', 'FOREIGN KEY');
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking foreign key existence: {$error['error']} (code: {$error['errno']})");
                }
            );

        return $result;
    }

    public function formatAlterAction($action) {
        return $action;
    }

    public function getAutoIncrementClause($type) {
        return ' AUTO_INCREMENT';
    }

    public function getCharsetAndCollationClause($charset, $collation) {
        $charsetClause = $charset ? " CHARACTER SET $charset" : '';
        $collationClause = $collation ? " COLLATE $collation" : '';
        return $charsetClause . $collationClause;
    }

    private function sanitizeDefault($value, $type) {
        if ($value === null) {
            return 'NULL';
        }
        $type = strtolower($type);
        switch ($type) {
            case 'varchar':
            case 'text':
            case 'json':
                return "'" . str_replace("'", "''", (string)$value) . "'";
            case 'int':
            case 'bigint':
            case 'tinyint':
            case 'decimal':
            case 'float':
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException("Default value must be numeric for type $type.");
                }
                return (string)$value;
            case 'date':
            case 'datetime':
            case 'timestamp':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value) && $value !== 'CURRENT_TIMESTAMP') {
                    throw new InvalidArgumentException("Default value for $type must be in YYYY-MM-DD or YYYY-MM-DD HH:MM:SS format, or CURRENT_TIMESTAMP.");
                }
                return $value === 'CURRENT_TIMESTAMP' ? 'CURRENT_TIMESTAMP' : "'$value'";
            default:
                return (string)$value;
        }
    }
}

/**
 * PostgreSQL-specific schema adapter.
 */
class PgSqlSchemaAdapter implements SchemaAdapter {
    public function formatColumn(ColumnDefinition $column) {
        $name = $column->getName();
        $type = $this->convertType($column->getType());
        $props = $column->getProperties();
        $length = isset($props['length']) ? "({$props['length']})" : '';
        if ($type === 'NUMERIC' && isset($props['precision'], $props['scale'])) {
            $length = "({$props['precision']},{$props['scale']})";
        }
        $null = $props['nullable'] ? 'NULL' : 'NOT NULL';
        $default = isset($props['default']) ? " DEFAULT " . $this->sanitizeDefault($props['default'], $type) : '';
        $autoIncrement = isset($props['autoIncrement']) && $props['autoIncrement'] ? ($type === 'BIGINT' ? ' BIGSERIAL' : ' SERIAL') : '';
        $comment = isset($props['comment']) ? " /* " . str_replace("'", "''", $props['comment']) . " */" : '';
        return "\"$name\" $type$length$autoIncrement $null$default$comment";
    }

    public function formatIndex(array $columns, $unique, $name, $comment = null) {
        $indexType = $unique ? 'UNIQUE' : 'INDEX';
        $columnList = '"' . implode('","', $columns) . '"';
        $commentClause = $comment ? " /* " . str_replace("'", "''", $comment) . " */" : '';
        return "$indexType \"$name\" ON ($columnList)$commentClause";
    }

    public function formatForeignKey($column, $references, $on, $onDelete, $onUpdate, $name, $comment = null) {
        $fk = "CONSTRAINT \"$name\" FOREIGN KEY (\"$column\") REFERENCES \"$on\" (\"$references\") ON DELETE $onDelete ON UPDATE $onUpdate";
        $commentClause = $comment ? " /* " . str_replace("'", "''", $comment) . " */" : '';
        return $fk . $commentClause;
    }

    public function tableExists($table) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table) {
                $qb->select('1')
                   ->from($table)
                   ->limit(1); // Minimalizujeme dopad dotazu
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = true;
                },
                function ($error, $db, $debug) {
                    $result = false;
                }
            );

        return $result;
    }

    public function columnExists($table, $column) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table, $column) {
                $qb->select('1')
                   ->from('information_schema.columns')
                   ->where('table_schema', '=', 'public')
                   ->where('table_name', '=', $table)
                   ->where('column_name', '=', $column);
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking column existence: {$error['error']} (code: {$error['errno']})");
                }
            );

        return $result;
    }

    public function indexExists($table, $indexName) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table, $indexName) {
                $qb->select('1')
                   ->from('pg_indexes')
                   ->where('schemaname', '=', 'public')
                   ->where('tablename', '=', $table)
                   ->where('indexname', '=', $indexName);
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking index existence: {$error['error']} (code: {$error['errno']})");
                }
            );

        return $result;
    }

    public function foreignKeyExists($table, $constraintName) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table, $constraintName) {
                $qb->select('1')
                   ->from('information_schema.table_constraints')
                   ->where('table_schema', '=', 'public')
                   ->where('table_name', '=', $table)
                   ->where('constraint_name', '=', $constraintName)
                   ->where('constraint_type', '=', 'FOREIGN KEY');
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking foreign key existence: {$error['error']} (code: {$error['errno']})");
                }
            );

        return $result;
    }

    public function formatAlterAction($action) {
        return $action;
    }

    public function getAutoIncrementClause($type) {
        return $type === 'BIGINT' ? ' BIGSERIAL' : ' SERIAL';
    }

    public function getCharsetAndCollationClause($charset, $collation) {
        // PostgreSQL supports encoding at the database level, not table level
        return $charset ? " ENCODING '$charset'" : '';
    }

    private function convertType($type) {
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
            case 'FLOAT':
                return 'REAL';
            case 'TIMESTAMP':
                return 'TIMESTAMP';
            case 'DATETIME':
                return 'TIMESTAMP';
            default:
                return $type;
        }
    }

    private function sanitizeDefault($value, $type) {
        if ($value === null) {
            return 'NULL';
        }
        $type = strtolower($type);
        switch ($type) {
            case 'varchar':
            case 'text':
            case 'jsonb':
                return "'" . str_replace("'", "''", (string)$value) . "'";
            case 'integer':
            case 'bigint':
            case 'smallint':
            case 'numeric':
            case 'real':
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException("Default value must be numeric for type $type.");
                }
                return (string)$value;
            case 'date':
            case 'timestamp':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value) && $value !== 'CURRENT_TIMESTAMP') {
                    throw new InvalidArgumentException("Default value for $type must be in YYYY-MM-DD or YYYY-MM-DD HH:MM:SS format, or CURRENT_TIMESTAMP.");
                }
                return $value === 'CURRENT_TIMESTAMP' ? 'CURRENT_TIMESTAMP' : "'$value'";
            default:
                return (string)$value;
        }
    }
}

/**
 * SQLite-specific schema adapter.
 */
class SQLiteSchemaAdapter implements SchemaAdapter {
    public function formatColumn(ColumnDefinition $column) {
        $name = $column->getName();
        $type = $this->convertType($column->getType());
        $props = $column->getProperties();
        $null = $props['nullable'] ? 'NULL' : 'NOT NULL';
        $default = isset($props['default']) ? " DEFAULT " . $this->sanitizeDefault($props['default'], $type) : '';
        $autoIncrement = isset($props['autoIncrement']) && $props['autoIncrement'] ? ' AUTOINCREMENT' : '';
        return "`$name` $type $null$default$autoIncrement";
    }

    public function formatIndex(array $columns, $unique, $name, $comment = null) {
        $indexType = $unique ? 'UNIQUE INDEX' : 'INDEX';
        $columnList = '`' . implode('`,`', $columns) . '`';
        return "$indexType `$name` ON ($columnList)";
    }

    public function formatForeignKey($column, $references, $on, $onDelete, $onUpdate, $name, $comment = null) {
        return "FOREIGN KEY (`$column`) REFERENCES `$on` (`$references`) ON DELETE $onDelete ON UPDATE $onUpdate";
    }

    public function tableExists($table) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table) {
                $qb->select('1')
                   ->from($table)
                   ->limit(1); // Minimalizujeme dopad dotazu
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = true;
                },
                function ($error, $db, $debug) {
                    $result = false;
                }
            );

        return $result;
    }

    public function columnExists($table, $column) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table, $column) {
                $qb->select('1')
                   ->from('pragma_table_info', [$table])
                   ->where('name', '=', $column);
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking column existence: {$error['error']} (code: {$error['errno']})");
                }
            );

        return $result;
    }

    public function indexExists($table, $indexName) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table, $indexName) {
                $qb->select('1')
                   ->from('sqlite_master')
                   ->where('type', '=', 'index')
                   ->where('tbl_name', '=', $table)
                   ->where('name', '=', $indexName);
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking index existence: {$error['error']} (code: {$error['errno']})");
                }
            );

        return $result;
    }

    public function foreignKeyExists($table, $constraintName) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table) {
                $qb->select('sql')
                   ->from('sqlite_master')
                   ->where('type', '=', 'table')
                   ->where('tbl_name', '=', $table);
            })
            ->execute(
                function ($rows) use ($constraintName, &$result) {
                    foreach ($rows as $row) {
                        if (strpos($row['sql'], "CONSTRAINT \"$constraintName\" FOREIGN KEY") !== false) {
                            $result = true;
                            return;
                        }
                    }
                    $result = false;
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking foreign key existence: {$error['error']} (code: {$error['errno']})");
                }
            );

        return $result;
    }

    public function formatAlterAction($action) {
        throw new InvalidArgumentException("SQLite does not support direct ALTER TABLE operations like DROP COLUMN or DROP CONSTRAINT.");
    }

    public function getAutoIncrementClause($type) {
        if ($type !== 'INTEGER') {
            throw new InvalidArgumentException("SQLite only supports AUTOINCREMENT on INTEGER type.");
        }
        return ' AUTOINCREMENT';
    }

    public function getCharsetAndCollationClause($charset, $collation) {
        // SQLite does not support charset or collation at the table level
        return '';
    }

    private function convertType($type) {
        switch ($type) {
            case 'VARCHAR':
                return 'TEXT';
            case 'INT':
            case 'BIGINT':
            case 'TINYINT':
                return 'INTEGER';
            case 'DECIMAL':
            case 'FLOAT':
                return 'REAL';
            case 'TIMESTAMP':
            case 'DATETIME':
                return 'DATETIME';
            default:
                return $type;
        }
    }

    private function sanitizeDefault($value, $type) {
        if ($value === null) {
            return 'NULL';
        }
        $type = strtolower($type);
        switch ($type) {
            case 'text':
                return "'" . str_replace("'", "''", (string)$value) . "'";
            case 'integer':
            case 'real':
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException("Default value must be numeric for type $type.");
                }
                return (string)$value;
            case 'datetime':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value)) {
                    throw new InvalidArgumentException("Default value for $type must be in YYYY-MM-DD or YYYY-MM-DD HH:MM:SS format.");
                }
                return "'$value'";
            default:
                return (string)$value;
        }
    }
}

/**
 * Oracle-specific schema adapter.
 */
class OracleSchemaAdapter implements SchemaAdapter {
    public function formatColumn(ColumnDefinition $column) {
        $name = $column->getName();
        $type = $this->convertType($column->getType());
        $props = $column->getProperties();
        $length = isset($props['length']) ? "({$props['length']})" : '';
        if ($type === 'NUMBER' && isset($props['precision'], $props['scale'])) {
            $length = "({$props['precision']},{$props['scale']})";
        }
        $null = $props['nullable'] ? 'NULL' : 'NOT NULL';
        $default = isset($props['default']) ? " DEFAULT " . $this->sanitizeDefault($props['default'], $type) : '';
        $comment = isset($props['comment']) ? " /* " . str_replace("'", "''", $props['comment']) . " */" : '';
        $autoIncrement = isset($props['autoIncrement']) && $props['autoIncrement'] ? ' GENERATED ALWAYS AS IDENTITY' : '';
        return "\"$name\" $type$length$autoIncrement $null$default$comment";
    }

    public function formatIndex(array $columns, $unique, $name, $comment = null) {
        $indexType = $unique ? 'UNIQUE INDEX' : 'INDEX';
        $columnList = '"' . implode('","', $columns) . '"';
        $commentClause = $comment ? " /* " . str_replace("'", "''", $comment) . " */" : '';
        return "$indexType \"$name\" ON ($columnList)$commentClause";
    }

    public function formatForeignKey($column, $references, $on, $onDelete, $onUpdate, $name, $comment = null) {
        $fk = "CONSTRAINT \"$name\" FOREIGN KEY (\"$column\") REFERENCES \"$on\" (\"$references\") ON DELETE $onDelete";
        $commentClause = $comment ? " /* " . str_replace("'", "''", $comment) . " */" : '';
        return $fk . $commentClause;
    }

    public function tableExists($table) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table) {
                $qb->select('1')
                   ->from($table)
                   ->limit(1); // Minimalizujeme dopad dotazu
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = true;
                },
                function ($error, $db, $debug) {
                    $result = false;
                }
            );

        return $result;
    }

    public function columnExists($table, $column) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table, $column) {
                $qb->select('1')
                   ->from('user_tab_columns')
                   ->where('table_name', '=', strtoupper($table))
                   ->where('column_name', '=', strtoupper($column));
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking column existence: {$error['error']} (code: {$error['errno']})");
                }
            );

        return $result;
    }

    public function indexExists($table, $indexName) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($indexName) {
                $qb->select('1')
                   ->from('user_indexes')
                   ->where('index_name', '=', strtoupper($indexName));
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking index existence: {$error['error']} (code: {$error['errno']})");
                }
            );

        return $result;
    }

    public function foreignKeyExists($table, $constraintName) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($constraintName) {
                $qb->select('1')
                   ->from('user_constraints')
                   ->where('constraint_name', '=', strtoupper($constraintName))
                   ->where('constraint_type', '=', 'R');
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking foreign key existence: {$error['error']} (code: {$error['errno']})");
                }
            );

        return $result;
    }

    public function formatAlterAction($action) {
        return $action;
    }

    public function getAutoIncrementClause($type) {
        return ' GENERATED ALWAYS AS IDENTITY';
    }

    public function getCharsetAndCollationClause($charset, $collation) {
        // Oracle supports character set at the database level
        return $charset ? " CHARACTER SET $charset" : '';
    }

    private function convertType($type) {
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
            case 'FLOAT':
                return 'BINARY_FLOAT';
            case 'TIMESTAMP':
            case 'DATETIME':
                return 'TIMESTAMP';
            default:
                return $type;
        }
    }

    private function sanitizeDefault($value, $type) {
        if ($value === null) {
            return 'NULL';
        }
        $type = strtolower($type);
        switch ($type) {
            case 'varchar2':
            case 'clob':
                return "'" . str_replace("'", "''", (string)$value) . "'";
            case 'number':
            case 'binary_float':
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException("Default value must be numeric for type $type.");
                }
                return (string)$value;
            case 'date':
            case 'timestamp':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value)) {
                    throw new InvalidArgumentException("Default value for $type must be in YYYY-MM-DD or YYYY-MM-DD HH:MM:SS format.");
                }
                return "'$value'";
            default:
                return (string)$value;
        }
    }
}

/**
 * SQL Server-specific schema adapter.
 */
class SqlSrvSchemaAdapter implements SchemaAdapter {
    public function formatColumn(ColumnDefinition $column) {
        $name = $column->getName();
        $type = $this->convertType($column->getType());
        $props = $column->getProperties();
        $length = isset($props['length']) ? "({$props['length']})" : '';
        if ($type === 'DECIMAL' && isset($props['precision'], $props['scale'])) {
            $length = "({$props['precision']},{$props['scale']})";
        }
        $null = $props['nullable'] ? 'NULL' : 'NOT NULL';
        $default = isset($props['default']) ? " DEFAULT " . $this->sanitizeDefault($props['default'], $type) : '';
        $autoIncrement = isset($props['autoIncrement']) && $props['autoIncrement'] ? ' IDENTITY(1,1)' : '';
        return "[$name] $type$length$autoIncrement $null$default";
    }

    public function formatIndex(array $columns, $unique, $name, $comment = null) {
        $columnList = '[' . implode('],[', $columns) . ']';
        if ($unique) {
            return "CONSTRAINT [$name] UNIQUE ($columnList)";
        } else {
            return "INDEX [$name] ($columnList)";
        }
    }

    public function formatForeignKey($column, $references, $on, $onDelete, $onUpdate, $name, $comment = null) {
        return "CONSTRAINT [$name] FOREIGN KEY ([$column]) REFERENCES [$on] ([$references]) ON DELETE $onDelete ON UPDATE $onUpdate";
    }

    public function tableExists($table) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table) {
                $qb->select('1')
                   ->from($table)
                   ->limit(1); // Minimalizujeme dopad dotazu
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = true;
                },
                function ($error, $db, $debug) {
                    $result = false;
                }
            );

        return $result;
    }

    public function columnExists($table, $column) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table, $column) {
                $qb->select('1')
                   ->from('information_schema.columns')
                   ->where('table_name', '=', $table)
                   ->where('column_name', '=', $column);
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking column existence: {$error['error']} (code: {$error['errno']})");
                }
            );

        return $result;
    }

    public function indexExists($table, $indexName) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table, $indexName) {
                $qb->select('1')
                   ->from('sys.indexes')
                   ->where('object_id', '=', 'OBJECT_ID(?)', [$table])
                   ->where('name', '=', $indexName);
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking index existence: {$error['error']} (code: {$error['errno']})");
                }
            );

        return $result;
    }

    public function foreignKeyExists($table, $constraintName) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table, $constraintName) {
                $qb->select('1')
                   ->from('information_schema.table_constraints')
                   ->where('table_name', '=', $table)
                   ->where('constraint_name', '=', $constraintName)
                   ->where('constraint_type', '=', 'FOREIGN KEY');
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking foreign key existence: {$error['error']} (code: {$error['errno']})");
                }
            );

        return $result;
    }

    public function formatAlterAction($action) {
        return $action;
    }

    public function getAutoIncrementClause($type) {
        return ' IDENTITY(1,1)';
    }

    public function getCharsetAndCollationClause($charset, $collation) {
        // SQL Server uses collation at the database or column level, not table level
        return '';
    }

    private function convertType($type) {
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
            case 'FLOAT':
                return 'FLOAT';
            case 'TIMESTAMP':
            case 'DATETIME':
                return 'DATETIME2';
            default:
                return $type;
        }
    }

    private function sanitizeDefault($value, $type) {
        if ($value === null) {
            return 'NULL';
        }
        $type = strtolower($type);
        switch ($type) {
            case 'nvarchar':
            case 'nvarchar(max)':
                return "'" . str_replace("'", "''", (string)$value) . "'";
            case 'int':
            case 'bigint':
            case 'tinyint':
            case 'decimal':
            case 'float':
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException("Default value must be numeric for type $type.");
                }
                return (string)$value;
            case 'date':
            case 'datetime2':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value) && $value !== 'CURRENT_TIMESTAMP') {
                    throw new InvalidArgumentException("Default value for $type must be in YYYY-MM-DD or YYYY-MM-DD HH:MM:SS format, or CURRENT_TIMESTAMP.");
                }
                return $value === 'CURRENT_TIMESTAMP' ? 'CURRENT_TIMESTAMP' : "'$value'";
            default:
                return (string)$value;
        }
    }
}

/**
 * Builder for defining database schemas and migrations.
 */
class SchemaBuilder {
    private $columnDefinitions = [];
    private $indexes = [];
    private $foreignKeys = [];
    private $constraints = [];
    private $alterActions = [];
    private $dbType;
    private $databaser;
    private $adapter;
    private $engine = null;
    private $charset = null;
    private $collation = null;

    public function __construct($databaser = null) {
        if ($databaser instanceof Databaser) {
            $this->databaser = $databaser;
        } elseif ($databaser instanceof DI && $databaser->classname === Databaser::class) {
            $this->databaser = $databaser->getTarget();
        } else {
            $this->databaser = null;
        }

        if ($this->databaser) {
            // Get all databases and the selected database
            $allDatabasesInOne = [];
            $allDatabases = $databaser->getDatabases();
            foreach ($allDatabases as $dbData) {
                $allDatabasesInOne = array_merge($allDatabasesInOne, $dbData);
            }
            $allDatabases = $allDatabasesInOne;
            unset($allDatabasesInOne);
            $usedDatabase = $this->databaser->getSelectedDatabase();
            if ($usedDatabase !== null) {
                $typeInfo = strtolower($allDatabases[$usedDatabase]['type'] ?? 'mysql');
                if (in_array($typeInfo, ['mysql', 'pgsql', 'sqlite', 'oci', 'sqlsrv'])) {
                    $this->dbType = $typeInfo;
                } else {
                    throw new \Exception("Unsupported database type: $typeInfo");
                }
            } else {
                $this->dbType = 'mysql';
            }
        } elseif (is_string($databaser) && in_array(strtolower($databaser), ['mysql', 'pgsql', 'sqlite', 'oci', 'sqlsrv'])) {
            // If $databaser is a string and contains a supported database type
            $this->dbType = strtolower($databaser);
        } else {
            // Default value
            $this->dbType = 'mysql';
        }

        $this->setDefaultCharsetAndCollation();
        $this->initializeAdapter();
    }

    private function quoteIdentifier($name) {
        switch ($this->dbType) {
            case 'mysql':
            case 'sqlite':
                return "`$name`";
            case 'pgsql':
            case 'oci':
                return "\"$name\"";
            case 'sqlsrv':
                return "[$name]";
            default:
                return $name;
        }
    }

    private function setDefaultCharsetAndCollation() {
        switch ($this->dbType) {
            case 'mysql':
                $this->charset = 'utf8mb4';
                $this->collation = 'utf8mb4_unicode_ci';
                break;
            case 'pgsql':
                $this->charset = 'UTF8';
                $this->collation = null;
                break;
            case 'oci':
                $this->charset = 'AL32UTF8';
                $this->collation = null;
                break;
            case 'sqlite':
            case 'sqlsrv':
                $this->charset = null;
                $this->collation = null;
                break;
        }
    }

    private function initializeAdapter() {
        switch ($this->dbType) {
            case 'mysql':
                $this->adapter = new MySqlSchemaAdapter();
                break;
            case 'pgsql':
                $this->adapter = new PgSqlSchemaAdapter();
                break;
            case 'sqlite':
                $this->adapter = new SQLiteSchemaAdapter();
                break;
            case 'oci':
                $this->adapter = new OracleSchemaAdapter();
                break;
            case 'sqlsrv':
                $this->adapter = new SqlSrvSchemaAdapter();
                break;
            default:
                throw new InvalidArgumentException("Unsupported database type: {$this->dbType}");
        }
    }

    public function getDbType() {
        return $this->dbType;
    }

    public function charset($charset) {
        if (is_array($charset)) {
            $charset = $charset[$this->dbType] ?? null;
        }
        if ($charset !== null) {
            $this->validateCharset($charset);
            $this->charset = $charset;
        }
        return $this;
    }

    public function collation($collation) {
        if (is_array($collation)) {
            $collation = $collation[$this->dbType] ?? null;
        }
        if ($collation !== null) {
            $this->validateCollation($collation);
            $this->collation = $collation;
        }
        return $this;
    }

    private function validateCharset($charset) {
        return;
    }

    private function validateCollation($collation) {
        return;
    }

    public function id($name = 'id', $defaultType = 'BIGINT') {
        $column = new ColumnDefinition($this, $name, 'BIGINT');
        $column->autoIncrement();
        $this->columnDefinitions[] = $column;
        $this->primaryKey([$name]);
        return $column;
    }

    public function string($name, $length = 255) {
        $column = new ColumnDefinition($this, $name, 'VARCHAR');
        $column->length((int)$length);
        $this->columnDefinitions[] = $column;
        return $column;
    }

    public function integer($name) {
        $column = new ColumnDefinition($this, $name, 'INT');
        $this->columnDefinitions[] = $column;
        return $column;
    }

    public function tinyInteger($name) {
        $column = new ColumnDefinition($this, $name, 'TINYINT');
        $this->columnDefinitions[] = $column;
        return $column;
    }

    public function bigInteger($name) {
        $column = new ColumnDefinition($this, $name, 'BIGINT');
        $this->columnDefinitions[] = $column;
        return $column;
    }

    public function boolean($name) {
        $column = new ColumnDefinition($this, $name, 'BOOLEAN');
        $this->columnDefinitions[] = $column;
        return $column;
    }

    public function decimal($name, $precision = 10, $scale = 2) {
        $column = new ColumnDefinition($this, $name, 'DECIMAL');
        $column->precision((int)$precision)->scale((int)$scale);
        $this->columnDefinitions[] = $column;
        return $column;
    }

    public function float($name) {
        $column = new ColumnDefinition($this, $name, 'FLOAT');
        $this->columnDefinitions[] = $column;
        return $column;
    }

    public function text($name) {
        $column = new ColumnDefinition($this, $name, 'TEXT');
        $this->columnDefinitions[] = $column;
        return $column;
    }

    public function json($name) {
        if (!in_array($this->dbType, ['mysql', 'pgsql', 'sqlite'])) {
            throw new InvalidArgumentException("JSON type is not supported in {$this->dbType}.");
        }
        $column = new ColumnDefinition($this, $name, $this->dbType === 'pgsql' ? 'JSONB' : 'JSON');
        $this->columnDefinitions[] = $column;
        return $column;
    }

    public function enum($name, array $values) {
        if (empty($values)) {
            throw new InvalidArgumentException("ENUM values cannot be empty.");
        }
        $column = new ColumnDefinition($this, $name, $this->dbType === 'mysql' ? 'ENUM' : 'TEXT');
        $this->constraints[] = $this->formatEnumConstraint($name, $values);
        $this->columnDefinitions[] = $column;
        return $column;
    }

    public function set($name, array $values) {
        if ($this->dbType !== 'mysql') {
            throw new InvalidArgumentException("SET type is only supported in MySQL.");
        }
        if (empty($values)) {
            throw new InvalidArgumentException("SET values cannot be empty.");
        }
        $column = new ColumnDefinition($this, $name, 'SET');
        $this->constraints[] = $this->formatSetConstraint($name, $values);
        $this->columnDefinitions[] = $column;
        return $column;
    }

    public function timestamp($name) {
        $column = new ColumnDefinition($this, $name, 'TIMESTAMP');
        $this->columnDefinitions[] = $column;
        return $column;
    }

    public function datetime($name) {
        $column = new ColumnDefinition($this, $name, 'DATETIME');
        $this->columnDefinitions[] = $column;
        return $column;
    }

    public function date($name) {
        $column = new ColumnDefinition($this, $name, 'DATE');
        $this->columnDefinitions[] = $column;
        return $column;
    }

    public function primaryKey($columns, $constraintName = null) {
        if (is_string($columns)) {
            $columns = [$columns];
        }
        if (!is_array($columns) || empty($columns)) {
            throw new InvalidArgumentException("Primary key must have at least one column.");
        }
        $columns = array_map([$this, 'sanitizeName'], $columns);
        $columnList = $this->formatColumnList($columns);
        $name = $constraintName ? $this->sanitizeName($constraintName) : 'pk_' . implode('_', $columns)."_".substr(md5(microtime()),0,8);
        $constraint = new ConstraintDefinition($this, 'PRIMARY_KEY', $name);
        $this->constraints[] = ['definition' => "CONSTRAINT " . $this->quoteIdentifier($name) . " PRIMARY KEY ($columnList)", 'constraint' => $constraint];
        return $constraint;
    }

    public function dropPrimaryKey() {
        if ($this->dbType === 'sqlite') {
            throw new InvalidArgumentException("SQLite does not support DROP PRIMARY KEY directly.");
        }
        $action = $this->dbType === 'sqlsrv' ? "DROP CONSTRAINT PK" : "DROP PRIMARY KEY";
        $this->alterActions[] = $this->adapter->formatAlterAction($action);
        return $this;
    }

    public function foreign($column, $constraintName = null) {
        $column = $this->sanitizeName($column);
        $references = $this->sanitizeName('id');
        $on = $this->guessTableName($column);
        if (empty($on)) {
            throw new InvalidArgumentException("Foreign key table name must be specified or inferred from column name.");
        }

        // Set database-specific defaults
        switch ($this->dbType) {
            case 'mysql':
                $onDelete = 'RESTRICT';
                $onUpdate = 'NO ACTION';
                break;
            case 'pgsql':
            case 'sqlsrv':
                $onDelete = 'NO ACTION';
                $onUpdate = 'NO ACTION';
                break;
            case 'sqlite':
                $onDelete = 'RESTRICT';
                $onUpdate = 'NO ACTION';
                break;
            case 'oci':
                $onDelete = 'NO ACTION';
                $onUpdate = 'NO ACTION';
                break;
            default:
                $onDelete = 'NO ACTION';
                $onUpdate = 'NO ACTION';
        }

        // Generate name after defaults are set
        $name = $constraintName ? $this->sanitizeName($constraintName) : "fk_{$column}_{$on}";
        $constraint = new ConstraintDefinition($this, 'FOREIGN_KEY', $name, $column);
        $constraint->references($references)
                ->on($on)
                ->onDelete($onDelete)
                ->onUpdate($onUpdate);

        $this->foreignKeys[] = ['column' => $column, 'constraint' => $constraint];
        return $constraint;
    }

    public function dropForeign($name) {
        if ($this->dbType === 'sqlite') {
            throw new InvalidArgumentException("SQLite does not support DROP FOREIGN KEY directly.");
        }
        $name = $this->sanitizeName($name);
        $action = $this->dbType === 'mysql' ? "DROP FOREIGN KEY `$name`" : "DROP CONSTRAINT \"$name\"";
        $this->alterActions[] = $this->adapter->formatAlterAction($action);
        return $this;
    }

    public function index($columns, $indexName = null) {
        if (is_string($columns)) {
            $columns = [$columns];
        }
        if (!is_array($columns) || empty($columns)) {
            throw new InvalidArgumentException("Index must have at least one column.");
        }
        $columns = array_map([$this, 'sanitizeName'], $columns);
        $name = $indexName ? $this->sanitizeName($indexName) : 'idx_' . implode('_', $columns);
        $constraint = new ConstraintDefinition($this, 'INDEX', $name);
        $this->indexes[] = ['columns' => $columns, 'unique' => false, 'name' => $name, 'constraint' => $constraint];
        return $constraint;
    }

    public function unique($columns, $indexName = null) {
        if (is_string($columns)) {
            $columns = [$columns];
        }
        if (!is_array($columns) || empty($columns)) {
            throw new InvalidArgumentException("Unique index must have at least one column.");
        }
        $columns = array_map([$this, 'sanitizeName'], $columns);
        $name = $indexName ? $this->sanitizeName($indexName) : 'uniq_' . implode('_', $columns);
        $constraint = new ConstraintDefinition($this, 'UNIQUE_INDEX', $name);
        $this->indexes[] = ['columns' => $columns, 'unique' => true, 'name' => $name, 'constraint' => $constraint];
        return $constraint;
    }

    public function fullTextIndex($columns, $indexName = null) {
        if (!in_array($this->dbType, ['mysql', 'pgsql'])) {
            throw new InvalidArgumentException("FULLTEXT index is only supported in MySQL and PostgreSQL.");
        }
        if (is_string($columns)) {
            $columns = [$columns];
        }
        if (!is_array($columns) || empty($columns)) {
            throw new InvalidArgumentException("FULLTEXT index must have at least one column.");
        }
        $columns = array_map([$this, 'sanitizeName'], $columns);
        $name = $indexName ? $this->sanitizeName($indexName) : 'ft_idx_' . implode('_', $columns);
        $columnList = $this->dbType === 'mysql' ? '`' . implode('`,`', $columns) . '`' : '"' . implode('","', $columns) . '"';
        $constraint = new ConstraintDefinition($this, 'FULLTEXT_INDEX', $name);
        $this->indexes[] = ['definition' => $this->dbType === 'mysql'
            ? "FULLTEXT INDEX `$name` ($columnList)"
            : "INDEX \"$name\" ON ($columnList) USING GIN (to_tsvector('english', $columnList))",
            'constraint' => $constraint];
        return $constraint;
    }

    public function dropIndex($name) {
        if ($this->dbType === 'sqlite') {
            throw new InvalidArgumentException("SQLite does not support DROP INDEX in ALTER TABLE.");
        }
        $name = $this->sanitizeName($name);
        $action = $this->dbType === 'mysql' ? "DROP INDEX `$name`" : "DROP INDEX \"$name\"";
        $this->alterActions[] = $this->adapter->formatAlterAction($action);
        return $this;
    }

    public function addColumn($name, $type, $length = null, $nullable = false, $default = null, $comment = null) {
        $column = new ColumnDefinition($this, $name, $type);
        if ($length !== null) {
            $column->length((int)$length);
        }
        if ($nullable) {
            $column->nullable();
        }
        if ($default !== null) {
            $column->default($default);
        }
        if ($comment !== null) {
            $column->comment($comment);
        }
        $this->alterActions[] = $this->adapter->formatAlterAction("ADD " . $this->adapter->formatColumn($column));
        return $this;
    }

    public function dropColumn($name) {
        if ($this->dbType === 'sqlite') {
            throw new InvalidArgumentException("SQLite does not support DROP COLUMN directly.");
        }
        $name = $this->sanitizeName($name);
        $action = $this->dbType === 'mysql' ? "DROP COLUMN `$name`" : "DROP COLUMN \"$name\"";
        $this->alterActions[] = $this->adapter->formatAlterAction($action);
        return $this;
    }

    public function modifyColumn($name, $type, $length = null, $nullable = false, $default = null, $onUpdateCurrent = false, $comment = null) {
        if ($this->dbType === 'sqlite') {
            throw new InvalidArgumentException("SQLite does not support MODIFY COLUMN directly.");
        }
        $column = new ColumnDefinition($this, $name, $type);
        if ($length !== null) {
            $column->length((int)$length);
        }
        if ($nullable) {
            $column->nullable();
        }
        if ($default !== null) {
            $column->default($default);
        }
        if ($onUpdateCurrent) {
            $column->onUpdateCurrentTimestamp();
        }
        if ($comment !== null) {
            $column->comment($comment);
        }
        $action = $this->dbType === 'mysql' ? "MODIFY COLUMN " . $this->adapter->formatColumn($column) : "ALTER COLUMN \"$name\" TYPE {$type}" . ($length ? "($length)" : "");
        if ($this->dbType === 'pgsql') {
            if ($default !== null) {
                $this->alterActions[] = $this->adapter->formatAlterAction("ALTER COLUMN \"$name\" SET DEFAULT " . $this->sanitizeDefault($default, $type));
            }
            $this->alterActions[] = $this->adapter->formatAlterAction("ALTER COLUMN \"$name\" " . ($nullable ? "DROP NOT NULL" : "SET NOT NULL"));
            if ($comment !== null) {
                $this->alterActions[] = $this->adapter->formatAlterAction("COMMENT ON COLUMN \"$name\" IS '$comment'");
            }
        }
        $this->alterActions[] = $this->adapter->formatAlterAction($action);
        return $this;
    }

    public function addConstraint($name, $constraint) {
        $name = $this->sanitizeName($name);
        if ($this->dbType === 'sqlite' && strpos($constraint, 'CHECK') === false) {
            throw new InvalidArgumentException("SQLite only supports CHECK constraints.");
        }
        $this->constraints[] = ['definition' => "CONSTRAINT `$name` $constraint", 'constraint' => new ConstraintDefinition($this, 'CHECK', $name)];
        return $this;
    }

    public function dropConstraint($name) {
        if ($this->dbType === 'sqlite') {
            throw new InvalidArgumentException("SQLite does not support DROP CONSTRAINT directly.");
        }
        $name = $this->sanitizeName($name);
        $action = $this->dbType === 'mysql' ? "DROP CONSTRAINT `$name`" : "DROP CONSTRAINT \"$name\"";
        $this->alterActions[] = $this->adapter->formatAlterAction($action);
        return $this;
    }

    public function tableExists($table) {
        if (!$this->databaser) {
            throw new InvalidArgumentException("Databaser instance is required to check table existence.");
        }
        $originalReturnType = $this->databaser->returnType;
        $this->databaser->returnType = 'RAW';
        $result = $this->adapter->tableExists($this->sanitizeName($table));
        $this->databaser->returnType = $originalReturnType;
        return $result;
    }

    public function columnExists($table, $column) {
        if (!$this->databaser) {
            throw new InvalidArgumentException("Databaser instance is required to check column existence.");
        }
        $originalReturnType = $this->databaser->returnType;
        $this->databaser->returnType = 'RAW';
        $result = $this->adapter->columnExists($this->sanitizeName($table), $this->sanitizeName($column));
        $this->databaser->returnType = $originalReturnType;
        return $result;
    }

    public function indexExists($table, $indexName) {
        if (!$this->databaser) {
            throw new InvalidArgumentException("Databaser instance is required to check index existence.");
        }
        $originalReturnType = $this->databaser->returnType;
        $this->databaser->returnType = 'RAW';
        $result = $this->adapter->indexExists($this->sanitizeName($table), $this->sanitizeName($indexName));
        $this->databaser->returnType = $originalReturnType;
        return $result;
    }

    public function foreignKeyExists($table, $constraintName) {
        if (!$this->databaser) {
            throw new InvalidArgumentException("Databaser instance is required to check foreign key existence.");
        }
        $originalReturnType = $this->databaser->returnType;
        $this->databaser->returnType = 'RAW';
        $result = $this->adapter->foreignKeyExists($this->sanitizeName($table), $this->sanitizeName($constraintName));
        $this->databaser->returnType = $originalReturnType;
        return $result;
    }

    public function getDefinition() {
        $columns = array_map(function ($column) {
            return $this->adapter->formatColumn($column);
        }, $this->columnDefinitions);

        $indexes = array_map(function ($index) {
            if (isset($index['definition'])) {
                return $index['definition'];
            }
            $columns = $index['columns'];
            $unique = $index['unique'];
            $name = $index['name'];
            $comment = $index['constraint']->getProperties()['comment'] ?? null;
            return $this->adapter->formatIndex($columns, $unique, $name, $comment);
        }, $this->indexes);

        $foreignKeys = array_map(function ($fk) {
            $constraint = $fk['constraint'];
            $comment = $constraint->getProperties()['comment'] ?? null;
            $fkData = $constraint->getForeignKeyData();

            $references = $fkData['references'] ?? 'id';
            $on = $fkData['on'] ?? $this->guessTableName($fk['column']);
            $onDelete = $fkData['onDelete'] ?? ($this->dbType === 'mysql' || $this->dbType === 'sqlite' ? 'RESTRICT' : 'NO ACTION');
            $onUpdate = $fkData['onUpdate'] ?? ($this->dbType === 'oci' ? 'NO ACTION' : 'NO ACTION');

            if (empty($on)) {
                throw new InvalidArgumentException("Foreign key table name must be specified or inferred for constraint {$constraint->getName()}.");
            }

            return $this->adapter->formatForeignKey(
                $fk['column'],
                $references,
                $on,
                $onDelete,
                $onUpdate,
                $constraint->getName(),
                $comment
            );
        }, $this->foreignKeys);

        $constraints = array_map(function ($constraint) {
            $comment = $constraint['constraint']->getProperties()['comment'] ?? null;
            return $constraint['definition'] . ($comment && $this->dbType === 'mysql' ? " COMMENT '" . str_replace("'", "''", $comment) . "'" : '');
        }, $this->constraints);

        $definition = implode(', ', array_merge($columns, $indexes, $foreignKeys, $constraints));

        $charsetAndCollation = $this->adapter->getCharsetAndCollationClause($this->charset, $this->collation);

        if ($this->dbType === 'mysql') {
            $engine = $this->getEngine() ?? 'InnoDB';
            return "($definition) $charsetAndCollation ENGINE=$engine";
        }

        return "($definition) $charsetAndCollation";
    }

    public function getAlterDefinition() {
        return implode(', ', $this->alterActions);
    }

    private function guessTableName($column) {
        return rtrim($column, '_id');
    }

    public function sanitizeName($name) {
        if (empty($name) || !preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new InvalidArgumentException("Column or table name must be a non-empty string with valid characters: $name");
        }
        return $name;
    }

    private function sanitizeDefault($value, $type) {
        if ($value === null) {
            return 'NULL';
        }
        $type = strtolower($type);
        switch ($type) {
            case 'varchar':
            case 'text':
            case 'json':
            case 'jsonb':
                return "'" . str_replace("'", "''", (string)$value) . "'";
            case 'int':
            case 'bigint':
            case 'tinyint':
            case 'decimal':
            case 'float':
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException("Default value must be numeric for type $type.");
                }
                return (string)$value;
            case 'date':
            case 'datetime':
            case 'timestamp':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value) && $value !== 'CURRENT_TIMESTAMP') {
                    throw new InvalidArgumentException("Default value for $type must be in YYYY-MM-DD or YYYY-MM-DD HH:MM:SS format, or CURRENT_TIMESTAMP.");
                }
                return $value === 'CURRENT_TIMESTAMP' ? 'CURRENT_TIMESTAMP' : "'$value'";
            default:
                return (string)$value;
        }
    }

    private function formatColumnList(array $columns) {
        switch ($this->dbType) {
            case 'mysql':
            case 'sqlite':
                return '`' . implode('`,`', $columns) . '`';
            case 'pgsql':
            case 'oci':
                return '"' . implode('","', $columns) . '"';
            case 'sqlsrv':
                return '[' . implode('],[', $columns) . ']';
        }
        return implode(',', $columns);
    }

    private function formatEnumConstraint($name, array $values) {
        $sanitizedValues = array_map(function ($value) {
            return "'" . str_replace("'", "''", $value) . "'";
        }, $values);
        $valueList = implode(',', $sanitizedValues);
        switch ($this->dbType) {
            case 'mysql':
                return $this->quoteIdentifier($name) . " ENUM($valueList)";
            case 'pgsql':
                $typeName = $name . '_enum';
                return "CREATE TYPE " . $this->quoteIdentifier($typeName) . " AS ENUM ($valueList)";
            default:
                return "CHECK (" . $this->quoteIdentifier($name) . " IN ($valueList))";
        }
    }

    private function formatSetConstraint($name, array $values) {
        $sanitizedValues = array_map(function ($value) {
            return "'" . str_replace("'", "''", $value) . "'";
        }, $values);
        $valueList = implode(',', $sanitizedValues);
        return $this->quoteIdentifier($name) . " SET($valueList)";
    }

    public function engine($engine) {
        if ($this->dbType === 'mysql') {
            if (!in_array(strtoupper($engine), ['INNODB', 'MYISAM'])) {
                throw new InvalidArgumentException("Unsupported MySQL engine: $engine. Supported engines: InnoDB, MyISAM.");
            }
            $this->engine = strtoupper($engine);
        }
        return $this;
    }

    public function getEngine() {
        return $this->engine;
    }
}

?>