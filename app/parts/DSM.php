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
 * @version   1.6 FREE
 * @license   MIT License
 * @date      2014 - 2025
 * 
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the 
 * following condition: You **must** retain this header in all copies or 
 * substantial portions of the code, including the author and company information.
 */

/*
    Reserved Variable Names:
    
    - _enc_key: Encryption key. Available only in the background.
    - _bridge.*: Bridge data for integration with other components.
    - _router.*: Router data for managing routing information.
    
    The `dsm` class provides an organized way to handle session management, 
    ensuring secure and efficient access to session variables throughout 
    the DotApp framework.
*/


namespace Dotsystems\App\Parts;
use \Dotsystems\App\Parts\Config;

class DSM {
    private $driver;
    private $config;
    public $sessname; 
    private $session_manager;

    /**
     * Constructor for the DotApp Session Manager.
     *
     * Initializes the session manager with a specific session name. 
     * Starts the session if it's not already active and sets up the 
     * default management logic for handling session variables.
     *
     * @param string $sessname The name of the session variable.
     */
    function __construct($sessname) {
        $this->session_manager["managers"] = [];
        $this->sessname = $sessname;
        $this->driver = Config::session("driver");
        if ($this->driver == "default") {
            //Config::sessionDriver($this->driver,SessionDriverDefault::driver($this->sessname));
            foreach (Config::sessionDriver($this->driver) as $way => $wayFn) {
                $this->session_manager['managers'][$this->driver][$way] = $wayFn;
            }
        } else {
            foreach (Config::sessionDriver($this->driver) as $way => $wayFn) {
                $this->session_manager['managers'][$this->driver][$way] = $wayFn;
            }
        }
    }

    /**
     * Loads the session state using the current manager.
     *
     * This method executes the load logic defined for the current manager 
     * to retrieve the session variables and values.
     *
     * @return $this
     */
    public function load() {
		call_user_func($this->session_manager['managers'][$this->driver]["load"], $this);
        return $this;
    }

    /**
     * Saves the current state of values and variables to the session.
     *
     * This method invokes the save logic defined in the current manager,
     * ensuring that any changes made to session variables are persisted.
     *
     * @return void
     */
    public function save() {
		call_user_func($this->session_manager['managers'][$this->driver]["save"], $this);
        return $this;
    }

    /**
     * Sets a value in the session.
     *
     * This method creates a unique ID for the variable name and stores the value 
     * associated with that ID. The variable name is mapped to the unique ID 
     * for future retrieval.
     *
     * @param string $name The name of the variable to set.
     * @param mixed $value The value to associate with the variable name.
     * @return $this
     */
    public function set($name, $value) {
		call_user_func($this->session_manager['managers'][$this->driver]["set"], $name, $value, $this);
		return $this;
    }

    /**
     * Gets a value from the session by variable name.
     *
     * This method checks if the variable name exists in the mapping and retrieves 
     * the associated value. If the variable does not exist, it returns null.
     *
     * @param string $name The name of the variable to retrieve.
     * @return mixed|null Returns the value associated with the variable name, or null if not found.
     */
    public function get($name) {
		return call_user_func($this->session_manager['managers'][$this->driver]["get"], $name, $this);
    }

}

?>
