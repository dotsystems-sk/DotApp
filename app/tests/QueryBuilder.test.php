<?php
/**
 * Core tests for QueryBuilder: bindings must follow the order of the placeholders in the SQL,
 * whatever order the builder methods were called in, and values must never become SQL.
 *
 * @package DotApp Framework
 */

namespace Dotsystems\App\Tests;

use Dotsystems\App\Parts\QueryBuilder;
use Dotsystems\App\Parts\Tester;

/**
 * Builds one query and returns "sql | json bindings | types" with collapsed whitespace.
 */
function qbTestDump(string $dbType, callable $build): string
{
    $qb = new QueryBuilder($dbType);
    try {
        $build($qb);
        $q = $qb->getQuery();
    } catch (\Throwable $e) {
        return 'THROWS ' . get_class($e);
    }
    return trim(preg_replace('/\s+/', ' ', $q['query'])) . ' | ' . json_encode($q['bindings']) . ' | ' . $q['types'];
}

Tester::addTest('test_querybuilder_binding_order', function () {
    $cases = [
        // limit() before where(): the id must stay in WHERE and 1 in LIMIT.
        ['mysql', function ($q) { $q->select('id', 'users')->limit(1)->where('id', '=', 7); },
            'SELECT `id` FROM `users` WHERE `id` = ? LIMIT ? | [7,1] | ii'],
        // offset() before limit().
        ['mysql', function ($q) { $q->select('id', 'users')->offset(20)->limit(10); },
            'SELECT `id` FROM `users` LIMIT ? OFFSET ? | [10,20] | ii'],
        // where() before set() in UPDATE.
        ['mysql', function ($q) { $q->update('users')->where('id', '=', 7)->set(['status' => 99]); },
            'UPDATE `users` SET `status` = ? WHERE `id` = ? | [99,7] | ii'],
        // having() before where().
        ['mysql', function ($q) { $q->select('status', 'users')->groupBy('status')->having('status', '>', 0)->where('id', '<', 9); },
            'SELECT `status` FROM `users` WHERE `id` < ? GROUP BY `status` HAVING `status` > ? | [9,0] | ii'],
        // limit() twice keeps the last value and one placeholder.
        ['mysql', function ($q) { $q->select('id', 'users')->limit(5)->limit(2); },
            'SELECT `id` FROM `users` LIMIT ? | [2] | i'],
        // where() between two paging calls, then a group.
        ['mysql', function ($q) {
            $q->select('id', 'users')->limit(3)->where('a', '=', 1)->offset(6)->where(function ($g) {
                $g->where('b', '=', 2)->orWhere('c', '=', 3);
            });
        }, 'SELECT `id` FROM `users` WHERE `a` = ? AND (`b` = ? OR `c` = ?) LIMIT ? OFFSET ? | [1,2,3,3,6] | iiiii'],
        // resetLimitOffset() must not remove a where binding.
        ['mysql', function ($q) { $q->select('id', 'users')->limit(1)->where('id', '=', 7)->resetLimitOffset(); },
            'SELECT `id` FROM `users` WHERE `id` = ? | [7] | i'],
        // Oracle puts OFFSET before FETCH FIRST.
        ['oci', function ($q) { $q->select('id', 'users')->where('status', '=', 1)->limit(10)->offset(20); },
            'SELECT "id" FROM "users" WHERE "status" = ? OFFSET ? ROWS FETCH FIRST ? ROWS ONLY | [1,20,10] | iii'],
        // SQL Server: TOP is the first placeholder; with OFFSET it becomes OFFSET ... FETCH NEXT.
        ['sqlsrv', function ($q) { $q->select('id', 'users')->where('status', '=', 1)->limit(10); },
            'SELECT TOP (?) [id] FROM [users] WHERE [status] = ? | [10,1] | ii'],
        ['sqlsrv', function ($q) { $q->select('id', 'users')->where('status', '=', 1)->orderBy('id')->limit(10)->offset(20); },
            'SELECT [id] FROM [users] WHERE [status] = ? ORDER BY [id] ASC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY | [1,20,10] | iii'],
    ];
    $failed = [];
    foreach ($cases as $index => $case) {
        $got = qbTestDump($case[0], $case[1]);
        if ($got !== $case[2]) {
            $failed[] = '#' . $index . ' got: ' . $got;
        }
    }
    return [
        'status' => $failed === [] ? 1 : 0,
        'info' => $failed === [] ? 'Bindings follow the placeholders for every call order' : implode(' ; ', $failed),
        'test_name' => 'Test QueryBuilder binding order',
        'context' => ['core' => true, 'database' => true, 'querybuilder' => true],
    ];
});

Tester::addTest('test_querybuilder_values_never_become_sql', function () {
    $cases = [
        // Shorthand where(column, value): the second argument is always a bound value.
        ['mysql', function ($q) { $q->select('id', 'users')->where('token', ''); },
            'SELECT `id` FROM `users` WHERE `token` = ? | [""] | s'],
        ['mysql', function ($q) { $q->select('id', 'users')->where('note', 'like'); },
            'SELECT `id` FROM `users` WHERE `note` = ? | ["like"] | s'],
        ['mysql', function ($q) { $q->select('id', 'users')->where('token', '='); },
            'SELECT `id` FROM `users` WHERE `token` = ? | ["="] | s'],
        ['mysql', function ($q) { $q->select('id', 'users')->where('token', null); },
            'SELECT `id` FROM `users` WHERE `token` IS NULL | [] | '],
        ['mysql', function ($q) { $q->select('id', 'users')->where('status', 0); },
            'SELECT `id` FROM `users` WHERE `status` = ? | [0] | i'],
        // A one-argument where() stays a hand-written condition.
        ['mysql', function ($q) { $q->select('id', 'users')->where('a > b'); },
            'SELECT `id` FROM `users` WHERE a > b | [] | '],
        // A quote character inside a column name cannot close the quoting.
        ['mysql', function ($q) { $q->select('id', 'users')->where('id` = 1 OR `id', '=', 2); },
            'SELECT `id` FROM `users` WHERE `id`` = 1 OR ``id` = ? | [2] | i'],
        // Operator, direction, list and range arguments are checked.
        ['mysql', function ($q) { $q->select('id', 'users')->where('id', '= 1 OR 1 =', 1); }, 'THROWS InvalidArgumentException'],
        ['mysql', function ($q) { $q->select('id', 'users')->orderBy('id', 'ASC, (SELECT 1)'); }, 'THROWS InvalidArgumentException'],
        ['mysql', function ($q) { $q->select('id', 'users')->whereIn('id', '3'); }, 'THROWS InvalidArgumentException'],
        ['mysql', function ($q) { $q->select('id', 'users')->whereBetween('id', [5]); }, 'THROWS InvalidArgumentException'],
        ['mysql', function ($q) { $q->insert('users', []); }, 'THROWS InvalidArgumentException'],
        ['mysql', function ($q) { $q->update('users')->set([]); }, 'THROWS InvalidArgumentException'],
        // An empty list matches nothing.
        ['mysql', function ($q) { $q->select('id', 'users')->whereIn('id', []); },
            'SELECT `id` FROM `users` WHERE 1 = 0 | [] | '],
        // raw(): a colon inside a string literal is not a parameter.
        ['mysql', function ($q) { $q->raw("SELECT id FROM users WHERE note <> '10:30' AND id = :id", ['id' => 3]); },
            "SELECT id FROM users WHERE note <> '10:30' AND id = ? | [3] | i"],
        ['mysql', function ($q) { $q->raw('SELECT id FROM users WHERE id = :id OR parent = :id', ['id' => 3]); },
            'SELECT id FROM users WHERE id = ? OR parent = ? | [3,3] | ii'],
    ];
    $failed = [];
    foreach ($cases as $index => $case) {
        $got = qbTestDump($case[0], $case[1]);
        if ($got !== $case[2]) {
            $failed[] = '#' . $index . ' got: ' . $got;
        }
    }
    return [
        'status' => $failed === [] ? 1 : 0,
        'info' => $failed === [] ? 'Values are bound and unsafe arguments are refused' : implode(' ; ', $failed),
        'test_name' => 'Test QueryBuilder values and arguments',
        'context' => ['core' => true, 'database' => true, 'querybuilder' => true],
    ];
});
