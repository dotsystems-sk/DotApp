<?php
namespace Dotsystems\App\Parts;

use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\DI;
use \Dotsystems\App\Parts\Facade;
use \Dotsystems\App\Parts\Config;
use \Dotsystems\App\Parts\Databaser;

/**
 * DB Facade
 *
 * Provides a clean, static interface to interact with the Databaser class in the DotApp framework.
 * It simplifies database operations like querying, schema management, transactions, and ORM handling.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.7 FREE
 * @license   MIT License
 */
class DB extends Facade {
    protected static $component = 'db';
    private static $databaserInstances = [];

    protected static $allowedMethods = [
        'driver',
        'add',
        'select_db',
        'selectDb',
        'q',
        'qb',
        'schema',
        'migrate',
        'execute',
        'raw',
        'first',
        'all',
        'return',
        'newEntity',
        'newCollection',
        'fetchArray',
        'fetchFirst',
        'cache',
        'inserted_id',
        'affected_rows',
        'transaction',
        'transact',
        'commit',
        'rollback'
    ];

    public static function isConnected() {
        try {
            return self::module()->getActiveConnection() !== [];
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function default($returnType = null) {
        return self::module($returnType);
    }

    public static function use($databaseName, $returnType = null) {
        $alldb = Config::get("databases");
        if (isset($alldb[$databaseName])) {
            $key = "use_" . $databaseName;
            if (isset(self::$databaserInstances[$key]) && self::$databaserInstances[$key] instanceof DI && self::$databaserInstances[$key]->classname === Databaser::class) {
                if ($returnType === null) {
                    return self::$databaserInstances[$key];
                }
                if ($returnType === 'RAW' || $returnType === 'ORM') {
                    return self::$databaserInstances[$key]->return($returnType);
                }
                throw new \Exception("Invalid returnType for DB. Use 'RAW' or 'ORM'.");
            } else {
                if ($returnType === null) {
                    $returnType = 'RAW';
                }
                if ($returnType !== 'RAW' && $returnType !== 'ORM') {
                    throw new \Exception("Invalid returnType for DB. Use 'RAW' or 'ORM'.");
                }
                $databaser = new DI(new Databaser());
                $databaser->driver($alldb[$databaseName]['driver'])
                          ->return($returnType)
                          ->selectDb($databaseName);
                self::$databaserInstances[$key] = $databaser;
                return $databaser;
            }
        } else {
            throw new \Exception("Database '$databaseName' not found in configuration.");
        }
    }

    public static function module($returnType = null) {
        return self::use(Config::db('maindb'), $returnType);
    }

    public static function schemaBuilder() {
        return new SchemaBuilder(self::module());
    }
}