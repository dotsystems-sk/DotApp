<?php
/**
 * Class TRANSLATOR
 * 
 * This class handles text translations used throughout templates or within the DotApp framework.
 * 
 * Supports multiple usage patterns:
 * 
 * 1. LEGACY: Global variable $translator (backward compatibility)
 *    global $translator;
 *    $translator("text to translate");
 *    $translator([])->set_locale("sk_sk");
 * 
 * 2. MODERN: Static facade methods
 *    Translator::trans("text to translate");
 *    Translator::setLocale("sk_sk");
 *    Translator::loadFile("/path/to/translations.json");
 * 
 * 3. TEMPLATE: Template syntax
 *    {{_ "text to translate" }}
 *    {{_ var: $variable }}
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
	  
	Modern Static Facade Usage:
	- Translator::trans("text to translate");
	- Translator::setLocale("sk_sk");
	- Translator::loadFile("/path/to/translations.json");
*/


namespace Dotsystems\App\Parts;

class Translator {
	
	// ==========================================
	// PRIVATE INSTANCE PROPERTIES
	// ==========================================
	
	/** @var array Pole s prekladmi [locale => [key => value]] */
	private $translations;
	
	/** @var string Default locale for fallback */
	private $default_locale;
	
	/** @var string Current active locale */
	private $locale;
	
	/** @var array List of translation files to load */
	private $translation_files;
	
	/** @var array List of locale-specific translation files [locale => [files]] */
	private $locale_translation_files;
	
	/** @var array Pole so subormi ktore boli ci neboli natiahnute [md5(file) => bool] */
	private $translation_loaded;
	
	/** @var array Legacy - locale translation loaded tracking */
	private $locale_translation_loaded;
	
	/** @var bool All translations loaded??? */
	private $translations_loaded;

	// ==========================================
	// STATIC SINGLETON INSTANCE
	// ==========================================
	
	/** @var self|null Single instance for facade pattern */
	private static $instance = null;
	
	
	// ==========================================
	// CONSTRUCTOR
	// ==========================================
	
	/**
	 * Constructor - ensures only one instance exists
	 * Sets up global $translator variable for backward compatibility
	 */
	function __construct() {
		// Ak uz instancia existuje, len nastavime globalnu premennu a vratime sa
		if (self::$instance !== null) {
			$this->setupGlobalProxy();
			return;
		}
		
		// Inicializacia stavu
		$this->locale = "en_us";
		$this->default_locale = "en_us";
		$this->translations[$this->locale] = array();
		$this->translations_loaded = true; // Nemame nic, takze su v podstate vsetky loadnute
		$this->locale_translation_loaded = array();
		$this->translation_files = array();
		$this->locale_translation_files = array();
		$this->translation_loaded = array();
		
		// Ulozime this ako singleton instanciu
		self::$instance = $this;
		
		// Nastavime globalnu premennu $translator (spatna kompatibilita)
		$this->setupGlobalProxy();
    }
	
	/**
	 * Sets up global $translator variable as callable for backward compatibility
	 * This allows: $translator("text") and $translator([])->method()
	 */
	private function setupGlobalProxy() {
		global $translator;
		
		$instance = self::$instance;
		
		// Vytvorime callable ktory proxuje na tuto instanciu
		$translator = function($text="",...$args) use ($instance) {
			// Ak je prazdne pole, vratime instanciu pre method chaining
			if ($text === []) {
				return $instance;
			}
			// Ak je text, prelozime ho
			if (isset($text) && ( ! is_array($text) ) ) {
				return $instance->translate($text,$args);
			}
			// Fallback - vratime original
			return $text;
		};
	}
	
	// ==========================================
	// STATIC FACADE METHODS (Modern API)
	// ==========================================
	
	/**
	 * Get singleton instance
	 * 
	 * @return self
	 */
	public static function getInstance() {
		if (self::$instance === null) {
			new self();
		}
		return self::$instance;
	}
	
	// ==========================================
	// PATH RESOLUTION HELPER
	// ==========================================
	
	/**
	 * Resolve file path - supports modular syntax
	 * 
	 * Supports two formats:
	 * 1. Regular path: "/path/to/file.json" - resolved relative to __ROOTDIR__
	 * 2. Module path: "ModuleName:filename.json" - resolved to module's translations folder
	 *    - "PharmList:sk_sk.json" → __ROOTDIR__/app/modules/PharmList/translations/sk_sk.json
	 *    - "PharmList:/subfolder/sk_sk.json" → __ROOTDIR__/app/modules/PharmList/translations/subfolder/sk_sk.json
	 * 
	 * When using module syntax:
	 * - The part before ":" is the module name
	 * - The part after ":" is the file path within the module's translations folder
	 * - Leading "/" after ":" is optional and will be normalized
	 * 
	 * @param string $file File path (regular or modular syntax)
	 * @return string Resolved absolute file path
	 */
	private function resolveFilePath($file) {
		// Check if it's a modular path (contains ":" and not a Windows drive letter like "C:")
		if (strpos($file, ':') !== false && !preg_match('/^[A-Za-z]:/', $file)) {
			// Parse modular path: "ModuleName:path/to/file.json"
			$parts = explode(':', $file, 2);
			$moduleName = trim($parts[0]);
			$filePath = isset($parts[1]) ? ltrim($parts[1], '/\\') : '';
			
			// Build full path to module's translations folder
			$rootDir = defined('__ROOTDIR__') ? __ROOTDIR__ : '';
			return $rootDir . '/app/modules/' . $moduleName . '/translations/' . $filePath;
		}
		
		// Regular path - prepend __ROOTDIR__ if defined
		return defined('__ROOTDIR__') ? __ROOTDIR__ . $file : $file;
	}
	
	/**
	 * Translate text (static facade)
	 * 
	 * @param string $text Text to translate
	 * @param mixed ...$args Dynamic arguments for {{ arg0 }}, {{ arg1 }}, etc.
	 * @return string Translated text or original if not found
	 */
	public static function trans($text, ...$args) {
		$inst = self::getInstance();
		return $inst->translate($text, $args);
	}
	
	/**
	 * Alias for trans() - shorter version
	 * 
	 * @param string $text Text to translate
	 * @param mixed ...$args Dynamic arguments
	 * @return string Translated text
	 */
	public static function t($text, ...$args) {
		return self::trans($text, ...$args);
	}
	
	/**
	 * Set current locale (static facade)
	 * 
	 * @param string $locale Locale code (e.g., "sk_sk", "en_us")
	 * @return self Instance for chaining
	 */
	public static function setLocale($locale) {
		$inst = self::getInstance();
		$inst->locale = strtolower($locale);
		return $inst;
	}
	
	/**
	 * Get current locale (static facade)
	 * 
	 * @return string Current locale
	 */
	public static function getLocale() {
		$inst = self::getInstance();
		return $inst->locale;
	}
	
	/**
	 * Set default fallback locale (static facade)
	 * 
	 * @param string $locale Default locale code
	 * @return self Instance for chaining
	 */
	public static function setDefaultLocale($locale) {
		$inst = self::getInstance();
		$inst->default_locale = strtolower($locale);
		return $inst;
	}
	
	/**
	 * Get default locale (static facade)
	 * 
	 * @return string Default locale
	 */
	public static function getDefaultLocale() {
		$inst = self::getInstance();
		return $inst->default_locale;
	}
	
	/**
	 * Load multi-locale translation file (static facade)
	 * File should contain: { "en_us": {...}, "sk_sk": {...} }
	 * 
	 * Supports modular path syntax:
	 * - Regular: "/translations/general.json" (relative to __ROOTDIR__)
	 * - Module: "PharmList:general.json" → __ROOTDIR__/app/modules/PharmList/translations/general.json
	 * - Module with subfolder: "PharmList:/api/messages.json" → __ROOTDIR__/app/modules/PharmList/translations/api/messages.json
	 * 
	 * @param string $file Path to JSON file (regular or modular syntax)
	 * @return self Instance for chaining
	 */
	public static function loadFile($file) {
		$inst = self::getInstance();
		$fullPath = $inst->resolveFilePath($file);
		
		if (file_exists($fullPath)) {
			$inst->translation_files[] = $fullPath;
			$inst->translation_loaded[md5($fullPath)] = false;
			$inst->translations_loaded = false;
		}
		
		return $inst;
	}
	
	/**
	 * Load single-locale translation file (static facade)
	 * File should contain: { "key": "value", ... }
	 * 
	 * Supports modular path syntax:
	 * - Regular: "/translations/sk_sk.json" (relative to __ROOTDIR__)
	 * - Module: "PharmList:sk_sk.json" → __ROOTDIR__/app/modules/PharmList/translations/sk_sk.json
	 * - Module with subfolder: "PharmList:/slovencina/sk_sk.json" → __ROOTDIR__/app/modules/PharmList/translations/slovencina/sk_sk.json
	 * 
	 * @param string $file Path to JSON file (regular or modular syntax)
	 * @param string $locale Locale for this file
	 * @return self Instance for chaining
	 */
	public static function loadLocaleFile($file, $locale) {
		$inst = self::getInstance();
		$fullPath = $inst->resolveFilePath($file);
		$locale = strtolower($locale);
		
		if (file_exists($fullPath)) {
			if (!isset($inst->locale_translation_files[$locale])) {
				$inst->locale_translation_files[$locale] = array();
			}
			$inst->locale_translation_files[$locale][] = $fullPath;
			$inst->translation_loaded[md5($fullPath)] = false;
			$inst->translations_loaded = false;
		}
		
		return $inst;
	}
	
	/**
	 * Load translations from PHP array (static facade)
	 * Array should be: ["en_us" => ["key" => "value"], "sk_sk" => [...]]
	 * 
	 * @param array $translations Translations array
	 * @return self Instance for chaining
	 */
	public static function loadArray($translations) {
		$inst = self::getInstance();
		$translations = $inst->array_change_key_case_recursive($translations);
		$inst->translations = $inst->array_merge_recursive($inst->translations, $translations);
		return $inst;
	}
	
	/**
	 * Load translations for specific locale from PHP array (static facade)
	 * Array should be: ["key" => "value", ...]
	 * 
	 * @param array $translations Translations array
	 * @param string $locale Locale for these translations
	 * @return self Instance for chaining
	 */
	public static function loadLocaleArray($translations, $locale) {
		$inst = self::getInstance();
		$locale = strtolower($locale);
		$translations = $inst->array_change_key_case_recursive($translations);
		
		if (!isset($inst->translations[$locale])) {
			$inst->translations[$locale] = array();
		}
		$inst->translations[$locale] = $inst->array_merge_recursive($inst->translations[$locale], $translations);
		
		return $inst;
	}
	
	/**
	 * Check if translation exists for given key (static facade)
	 * 
	 * @param string $key Translation key
	 * @param string|null $locale Locale to check (null = current locale)
	 * @return bool True if translation exists
	 */
	public static function has($key, $locale = null) {
		$inst = self::getInstance();
		$inst->ensureTranslationsLoaded();
		
		$locale = $locale !== null ? strtolower($locale) : $inst->locale;
		$key = strtolower($key);
		
		return isset($inst->translations[$locale][$key]);
	}
	
	/**
	 * Get all translations for current or specified locale (static facade)
	 * 
	 * @param string|null $locale Locale (null = current)
	 * @return array All translations for locale
	 */
	public static function all($locale = null) {
		$inst = self::getInstance();
		$inst->ensureTranslationsLoaded();
		
		$locale = $locale !== null ? strtolower($locale) : $inst->locale;
		
		return isset($inst->translations[$locale]) ? $inst->translations[$locale] : array();
	}
	
	// ==========================================
	// LEGACY INSTANCE METHODS (Backward Compatibility)
	// ==========================================
	
	public function __debugInfo() {
        return [
            'publicData' => 'DotApp Translator v1.7 - Facade Pattern',
			'locale' => $this->locale,
			'default_locale' => $this->default_locale
        ];
    }
	
	/**
	 * Core translate method
	 * 
	 * @param string $text Text to translate
	 * @param array $args Dynamic arguments for {{ arg0 }}, {{ arg1 }}, etc.
	 * @return string Translated text or original if not found (fallback)
	 */
	public function translate($text, $args = array()) {
		$textl = strtolower($text);
		
		// Ak nie su loadnute vsetky preklady, doloadneme tie ktore nie su...
		$this->ensureTranslationsLoaded();
		
		if (isset($this->translations[$this->locale][$textl])) {
			$navrat = $this->translations[$this->locale][$textl];
		} else {
			$navrat = $text;
		}

        $argnum = 0;
        foreach ($args as $arg) {
            $navrat = str_replace("{{ arg".$argnum." }}", $arg, $navrat);
            $argnum++;
        }

		return $navrat;
	}
	
	/**
	 * Ensure all pending translation files are loaded
	 */
	private function ensureTranslationsLoaded() {
		if ($this->translations_loaded == true) {
			return;
		}
		
		foreach ($this->translation_files as $file) {
			if (!isset($this->translation_loaded[md5($file)]) || !$this->translation_loaded[md5($file)]) {
				$this->load_translation_file_now($file);
			}
		}
		
		if (isset($this->locale_translation_files[$this->locale]) && is_array($this->locale_translation_files[$this->locale])) {
			foreach ($this->locale_translation_files[$this->locale] as $file) {
				if (!isset($this->translation_loaded[md5($file)]) || !$this->translation_loaded[md5($file)]) {
					$this->load_locale_translation_file_now($file, $this->locale);
				}
			}
		}
		
		$this->translations_loaded = true;
	}
	
	/**
	 * Set locale (legacy method)
	 * @param string $locale
	 * @return Translator
	 */
	public function set_locale($locale) {
		$this->locale = strtolower($locale);
		return $this;
	}
	
	/**
	 * Set default locale (legacy method)
	 * @param string $locale
	 * @return Translator
	 */
	public function set_default_locale($locale) {
		$this->default_locale = strtolower($locale);
		return $this;
	}
	
	/**
	 * Load multi-locale translation file (legacy method)
	 * 
	 * Supports modular path syntax:
	 * - Regular: "/translations/general.json" (relative to __ROOTDIR__)
	 * - Module: "PharmList:general.json" → __ROOTDIR__/app/modules/PharmList/translations/general.json
	 * - Module with subfolder: "PharmList:/api/messages.json" → __ROOTDIR__/app/modules/PharmList/translations/api/messages.json
	 * 
	 * @param string $file Path to JSON file (regular or modular syntax)
	 * @return Translator
	 */
	public function load_translation_file($file) {
		$fullPath = $this->resolveFilePath($file);
		if (file_exists($fullPath)) {
			$this->translation_files[] = $fullPath;
			$this->translation_loaded[md5($fullPath)] = false;
			$this->translations_loaded = false;
		}
		return $this;
	}
	
	/**
	 * Load multi-locale translation file immediately
	 * Expected format: { "en_us": { "key": "value" }, "sk_sk": { "key": "value" } }
	 */
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
			$json_translation = json_decode($json_translation, true);
			if (is_array($json_translation)) {
				$json_translation = $this->array_change_key_case_recursive($json_translation);
				$this->translations = $this->array_merge_recursive($this->translations, $json_translation);
				$this->translation_loaded[md5($file)] = true;
			}				
		} catch (\Exception $e) {
			// Silently fail
		}
	}
	
	/**
	 * Load single-locale translation file (legacy method)
	 * 
	 * Supports modular path syntax:
	 * - Regular: "/translations/sk_sk.json" (relative to __ROOTDIR__)
	 * - Module: "PharmList:sk_sk.json" → __ROOTDIR__/app/modules/PharmList/translations/sk_sk.json
	 * - Module with subfolder: "PharmList:/slovencina/sk_sk.json" → __ROOTDIR__/app/modules/PharmList/translations/slovencina/sk_sk.json
	 * 
	 * @param string $file Path to JSON file (regular or modular syntax)
	 * @param string $locale Locale for this file
	 * @return Translator
	 */
	public function load_locale_translation_file($file, $locale) {
		$fullPath = $this->resolveFilePath($file);
		$locale = strtolower($locale);
		
		if (file_exists($fullPath)) {
			if (!isset($this->locale_translation_files[$locale])) {
				$this->locale_translation_files[$locale] = array();
			}
			$this->locale_translation_files[$locale][] = $fullPath;
			$this->translation_loaded[md5($fullPath)] = false;
			$this->translations_loaded = false;
		}
		return $this;
	}
	
	/**
	 * Load single-locale translation file immediately
	 * Expected format: { "key": "value", ... }
	 */
	private function load_locale_translation_file_now($file, $locale) {
		/*
			V subore ktore musi byt PHP skriptom ma byt pole s nazvom $translation a to pole musi mat definovane preklady podla locales...;
			{
				"Môj účet" : "My account",
				"Košík" : "Cart"
			}
		*/
		try {
			$json_translation = file_get_contents($file);
			$json_translation = json_decode($json_translation, true);
			if (is_array($json_translation)) {
				$json_translation = $this->array_change_key_case_recursive($json_translation);
				if (!isset($this->translations[$locale])) {
					$this->translations[$locale] = array();
				}
				$this->translations[$locale] = $this->array_merge_recursive($this->translations[$locale], $json_translation);
				$this->translation_loaded[md5($file)] = true;
			}	
		} catch (\Exception $e) {
			// Silently fail
		}
	}
	
	/**
	 * Load translations from array (legacy method)
	 * @param array $translation
	 * @return Translator
	 */
	public function load_translation($translation) {
		$translation = $this->array_change_key_case_recursive($translation);
		$this->translations = $this->array_merge_recursive($this->translations, $translation);
		return $this;
	}
	
	/**
	 * Load locale-specific translations from array (legacy method)
	 * @param array $translation
	 * @param string $locale
	 * @return Translator
	 */
	public function load_locale_translation($translation, $locale) {
		$locale = strtolower($locale);
		$translation = $this->array_change_key_case_recursive($translation);
		if (!isset($this->translations[$locale])) {
			$this->translations[$locale] = array();
		}
		$this->translations[$locale] = $this->array_merge_recursive($this->translations[$locale], $translation);
		return $this;
	}

	// ==========================================
	// HELPER METHODS
	// ==========================================

	/**
	 * Recursively change all array keys to lowercase
	 * Makes translation keys case-insensitive
	 * Napriklad: Môj účet, môj účet , môJ Účet a pod
	 * 
	 * @param array $array
	 * @return array
	 */
	public function array_change_key_case_recursive($array) {
		return array_map(function($item) {
			if (is_array($item)) {
				$item = $this->array_change_key_case_recursive($item);
			}
			return $item;
		}, array_change_key_case($array, CASE_LOWER));
	}
	
	/**
	 * Recursively merge two arrays
	 * Unlike PHP's array_merge_recursive, this properly overwrites values
	 * 
	 * @param array $array1
	 * @param array $array2
	 * @return array
	 */
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
