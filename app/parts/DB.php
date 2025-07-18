<?php
namespace Dotsystems\App\Parts;

use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\DI;
use \Dotsystems\App\Parts\Facade;
use \Dotsystems\App\Parts\Config;


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
            DotApp::DotApp()->DB
            ->driver(Config::db('driver'))
            ->selectDb(Config::db('maindb'));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function default($returnType=null) {
        return self::module($returnType);
    }

    public static function module($returnType=null) {
        if ($returnType === null) {
            return DotApp::DotApp()->DB
            ->driver(Config::db('driver'))
            ->selectDb(Config::db('maindb'));
        } else {
            if ($returnType == "RAW" || $returnType == "ORM") {
                return DotApp::DotApp()->DB
                ->driver(Config::db('driver'))
                ->return($returnType)
                ->selectDb(Config::db('maindb'));
            } else {
                throw new \Exception("Invalid returnType for DB. Use 'RAW' or 'ORM'.");
            }
        }        
    }

    public static function schemaBuilder() {
        return new SchemaBuilder(DB::module());
    }

}