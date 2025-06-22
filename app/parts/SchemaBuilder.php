<?php

namespace Dotsystems\App\Parts;

use InvalidArgumentException;

/**
 * Interface for database-specific schema adapters.
 */
interface SchemaAdapter {
    public function formatColumn(ColumnDefinition $column);
    public function formatIndex(array $columns, $unique, $name);
    public function formatForeignKey($column, $references, $on, $onDelete, $onUpdate, $name);
    public function tableExists($table);
    public function columnExists($table, $column);
    public function indexExists($table, $indexName);
    public function foreignKeyExists($table, $constraintName);
    public function formatAlterAction($action);
    public function getAutoIncrementClause($type);
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

    /**
     * Sets the default value for the column.
     *
     * This method allows you to specify a default value for the column
     * when it's created or when a new row is inserted without a value
     * for this column.
     *
     * @param mixed $value The default value to be set for the column.
     *                     This can be of any type that is compatible with the column's data type.
     *
     * @return $this Returns the current ColumnDefinition instance, allowing for method chaining.
     */
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

    public function formatIndex(array $columns, $unique, $name) {
        $indexType = $unique ? 'UNIQUE INDEX' : 'INDEX';
        $columnList = '`' . implode('`,`', $columns) . '`';
        return "$indexType `$name` ($columnList)";
    }

    public function formatForeignKey($column, $references, $on, $onDelete, $onUpdate, $name) {
        return "CONSTRAINT `$name` FOREIGN KEY (`$column`) REFERENCES `$on` (`$references`) ON DELETE $onDelete ON UPDATE $onUpdate";
    }

    public function tableExists($table) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table) {
                $qb->select('1')
                   ->from('information_schema.tables')
                   ->where('table_name', '=', $table);
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking table existence: {$error['error']} (code: {$error['errno']})");
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

    public function formatIndex(array $columns, $unique, $name) {
        $indexType = $unique ? 'UNIQUE' : 'INDEX';
        $columnList = '"' . implode('","', $columns) . '"';
        return "$indexType \"$name\" ON ($columnList)";
    }

    public function formatForeignKey($column, $references, $on, $onDelete, $onUpdate, $name) {
        return "CONSTRAINT \"$name\" FOREIGN KEY (\"$column\") REFERENCES \"$on\" (\"$references\") ON DELETE $onDelete ON UPDATE $onUpdate";
    }

    public function tableExists($table) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table) {
                $qb->select('1')
                   ->from('information_schema.tables')
                   ->where('table_schema', '=', 'public')
                   ->where('table_name', '=', $table);
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking table existence: {$error['error']} (code: {$error['errno']})");
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

    public function formatIndex(array $columns, $unique, $name) {
        $indexType = $unique ? 'UNIQUE INDEX' : 'INDEX';
        $columnList = '`' . implode('`,`', $columns) . '`';
        return "$indexType `$name` ON ($columnList)";
    }

    public function formatForeignKey($column, $references, $on, $onDelete, $onUpdate, $name) {
        return "FOREIGN KEY (`$column`) REFERENCES `$on` (`$references`) ON DELETE $onDelete ON UPDATE $onUpdate";
    }

    public function tableExists($table) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table) {
                $qb->select('1')
                   ->from('sqlite_master')
                   ->where('type', '=', 'table')
                   ->where('name', '=', $table);
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking table existence: {$error['error']} (code: {$error['errno']})");
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

    public function formatIndex(array $columns, $unique, $name) {
        $indexType = $unique ? 'UNIQUE INDEX' : 'INDEX';
        $columnList = '"' . implode('","', $columns) . '"';
        return "$indexType \"$name\" ON ($columnList)";
    }

    public function formatForeignKey($column, $references, $on, $onDelete, $onUpdate, $name) {
        return "CONSTRAINT \"$name\" FOREIGN KEY (\"$column\") REFERENCES \"$on\" (\"$references\") ON DELETE $onDelete";
    }

    public function tableExists($table) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table) {
                $qb->select('1')
                   ->from('user_tables')
                   ->where('table_name', '=', strtoupper($table));
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking table existence: {$error['error']} (code: {$error['errno']})");
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

    public function formatIndex(array $columns, $unique, $name) {
        $indexType = $unique ? 'UNIQUE INDEX' : 'INDEX';
        $columnList = '[' . implode('],[', $columns) . ']';
        return "$indexType [$name] ON ($columnList)";
    }

    public function formatForeignKey($column, $references, $on, $onDelete, $onUpdate, $name) {
        return "CONSTRAINT [$name] FOREIGN KEY ([$column]) REFERENCES [$on] ([$references]) ON DELETE $onDelete ON UPDATE $onUpdate";
    }

    public function tableExists($table) {
        $result = false;

        DB::module("RAW")
            ->q(function ($qb) use ($table) {
                $qb->select('1')
                   ->from('information_schema.tables')
                   ->where('table_name', '=', $table);
            })
            ->execute(
                function ($rows) use (&$result) {
                    $result = !empty($rows);
                },
                function ($error, $db, $debug) {
                    throw new InvalidArgumentException("Error checking table existence: {$error['error']} (code: {$error['errno']})");
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

    public function __construct($databaser = null) {
        $this->databaser = $databaser instanceof Databaser ? $databaser : null;
        if ($this->databaser) {
            if (isset($this->databaser->database_drivers['driver'])) {
                $driver = $this->databaser->database_drivers['driver'];
                $name = key($this->databaser->databases[$driver] ?? []);
                $this->dbType = strtolower($this->databaser->databases[$driver][$name]['type'] ?? 'mysql');
            } else {
                $this->dbType = 'mysql';
            }
        } elseif (is_string($databaser) && in_array(strtolower($databaser), ['mysql', 'pgsql', 'sqlite', 'oci', 'sqlsrv'])) {
            $this->dbType = strtolower($databaser);
        } else {
            $this->dbType = 'mysql';
        }
        $this->initializeAdapter();
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

    public function id($name = 'id') {
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

    public function primaryKey($columns) {
        if (is_string($columns)) {
            $columns = [$columns];
        }
        if (!is_array($columns) || empty($columns)) {
            throw new InvalidArgumentException("Primary key must have at least one column.");
        }
        $columns = array_map([$this, 'sanitizeName'], $columns);
        $columnList = $this->formatColumnList($columns);
        $this->constraints[] = "PRIMARY KEY ($columnList)";
        return $this;
    }

    public function dropPrimaryKey() {
        if ($this->dbType === 'sqlite') {
            throw new InvalidArgumentException("SQLite does not support DROP PRIMARY KEY directly.");
        }
        $action = $this->dbType === 'sqlsrv' ? "DROP CONSTRAINT PK" : "DROP PRIMARY KEY";
        $this->alterActions[] = $this->adapter->formatAlterAction($action);
        return $this;
    }

    public function foreign($column, $references = 'id', $on = null, $onDelete = 'CASCADE', $onUpdate = 'NO ACTION') {
        $validActions = ['CASCADE', 'RESTRICT', 'SET NULL', 'NO ACTION'];
        if (!in_array($onDelete, $validActions)) {
            throw new InvalidArgumentException("Invalid ON DELETE action: $onDelete");
        }
        if (!in_array($onUpdate, $validActions)) {
            throw new InvalidArgumentException("Invalid ON UPDATE action: $onUpdate");
        }
        $column = $this->sanitizeName($column);
        $references = $this->sanitizeName($references);
        $on = $this->sanitizeName($on ?: $this->guessTableName($column));
        $name = "fk_{$on}_{$column}";
        $this->foreignKeys[] = $this->adapter->formatForeignKey($column, $references, $on, $onDelete, $onUpdate, $name);
        return $this;
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

    public function index($columns, $unique = false) {
        if (is_string($columns)) {
            $columns = [$columns];
        }
        $columns = array_map([$this, 'sanitizeName'], $columns);
        $name = 'idx_' . implode('_', $columns);
        $this->indexes[] = $this->adapter->formatIndex($columns, $unique, $name);
        return $this;
    }

    public function unique($columns) {
        return $this->index($columns, true);
    }

    public function fullTextIndex($columns) {
        if (!in_array($this->dbType, ['mysql', 'pgsql'])) {
            throw new InvalidArgumentException("FULLTEXT index is only supported in MySQL and PostgreSQL.");
        }
        if (is_string($columns)) {
            $columns = [$columns];
        }
        $columns = array_map([$this, 'sanitizeName'], $columns);
        $name = 'ft_idx_' . implode('_', $columns);
        $columnList = $this->dbType === 'mysql' ? '`' . implode('`,`', $columns) . '`' : '"' . implode('","', $columns) . '"';
        $this->indexes[] = $this->dbType === 'mysql'
            ? "FULLTEXT INDEX `$name` ($columnList)"
            : "INDEX \"$name\" ON ($columnList) USING GIN (to_tsvector('english', $columnList))";
        return $this;
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
        $this->constraints[] = "CONSTRAINT `$name` $constraint";
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

        return implode(', ', array_merge($columns, $this->indexes, $this->foreignKeys, $this->constraints));
    }

    public function getAlterDefinition() {
        return implode(', ', $this->alterActions);
    }

    private function guessTableName($column) {
        return rtrim($column, '_id');
    }

    private function sanitizeName($name) {
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
                return "`$name` ENUM($valueList)";
            case 'pgsql':
                $typeName = $name . '_enum';
                return "CREATE TYPE \"$typeName\" AS ENUM ($valueList)";
            default:
                return "CHECK (`$name` IN ($valueList))";
        }
    }

    private function formatSetConstraint($name, array $values) {
        $sanitizedValues = array_map(function ($value) {
            return "'" . str_replace("'", "''", $value) . "'";
        }, $values);
        $valueList = implode(',', $sanitizedValues);
        return "`$name` SET($valueList)";
    }
}

?>