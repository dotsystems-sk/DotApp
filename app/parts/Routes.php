<?php

namespace Dotsystems\App\Parts;

class Routes {
	private $dotApp;

	function __construct($dotApp) {
		$this->dotApp = $dotApp;
		$this->defaultRoutes();
	}
	// Defaultne routy pre beh dotApp
	private function defaultRoutes() {
		/*
			-- Obsadene cez BRIDGE
			dotApp.js framework JS - /assets/dotapp/dotapp.js
			API point -> /dotapp/bridge
		*/
		$this->assets();
	}
	
	private function assets() {
		// nahradene triedou Assets
        // Ale zvysok nechavame tu. Cize ak subor neexistuje, ak ho trieda assets nenasla v assetoch modulu, tak potom este moze uzivatel si ho nastavit v routeri.
		$this->dotApp->router->get('/assets/{modul}/{cesta*}', function($request) {
			$matchdata = $request->matchData();
            if ($this->dotApp->moduleExist($matchdata['modul'])) {
                $matchdata['cesta'] = str_replace("../","",$matchdata['cesta']);
                $matchdata['cesta'] = str_replace("./","",$matchdata['cesta']);
                $cesta = __ROOTDIR__ . "/app/modules/" . $matchdata['modul'] . "/assets/" . $matchdata['cesta'];
                
                // Zavolame
                return $this->dotApp->module($matchdata['modul'])->assets($request,$cesta);

            }
		});
		
		$this->dotApp->router->reserved[] = "/assets/{modulename}/{cesta*}";
	}
}

?>