<?php

/**
 * CLASS DSM - DotApp Session Manager
 *
 * This class is responsible for managing session variables within the DotApp framework. 
 * It provides methods to set and retrieve session values, ensuring they persist 
 * across different pages and user interactions within the application. 
 * 
 * The session manager plays a crucial role in maintaining user state and storing 
 * necessary information throughout the user's session lifecycle.
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

 Reserved Variable Names:
    
    - _enc_key: Encryption key. Available only in the background.
    - _bridge.*: Bridge data for integration with other components.
    - _router.*: Router data for managing routing information.
    - _request.auth
    
    The `dsm` class provides an organized way to handle session management, 
    ensuring secure and efficient access to session variables throughout 
    the DotApp framework.
*/

namespace Dotsystems\App\Parts;
use \Dotsystems\App\Parts\Config;

class DSM {
    private static $dsm=[]; // Aktualne dostupne DSM instancie
    private $driver;
    private $config;
    public $sessname; 
    private $session_manager;

    /**
     * Constructor for the DotApp Session Manager.
     *
     * Initializes the session manager with a specific session name. 
     * Sets up the session driver based on configuration and prepares 
     * the session management logic for handling session variables.
     *
     * @param string $sessname The name of the session variable.
     */
    function __construct($sessname) {
        $this->session_manager["managers"] = [];
        $this->sessname = $sessname;
        self::$dsm[$sessname] = $this;
        $this->driver = Config::session("driver");
        foreach (Config::sessionDriver($this->driver) as $way => $wayFn) {
            $this->session_manager['managers'][$this->driver][$way] = $wayFn;
        }
    }

    /**
     * Creates or retrieves a DSM instance for the specified session name.
     *
     * If a DSM instance already exists for the given session name, it is returned.
     * Otherwise, a new DSM instance is created with the provided session name.
     */
    public static function use($sessname = null) {
        // Null pouzijeme vtedy, ak chcem pristup len k regenerate_id alebo session_id alebo start a podobne. Aby sme ziskali chain
        if ($sessname === null) {
            $sessname = hash('sha256', "DotApp Framework null Session :)");
        }
        if (isset(self::$dsm[$sessname])) {
            return self::$dsm[$sessname];
        } else {
            $selficko = new self($sessname);
            $selficko->load();
            return $selficko;
        }
    }

    /**
     * Regenerates the session ID to prevent session fixation attacks.
     *
     * This method calls the regenerate_id function of the configured session driver,
     * which updates the session ID while maintaining the session data.
     *
     * @return $this Returns the DSM instance for method chaining.
     */
    public function regenerate_id($deleteOld=false) {
        call_user_func($this->session_manager['managers'][$this->driver]["regenerate_id"], $deleteOld, $this);
        return $this;
    }

    public function start() {
        call_user_func($this->session_manager['managers'][$this->driver]["start"], $this);
        return $this;
    }

    public function destroy() {
        call_user_func($this->session_manager['managers'][$this->driver]["destroy"], $this);
        return $this;
    }

    public function status() {
        call_user_func($this->session_manager['managers'][$this->driver]["status"], $this);
        return $this;
    }

    /**
     * Retrieves the current session ID.
     *
     * This method calls the session_id function of the configured session driver,
     * which returns the current session ID associated with the session.
     *
     * @return $this Returns the DSM instance for method chaining.
     */
    public function session_id($new=null) {
        if ($new!== null) {
            return call_user_func($this->session_manager['managers'][$this->driver]["session_id"], $new, $this);
        } else {
            return call_user_func($this->session_manager['managers'][$this->driver]["session_id"], null, $this);
        }
    }

    /**
     * Loads session data from the underlying storage.
     *
     * Invokes the load logic of the configured session driver to retrieve 
     * session data from PHP's session storage (`$_SESSION`). The data is 
     * managed by the driver and may be stored in various backends (e.g., Redis, files) 
     * depending on the session handler configuration.
     *
     * @return $this Returns the DSM instance for method chaining.
     */
    public function load() {
        call_user_func($this->session_manager['managers'][$this->driver]["load"], $this);
        return $this;
    }

    /**
     * Saves session data to the underlying storage.
     *
     * Executes the save logic of the configured session driver to persist 
     * session data into PHP's session storage (`$_SESSION`). The data is 
     * stored in the backend defined by the session handler (e.g., Redis, files).
     *
     * @return $this Returns the DSM instance for method chaining.
     */
    public function save() {
        call_user_func($this->session_manager['managers'][$this->driver]["save"], $this);
        return $this;
    }

    /**
     * Sets a session variable with the specified name and value.
     *
     * Delegates to the configured session driver to store the value in 
     * PHP's session storage (`$_SESSION`). The driver handles the mapping 
     * of the variable name to a unique identifier and persists the data 
     * to the backend defined by the session handler.
     *
     * @param string $name The name of the session variable.
     * @param mixed $value The value to store.
     * @return $this Returns the DSM instance for method chaining.
     */
    public function set($name, $value) {
        call_user_func($this->session_manager['managers'][$this->driver]["set"], $name, $value, $this);
        return $this;
    }

    /**
     * Retrieves the value of a session variable by name.
     *
     * Uses the configured session driver to fetch the value associated with 
     * the specified variable name from PHP's session storage (`$_SESSION`). 
     * Returns null if the variable does not exist.
     *
     * @param string $name The name of the session variable.
     * @return mixed|null The value of the session variable, or null if not found.
     */
    public function get($name) {
        return call_user_func($this->session_manager['managers'][$this->driver]["get"], $name, $this);
    }

    public function delete($name) {
        return call_user_func($this->session_manager['managers'][$this->driver]["delete"], $name, $this);
    }

    public function clear() {
        return call_user_func($this->session_manager['managers'][$this->driver]["clear"], $this);
    }

    public function gc() {
        return call_user_func($this->session_manager['managers'][$this->driver]["gc"], $this);
    }
}
?>