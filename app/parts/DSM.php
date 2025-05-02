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

class DSM {
    private $values;
    private $variables;
    private $sessname; 
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
        $this->sessname = $sessname;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->default_manager();
    }

    /**
     * Sets up the default session management logic.
     *
     * Initializes the session manager with logic for loading, saving,
     * setting, and getting session variables. This includes defining 
     * how variables are serialized and stored in the session.
     *
     * @return void
     */
    private function default_manager() {
        $this->session_manager['manager'] = "default";

        // Load SESSION
        $this->set_manager($this->session_manager['manager'], "load", function ($dsm) {
            if (isset($_SESSION[$this->sessname])) {
                $construct_session = $_SESSION[$this->sessname];
                $this->values = $construct_session['values'];
                $this->variables = $construct_session['variables'];
            } else {
                $this->values = [];
                $this->variables = [];
                $this->save(); // Save the initial empty state
            }                
        });

        // Save SESSION
        $this->set_manager($this->session_manager['manager'], "save", function ($dsm) {
            $save_session = [];
            $save_session['values'] = $this->values;
            $save_session['variables'] = $this->variables;
            $_SESSION[$this->sessname] = $save_session;
        });

        // GET variable
        $this->set_manager($this->session_manager['manager'], "get", function ($name,$dsm) {
            if (isset($this->variables[$name])) {
                $varid = $this->variables[$name];
                $value = $this->values[$varid];

                if ($value && !is_array($value) && strpos($value, 'O:') === 0) {
                    $value = unserialize($value);
                }

                return $value; // Return the retrieved value
            }

            return null; // Return null if the variable does not exist
        });

        // SET variable
        $this->set_manager($this->session_manager['manager'], "set", function ($name, $value,$dsm) {
            $varid = md5($name) . md5($name . rand(1000, 2000));
        
            if (isset($this->variables[$name])) {
                unset($this->values[$this->variables[$name]]);
            }

            $this->variables[$name] = $varid;

            if (is_object($value)) {
                $value = serialize($value);
            }

            $this->values[$varid] = $value;
            $this->save(); // Save the session state after setting a value

            return $this; // For chaining
        });
    }

    /**
     * Registers a new manager for handling session operations.
     *
     * This method allows defining custom logic for managing session variables
     * by specifying how to load, save, set, or get variable values.
     *
     * @param string $manager The name of the manager.
     * @param string $way The operation type (load, save, set, get).
     * @param callable $callback The function to execute for the operation.
     * @return $this
     */

    public function set_manager($manager, $way, $callback) {
		if (is_callable($callback)) {
			$this->session_manager['managers'][$manager][$way] = $callback;
		} else throw new \Exception("Callback is not callable !");
        return $this;
    }

    /**
     * Switches to a specified manager if it is defined.
     *
     * This method updates the current session manager to the one specified 
     * by the user, allowing for different session handling strategies.
     *
     * @param string $manager The name of the manager to switch to.
     * @return $this
     * @throws \Exception If the specified manager is not defined.
     */
    public function use($manager) {
        if (isset($this->session_manager['managers'][$manager])) {
            $this->session_manager['manager'] = $manager;
        } else {
            throw new \Exception("Manager is not defined!");
        }
        return $this;
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
		call_user_func($this->session_manager['managers'][$this->session_manager['manager']]["load"], $this);
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
		call_user_func($this->session_manager['managers'][$this->session_manager['manager']]["save"], $this);
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
		call_user_func($this->session_manager['managers'][$this->session_manager['manager']]["set"], $name, $value, $this);
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
		return call_user_func($this->session_manager['managers'][$this->session_manager['manager']]["get"], $name, $this);
    }

}

?>
