<?php

/**
 * Class ROUTER
 * 
 * This class is responsible for handling routing in the DotApp framework. It manages different 
 * HTTP request methods such as GET, POST, and ANY, allowing you to define routes for various 
 * endpoints in your application. 
 * 
 * The router class ensures that specific controllers or functions are executed when a 
 * corresponding route is matched, supporting flexible routing configurations for your web application.
 * 
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.7 FREE
 * @license   MIT License
 * @date      2014 - 2025
 * 
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the 
 * following condition: You **must** retain this header in all copies or 
 * substantial portions of the code, including the author and company information.
 */

/*
    Router Class Usage:

    The router class is responsible for handling all routing logic in your application. 
    It supports common HTTP methods like GET, POST, and ANY, allowing for versatile route 
    definitions.

    Examples:
    - Define a GET route:
      `$dotapp->router->get('/home', function() { ... });`
    
    - Define a POST route:
      `$dotapp->router->post('/submit', function() { ... });`
    
    - Use ANY to match all HTTP methods:
      `$dotapp->router->any('/api', function() { ... });`

    The router ensures that the correct controller or callback function is executed based on 
    the matching route and request method.
*/


namespace Dotsystems\App\Parts;
use Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\Middleware;
use \Dotsystems\App\Parts\Limiter;



class RouterObj {
	/* Pole kde budu ulozene vsetky routy podelene do GET, POST */
	protected $routy; // Tu su ulozene route vo svojom povodnom tvare
    protected $obsadene_routy; // Su nutne pretoze potrbeujeme ukladat aj celu url ak je get volany pomocou {premenna} / {*} - proste prva routa plati, zvysok ignorujeme
	public $request;
    public $Request;
	public $renderer;
    public $Renderer;
	public $dotapp;
    public $dotApp;
    public $DotApp;
	private $dotAppObj;
	private $dir;
	public $reserved;
	private $hooks; // Before, After 
	private $chain; // Just store for functions...
	private $ratelimiter; // Rate limiting for urls...
	private $thendata;
    private $match_cache; // Pouzivame ako MEMORY CACHE
    private $match_cache_use; // Pouzivame mem cache?
    private $match_cache_file;
    private $match_cache_maxsize;
    private $route_matched; // Match routy uz nastal? Ak ano, dropneme zvysok
    private $matchdata;
    private $emptyChain; // Aby sme setrili pamat, nebudeme zakazdym tvorit prazdny objekt a vraciat ho ako referenciu ale pouzijeme tento
    private $patternCache;
	
	function __construct($dotAppObj=null) {
        $this->patternCache = array();
        $this->emptyChain = $this->routeChain(false,"");
        $this->match_cache_maxsize = 100;
        $this->match_cache_use = Config::router('match_cache');
        $this->hooks = array();
        $this->match_cache_file = "";
        $this->match_cache = array();
		$this->dotAppObj = DotApp::dotApp();
		$this->dotapp = $dotAppObj;
        $this->dotApp = $dotAppObj;
        $this->DotApp = $dotAppObj;
        $this->request = $dotAppObj->request;
        $this->Request = $dotAppObj->request;
		$this->renderer = new Renderer($dotAppObj);
        $this->Renderer = $this->renderer;
		$this->dir = __ROOTDIR__."/app/parts/Controllers/";
		$this->reserved = array();
		$this->clear_chain();
		$this->thendata = array();
        $this->routy = array();
        $this->obsadene_routy = array();
        $this->obsadene_routy['any'] = array();
        $this->obsadene_routy['get'] = array();
        $this->obsadene_routy['post'] = array();
        $this->obsadene_routy['put'] = array();
        $this->obsadene_routy['delete'] = array();
        $this->obsadene_routy['patch'] = array();
        $this->obsadene_routy['options'] = array();
        $this->obsadene_routy['head'] = array();
        $this->obsadene_routy['trace'] = array();
		if ($this->dotapp->dsm->get('_router.ratelimiter.counters') != null) {
			$this->ratelimiter['counters'] = $this->dotapp->dsm->get('_router.ratelimiter.counters');
		} else {
			$this->ratelimiter = array();
			$this->ratelimiter['counters'] = array();
		}
		$this->set_default_limiter();
        $this->route_matched = false;
        $this->matchdata = array();
        $this->matchdata['matched'] = array(); // Data po matchovani, ktore pojdu do hookov a middleware
        $this->matchdata['helper'] = array(); // Data po akomkolvek matchovani, sluzia len ako helper
    }

	function __destruct() {
		$this->dotapp->dsm->set('_router.ratelimiter.counters', $this->ratelimiter['counters']);
	}
	
	public function __debugInfo() {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }

	public function new_renderer() {
		return new Renderer($this->dotapp);
	}
	
	// Pre stare aplikacie, kompatibilita, bude odstranena do buducna.C
	public function resources($cesta,$callback) {
		$cesta = $cesta."//";
		$cesta = str_replace("///","",$cesta);
		$cesta = str_replace("//","",$cesta);
		$this->routy['resources'][$cesta] = $callback;
		$this->routy['resourcespath'][$cesta] = $this->dir;
	}

	// Pre novsie verzie, bude podporovana do buducna
    public function apiPoint($verzia, $modul, $controller, $custom = null) {
		$apiRouty = array();
		
		if ($custom === null) {
			// Defaultna routa vhodna pre vacsinu pripadov
			$apiRouty[] = "/api/v".$verzia."/".$modul."/{resource}(?:/{id})?";
		} elseif (is_array($custom)) {
			// Uzivatel vie co robi a zadava vlastne routy.
			foreach ($custom as $value) {
				$apiRouty[] = "/api/v".$verzia."/".$modul."/".$value;
			}
		} else {
			$apiRouty[] = "/api/v".$verzia."/".$modul."/".$custom;
		}

		$this->any($apiRouty, $controller);
	}

    public function controller($cesta,$callback) {
        // Just alias, as it is easier to remember.
        $this->resources($cesta,$callback);
    }  
	
	public function module($name) {
		$this->dir = __ROOTDIR__."/app/modules/".$name."/Controllers/";
		return $this;
	}
	
	/*
		get('/user/{firsttext}/{secondtext}',function() {
			//main logic here
		}).before()
		$route = '/user/article/how-to-cook-eggs';	- static route ( priority 1 )
		$route = '/user/{firsttext}/{secondtext}'; - route with variables ( priority 3 )
		example: 
		$route = '/user/category/12';
		$matchdata['firsttext'] = 'category';
		$matchdata['secondtext'] = '12';

		before:
			$dotapp,$matchdata
		hlavna logika: 
			$dotapp,$matchdata
		after:
			$dotapp,$navrat,$matchdata
	*/

	private function clear_chain() {
		$this->chain = array();
		$this->chain['method'] = array();
		$this->chain['path'] = array();
		return $this;
	}

    // Sluzi ako obal, ktory spusti vsetky chainy aby sa spravne ku kazdej route poznacili BEFORE a AFTER HOOKY teda middleware
    private function dotapperRouteChain($metoda,$routa,$chain=null) {
        // Dorobime funkciu, ktora obali routeChain magickymi metodami aby sme sa dostali k hookom
        return new class($metoda,$routa,$this,$chain) extends \stdClass {
            public $chainy=array();

            public function __construct($metoda,$routa,$router,$chain=null) {
                if ($chain !== null) {
                    if (isSet($chain->chainy) && !empty($chain->chainy)) {
                        //preberieme chainy z predchadzajuceho objektu...
                        $this->chainy = $chain->chainy;
                    }
                }
                $this->chainy[] = $router->routeChain($metoda,$routa);
            }

            public function __call($name, $arguments) {
                $navrat = null;
                foreach ($this->chainy as $chain) {
                    if ($navrat === null) {
                        $navrat = call_user_func_array([$chain, $name], $arguments);
                        // Ak vratime chain tak to prepiseme za aktualny objekt pretoze je to dolezite.
                        if ($navrat === $chain) $navrat = $this;
                    } else {
                        call_user_func_array([$chain, $name], $arguments);
                    }
                }
                return $navrat;
            }

        };
    }

    public function routeChain($metoda,$routa) {
        return new class($metoda,$routa,$this) extends \stdClass {
            public $metoda;
            private $routa;
            private $router;
            private $exceedCallback;
            public $limiter;

            public function __construct($metoda,$routa,$router) {
                $this->metoda = $metoda;
                $this->routa = $routa;
                $this->router = $router;
                $this->limiter = false;
                $this->exceedCallback = null; // Zavolame ak prekrocime
            }

            public function __call($name, $arguments) {
                // Cisto kvoli debbugovaniu aby som vedel co sa vola. Koncime nadobro s php 5.6 a musime nejak poriesit stare funkcie modernejsie.
                if ($this->metoda === false) return $this;
                if (!method_exists($this, $name)) {
                    throw new \BadMethodCallException("Method $name does not exist");
                }
                return call_user_func_array([$this, $name], $arguments);
            }

            public function middleware($fn) {
                return $this->before($fn);
            }
    
            public function before($fn) {
                if ($this->metoda === false && !defined('__DOTAPPER_RUN__')) return $this;
                if (is_array($this->routa)) {
                    foreach ($this->routa as $routa) $this->router->hooksFn($this,"before",$this->metoda,$routa,$fn,$this->limiter);
                } else $this->router->hooksFn($this,"before",$this->metoda,$this->routa,$fn,$this->limiter);
                return $this;
            }
    
            public function after($fn) {
                if ($this->metoda === false && !defined('__DOTAPPER_RUN__')) return $this;
                if (is_array($this->routa)) {
                    foreach ($this->routa as $routa) $this->router->hooksFn($this,"after",$this->metoda,$routa,$fn,$this->limiter);
                } else $this->router->hooksFn($this,"after",$this->metoda,$this->routa,$fn,$this->limiter);
                return $this;
            }

            // Pri pouziti RouterCache zrekonstruujeme kompletne cele volanie vratane limitera
            public function useLimiter(array $limity, string $limiterID) {
                $this->limiter = new Limiter($limity,$limiterID,DotApp::dotApp()->limiter['getter'],DotApp::dotApp()->limiter['setter']);
                $this->router->request->response->limiter = $this->limiter;
            }

            public function throttle(array $limity) {
                if ($this->metoda === false && !defined('__DOTAPPER_RUN__')) return $this;
                if (is_array($this->routa)) {
                    $limiterID = md5($this->metoda.implode(",",$this->routa));
                } else $limiterID = md5($this->metoda.$this->routa);
                
                $this->limiter = new Limiter($limity,$limiterID,$this->router->dotapp->limiter['getter'],$this->router->dotapp->limiter['setter']);
                $this->router->request->response->limiter = $this->limiter;
                if (defined('__DOTAPPER_RUN__')) {
                    $metodaInner = [$this->metoda];
                    if (is_array($this->metoda)) $metoda = $this->metoda;
                    $serialized = array();
                    $serialized['limiter'] = true;
                    $serialized['limiterID'] = $this->limiter->identifier();
                    $serialized['limiterLimits'] = $limity;
                    if (is_array($this->routa)) {
                        foreach ($this->routa as $cesta1) {
                            foreach($metodaInner as $metoda1) DotApp::dotApp()->dotapper['RouteByURL'][$cesta1][$metoda1] = array_merge(DotApp::dotApp()->dotapper['RouteByURL'][$cesta1][$metoda1], $serialized);
                        }
                    } else {
                        foreach($metodaInner as $metoda1) DotApp::dotApp()->dotapper['RouteByURL'][$this->routa][$metoda1] = array_merge(DotApp::dotApp()->dotapper['RouteByURL'][$this->routa][$metoda1], $serialized);
                    }
                }
                return $this;
            }

            public function limitExceeded(callable $callback) {
                if ($this->metoda === false && !defined('__DOTAPPER_RUN__')) return $this;
                $this->exceedCallback = $callback;
                return $this;
            }

            public function limitExceededRun() {
                if (is_callable($this->exceedCallback)) {
                    $this->router->request->response->limiter = $this->limiter;
                    call_user_func($this->exceedCallback,$this->router->request);
                } else {
                    $json = json_encode($this->limiter->getLimitHeaders($this->routa));
                    http_response_code(429);
                    echo $json;
                    exit();
                }
            }

            /*
                Toto su stare funkcie, stary limiter ostava kvoli kompatiblite starych aplikacii
                treba uz ale pouzivat throttle a limitExceeded
            */
            public function rateLimiter($limiter, ...$limiterdata) {
                if ($this->metoda === false) return $this;
                $this->rate_limit($limiter, ...$limiterdata);
                return $this;
            }

            public function rateLimit($limiter, ...$limiterdata) {
                if ($this->metoda === false) return $this;
                $this->rate_limit($limiter, ...$limiterdata);
                return $this;
            }

            public function rate_limit($limiter, ...$limiterdata) {
                if ($this->metoda === false) return $this;
                // Zavolame limiter s pevne nastavenou routou pre tento konkretny objekt.
                $this->router->rate_limit($limiter, $limiterdata[0],$limiterdata[1],$this->routa);
                return $this;
            }

            public function onLimit($callback = null) {
                if ($this->metoda === false) return $this;
                $this->router->onLimit($callback,$this->routa);
                return $this;
            }

            /*
                Toto su stare funkcie, stary limiter ostava kvoli kompatiblite starych aplikacii            
            */
        };
    }

    public function any($cesta,$callback,$static=false) {
        return $this->match(['any'],$cesta,$callback,$static);        
	}

    public function get($cesta,$callback,$static=false) {
        return $this->match(['get'],$cesta,$callback,$static);
	}
	
	public function post($cesta,$callback,$static=false) {
        return $this->match(['post'],$cesta,$callback,$static);
	}

    public function put($cesta,$callback,$static=false) {
        return $this->match(['put'],$cesta,$callback,$static);
	}

    public function delete($cesta,$callback,$static=false) {
        return $this->match(['delete'],$cesta,$callback,$static);
	}

    public function patch($cesta,$callback,$static=false) {
        return $this->match(['patch'],$cesta,$callback,$static);
	}

    public function options($cesta,$callback,$static=false) {
        return $this->match(['options'],$cesta,$callback,$static);
	}

    public function head($cesta,$callback,$static=false) {
        return $this->match(['head'],$cesta,$callback,$static);
	}

    public function trace($cesta,$callback,$static=false) {
        return $this->match(['trace'],$cesta,$callback,$static);
	}

    public function reset() {
        $this->route_matched = false;
    }

    private function uniMethod($cesta,$callback,$static=false,$method="get",$fromArray=false) {
        $method = strtolower($method);
        $validMethods = ['any','get', 'post', 'put', 'delete', 'patch', 'options', 'head','trace'];
        if (!in_array($method,$validMethods)) throw new \InvalidArgumentException("Unknown REQUEST METHOD !");
        $this->chain['method'] = $method;
		$this->chain['path'] = $cesta;
        if ($this->route_matched) return $this->emptyChain;
        if (is_array($cesta)) {
            foreach ($cesta as $cestav) {
                $chain = $this->uniMethod($cestav,$callback,$static,$method,true);
                if ($chain->metoda !== false) {
                    return $chain;
                }
            }
            return $this->emptyChain;
        }
        $chain = $this->emptyChain;
		$this->clear_chain();
		if ( ($static && $cesta == $this->request->getPath()) || ( !$static && $this->route_allow($method,$cesta)) ) {
            $this->route_matched = true;
            // Ako obsadenu routu musime ratat aktualnu cestu ale zachovat musime aj originalny vyraz
            ($this->request->getPath() != $cesta) ? $this->obsadene_routy[$method][] = $this->request->getPath() : $this->obsadene_routy[$method][] = $cesta;
            $chain = $this->routeChain($method,$cesta);
			if (is_callable($callback)) {
                $callback_s_di = function(...$args) use ($callback,$chain,$cesta) {
                    if ($chain->limiter === false) {
                        return $this->dotapp->di(null, $callback, $args);
                    } else {
                        if ($chain->limiter->isAllowed($cesta)) {
                            return $this->dotapp->di(null, $callback, $args);
                        } else {
                            $chain->limitExceededRun();
                        }
                    }                    
                };
                //$callback_s_di = $callback_s_di->bindTo($this->dotapp, get_class($this->dotapp));
                $this->routy[$method][$cesta] = $callback_s_di;
            } else {
                $callback_fn = function(...$args) use ($callback,$chain,$cesta) {
                    if ($chain->limiter === false) {
                        $toCall = $this->dotapp->stringToCallable($callback);
                        //return call_user_func($toCall,$callback);
                        return $this->dotapp->di(null, $toCall, $args);
                    } else {
                        if ($chain->limiter->isAllowed($cesta)) {
                            $toCall = $this->dotapp->stringToCallable($callback);
                            //return call_user_func($toCall,$callback);
                            return $this->dotapp->di(null, $toCall, $args);
                        } else {
                            $chain->limiter->limitExceededRun();
                        }
                    }
                    
                };
                $this->routy[$method][$cesta] = $callback_fn;
            }
            
			$this->chain['method'] = $method;
			$this->chain['path'] = $cesta;
			if (! isSet($this->ratelimiter[$method][$cesta])) {
				$this->ratelimiter[$method][$cesta] = 0;
			}
			
		}
		$this->thendata['then'] = $cesta;
		$this->thendata['err'] = $cesta;
		$this->thendata['method'] = $method;
		$this->thendata['limit'] = $cesta;
		return $chain;
	}

    public function matched() {
        return $this->route_matched;
    }

    public function match(array $method,$cesta,$callback,$static=false) {
        if (defined('__DOTAPPER_RUN__')) {
            $serialized = array();
            $cesta2 = $cesta;
            if (is_array($cesta)) $cesta2 = implode(",",$cesta);
            if (is_string($callback)) {
                if (strpos($callback,"@") === false) {
                    $serialized['callback'] = $callback;
                    $serialized['callbackType'] = "Invoke";
                    $this->dotapp->dotapper['moduleRoutes'][$this->dotapp->dotapper['routes_module']]['route'][implode(",",$method)][] = $cesta2." -> Invoke: ".$callback;
                    $this->dotapp->dotapper['routes'][implode(",",$method)][] = $cesta2." -> Invoke: ".$callback;
                } else {
                    if (strpos($callback,":") === false) {
                        $serialized['callback'] = $this->dotapp->dotapper['routes_module'].":".$callback;
                    } else {
                        $serialized['callback'] = $callback;
                    }
                    $serialized['callbackType'] = "Controller";
                    $this->dotapp->dotapper['moduleRoutes'][$this->dotapp->dotapper['routes_module']]['route'][implode(",",$method)][] = $cesta2." -> Controller: ".$callback;
                    $this->dotapp->dotapper['routes'][implode(",",$method)][] = $cesta2." -> Controller: ".$callback;
                }
            } else if (is_array($callback)) {
                if (isset($callback['module']) && isset($callback['class']) && isset($callback['function'])) {
                    $serialized['callback'] = $callback['module'].":".$callback['class']."@".$callback['function'];
                    $serialized['callbackType'] = "Controller";
                } else if (isset($callback['class']) && isset($callback['function'])) {
                    $serialized['callback'] = $this->dotapp->dotapper['routes_module'].":".$callback['class']."@".$callback['function'];
                    $serialized['callbackType'] = "Controller";
                } else if (count($callback) == 3) {
                    $serialized['callback'] = $callback[0].":".$callback[1]."@".$callback[2];
                    $serialized['callbackType'] = "Controller";
                } else if (count($callback) == 2) {
                    $serialized['callback'] = $this->dotapp->dotapper['routes_module'].":".$callback[1]."@".$callback[2];
                    $serialized['callbackType'] = "Controller";
                }                
                $this->dotapp->dotapper['moduleRoutes'][$this->dotapp->dotapper['routes_module']]['route'][implode(",",$method)][] = $cesta2." -> Controller: ".print_r($callback,true);
                $this->dotapp->dotapper['routes'][implode(",",$method)][] = $cesta2." -> Controller: ".print_r($callback,true);
            } else {
                $serialized['callback'] = "Closure ()";
                $serialized['callbackType'] = "Closure";
                $this->dotapp->dotapper['moduleRoutes'][$this->dotapp->dotapper['routes_module']]['route'][implode(",",$method)][] = $cesta2." -> Closure()";
                $this->dotapp->dotapper['routes'][implode(",",$method)][] = $cesta2." -> Closure()";
            }
            if (is_array($cesta)) {
                foreach ($cesta as $cesta1) {
                    if (!isset($this->dotapp->dotapper['RouteByURL'][$cesta1])) $this->dotapp->dotapper['RouteByURL'][$cesta1] = array();
                    foreach($method as $metoda) {
                        if (!isset($this->dotapp->dotapper['RouteByURL'][$cesta1][$metoda])) $this->dotapp->dotapper['RouteByURL'][$cesta1][$metoda] = array();
                        $this->dotapp->dotapper['RouteByURL'][$cesta1][$metoda] = $this->doatpperMergeArrays($this->dotapp->dotapper['RouteByURL'][$cesta1][$metoda],$serialized);
                    }
                }
            } else {
                if (!isset($this->dotapp->dotapper['RouteByURL'][$cesta])) $this->dotapp->dotapper['RouteByURL'][$cesta] = array();
                foreach($method as $metoda) {
                    if (!isset($this->dotapp->dotapper['RouteByURL'][$cesta][$metoda])) $this->dotapp->dotapper['RouteByURL'][$cesta][$metoda] = array();
                    $this->dotapp->dotapper['RouteByURL'][$cesta][$metoda] = $this->doatpperMergeArrays($this->dotapp->dotapper['RouteByURL'][$cesta][$metoda],$serialized);
                }
            }
        }
        $navratovametoda = "";
        if (defined('__DOTAPPER_RUN__')) {
            $dotapperChain = null;
            foreach ($method as $metoda) {
                $dotapperChain = $this->dotapperRouteChain($metoda,$cesta,$dotapperChain);
            }
            return $dotapperChain;
        }

        foreach ($method as $metoda) {
            if ($metoda == "any") $metoda = $this->request->getMethod();
            // Mozme mat v poli oc chceme, ale aktualny request method je tak ci tak len jeden :)
            if ($this->request->getMethod() == $metoda) {
                return $this->uniMethod($cesta,$callback,$static,$metoda);
            }
        }
        /*
            Vratime chain aby nedoslo k chybam :) Ale nie je v nich nic.
        */
        return $this->routeChain(false,"");
    }

	/*
		$dotapp->router->get("/clanok/",function() {
		})
		->rate_limit("default",10,20)
		->onLimit(function($metoda,$data) {
	        echo "Nepojdes lebo vela ".print_r($data,true);
        }); 
	*/

	public function rate_limit($limiter, ...$limiterdata) {
		if ($this->thendata['method'] == "any") {
			$this->ratelimiter['limiters'][$this->thendata['limit']]["get"] = $limiter;
			$this->ratelimiter['limiters'][$this->thendata['limit']]["post"] = &$this->ratelimiter['limiters'][$this->thendata['limit']]["get"];
			$this->ratelimiter['limiters_data'][$this->thendata['limit']]["get"] = $limiterdata;
			$this->ratelimiter['limiters_data'][$this->thendata['limit']]["post"] = &$this->ratelimiter['limiters_data'][$this->thendata['limit']]["get"];
		} else {
			$this->ratelimiter['limiters_data'][$this->thendata['limit']][$this->thendata['method']] = $limiterdata;
			$this->ratelimiter['limiters'][$this->thendata['limit']][$this->thendata['method']] = $limiter;
		}		
		return $this;
	}

	private function throttle($limiter,$data) {
		if (! isSet($limiter)) return(true);
		if ($limiter == "") $limiter = "default";
		if (is_callable($this->ratelimiter['limiters_fn'][$limiter])) {
			return $this->ratelimiter['limiters_fn'][$limiter]($data);
		}
		return(false);
	}
	
	private function set_default_limiter() {
		$this->add_limiter("default",function($data) {
			$perminute = $data[0];
			$perhour = $data[1];
            $url = $data[2] ?? $this->thendata['limit'];

			if ($perminute == 0 && $perhour == 0) return(true);

			if (! is_array($this->ratelimiter['counters']['default'])) {
				$this->ratelimiter['counters']['default'] = array();
			}
			
			$method = $this->thendata['method'];
			$counters = &$this->ratelimiter['counters']['default'];

			if (! is_array($counters[$url][$method])) {
				$counters[$url][$method] = array();
			}

			if ($perminute > 0) {
				$clear = 1;
				if ($perhour > 0) $clear = 0;
				$inminute = $this->check_clicks_in_time($counters[$url][$method],60,$clear);
				if ($inminute >= $perminute) return false;
			}

			if ($perhour > 0) {
				$inhour = $this->check_clicks_in_time($counters[$url][$method],3600,1);
				if ($inhopur >= $perhour) return false;
			}

			$timekey = ceil(microtime(true)*100);
			$counters[$url][$method][$timekey] = 1;

			return true;
		});
	}

	private function check_clicks_in_time(&$timelist,$time,$clear=0) {
        if (! is_array($timelist)) return(0);

        // Vypocitame si cas od ktoreho chceme spicitat pocet kliknuti az po sucasnost.
        $time_start = ceil(microtime(true)*100) - $time*100;

        $clickCount = array_filter($timelist, function($clicktime) use ($time_start) {
            return $clicktime > $time_start;
        }, ARRAY_FILTER_USE_KEY);

        // Clear old clicks list...
        if ($clear == 1 ) {
            $timelist = $clickCount;
        }

        // Count the number of clicks in the last hour
        return(count($clickCount));
    }

	public function add_limiter($name,$callback) {
		if (is_callable($callback)) {
			$this->ratelimiter['limiters_fn'][$name] = $callback;
		}
		return $this;
	}

	public function then($callback = "") {
		if (is_callable($callback)) {
			$this->thendata['then'] = $callback($this->thendata['then']);
		}
		return $this;
	}

	public function onLimit($callback = "",$url=null) {
        // Presuvame sa do objektu, dorabame nove vstupne premenne
		if (isset($url) && is_callable($callback)) {
			$this->ratelimiter['limiters_onlimitCallback'][$url][$this->thendata['method']] = $callback;
		}
        if ($url == null && is_callable($callback)) {
			$this->ratelimiter['limiters_onlimitCallback'][$this->thendata['limit']][$this->thendata['method']] = $callback;
		}
		return $this;
	}

	/*
		Definujeme nejaku funkciu, ktora sa spusti po vykonanim hlavnej logiky. Inak povedane je to kvazi middleware.
		before($function); - Spusti sa s kazdym requestom
		before($route,$function);
		before($method,$route,$function);
	*/
	public function before(...$args) {
        $this->hooksFn($this,"before",...$args);
	}

	/*
		Definujeme nejaku funkciu, ktora sa spusti po vykonanim hlavnej logiky. Inak povedane je to middleware.
        after($function); - Spusti sa s kazdym requestom
		after($route,$function);
		after($method,$route,$function);
	*/
	public function after(...$args) {
        $this->hooksFn($this,"after",...$args);
    }

    private function hooksToCache($obj,$hookname,$method,$cesta,$callback,$throttle=false) {
        if (defined('__DOTAPPER_RUN__')) {
            // Ak mame dynamicku ROUTU tak musime prebehnut cele existujuce pole a pre vsetky matchujuce routy pridat BEFORE a AFTER eventy.
            // Takto bude zarucene spravne ich poradie a zaroven dostaneme spravny vystup pre vsetky routy.
            // Nasledne po vypusteni velkeho finalneho pola prebehne v dotapperi jeho optimalizacia
            $spustac = get_class($obj);
            $chain = false;
            if (strpos($spustac,"stdClass@anonymous") !== false && strpos($spustac,"RouterObj.php") !== false) {
                $chain = true; // Volane z chainu
            }
            if (strpbrk($cesta, '{:*?}') === false) {
                // Hooky pre staticke cesty...
                $this->hooksToCacheRun($hookname,$method,$cesta,$callback,$throttle);
            } else {
                // Hooky pre dynamicke cesty...
                
                // Ak som z chainu, nesmieme aplikovat na vsetky routy...
                if ($chain === true) {
                    $this->hooksToCacheRun($hookname,$method,$cesta,$callback,$throttle);
                        
                }

                // Ak som globalne definovany BEFORE a AFTER hook, musim byt poznaceny do globalnych hookov
                if ($chain === false) {
                    if (is_string($method)) $method = [$method];
                    foreach($method as $metodaR) {
                        if ($metodaR == "any") {
                            $forMerthod = ['get', 'post', 'put', 'delete', 'patch', 'head', 'options'];
                        } else {
                            $forMerthod = [$metodaR];
                        }
                        foreach($forMerthod as $metoda) {
                            if (!isset($this->dotapp->dotapper['GlobalHooks'])) $this->dotapp->dotapper['GlobalHooks'] = array();
                            if (!isset($this->dotapp->dotapper['GlobalHooks'][$cesta])) $this->dotapp->dotapper['GlobalHooks'][$cesta] = array();
                            if (!isset($this->dotapp->dotapper['GlobalHooks'][$cesta][$metoda])) $this->dotapp->dotapper['GlobalHooks'][$cesta][$metoda] = array();
                            $this->dotapp->dotapper['GlobalHooks'][$cesta][$metoda] = $this->doatpperMergeArrays($this->dotapp->dotapper['GlobalHooks'][$cesta][$metoda],$this->hooksToCacheRun($hookname,$metoda,$cesta,$callback,$throttle,true));
                        }
                    }
                }
                
            }
        }
        
    }

    private function hooksToCacheRun($hookname,$method,$cesta,$callback,$throttle=false,$return =false) {
        if (is_string($method)) $method = [$method];
        if (defined('__DOTAPPER_RUN__')) {
            $serialized = array();
            $cesta2 = $cesta;
            if (is_array($cesta)) $cesta2 = implode(",",$cesta);
            if (is_string($callback)) {
                if (strpos($callback,"@") === false) {
                    $serialized[$hookname] = $callback;
                    $serialized[$hookname.'Type'] = "Invoke";
                    if ($return) return $serialized;
                    $this->dotapp->dotapper['moduleRoutes'][$this->dotapp->dotapper['routes_module']]['route'][implode(",",$method)][] = $cesta2." -> Invoke: ".$callback;
                    $this->dotapp->dotapper['routes'][implode(",",$method)][] = $cesta2." -> Invoke: ".$callback;
                } else {
                    if (strpos($callback,":") === false) {
                        $serialized[$hookname] = $this->dotapp->dotapper['routes_module'].":".$callback;
                    } else {
                        $serialized[$hookname] = $callback;
                    }
                    $serialized[$hookname.'Type'] = "Controller";
                    if ($return) return $serialized;
                    $this->dotapp->dotapper['moduleRoutes'][$this->dotapp->dotapper['routes_module']]['route'][implode(",",$method)][] = $cesta2." -> Controller: ".$callback;
                    $this->dotapp->dotapper['routes'][implode(",",$method)][] = $cesta2." -> Controller: ".$callback;
                }
            } else if (is_array($callback)) {
                if (isset($callback['module']) && isset($callback['class']) && isset($callback['function'])) {
                    $serialized[$hookname] = $callback['module'].":".$callback['class']."@".$callback['function'];
                    $serialized[$hookname.'Type'] = "Controller";
                    if ($return) return $serialized;
                } else if (isset($callback['class']) && isset($callback['function'])) {
                    $serialized[$hookname] = $this->dotapp->dotapper['routes_module'].":".$callback['class']."@".$callback['function'];
                    $serialized[$hookname.'Type'] = "Controller";
                    if ($return) return $serialized;
                } else if (count($callback) == 3) {
                    $serialized[$hookname] = $callback[0].":".$callback[1]."@".$callback[2];
                    $serialized[$hookname.'Type'] = "Controller";
                    if ($return) return $serialized;
                } else if (count($callback) == 2) {
                    $serialized[$hookname] = $this->dotapp->dotapper['routes_module'].":".$callback[1]."@".$callback[2];
                    $serialized[$hookname.'Type'] = "Controller";
                    if ($return) return $serialized;
                }                
                $this->dotapp->dotapper['moduleRoutes'][$this->dotapp->dotapper['routes_module']]['route'][implode(",",$method)][] = $cesta2." -> Controller: ".print_r($callback,true);
                $this->dotapp->dotapper['routes'][implode(",",$method)][] = $cesta2." -> Controller: ".print_r($callback,true);
            } else if ($callback instanceof Middleware) {
                $serialized[$hookname] = json_decode($callback->name,true);
                $serialized[$hookname.'Type'] = "Middleware";
                if ($return) return $serialized;
            } else if ($callback instanceof MiddlewareChain) {
                $serialized[$hookname] = json_decode($callback->middleware->name);
                $serialized[$hookname.'Type'] = "Middleware";
                if ($return) return $serialized;
            } else {
                $serialized[$hookname] = "Closure ()";
                $serialized[$hookname.'Type'] = "Closure";
                if ($return) return $serialized;
                $this->dotapp->dotapper['moduleRoutes'][$this->dotapp->dotapper['routes_module']]['route'][implode(",",$method)][] = $cesta2." -> Closure()";
                $this->dotapp->dotapper['routes'][implode(",",$method)][] = $cesta2." -> Closure()";
            }
            if (is_array($cesta)) {
                foreach ($cesta as $cesta1) {
                    foreach($method as $metodaR) {
                        if ($metodaR == "any") {
                            $forMerthod = ['get', 'post', 'put', 'delete', 'patch', 'head', 'options'];
                        } else {
                            $forMerthod = [$metodaR];
                        }
                        foreach($forMerthod as $metoda) {
                            if (!isset($this->dotapp->dotapper['RouteByURL'][$cesta1])) $this->dotapp->dotapper['RouteByURL'][$cesta1] = array();
                            if (!isset($this->dotapp->dotapper['RouteByURL'][$cesta1][$metoda])) $this->dotapp->dotapper['RouteByURL'][$cesta1][$metoda] = array();
                            $this->dotapp->dotapper['RouteByURL'][$cesta1][$metoda] = $this->doatpperMergeArrays($this->dotapp->dotapper['RouteByURL'][$cesta1][$metoda], $serialized);
                        }
                    }
                }
            } else {
                foreach($method as $metodaR) {
                    if ($metodaR == "any") {
                        $forMerthod = ['get', 'post', 'put', 'delete', 'patch', 'head', 'options'];
                    } else {
                        $forMerthod = [$metodaR];
                    }
                    foreach($forMerthod as $metoda) {
                        if (!isset($this->dotapp->dotapper['RouteByURL'][$cesta])) $this->dotapp->dotapper['RouteByURL'][$cesta] = array();
                        if (!isset($this->dotapp->dotapper['RouteByURL'][$cesta][$metoda])) $this->dotapp->dotapper['RouteByURL'][$cesta][$metoda] = array();
                        $this->dotapp->dotapper['RouteByURL'][$cesta][$metoda] = $this->doatpperMergeArrays($this->dotapp->dotapper['RouteByURL'][$cesta][$metoda], $serialized);
                    }
                }
            }
        }
    }

    public function doatpperMergeArrays($array1,$array2) {
        // Riesime hooky before a after
        if (isset($array2['before'])) {
            $realAdd = array();
            if (isset($array1['before'])) $realAdd = $array1['before'];
            $realAdd[] = $array2['before'];
            $realAddType = array();
            if (isset($array1['beforeType'])) $realAddType = $array1['beforeType'];
            $realAddType[] = $array2['beforeType'];
            $array1['before'] = $realAdd;
            $array1['beforeType'] = $realAddType;
            return $array1;
        }
        if (isset($array2['after'])) {
            $realAdd = array();
            if (isset($array1['after'])) $realAdd = $array1['after'];
            $realAdd[] = $array2['after'];
            $realAddType = array();
            if (isset($array1['afterType'])) $realAddType = $array1['afterType'];
            $realAddType[] = $array2['afterType'];
            $array1['after'] = $realAdd;
            $array1['afterType'] = $realAddType;
            return $array1;
        }
        return array_merge($array1,$array2);
    }

    public function hooksFn($obj,$hookname,...$args) {
        
        $newfn = array();
        $newfn['fn'] = null;
        $newfn['data'] = array();
        
		if (count($args) == 1) {
            $this->hooksToCache($obj,$hookname,"any","*",$args[0]);
            if (!is_callable($args[0])) $args[0] = $this->dotapp->stringToCallable($args[0]);
			if (is_callable($args[0])) {
				$newfn['fn'] = $args[0];
                $newfn['route'] = "";
                $newfn['data'] = array();
                $fn_s_di = function(...$args) use ($newfn) {
                    return $this->dotapp->di(null, $newfn['fn'], $args);
                };
                $newfn['fn'] = $fn_s_di;
				$this->hooks[$hookname][] = $newfn;
                
			} else {
				throw new \InvalidArgumentException("Incorrect input ! Input must be function !");
			}
		} else $this->clear_chain();

		if (count($args) == 2) {
            $this->hooksToCache($obj,$hookname,"any",$args[0],$args[1]);
            if (is_array($args[0])) {
                foreach ($args[0] as $argval) $this->hooksFn($hookname,$argval,$args[1]);
                return $this;
            }
			if ($this->route_allow("any",$args[0],1)) {
                if (!is_callable($args[1])) $args[1] = $this->dotapp->stringToCallable($args[1]);
				if (is_callable($args[1])) {
					    $newfn['fn'] = $args[1];
                        $newfn['route'] = $args[0];
                        $newfn['data'] = $this->matchdata['matched'];
                        $fn_s_di = function(...$args) use ($newfn) {
                            return $this->dotapp->di(null, $newfn['fn'], $args);
                        };
                        $newfn['fn'] = $fn_s_di;
						$this->hooks[$hookname][] = $newfn;
				} else {
					throw new \InvalidArgumentException("Incorrect input ! Input must be function !");
				}
			}			
		}

		if (count($args) == 3) {
            $this->hooksToCache($obj,$hookname,$args[0],$args[1],$args[2]);
            if (is_array($args[1])) {
                foreach ($args[1] as $argval) $this->hooksFn($hookname,$args[0],$argval,$args[2]);
                return $this;
            }
			if ($this->route_allow($args[0],$args[1],1)) {
                if (!is_callable($args[2])) $args[2] = $this->dotapp->stringToCallable($args[2]);
				if (is_callable($args[2])) {                        
					    $newfn['fn'] = $args[2];
                        $newfn['route'] = $args[1];
                        $newfn['data'] = $this->matchdata['matched'];
                        $fn_s_di = function(...$args) use ($newfn) {
                            return $this->dotapp->di(null, $newfn['fn'], $args);
                        };
                        $newfn['fn'] = $fn_s_di;
                        $this->hooks[$hookname][] = $newfn;
				} else {
					throw new \InvalidArgumentException("Incorrect input ! Input must be function !");
				}
			}
		}

        // Throttle !
        if (count($args) == 4) {
            $this->hooksToCache($obj,$hookname,$args[0],$args[1],$args[2],$args[3]);
            $limiter = $args[3];
            if (is_array($args[1])) {
                foreach ($args[1] as $argval) $this->hooksFn($hookname,$args[0],$argval,$args[2]);
                return $this;
            }
			if ($this->route_allow($args[0],$args[1],1)) {
                if (!is_callable($args[2])) $args[2] = $this->dotapp->stringToCallable($args[2]);
				if (is_callable($args[2])) {                        
					    $newfn['fn'] = $args[2];
                        $newfn['route'] = $args[1];
                        $newfn['data'] = $this->matchdata['matched'];
                        $fn_s_di = function(...$args) use ($newfn,$limiter) {
                            return $this->dotapp->di(null, $newfn['fn'], $args);
                        };
                        $newfn['fn'] = $fn_s_di;
                        $this->hooks[$hookname][] = $newfn;
				} else {
					throw new \InvalidArgumentException("Incorrect input ! Input must be function !");
				}
			}
		}

		return($this);
	}

	public function errorHandle($code,$view) {
		$this->routy['error'][$code] = $view;
	}

	public function actual_path() {
		return($this->request->getPath());
	}

    public function hasRoute(...$args) {
        if (count($args) < 3) {
            if ($this->route_allow(...$args)) {
                return false;
            }
            return true;
        }        
    }

	/*
		Povolime vlozenie routy?
		route_allow($path);
		route_allow($method,$path);
        route_allow($method,$path,$ignoruj_obsadene); - Toto pouzivame len v ramci vnutornych funkcii
	*/
	private function route_allow(...$args) {

		if (count($args) == 1) {
			if (in_array($args[0],$this->reserved)) return(false);
            if (in_array($args[0],$this->obsadene_routy['get'])) return(false);
            if (in_array($args[0],$this->obsadene_routy['post'])) return(false);

			if ($args[0] == $this->request->getPath()) {
                $this->matchdata['matched'] = $this->match_url($args[0], $this->request->getPath());
                $this->route_matched = true;
				return(true);
			} else {
				$matchdata = $this->match_url($args[0], $this->request->getPath());
					if ($matchdata !== false) {
                        $this->matchdata['matched'] = $matchdata;
                        $this->route_matched = true;
						return(true);
					}
				return(false);
			}
		}
		
		if (count($args) == 2) {
			if (in_array($args[1],$this->reserved)) return(false);

			if ($args[0] == $this->request->getMethod() || $args[0] == "any") {
                if ($args[0] == "any") {
                    if (in_array($args[1],$this->obsadene_routy["get"])) return(false);
                    if (in_array($args[1],$this->obsadene_routy["post"])) return(false);
                    if (in_array($args[1],$this->obsadene_routy["put"])) return(false);
                    if (in_array($args[1],$this->obsadene_routy["delete"])) return(false);
                    if (in_array($args[1],$this->obsadene_routy["patch"])) return(false);
                    if (in_array($args[1],$this->obsadene_routy["options"])) return(false);
                    if (in_array($args[1],$this->obsadene_routy["head"])) return(false);
                    if (in_array($args[1],$this->obsadene_routy["trace"])) return(false);
                } else {
                    if (in_array($args[1],$this->obsadene_routy[$args[0]])) return(false);
                }
                
				if ($args[1] == $this->request->getPath()) {
                    $this->matchdata['matched'] = $this->match_url($args[1], $this->request->getPath());
					return(true);
				} else {
					$matchdata = $this->match_url($args[1], $this->request->getPath());
						if ($matchdata !== false) {
                            $this->matchdata['matched'] = $matchdata;
							return(true);
						}
					return(false);
				}
			} else return(false);
		}

        if (count($args) == 3) {
			if (in_array($args[1],$this->reserved)) return(false);

			if ($args[0] == $this->request->getMethod() || $args[0] == "any") {      
				if ($args[1] == $this->request->getPath()) {
                    $this->matchdata['matched'] = array();
					return(true);
				} else {
					$matchdata = $this->match_url($args[1], $this->request->getPath());
						if ($matchdata !== false) {
                            $this->matchdata['matched'] = $matchdata;
							return(true);
						}
					return(false);
				}
			} else return(false);
		}

        return(false);

	}

	private function resolve_with_hooks($method,$path,$function,$matchdata=[],$matched_path="") {
        $this->request->matchData($matchdata);
		if ($matched_path != "") $path = $matched_path;

		if (! $this->throttle($this->ratelimiter['limiters'][$path][$method],$this->ratelimiter['limiters_data'][$path][$method],$method,$path)) {
			http_response_code(429);
			if (isSet($this->ratelimiter['limiters_onlimitCallback'][$path][$method])) {
                $this->save_cache();
				return $this->ratelimiter['limiters_onlimitCallback'][$path][$method]($method,$matchdata);
			} else {
				$najdeny429 = isset($this->routy['error']['429']) ? $this->routy['error']['429'] : false;
				if ($najdeny429 != false) {
					echo $this->renderer->loadViewStatic('error_'.$najdeny404);
                    $this->save_cache();
					die();
				}
                $this->save_cache();
				return "Too much requests !";								
			}
		}

		foreach( $this->hooks['before'] as $key => $hook) {
			//call_user_func($hook,$this->dotAppObj,$matchdata);
            //Zakomentovane po pridani DI funkcie
            $this->request->route($hook['route']);
            $this->request->hookData($hook['data']);
            if (!defined('__DOTAPPER_RUN__')) {
                //$this->request->response->body = call_user_func($hook['fn'], $this->request->lock()) ?? $this->request->response->body;
                $result = call_user_func($hook['fn'], $this->request->lock());
                if ($result instanceof \Dotsystems\App\Parts\Response) {
                    return $result;
                }
                $this->request->response->body = $result ?? $this->request->response->body;
            }
		}

        $this->request->route($path);
        $this->request->hookData($matchdata);
		if (!defined('__DOTAPPER_RUN__')) {
            //$this->request->response->body = call_user_func($function, $this->request->lock()) ?? $this->request->response->body;
            $result = call_user_func($function, $this->request->lock());
            if ($result instanceof \Dotsystems\App\Parts\Response) {
                return $result;
            }
            $this->request->response->body = $result ?? $this->request->response->body;
        }

		foreach( $this->hooks['after'] as $key => $hook) {
            $this->request->route($hook['route']);
            $this->request->hookData($hook['data']);
            if (!defined('__DOTAPPER_RUN__')) {
			    //$this->request->response->body = call_user_func($hook['fn'], $this->request->lock()) ?? $this->request->response->body;
                $result = call_user_func($hook['fn'], $this->request->lock());
                if ($result instanceof \Dotsystems\App\Parts\Response) {
                    return $result;
                }
                $this->request->response->body = $result ?? $this->request->response->body;
            }
		}

        $this->save_cache();
		return $this->request;
	}
	
	public function resolve() {
		$path = $this->request->getPath();
		$method = $this->request->getMethod();
        // Vyytvarame middleware... dotapp.router.resolve ma zaroven alias ako middleware
        $this->dotapp->trigger("dotapp.router.resolve", $path, $method);
		// Priorita 1 - Presne sa zhodujuca routa
		$najdenarouta = isset($this->routy[$method][$path]) ? $this->routy[$method][$path] : false;
		if ($najdenarouta) {
			/*
				Najdena ROUTA je FUNKCIOU, takze ju zavolame...
			*/
			if (is_callable($najdenarouta)) {
                $matchdata = array();
				return $this->resolve_with_hooks($method,$path,$najdenarouta,$matchdata);
			} else if (is_string($najdenarouta)) {
				/*
					Najdena ROUTA je TEXT, volame VIEW...
				*/
                $this->save_cache();
				return $this->renderer->view($najdenarouta);
			}
			
		} else {
			/*
				Priorita 2 
				Nenasli sme routu v GET ani POST... Ideme na RESOURCES
			*/
			$najdenyresource = isset($this->routy['resources'][$path]) ? $this->routy['resources'][$path] : false;
			$cestakresource = $this->dir;
			
			if ($najdenyresource == false) {
				/*
					Nenasli sme konkretny resource tak ideme stromovo sprava dolava
					Rozsekame path na kusky a budeme skladat a hladat najdlhsi existujuci resource
				*/
				$patha = explode("/",$path);
				for ($i=(count($patha)-1); $i > 0; $i--) {
					$podpatha = array();
					for ($i2=0; $i2 <= $i; $i2++) {
						$podpatha[] = $patha[$i2];
					}
					$podpath = implode("/",$podpatha);
					
					if ($najdenyresource == false) {
						$najdenyresource = isset($this->routy['resources'][$podpath]) ? $this->routy['resources'][$podpath] : false;
						$rootpathresource = $podpath;
						if ($najdenyresource) {
							$cestakresource = $this->routy['resourcespath'][$podpath];
							break;
						}
					}
				}				
			} else {
				$cestakresource = $this->routy['resourcespath'][$podpath];
			}
			
			if ($najdenyresource) {
				/*
					PRIPOJIME RESOURCE... Resource je EXTEND abstraktnej triedy {<controller>NIECO}
					Konstruktor objektu zaroven rozhoduje co sa bude diat.
					Vsetky kontrolery su v priecinku __BASEDIR__/App/Parts/Controllers/
				*/
				$dotapp = $this->dotAppObj;
				include $cestakresource.$najdenyresource.".php";
				/*
					Vytvorime hned objekt, konstruktor sa nam postara o nasmerovanie do spravnej funkcie...
				*/
				new $najdenyresource($path,$rootpathresource,$method,$this->dotAppObj);
			} else {
				/*
					Priorita 3
					Teraz ideme patterny pre get a post...

				*/
				foreach ($this->routy[$method] as $route => $callback) {
					$matchdata = $this->match_url($route, $path);
					if ($matchdata !== false) {
						return $this->resolve_with_hooks($method,$path,$callback,$matchdata,$route);
					}
				}

				/*
					Nenasli sme to ani v patternoch
				*/
				// Vyhodime ERROR 404 NOT FOUND
				if ($this->dotapp->hasListener("dotapp.router.resolve.404")) {
                    $this->dotapp->trigger("dotapp.router.resolve.404");
                } else {
                    $najdeny404 = isset($this->routy['error']['404']) ? $this->routy['error']['404'] : false;
                    http_response_code(404);
                    if ($najdeny404) {
                        echo $this->renderer->loadViewStatic('error_'.$najdeny404);
                    }
                }

				die();
			}
		}
	}

    private function save_cache() {
        /* NEPOUZIVAT, je to pozostatok starsej verzie ktora mala inu logiku
        if ($this->match_cache_use) {
            if ($this->match_cache_file != "") {
                file_put_contents($this->match_cache_file, '<?php return ' . var_export($this->match_cache['static'], true) . '; ?>');
            }
        }
        */
    }

    /**
         * Porovná zadanú URL adresu s definovaným routovacím vzorom a extrahuje parametre.
         *
         * Táto funkcia konvertuje zadaný route pattern na regulárny výraz a následne 
         * ho porovnáva s aktuálnou URL adresou alebo s URL adresou odovzdanou v argumente `$url`.
         * Ak URL zodpovedá vzoru, vráti asociatívne pole s extrahovanými parametrami.
         * Ak sa zhoda nenájde, vráti `false`.
         *
         * Podporované parametre v route vzore:
         * - `{param}` – povinný parameter bez obmedzenia typu
         * - `{param?}` – voliteľný parameter bez obmedzenia typu
         * - `{param:s}` – povinný parameter typu string (žiadne `/`)
         * - `{param:s?}` – voliteľný parameter typu string
         * - `{param:i}` – povinný parameter typu integer (iba čísla)
         * - `{param:i?}` – voliteľný parameter typu integer
         * - `{param*}` – povinný wildcard parameter (zachytí všetko)
         * - `{*}` – anonymný wildcard parameter (zachytí všetko)
         * - `*` – wildcard kdekoľvek v route (ignoruje sa)
         *
         * @param string $route Definícia routovacieho vzoru, ktorý sa porovnáva s URL.
         * @param string|false $url (voliteľné) URL adresa na porovnanie. Ak nie je zadaná, použije sa aktuálna požadovaná cesta.
         * @return array|false Pole extrahovaných parametrov v prípade zhody, alebo `false`, ak sa zhoda nenájde.
     */
    
    public function match_url_cache_clear($file=false) {
        if (file_exists(__ROOTDIR__."/App/runtime/routercache/".$file.".cache")) {
            file_put_contents(__ROOTDIR__."/App/runtime/routercache/".$file.".cache", '<?php return ' . var_export(array(), true) . '; ?>');
        }
    }

    public function match_url_cache($file=false) {
        switch ($file) {
            // Vypnut cache uplne - defaultne je vypnuta. Pomoct by mohla len v pripade ze mame velke mnozstvo rovnakych rout co je ale nerealne ak je aplikacia pisana dobre
            // Ak je vypnuta setrime pamat a spomalenie je skoro nemeralne. Osobne odporucam vypnut.
            // Je to pozostatok zaciatkov, ale teraz sa to uz nepouziva, mame CLI nastroj ktory toto plne nahradi a hlavne vykoonovo je ina liga.
            case (false):
                $this->save_cache();
                $this->match_cache_file = "";
                $this->match_cache_use = false;
                $this->match_cache = array();
                break;
            // Pamatova cache
            case ("*"):
                $this->save_cache();
                $this->match_cache_file = "";
                $this->match_cache_use = true;
                $this->match_cache = array();
                $this->match_cache_maxsize = $maxsize;
                break;
            default:
                // NEPOUZIVAT - pozostatok starej verzie ostava len kvoli spatnej kompatibilite a bude vyradena
                $this->save_cache();
                $this->match_cache_file = __ROOTDIR__."/App/runtime/routercache/".$file.".cache";
                $this->match_cache_use = true;
                $this->match_cache = array();
                $this->match_cache_maxsize = $maxsize;                
                if (file_exists($this->match_cache_file)) $this->match_cache['static'] = @include($this->match_cache_file);
        }
    }

	public function match_url($route, $url = false, $static = false) {
        // Last update 2025-03-18
        if (!$url) $url = $this->request->getPath();
        
        // Nemusime porovnavat ak je routa staticka
        if ($static || strpbrk($route, '{:*?}') === false) {
            $this->match_cache[$route][$url] = true;
            return $route === $url;
        }

        if (isset($this->match_cache[$route][$url])) return $this->match_cache[$route][$url];

        /* Pre php <8 doplnime funkciu. Takze na php 7.4 to bude o nieco pomalsie, ale php 8.0+ vyuzije rychlsot na plno */
        if (!function_exists('str_ends_with')) {
            $str_ends_with = function(string $haystack, string $needle) {
                if ($needle === '') {
                    return true;
                }
                $needleLength = strlen($needle);
                if ($needleLength > strlen($haystack)) {
                    return false;
                }
                return substr($haystack, -$needleLength) === $needle;
            };
        } else {
            $str_ends_with = function(string $haystack, string $needle) {
                return str_ends_with($haystack,$needle);
            };
        }

        if (!function_exists('\str_starts_with')) {
            $str_starts_with = function(string $haystack, string $needle) {
                if ($needle === '') {
                    return true;
                }
                return substr($haystack, 0, strlen($needle)) === $needle;
            };
        } else {
            $str_starts_with = function(string $haystack, string $needle) {
                return str_starts_with($haystack,$needle);
            };
        }

        // Rychla detekcia pre /nieco/*
        if ($str_ends_with($route, '*') && strpbrk($route, '{?}') === false) {
            $prefix = substr($route, 0, -1); // Odstráni * efektívnejšie než str_replace
            $result = $str_starts_with($url, $prefix);
            $this->match_cache[$route][$url] = $result ? ['wildcard' => substr($url, strlen($prefix))] : false;
            return $result ? ['wildcard' => substr($url, strlen($prefix))] : false;
        }
        
        $pattern = str_replace('/', '\/', $route);
        
        // Handle optional parameter with optional trailing slash
        $pattern = preg_replace(
            '/\{([a-zA-Z0-9_]+)\?\}/', 
            '(?P<trailing_slash>\/)?(?P<$1>[^/]+)?', 
            $pattern
        );
        
        if (isset($this->patternCache[$route])) {
            $pattern = $this->patternCache[$route];
        } else {
            // Existing replacements from older dotapp versions
            $pattern = str_replace('/', '\/', $route);
            $pattern = preg_replace(
                [
                    '/\{([a-zA-Z0-9_]+)\?\}/',
                    '/\{([a-zA-Z0-9_]+)\}/',
                    '/\{([a-zA-Z0-9_]+):s\}/',
                    '/\{([a-zA-Z0-9_]+):i\}/',
                    '/\{([a-zA-Z0-9_]+):l\}/',
                    '/\{([a-zA-Z0-9_]+):s\?\}/',
                    '/\{([a-zA-Z0-9_]+):i\?\}/',
                    '/\{([a-zA-Z0-9_]+):l\?\}/',
                    '/\{([a-zA-Z0-9_]+)[*]\}/',
                    '/\{[*]\}/',
                    '/\*/',
                ],
                [
                    '(?P<trailing_slash>\/)?(?P<$1>[^/]+)?',
                    '(?P<$1>[^/]+)',
                    '(?P<$1>[^/]+)',
                    '(?P<$1>[0-9]+)',
                    '(?P<$1>[a-zA-Z]+)',
                    '(?:(?P<$1>[^/]+))?',
                    '(?:(?P<$1>[0-9]+))?',
                    '(?:(?P<$1>[a-zA-Z]+))?',
                    '(?P<$1>.+)',
                    '(?P<wildcard>.+)',
                    '(.*?)',
                ],
                $pattern
            );
            $pattern = '#^' . $pattern . '$#';
            $this->patternCache[$route] = $pattern;
        }
        
        if (preg_match($pattern, $url, $matches)) {
            // Filter out the trailing_slash parameter
            $filtered_matches = array_filter($matches, function($key) {
                return is_string($key) && $key !== 'trailing_slash';
            }, ARRAY_FILTER_USE_KEY);
            
            if ($this->match_cache_use) {
                $this->match_cache[$route][$url] = $filtered_matches;
            }
            return $filtered_matches;
        }

        if ($this->match_cache_use) {
            $this->match_cache[$route][$url] = false;
        }
        return false;
    }
	
	/* Aby sme nemuseli ifovat tak vieme pouzit 
		Router::onPath('/admin*', function() {
		
		Middleware::use('is_admin')->group(function() {
			Router::get('/admin/users', 'UserController@list');
			Router::post('/admin/users/delete', 'UserController@delete');
		});
		
	});*/
	public function onPath($pattern, $callback) {
		if ($this->match_url($pattern)) {
			return call_user_func($callback, $this->request);
		}
		return $this;
	}

}


?>