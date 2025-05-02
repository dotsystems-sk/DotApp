<?php
/**
 * DotApp Framework
 * 
 * This class provides caching functionality for the DotApp Framework, 
 * enabling the storage and retrieval of rendered pages and CSS files.
 * 
 * @package   DotApp Framework
 * @category  Framework Parts
 * @author    Štefan Miščík <stefan@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.6 FREE
 * @date      2014 - 2025
 * @license   MIT License
 * 
 * License Notice:
 * Permission is granted to use, modify, and distribute this code under the MIT License,
 * provided this header is retained in all copies or substantial portions of the file,
 * including author and company information.
 */

namespace Dotsystems\App\Parts;

class Cache {

	public $parentobj;
	public $cachedir = __ROOTDIR__."/app/runtime/cache/";
	
	function __construct($parent) {
        $this->parendobj = $parent;
    }
	
	function cachePageExists($name) {
		if (file_exists($this->cachedir.$name.".php")) {
			return(true);
		} else return(false);
	}
	
	function cachePageSave($name,$renderedpage) {
		file_put_contents($this->cachedir.$name.".php",$renderedpage);
	}
	
	function cachePageRead($name,$data) {
		ob_start();
			$dotapp = $this->parentobj->dotapp;
			include $this->cachedir.$name.".php";
		return ob_get_clean();
	}
	
	function cacheCssExists($name) {
		if (file_exists($this->cachedir.$name.".php")) {
			return(true);
		} else return(false);
	}
	
	function cacheCssSave($name,$renderedpage) {
		file_put_contents($this->cachedir.$name.".php",$renderedpage);
	}
	
	function cacheCssRead($name,$data) {
		ob_start();
			$dotapp = $this->parentobj->dotapp;
			include $this->cachedir.$name.".php";
		return ob_get_clean();
	}	
	
}


?>