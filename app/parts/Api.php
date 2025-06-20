<?php
/**
 * DotApp Framework
 * 
 * This abstract class serves as a base controller for handling API calls 
 * within the DotApp Framework. It defines endpoints for HTTP request management.
 * 
 * @package   DotApp Framework
 * @category  Framework Parts
 * @author    Štefan Miščík <stefan@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.7 FREE
 * @date      2014 - 2025
 * @license   MIT License
 * 
 * License Notice:
 * Permission is granted to use, modify, and distribute this code under the MIT License,
 * provided this header is retained in all copies or substantial portions of the file,
 * including author and company information.
 */

/*
    The `Controller` class is designed to simplify API development 
    by providing a structured approach to defining endpoints and handling 
    various HTTP request methods.

    Key Features:
    - Define API points using the `add_point($urlpoint, $callback)` method.
    - Automatically map HTTP request methods (POST, GET, PUT, etc.) 
      to their corresponding functions in the controller.
    
    This class is essential for creating RESTful APIs within the 
    DotApp framework, enabling clear routing and functionality 
    assignment for different endpoints.
	
	$dotapp->router->resources('/api/v1/', 'api');
	then we need to store api.php file in /App/Parts/Controllers
	
	this file api.php must contain:
	
	class api extends Dotsystems\App\Parts\controllerpost {
	
		function constructor2() {
			// This is called after constructor
			$this->add_point("/login/","loginPrefix");  ** ( url, method_prefix) **
		}
		
		public function loginPrefix_post() {
			
		}
		
		public function loginPrefix_get() {
			
		}	
	
	}
	
	Now if there is call to: mydomain.com/api/v1/login
		if method is POST then loginPrefix_post is called, for get loginPrefix_get is called... The same for other methods.
*/


namespace Dotsystems\App\Parts;

/**
	* CLASS ROUTER
	*
	* @author: Stefan Miscik <info@dotsystems.sk>
	* @package dotapp/app
*/

abstract class Api {
	// Stara trieda controller premenovana na API, ostava uz len kvoli spatnej kompatibilite aby mi stacilo prepisat v starych aplikaciach Controller za Api. 
	/*
		Namiesto INTERFACE ideme do abstract triedy, lebo potrebujeme premenne
		Potrebujeme RENDERER Renderer();
	*/
	public $renderer;
	public $path;
	public $method;
	public $vars;
	public $dotapp;
	public $dotApp; //cameCase blbuvzdornost
	public $DotApp; // PascalCase blbuvzdornost
	public $points;
	public $rootpath;
	public $urlvar;
	public $endpath;
	
	function __construct($path,$rootpath,$method,$dotAppObj) {
		/*
			Skusame zavolat triedu...
		*/
		$this->renderer = $dotAppObj->router->renderer;
		$this->path = $path;
		$this->rootpath = $rootpath;
		$this->method = $method;
		$this->vars = $dotAppObj->request->reqVars;
		$this->dotapp = $dotAppObj;
		$this->dotApp = $this->dotapp;
		$this->DotApp = $this->dotapp;
		$this->fetch_urlvar($rootpath,$path);
		$this->constructor2();
		$this->local_route();		
	}
	
	public function __debugInfo() {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }
	
	function fetch_urlvar($rootpath,$path) {
		$this->urlvar = str_replace($rootpath,"",$path);
		$this->urlvar = str_replace('\\',"/",$this->urlvar);
		$this->endpath = $this->urlvar;

		$urlvara = explode("/",$this->urlvar);
		foreach ($urlvara as $key => $value) {
			if ($value != "") {
				$this->urlvar = $value;
				break;
			}
		}		
	}
	
	function constructor2() {
		// Nic :)
	}
	
	function add_point($urlpoint,$callback) {
		$novypoint = $this->rootpath."/".$urlpoint."/";
		$novypoint = str_replace("///","/",$novypoint);
		$novypoint = str_replace("//","/",$novypoint);
		$novypoint = str_replace("//","/",$novypoint);
		$this->points[$novypoint] = $callback;
	}
	
	function get_point($urlpoint) {
		if (isSet($this->points[$urlpoint])) {
			return($this->points[$urlpoint]);
		} else return(false);
	}
	
	function local_route() {
		$point = $this->get_point($this->path);
		if ($this->method == "get" && $this->vars != "") {
			if ($point == "") {
				$this->get();
			} else {
				$metodapoint = $point.'_get';
				if (is_callable($this->$metodapoint())) $this->$metodapoint();
			}
		} else if ($this->method == "get" && $this->vars == "") {
			if ($point == "") {
				$this->index();
			} else {
				$metodapoint = $point.'_index';
				if (is_callable($this->$metodapoint())) $this->$metodapoint();
			}
		} else if ($this->method == "post") {
			if ($point == "") {
				$this->post();
			} else {
				$metodapoint = $point.'_post';
				if (is_callable($this->$metodapoint())) $this->$metodapoint();
			}
		} else if ($this->method == "put") {
			if ($point == "") {
				$this->put();
			} else {
				$metodapoint = $point.'_put';
				if (is_callable($this->$metodapoint())) $this->$metodapoint();
			}
		} else if ($this->method == "delete") {
			if ($point == "") {
				$this->delete();
			} else {
				$metodapoint = $point.'_delete';
				if (is_callable($this->$metodapoint())) $this->$metodapoint();
			}
		}
	}
	
	/*
		
	*/
	public function index() {}
	public function get() {}
	public function post() {}
	public function put() {}
	public function delete() {}
	
}

?>