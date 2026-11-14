<?php
/**
 * Inert module fixture for base-language loader tests.
 *
 * @package DotApp Framework
 */

namespace Dotsystems\App\Modules\BaseLanguageProbe;

class Module extends \Dotsystems\App\Parts\Module
{
    public static $initializeCalls = 0;

    /**
     * Record an unexpected full module initialization.
     *
     * @param mixed $dotapp Unused DotApp instance.
     * @return void
     */
    public function initialize($dotapp)
    {
        unset($dotapp);
        self::$initializeCalls++;
    }

    /**
     * Keep the fixture free of base translation file I/O.
     *
     * @return array<int, array{file: string, locale: string}> Empty descriptor list.
     */
    public function baseLanguages()
    {
        return [];
    }

    /**
     * Keep the fixture asleep on every route.
     *
     * @return array<int, string> Empty route map.
     */
    public function initializeRoutes()
    {
        return [];
    }
}
