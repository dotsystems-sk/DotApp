<?php

/**
 * Abstract class LISTENERS
 * 
 * This abstract class defines the core structure for event listeners within the DotApp framework. 
 * It provides essential methods for handling event-based communication, ensuring modularity and 
 * reusability across different components. 
 * 
 * By extending this class, developers can implement custom event listeners that respond to 
 * triggers within the application, facilitating seamless interaction between various modules. 
 * This approach enables a robust and flexible event-driven architecture.
 * 
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.6 FREE
 * @license   MIT License
 * @date      2014 - 2025
 * 
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the 
 * following condition: You **must** retain this header in all copies or 
 * substantial portions of the code, including the author and company information.
 */




namespace Dotsystems\App\Parts;

abstract class Listeners {
	/*
		Namiesto INTERFACE ideme do abstract triedy, lebo potrebujeme premenne
	*/
	public $dotapp;
	public $modulename;
	
	function __construct($dotapp) {
		$classname = get_class($this);
        // New PascalCase
        $classname = str_replace("Dotsystems\\App\\Modules\\","",$classname);
		$classnamea = explode("\\",$classname);
		$classname = $classnamea[0];
		$classname = str_replace("module_","",$classname);
		$this->modulename = $classname;
		$this->dotapp = $dotapp;
		$this->register($dotapp);
	}
	
	public function __debugInfo() {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }

	abstract function register($dotapp);
	
}

?>