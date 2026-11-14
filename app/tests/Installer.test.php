<?php
/**
 * Core tests: installer() keys run in PHP array order (no ksort).
 *
 * @package DotApp Framework
 */

namespace Dotsystems\App\Tests;

use Dotsystems\App\Parts\Installer;
use Dotsystems\App\Parts\Tester;

/**
 * Probe installer whose keys would be reordered by ksort (1.0.10 before 1.0.9).
 */
class InstallerOrderProbe extends Installer
{
    /** @var list<string> */
    public static $ran = [];

    /**
     * Intentionally lists 1.0.10 before 1.0.9 so ksort would change the order.
     *
     * @return array<string, callable>
     */
    public static function installer()
    {
        return [
            '1.0.10' => function () {
                self::$ran[] = '1.0.10';
            },
            '1.0.9' => function () {
                self::$ran[] = '1.0.9';
            },
            '1.0.8' => function () {
                self::$ran[] = '1.0.8';
            },
        ];
    }

    /**
     * Uninstall keys in written order; reverse foreach must not krsort.
     *
     * @return array<string, callable>
     */
    public static function uninstaller()
    {
        return [
            '1.0.8' => function () {
                self::$ran[] = 'u1.0.8';
            },
            '1.0.9' => function () {
                self::$ran[] = 'u1.0.9';
            },
            '1.0.10' => function () {
                self::$ran[] = 'u1.0.10';
            },
        ];
    }
}

Tester::addTest('Installer install follows installer() array order not ksort', function () {
    $src = (string) file_get_contents(dirname(__DIR__) . '/parts/Installer.php');
    InstallerOrderProbe::$ran = [];
    (new InstallerOrderProbe('Probe'))->install();
    $order = InstallerOrderProbe::$ran;
    InstallerOrderProbe::$ran = [];
    (new InstallerOrderProbe('Probe'))->install('1.0.9');
    $upto = InstallerOrderProbe::$ran;
    InstallerOrderProbe::$ran = [];
    (new InstallerOrderProbe('Probe'))->uninstall();
    $down = InstallerOrderProbe::$ran;
    $ok = strpos($src, 'ksort($migrations)') === false
        && strpos($src, 'krsort($migrations)') === false
        && strpos($src, 'uksort($migrations)') === false
        && $order === ['1.0.10', '1.0.9', '1.0.8']
        && $upto === ['1.0.9', '1.0.8']
        && $down === ['u1.0.10', 'u1.0.9', 'u1.0.8'];

    return [
        'status' => $ok ? 1 : 0,
        'info' => $ok
            ? 'install() foreach keeps written order; uninstall() reverses that map; no ksort on migrations'
            : 'Installer still sorts migration keys or skips later matching versions',
        'test_name' => 'Installer install follows installer() array order not ksort',
        'context' => ['core' => true, 'installer' => true],
    ];
});
