<?php
namespace Dotsystems\App\Parts;

use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\DI;
use \Dotsystems\App\Parts\Facade;


/**
 * DB Facade
 *
 * Provides a clean, static interface to interact with the Databaser class in the DotApp framework.
 * It simplifies database operations like querying, schema management, transactions, and ORM handling.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.6 FREE
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
}