<?php

namespace Dotsystems\App\Parts;
use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\Databaser;
use \Dotsystems\App\Parts\QueryBuilder;

class SchemaBuilder {
    private $columns = [];
    private $indexes = [];
    private $foreignKeys = [];
    private $alterActions = [];
    private $dbType; // Typ databázy (mysql, pgsql, sqlite, oci, sqlsrv atď.)

    function __construct($databaser = null) {
        if ($databaser instanceof Databaser) {
            // Vyberieme typ databazy z instancie datbasera
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

    // Primárny kľúč s automatickým inkrementom
    public function id($name = 'id') {
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
            case 'oci': // Oracle
                $this->columns[] = "\"$name\" NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY";
                break;
            case 'sqlsrv': // MS SQL Server
                $this->columns[] = "[$name] BIGINT IDENTITY(1,1) PRIMARY KEY";
                break;
        }
        return $this;
    }

    // Reťazec (VARCHAR/TEXT)
    public function string($name, $length = 255, $nullable = false, $default = null) {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT '$default'" : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` VARCHAR($length) $null$defaultStr";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" VARCHAR($length) $null$defaultStr";
                break;
            case 'sqlite':
                $this->columns[] = "`$name` TEXT $null$defaultStr"; // SQLite nemá dĺžku pre VARCHAR
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

    // Celé číslo (INTEGER)
    public function integer($name, $nullable = false, $default = null) {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT $default" : '';
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

    // Veľké celé číslo (BIGINT)
    public function bigInteger($name, $nullable = false, $default = null) {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT $default" : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` BIGINT $null$defaultStr";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" BIGINT $null$defaultStr";
                break;
            case 'sqlite':
                $this->columns[] = "`$name` INTEGER $null$defaultStr"; // SQLite nemá BIGINT
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

    // Boolean
    public function boolean($name, $nullable = false, $default = null) {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT " . ($default ? '1' : '0') : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` TINYINT(1) $null$defaultStr";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" BOOLEAN $null" . ($default !== null ? " DEFAULT " . ($default ? 'TRUE' : 'FALSE') : '');
                break;
            case 'sqlite':
                $this->columns[] = "`$name` INTEGER $null$defaultStr"; // SQLite používa 0/1
                break;
            case 'oci':
                $this->columns[] = "\"$name\" NUMBER(1) $null$defaultStr";
                break;
            case 'sqlsrv':
                $this->columns[] = "[$name] BIT $null" . ($default !== null ? " DEFAULT " . ($default ? '1' : '0') : '');
                break;
        }
        return $this;
    }

    // Desatinné číslo (DECIMAL)
    public function decimal($name, $precision = 10, $scale = 2, $nullable = false, $default = null) {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT $default" : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` DECIMAL($precision,$scale) $null$defaultStr";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" NUMERIC($precision,$scale) $null$defaultStr";
                break;
            case 'sqlite':
                $this->columns[] = "`$name` REAL $null$defaultStr"; // SQLite nemá presné DECIMAL
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

    // Časové značky (timestamps)
    public function timestamps() {
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                $this->columns[] = "`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
                break;
            case 'pgsql':
                $this->columns[] = "\"created_at\" TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                $this->columns[] = "\"updated_at\" TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                // Pre ON UPDATE by bolo potrebné trigger
                break;
            case 'sqlite':
                $this->columns[] = "`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP";
                $this->columns[] = "`updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP";
                // Pre ON UPDATE by bolo potrebné trigger
                break;
            case 'oci':
                $this->columns[] = "\"CREATED_AT\" TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                $this->columns[] = "\"UPDATED_AT\" TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                break;
            case 'sqlsrv':
                $this->columns[] = "[created_at] DATETIME2 DEFAULT CURRENT_TIMESTAMP";
                $this->columns[] = "[updated_at] DATETIME2 DEFAULT CURRENT_TIMESTAMP";
                // Pre ON UPDATE by bolo potrebné trigger
                break;
        }
        return $this;
    }

    // Dátum
    public function date($name, $nullable = false, $default = null) {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $defaultStr = $default !== null ? " DEFAULT '$default'" : '';
        switch ($this->dbType) {
            case 'mysql':
                $this->columns[] = "`$name` DATE $null$defaultStr";
                break;
            case 'pgsql':
                $this->columns[] = "\"$name\" DATE $null$defaultStr";
                break;
            case 'sqlite':
                $this->columns[] = "`$name` TEXT $null$defaultStr"; // SQLite ukladá dátum ako text
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

    // Cudzí kľúč
    public function foreign($column, $references = 'id', $on = null, $onDelete = 'CASCADE', $onUpdate = 'NO ACTION') {
        $on = $on ?? $this->guessTableName($column);
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
                // Oracle nepodporuje ON UPDATE priamo v definícii
                break;
            case 'sqlsrv':
                $this->foreignKeys[] = "FOREIGN KEY ([$column]) REFERENCES [$on] ([$references]) ON DELETE $onDelete ON UPDATE $onUpdate";
                break;
        }
        return $this;
    }

    // Index
    public function index($column, $unique = false) {
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

    public function unique($column) {
        return $this->index($column, true);
    }

    // Pre ALTER TABLE
    public function addColumn($name, $type, $length = null, $nullable = false, $default = null) {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $lengthStr = $length ? "($length)" : '';
        $defaultStr = $default !== null ? " DEFAULT " . (is_string($default) ? "'$default'" : $default) : '';
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
                $this->alterActions[] = "ADD `$name` $type $null$defaultStr"; // SQLite ignoruje dĺžku
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

    public function dropColumn($name) {
        switch ($this->dbType) {
            case 'mysql':
                $this->alterActions[] = "DROP COLUMN `$name`";
                break;
            case 'pgsql':
                $this->alterActions[] = "DROP COLUMN \"$name\"";
                break;
            case 'sqlite':
                // SQLite nepodporuje DROP COLUMN priamo, placeholder
                $this->alterActions[] = "DROP COLUMN `$name`";
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

    public function getDefinition() {
        return implode(', ', array_merge($this->columns, $this->indexes, $this->foreignKeys));
    }

    public function getAlterDefinition() {
        return implode(', ', $this->alterActions);
    }

    private function guessTableName($column) {
        return rtrim($column, '_id');
    }

    // Konverzia typov pre PostgreSQL
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

    // Konverzia typov pre SQLite
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

    // Konverzia typov pre Oracle
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

    // Konverzia typov pre SQL Server
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
?>