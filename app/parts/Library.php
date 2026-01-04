<?php
/**
 * Class LIBRARY
 * 
 * This abstract class serves as a blueprint for creating libraries within the DotApp framework. 
 * Libraries are collections of reusable functions and utilities that can be used in module library belong to.
 * 
 * By extending this class, developers can create custom libraries that encapsulate specific 
 * functionality, making it easier to manage and utilize shared resources within their modules.
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
    Library Class Usage:

    The `library` class is an abstract class that defines the structure for all libraries 
    within the DotApp framework module. Any class extending this abstract class must implement 
    its abstract methods, ensuring that all libraries provide the required functionality.

    This abstraction allows developers to build powerful utilities that can be easily 
    integrated into multiple modules, enhancing the overall functionality of the 
    DotApp framework.
	
	Load library in module:
	
		Module Class Usage:

		The `module` class provides the framework for creating modules within DotApp. 
		Modules can load libraries using the `load_library` method, enabling access 
		to shared functions and utilities.

		Example usage in module:
		```php
		$this->load_library("DotcmsfeRoutes");
		$this->load_library("dotcmsfe_smartblocks");
		$this->load_library("dotcmsfe_bridges");
		```

*/


namespace Dotsystems\App\Parts;
use Dotsystems\App\Dotapp;
use Dotsystems\App\Parts\DI;

abstract class Library {
	/*
		Namiesto INTERFACE ideme do abstract triedy, lebo potrebujeme premenne
	*/
	public $dotapp;
	public $modulename;
	public $di;
	public $DI; // Alias pre di, blbuvzdornost.
    public $call; // Alias pre di, blbuvzdornost.
    public $Call; // Alias pre di, blbuvzdornost.
	
	function __construct($module_name,$dotapp) {
		$this->dotapp = $dotapp;
		$this->modulename = $module_name;
		$this->di = new DI($this,$dotapp);
		$this->DI = $this->di; // Alias pre di, blbuvzdornost.
        $this->call = $this->di; // Alias pre di, blbuvzdornost.
        $this->Call = $this->di; // Alias pre di, blbuvzdornost.
		$this->construct2($module_name,$dotapp);
		$classname = get_class($this);
        $classname = str_replace("Dotsystems\\App\\Modules\\".$module_name."\\Libraries\\","",$classname);
		$classnamea = explode("\\",$classname);
		$classname = $classnamea[0];
		$classname = str_replace("module_","",$classname);
		$dotapp->register_module_class($module_name,$this->di,$classname);
	}
	
	public function __debugInfo() {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }
	
	public function construct2($module_name,$dotapp) {
		
	}
	
}

?>
