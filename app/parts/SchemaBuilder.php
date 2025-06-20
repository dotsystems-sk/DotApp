<?php

namespace Dotsystems\App\Parts;

use Dotsystems\App\Parts\Databaser;
use InvalidArgumentException;

/**
 * Builder for defining database schemas and migrations.
 */
class SchemaBuilder {
    private $columns = [];
    private $indexes = [];
    private $foreignKeys = [];
    private $alterActions = [];
    private $dbType;

    /**
     * Constructor initializes the database type.
     * @param Databaser|string|null $databaser Databaser object, database type string, or null.
     */
    public function __construct($databaser = null) {
        if ($databaser instanceof Databaser) {
            if (isset($databaser->database_drivers['driver'])) {
                $driver = $databaser->database_drivers['driver'];
                $name = key($databaser->databases[$driver] ?? []);
                $this->dbType = strtolower($databaser->databases[$driver][$name]['type'] ?? 'mysql');
            } else {
                $this->dbType = 'mysql';
            }
        } elseif (is_string($databaser) && in_array(strtolower($databaser), ['mysql', 'pgsql', 'sqlite', 'oci', 'sqlsrv'])) {
            $this->dbType = strtolower($databaser);
        } else {
            $this->dbType = 'mysql';
        }
    }

    /**
     * Adds a primary key with auto-increment.
     * @param string $name Column name (default: 'id').
     * @return $this
     */
    public function id($name = 'id') {
        $name = $this->sanitizeName($name);
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
            case 'oci':
                $this->columns[] = "\"$name\" NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY";
                break;
            case 'sqlsrv':
                $this->columns[] = "[$name] BIGINT IDENTITY(1,1) PRIMARY KEY";
                break;
        }
        return $this;
    }

    /**
     * Adds a VARCHAR/TEXT column.
     * @param string $name Column name.
     * @param int $length Maximum length (default: 255).
     * @param bool $nullable Allow NULL (default: false).
     * @param mixed $default Default value (default: null).
     * @return $this
     * @throws InvalidArgumentException If length is invalid.
     */
    public function string($name, $length = 255, $nullable = false, $default = null) {
        if (!is_int($length) || $length <= 0) {
            throw new InvalidArgumentException("Column length must be a positive integer.");
        }
        $name = $this->sanitizeName($name);
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT " . $this->sanitizeDefault($default, 'string') : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` VARCHAR($length) $null$defaultStr";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" VARCHAR($length) $null$defaultStr";
                break;
            case 'sqlite':
                $this->columns[] = "`$name` TEXT $null$defaultStr";
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

    /**
     * Adds an INTEGER column.
     * @param string $name Column name.
     * @param bool $nullable Allow NULL (default: false).
     * @param mixed $default Default value (default: null).
     * @return $this
     */
    public function

 integer($name, $nullable = false, $default = null) {
        $name = $this->sanitizeName($name);
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT " . $this->sanitizeDefault($default, 'integer') : '';
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

    /**
     * Adds a BIGINT column.
     * @param string $name Column name.
     * @param bool $nullable Allow NULL (default: false).
     * @param mixed $default Default value (default: null).
     * @return $this
     */
    public function bigInteger($name, $nullable = false, $default = null) {
        $name = $this->sanitizeName($name);
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT " . $this->sanitizeDefault($default, 'integer') : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` BIGINT $null$defaultStr";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" BIGINT $null$defaultStr";
                break;
            case 'sqlite':
                $this->columns[] = "`$name` INTEGER $null$defaultStr";
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

    /**
     * Adds a BOOLEAN column.
     * @param string $name Column name.
     * @param bool $nullable Allow NULL (default: false).
     * @param mixed $default Default value (default: null).
     * @return $this
     */
    public function boolean($name, $nullable = false, $default = null) {
        $name = $this->sanitizeName($name);
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT " . ($default ? ($this->dbType === 'pgsql' ? 'TRUE' : '1') : ($this->dbType === 'pgsql' ? 'FALSE' : '0')) : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` TINYINT(1) $null$defaultStr";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" BOOLEAN $null$defaultStr";
                break;
            case 'sqlite':
                $this->columns[] = "`$name` INTEGER $null$defaultStr";
                break;
            case 'oci':
                $this->columns[] = "\"$name\" NUMBER(1) $null$defaultStr";
                break;
            case 'sqlsrv':
                $this->columns[] = "[$name] BIT $null$defaultStr";
                break;
        }
        return $this;
    }

    /**
     * Adds a DECIMAL column.
     * @param string $name Column name.
     * @param int $precision Total precision (default: 10).
     * @param int $scale Number of decimal places (default: 2).
     * @param bool $nullable Allow NULL (default: false).
     * @param mixed $default Default value (default: null).
     * @return $this
     * @throws InvalidArgumentException If precision or scale is invalid.
     */
    public function decimal($name, $precision = 10, $scale = 2, $nullable = false, $default = null) {
        if (!is_int($precision) || $precision <= 0 || !is_int($scale) || $scale < 0 || $scale > $precision) {
            throw new InvalidArgumentException("Invalid precision or scale for DECIMAL.");
        }
        $name = $this->sanitizeName($name);
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT " . $this->sanitizeDefault($default, 'decimal') : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` DECIMAL($precision,$scale) $null$defaultStr";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" NUMERIC($precision,$scale) $null$defaultStr";
                break;
            case 'sqlite':
                $this->columns[] = "`$name` REAL $null$defaultStr";
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

    /**
     * Adds a TEXT column.
     * @param string $name Column name.
     * @param bool $nullable Allow NULL (default: false).
     * @param mixed $default Default value (default: null).
     * @return $this
     */
    public function text($name, $nullable = false, $default = null) {
        $name = $this->sanitizeName($name);
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT " . $this->sanitizeDefault($default, 'string') : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` TEXT $null$defaultStr";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" TEXT $null$defaultStr";
                break;
            case 'sqlite':
                $this->columns[] = "`$name` TEXT $null$defaultStr";
                break;
            case 'oci':
                $this->columns[] = "\"$name\" CLOB $null$defaultStr";
                break;
            case 'sqlsrv':
                $this->columns[] = "[$name] NVARCHAR(MAX) $null$defaultStr";
                break;
        }
        return $this;
    }

    /**
     * Adds a JSON column.
     * @param string $name Column name.
     * @param bool $nullable Allow NULL (default: false).
     * @param mixed $default Default value (default: null).
     * @return $this
     * @throws InvalidArgumentException If JSON is not supported by the database.
     */
    public function json($name, $nullable = false, $default = null) {
        $name = $this->sanitizeName($name);
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT " . $this->sanitizeDefault($default, 'json') : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` JSON $null$defaultStr";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" JSONB $null$defaultStr";
                break;
            case 'sqlite':
                $this->columns[] = "`$name` TEXT $null$defaultStr";
                break;
            case 'oci':
            case 'sqlsrv':
                throw new InvalidArgumentException("JSON type is not supported in {$this->dbType}.");
        }
        return $this;
    }

    /**
     * Adds created_at and updated_at columns.
     * @param string $createdAt Name of created_at column (default: 'created_at').
     * @param string $updatedAt Name of updated_at column (default: 'updated_at').
     * @return $this
     */
    public function timestamps($createdAt = 'created_at', $updatedAt = 'updated_at') {
        $createdAt = $this->sanitizeName($createdAt);
        $updatedAt = $this->sanitizeName($updatedAt);
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                $this->columns[] = "`$updatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
                break;
            case 'pgsql':
                $this->columns[] = "\"$createdAt\" TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                $this->columns[] = "\"$updatedAt\" TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                break;
            case 'sqlite':
                $this->columns[] = "`$createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP";
                $this->columns[] = "`$updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP";
                break;
            case 'oci':
                $this->columns[] = "\"$createdAt\" TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                $this->columns[] = "\"$updatedAt\" TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                break;
            case 'sqlsrv':
                $this->columns[] = "[$createdAt] DATETIME2 DEFAULT CURRENT_TIMESTAMP";
                $this->columns[] = "[$updatedAt] DATETIME2 DEFAULT CURRENT_TIMESTAMP";
                break;
        }
        return $this;
    }

    /**
     * Adds a DATE column.
     * @param string $name Column name.
     * @param bool $nullable Allow NULL (default: false).
     * @param mixed $default Default value (default: null).
     * @return $this
     */
    public function date($name, $nullable = false, $default = null) {
        $name = $this->sanitizeName($name);
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT " . $this->sanitizeDefault($default, 'date') : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` DATE $null$defaultStr";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" DATE $null$defaultStr";
                break;
            case 'sqlite':
                $this->columns[] = "`$name` TEXT $null$defaultStr";
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

    /**
     * Adds a foreign key.
     * @param string $column Column name.
     * @param string $references Referenced column (default: 'id').
     * @param string|null $on Referenced table (default: derived from column name).
     * @param string $onDelete Action on delete (default: 'CASCADE').
     * @param string $onUpdate Action on update (default: 'NO ACTION').
     * @return $this
     * @throws InvalidArgumentException If actions are invalid.
     */
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
        $on = $this->sanitizeName($on ?? $this->guessTableName($column));
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
                break;
            case 'sqlsrv':
                $this->foreignKeys[] = "FOREIGN KEY ([$column]) REFERENCES [$on] ([$references]) ON DELETE $onDelete ON UPDATE $onUpdate";
                break;
        }
        return $this;
    }

    /**
     * Adds an index.
     * @param string $column Column name.
     * @param bool $unique Whether the index is unique (default: false).
     * @return $this
     */
    public function index($column, $unique = false) {
        $column = $this->sanitizeName($column);
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

    /**
     * Adds a unique index.
     * @param string $column Column name.
     * @return $this
     */
    public function unique($column) {
        return $this->index($column, true);
    }

    /**
     * Adds a column for ALTER TABLE.
     * @param string $name Column name.
     * @param string $type Column type.
     * @param int|null $length Length (optional).
     * @param bool $nullable Allow NULL (default: false).
     * @param mixed $default Default value (default: null).
     * @return $this
     */
    public function addColumn($name, $type, $length = null, $nullable = false, $default = null) {
        $name = $this->sanitizeName($name);
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $lengthStr = $length ? "($length)" : '';
        $defaultStr = $default !== null ? " DEFAULT " . $this->sanitizeDefault($default, $type) : '';
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
                $this->alterActions[] = "ADD `$name` $type $null$defaultStr";
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

    /**
     * Drops a column for ALTER TABLE.
     * @param string $name Column name.
     * @return $this
     * @throws InvalidArgumentException If SQLite does not support the operation.
     */
    public function dropColumn($name) {
        $name = $this->sanitizeName($name);
        if ($this->dbType === 'sqlite') {
            throw new InvalidArgumentException("SQLite does not support DROP COLUMN directly. Use a migration with a new table creation.");
        }
        switch ($this->dbType) {
            case 'mysql':
                $this->alterActions[] = "DROP COLUMN `$name`";
                break;
            case 'pgsql':
                $this->alterActions[] = "DROP COLUMN \"$name\"";
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

    /**
     * Modifies a column's type or properties for ALTER TABLE.
     * @param string $name Column name.
     * @param string $type New column type.
     * @param int|null $length New length (optional).
     * @param bool $nullable Allow NULL (default: false).
     * @param mixed $default New default value (default: null).
     * @return $this
     * @throws InvalidArgumentException If SQLite does not support the operation.
     */
    public function modifyColumn($name, $type, $length = null, $nullable = false, $default = null) {
        $name = $this->sanitizeName($name);
        if ($this->dbType === 'sqlite') {
            throw new InvalidArgumentException("SQLite does not support MODIFY COLUMN directly. Use a migration with a new table creation.");
        }
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $lengthStr = $length ? "($length)" : '';
        $defaultStr = $default !== null ? " DEFAULT " . $this->sanitizeDefault($default, $type) : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->alterActions[] = "MODIFY COLUMN `$name` $type$lengthStr $null$defaultStr";
                break;
            case 'pgsql':
                $type = $this->convertTypeForPg($type);
                $this->alterActions[] = "ALTER COLUMN \"$name\" TYPE $type$lengthStr";
                if ($default !== null) {
                    $this->alterActions[] = "ALTER COLUMN \"$name\" SET$defaultStr";
                }
                if (!$nullable) {
                    $this->alterActions[] = "ALTER COLUMN \"$name\" SET NOT NULL";
                } else {
                    $this->alterActions[] = "ALTER COLUMN \"$name\" DROP NOT NULL";
                }
                break;
            case 'oci':
                $type = $this->convertTypeForOracle($type);
                $this->alterActions[] = "MODIFY \"$name\" $type$lengthStr $null$defaultStr";
                break;
            case 'sqlsrv':
                $type = $this->convertTypeForSqlSrv($type);
                $this->alterActions[] = "ALTER COLUMN [$name] $type$lengthStr $null$defaultStr";
                break;
        }
        return $this;
    }

    /**
     * Returns the definition for CREATE TABLE.
     * @return string
     */
    public function getDefinition() {
        return implode(', ', array_merge($this->columns, $this->indexes, $this->foreignKeys));
    }

    /**
     * Returns the definition for ALTER TABLE.
     * @return string
     */
    public function getAlterDefinition() {
        return implode(', ', $this->alterActions);
    }

    /**
     * Derives the table name from the column name.
     * @param string $column Column name.
     * @return string
     */
    private function guessTableName($column) {
        return rtrim($column, '_id');
    }

    /**
     * Sanitizes the column or table name.
     * @param string $name Name to sanitize.
     * @return string
     * @throws InvalidArgumentException If the name is invalid.
     */
    private function sanitizeName($name) {
        if (empty($name) || !is_string($name)) {
            throw new InvalidArgumentException("Column or table name must be a non-empty string.");
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new InvalidArgumentException("Column or table name contains invalid characters: $name");
        }
        return $name;
    }

    /**
     * Sanitizes the default value based on column type.
     * @param mixed $value Value to sanitize.
     * @param string $type Column type (string, integer, decimal, date, json).
     * @return string
     * @throws InvalidArgumentException If the value is invalid.
     */
    private function sanitizeDefault($value, $type) {
        if ($value === null) {
            return 'NULL';
        }
        switch ($type) {
            case 'string':
            case 'text':
            case 'json':
                return "'" . str_replace("'", "''", (string)$value) . "'";
            case 'integer':
            case 'decimal':
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException("Default value must be numeric for type $type.");
                }
                return (string)$value;
            case 'date':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    throw new InvalidArgumentException("Default value for date must be in YYYY-MM-DD format.");
                }
                return "'$value'";
            default:
                return (string)$value;
        }
    }

    /**
     * Converts type for PostgreSQL.
     * @param string $type Column type.
     * @return string
     */
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

    /**
     * Converts type for SQLite.
     * @param string $type Column type.
     * @return string
     */
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

    /**
     * Converts type for Oracle.
     * @param string $type Column type.
     * @return string
     */
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

    /**
     * Converts type for SQL Server.
     * @param string $type Column type.
     * @return string
     */
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