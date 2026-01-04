<?php
/**
 * Class WRAPPER
 * 
 * This class serves as a wrapper for isolating rendering processes within the DotApp framework.
 * It enhances security by allowing controlled access to specific functions, preventing direct 
 * access to the main DotApp object during rendering operations.
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
    Rendering Security Isolation:

    During the rendering process, we aim to provide the highest level of protection. While 
    the rendering engine is highly secure, it is critical to completely isolate the rendering 
    process for added safety. 

    The rendering process requires various functions. We have two options:
    1. Use functions in the router, assign the result to variables, and pass them to views for rendering.
    2. Create a wrapper with function symlinks, thus isolating the main DotApp object. This method 
       provides enhanced security as the rendering runs without direct access to the main object.

    The wrapper only allows functions that are explicitly permitted, ensuring that access to 
    the object's properties is restricted during the rendering phase.
*/

namespace Dotsystems\App\Parts;



class Wrapper {
	
	private $dotapp;
	private $object_data;
	private $actual_name;
	private $callnumber;
	
	public function __construct($dotapp,$object_data="",$callnumber=0) {
        $this->dotapp = $dotapp;
		if ($object_data == "") {
			$this->object_data = $this->get_dotapp();
		} else $this->object_data = $object_data;
		$this->callnumber = $callnumber;
    }
	
	public function __debugInfo() {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }
	
	/*
		Volame nejaku metodu, plati aj rekurzivne.
		
		Pozor !!! Dotapp v tomto pripade uz nie je realne dotapp. Zachovane je to preto, ze v sablonach sa pouziva dotapp a $this->dotapp.
		Na to, aby sablony fungovali bez toho aby si uzivatel vobec uvedomil ze nevola realnu dotapp je to takto nutne :) 
		
		Realne uzivatel vola len wrapper dotappu ktory je bezpecny na pouzivanie aj ked uzivatel spravi nejaku bezpecnostnu chybu.
	*/
	public function __call($name, $arguments) {
			$allow_next = 0;
			if ($this->callnumber == 0 && $this->object_data == "") {
				if ($this->allowed_functions(get_class($this->object_data),$name)) $allow_next = 1;
			} else {
				if (is_object($this->object_data)) $allow_next = 1;
			}
			if ($allow_next == 1) {
				$thisv = $this;
				$closure = function() use ($thisv, $name, $arguments, $newHistory) {
					if (method_exists($thisv->get_dotapp(), $name)) {					
						$result = call_user_func_array([$thisv->get_dotapp(), $name], $arguments);
						
						if (is_object($result)) {
							
							if ($thisv->allowed_functions(get_class($thisv->object_data),$name)) {
								return new self($thisv->dotapp,$result,$callnumber+1);
							} else return(null);
						}
						
						if ($thisv->allowed_functions(get_class($thisv->object_data),$name)) {
							return $result;
						} else return(null);
					}

					throw new BadMethodCallException("Method $name does not exist on the wrapped object.");
				};
				
				return $closure();
			} else return(null);
			
    }
	
	public function allowed_functions($cname,$fname) {
		
		//echo $cname."->".$fname;
		
		$disalloweda = array();
		$disalloweda[md5("Dotsystems\App\DotApp")][] = "set_wrapper";
		$disalloweda[md5("Dotsystems\App\DotApp")][] = "errhandler";
		$disalloweda[md5("Dotsystems\App\DotApp")][] = "add_renderer";
		$disalloweda[md5("Dotsystems\App\DotApp")][] = "custom_renderers";
		$disalloweda[md5("Dotsystems\App\DotApp")][] = "register_email_sender";
		$disalloweda[md5("Dotsystems\App\DotApp")][] = "register_sms_sender";
		
		if (isset($disalloweda[md5($cname)])) {
			$data = $disalloweda[md5($cname)];
			if (is_array($data)) {
				if (in_array($fname,$data)) {
					return(false);
				}
			} else {
				return(false);
			}
		}
		
		return(true);
	}
	
	private function get_dotapp() {
		if (is_object($this->object_data)) {
			return($this->object_data);
		} 
		if (is_callable($this->dotapp)) {
			return ($this->dotapp)($this);
		}
	}
	
}


?>
