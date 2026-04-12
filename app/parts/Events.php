<?php
/**
 * Class Events
 *
 * Static facade pre listenery v DotApp: registrácia handlerov, spustenie udalosti,
 * zrušenie jedného listenera (cez objekt z on) alebo všetkých pod menom udalosti,
 * a kontrola, či má udalosť odberateľov.
 *
 * Zodpovedá verejným metódam DotApp okolo `$listeners`: on, trigger, offevent, hasListener.
 * Metóda `off` na DotApp je private — jednotlivý listener sa ruší cez `->off()` na objekte
 * vrátenom z `on()`. Načítanie `module.listeners.php` rieši framework (`load_module_listeners`),
 * nie táto fasáda.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8 FREE
 * @license   MIT License
 * @date      2014 - 2026
 *
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the
 * following condition: You **must** retain this header in all copies or
 * substantial portions of the code, including the author and company information.
 */

/*
    Events — použitie (namiesto DotApp::dotApp()->…)

    1) Základ: udalosť + callback (mená udalostí sú v DotApp normalizované na lowercase)
    ----------------------------------------------------------------
    use Dotsystems\App\Parts\Events;

    $subscription = Events::on('user.registered', function ($result, ...$extra) {
        // trigger() posiela ako prvý argument $result, ďalšie cez ...$data
    });

    Events::trigger('user.registered', $userRow);
    // alebo s ďalšími argumentmi: Events::trigger('order.paid', $order, $invoiceId);

    2) Zrušiť jeden konkrétny listener (ten, čo vráti on)
    ----------------------------------------------------------------
    $subscription = Events::on('cache.invalidate', $callable);
    $subscription->off();   // rovnaké ako DotApp::dotApp()->on(...)->off()

    // ID listenera (ak ho potrebuješ uložiť): $subscription->id

    3) Zrušiť všetky listenery pre dané meno udalosti
    ----------------------------------------------------------------
    Events::offevent('user.registered');

    4) Skontrolovať, či niekto počúva
    ----------------------------------------------------------------
    if (Events::hasListener('report.generate')) {
        Events::trigger('report.generate', $ctx);
    }

    5) Trasa / metóda (registrácia len ak sedí URL alebo GET+URL) — rovnaké arity ako DotApp::on
    ----------------------------------------------------------------
    // Iba ak aktuálna cesta matchne pattern:
    Events::on('/product/*', 'product.sold', function ($result, ...$extra) { });

    // Iba ak HTTP metóda a cesta sedí:
    Events::on('get', '/product/{id:i}', 'product.view', function ($params) { });

    6) Controller reťazec namiesto closure (stringToCallable v DotApp)
    ----------------------------------------------------------------
    Events::on('some.event', 'MyModule:MyController@handler!');
*/

namespace Dotsystems\App\Parts;

use Dotsystems\App\DotApp;

class Events {

    public static function on(...$args) {
        return DotApp::dotApp()->on(...$args);
    }

    public static function trigger($eventname, $result = null, ...$data) {
        return DotApp::dotApp()->trigger($eventname, $result, ...$data);
    }

    public static function offevent($eventname) {
        return DotApp::dotApp()->offevent($eventname);
    }

    public static function hasListener($eventname) {
        return DotApp::dotApp()->hasListener($eventname);
    }
}

?>
