<?php
/**
 * Class TRANSLATOR
 * 
 * This class handles text translations used throughout templates or within the DotApp framework.
 * A globally accessible function `translator()` is defined, which allows text translation via:
 * `translator('text to translate')`. 
 * 
 * To access the translator object for configuration, such as setting the current or default 
 * language, use the following syntax:
 * `translator([])->function()`, for example:
 * `translator([])->set_locale("en_US")->set_default_locale("en_US");`
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
    Translator Class Usage:

    The translator class is responsible for handling text translations across the application. 
    It is accessible globally via the `translator()` function. 
    - To translate text: 
      `translator('text to translate')`
      
    If you need access to the translator object for setting configurations, such as changing 
    the current language or default language, use the following:
    - To access the translator object: 
      `translator([])->function()`
    
    Example:
    - Set the current language and default language:
      `translator([])->set_locale("en_US")->set_default_locale("en_US");`
*/


namespace Dotsystems\App\Parts;

class Translator {
	// Pole s prekladmi
	private $translations;
	private $default_locale;
	private $locale;
	private $translation_files;
	private $locale_translation_files;
	private $translation_loaded; // Pole so subormi ktore boli ci neboli natiahnute.
	private $locale_translation_loaded;
	private $translations_loaded; // All translations loaded???
	
	
	function __construct() {
		global $translator;
		$this->locale = "en_us";
		$this->default_locale = "en_us";
		$this->translations[$this->locale] = array();
		$this->translations_loaded = true; // Nemame nic, takze su v pdostate vsetky loadnute
		$this->locale_translation_loaded = array();
		$this->translation_files = array();
		
		$translator = function($text="",...$args) {
			if ($text === []) {
				return($this);
			}
			if (isset($text) && ( ! is_array($text) ) ) {
				return($this->translate($text,$args));
			} else return($text);
		};
		
    }
	
	public function __debugInfo() {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }
	
	public function translate($text,$args=array()) {
		$textl = strtolower($text);
		/*
			Ak nie su loadnute vsetky preklady, doloadneme tie ktore nie su...
		*/
		if ($this->translations_loaded == false) {
			
			foreach ($this->translation_files as $file) {
				if (! ($this->translation_loaded[md5($file)])) $this->load_translation_file_now($file);
			}
			
			if (isSet($this->locale_translation_files[$this->locale]) && is_array($this->locale_translation_files[$this->locale])) {
				foreach ($this->locale_translation_files[$this->locale] as $file) {
					if (! ($this->translation_loaded[md5($file)])) $this->locale_load_translation_file_now($file,$this->locale);
				}
			}			
			
			$this->translations_loaded = true;
		}
		
		if (isset($this->translations[$this->locale][$textl])) {
			$navrat = $this->translations[$this->locale][$textl];
		} else {
			$navrat =  $text;
		}

        $argnum = 0;
        foreach ($args as $arg) {
            $navrat = str_replace("{{ arg".$argnum." }}",$arg,$navrat);
            $argnum++;
        }

		return($navrat);
	}
	
	public function set_locale($locale) {
		$this->locale = strtolower($locale);
		return $this;
	}
	
	public function set_default_locale($locale) {
		$this->default_locale = strtolower($locale);
		return $this;
	}
	
	public function load_translation_file($file) {
		if (file_exists(__ROOTDIR__.$file)) {
			$this->translation_files[] = __ROOTDIR__.$file;
			$this->translation_loaded[md5(__ROOTDIR__.$file)] = false;
			$this->translations_loaded = false;
		}
	}
	
	private function load_translation_file_now($file) {
		/*
			Subor prekladu je JSON format. 
			{
				"en_US" : {
					"Môj účet" : "My account",
					"Košík" : "Cart"
				},
				"de_DE" : {
					"Môj účet" : "Das účtn",
					"Košík" : "Krankenwagen"
				}
			}
		*/
		try {
			$json_translation = file_get_contents($file);
			$json_translation = json_decode($json_translation,true);
			if (is_array($json_translation)) {
				$json_translation = $this->array_change_key_case_recursive($json_translation);
				$this->translations = $this->array_merge_recursive($this->translations,$json_translation);
				$this->translation_loaded[md5($file)] = true;
			}				
		} catch (Exception $e) {
			
		}

	}
	
	public function load_locale_translation_file($file,$locale) {
		/*
			V subore ktore musi byt PHP skriptom ma byt pole s nazvom $translation a to pole musi mat definovane preklady podla locales...;
			$translation = array();
			$translation = {
				'Prihlásiť' => 'Login'
			}
		*/
		if (file_exists(__ROOTDIR__.$file)) {
			$this->locale_translation_files[$locale][] = __ROOTDIR__.$file;
			$this->translation_loaded[md5(__ROOTDIR__.$file)] = false;
			$this->translations_loaded = false;
		}
	}
	
	private function load_locale_translation_file_now($file,$locale) {
		/*
			V subore ktore musi byt PHP skriptom ma byt pole s nazvom $translation a to pole musi mat definovane preklady podla locales...;
			{
				"Môj účet" : "My account",
				"Košík" : "Cart"
			}
		*/
		try {
			$json_translation = file_get_contents($file);
			$json_translation = json_decode($json_translation,true);
			if (is_array($json_translation)) {
				$json_translation = $this->array_change_key_case_recursive($json_translation);
				$this->translations[$locale] = $this->array_merge_recursive($this->translations[$locale],$json_translation);
				$this->translation_loaded[md5($file)] = true;
			}	
			
		} catch (Exception $e) {
			
		}
		
	}
	
	/* Ak chceme zadat preklad v premennej */
	public function load_translation($translation) {
		$this->array_merge_recursive($this->translations,$translation);
	}
	
	/* Ak chceme zadat preklad danej locale v premennej */
	public function load_locale_translation($translation,$locale) {
		$this->array_merge_recursive($this->translations[$locale],$translation);
	}

	/*
		Kedze polia su CASE sensitive, tak musime vsetky kluce poli zmenit na lowercase. Aby preklad fungoval aj ked je preklep.
		Napriklad: Môj účet, môj účet , môJ Účet a pod
	*/
	public function array_change_key_case_recursive($array) {
		return array_map(function($item) {
			if (is_array($item)) {
				$item = $this->array_change_key_case_recursive($item);
			}
			return $item;
		}, array_change_key_case($array, CASE_LOWER));
	}
	
	public function array_merge_recursive(array $array1, array $array2) {
			$merged = $array1;

			foreach ($array2 as $key => $value) {
				if (is_array($value)) {
					if (isset($merged[$key]) && is_array($merged[$key])) {

						$isNestedArray1 = array_filter($merged[$key], 'is_array') !== [];
						$isNestedArray2 = array_filter($value, 'is_array') !== [];

						if (!$isNestedArray1 && !$isNestedArray2) {
							$merged[$key] = $value;
						} else {
							$merged[$key] = $this->array_merge_recursive($merged[$key], $value);
						}
					} else {
						$merged[$key] = $value;
					}
				} else {
					$merged[$key] = $value;
				}
			}

			return $merged;
		}
	
}

?>