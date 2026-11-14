<?php
/*
    Globalne listeners - jednotlive moduly si mozu zaregistrovat globalne listenery / je mozne aj manualne upravovat tento subor
    self::registerGlobalListener("udalost","modul"); - loadneme modul s danym nazvom. SLuzi vtedy ak vytvarame modul, ktory sa ma nacitat spolu s inym modulom. Napriklad mame frontend modul, a chceme aby sa do neho vladalo cookie consent takze aktivujeme ho len vtedy, ak je aktivny frontendovy
    self::registerGlobalListener("udalost","modul:controller"); - zavolame kontroler v danom module. Cize dojde k aktivacii modulu + zavola sa kontroler

    Staci ak tuto funkciu moduly zavolaju pri svojom prvom spusteni.
    Priklad, majme modul CookieConsent, a chceme aby sa spustil stale ked sa spusti aj FrontendCMS. V module CookieConsent v module.init.php:
    
    public function initialize($dotapp) {
        $settings = $this->settings();
        $firstRun = $settings['firstRun'] ?? true;
        if ($firstRun === true) {
            // Zapiseme defaultne nastavneia, ak ich nepotrbeujeme zapiseme si len ze uz bolo spustene
            $this->settings(['firstRun' => false]);
            self::registerGlobalListener("dotapp.module.FrontendCMS.loaded","CookieConsent"); - Loadneme modul CookieConsent
        }
        
        // .... Vas kod
    }

*/

?>