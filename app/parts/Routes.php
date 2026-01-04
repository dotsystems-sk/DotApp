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
		$this->dotApp->router->get('/assets/{modul}/{cesta*}', function($request) {
			$matchdata = $request->matchData();
			
			if ($this->dotApp->moduleExist($matchdata['modul'])) {
				$basePath = __ROOTDIR__ . "/app/modules/" . $matchdata['modul'] . "/assets/";
				
				$realBase = realpath($basePath);
				$requestedPath = realpath($basePath . $matchdata['cesta']);

				if ($requestedPath && $realBase && strpos($requestedPath, $realBase) === 0) {
					
					if (pathinfo($requestedPath, PATHINFO_EXTENSION) === 'php') {
						return $request->response->code(403)->body("Access denied to source files.");
					}

					return $this->dotApp->module($matchdata['modul'])->assets($request, $requestedPath);
				}
				
				return $request->response->code(404)->body("Asset not found.");
			}
		});
		
		$this->dotApp->router->reserved[] = "/assets/{modulename}/{cesta*}";
	}
}

?>
