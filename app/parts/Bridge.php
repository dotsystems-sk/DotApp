<?php

/**
 * DotApp Framework
 * 
 * This class facilitates the communication bridge between PHP and JavaScript 
 * within the DotApp Framework. It enables secure function invocation via 
 * AJAX requests, allowing front-end components to interact with back-end 
 * logic seamlessly.
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

/*
    The Bridge class serves as an essential component for enabling 
    client-server communication in the DotApp Framework, bridging the 
    gap between PHP functionality and JavaScript execution.

    Key Features:
    - Define PHP functions callable from JavaScript through AJAX.
    - Implement before and after callbacks for enhanced control 
      over function execution.
    - Manage session keys to ensure secure and authenticated 
      communication.

    This class is crucial for developers looking to integrate 
    dynamic functionalities within their applications while 
    maintaining a high level of security and efficiency.
*/


namespace Dotsystems\App\Parts;

class Bridge {
    private $bridge; // Stores the bridge data (functions, before/after callbacks)
    private $dotapp; // Reference to the main DotApp object
	public $dotApp; //cameCase blbuvzdornost
	public $DotApp; // PascalCase blbuvzdornost
    private $key;    // Secure key for verifying requests
    private $objects; // Store objects defined by rendering
    private $max_keys;
    private $input_filters;
    private $register_functions; // Bool. If we are not using bridge, we do not waste memory by keeping functions in memory
    private $register_function; // Ostatne dropneme kotre nesedia s tymto
    private $chain;
    private $newlimiters;
    private $keyExchange = []; // Vymena klucov pre sifrovanie.
    

    /**
     * Constructor: Initializes the bridge object, generating a secure key
     * if not already set, and setting up the bridge resolver.
     * 
     * @param object $dotapp Reference to the main DotApp instance
     */
    function __construct($dotapp) {
        $this->newlimiters = array();
        $this->dotapp = $dotapp;
		$this->dotApp = $this->dotapp;
		$this->DotApp = $this->dotapp;
		
		$this->bridge = array();
		$this->bridge['fn'] = array();
		
        $this->register_functions = true;
        $this->objects = [];
        $this->objects['limits']['global']['minute'] = 0; // No limits per minute
        $this->objects['limits']['global']['hour'] = 0; // No limits per hour
        $this->objects['limits']['global']['used'] = 0; //
        $this->objects['limiters'] = array();
        $this->objects['limitersLimits'] = array();
        $this->max_keys = 500;
        
        // Check if the bridge key exists in the session, if not, generate a new one
        if ($this->dotapp->dsm->get('_bridge.key') != null) {
            $this->key = $this->dotapp->dsm->get('_bridge.key');
            $this->objects = $this->dotapp->dsm->get('_bridge.objects');
        } else {
            $this->key = $this->dotapp->generate_strong_password(32, "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789@-");
            $this->dotapp->dsm->set('_bridge.key', $this->key);
        }

        if ($this->dotapp->dsm->get('_bridge.exchange') != null) {
            $this->keyExchange = $this->dotapp->dsm->get('_bridge.exchange');
        } else {
            $this->keyExchange['key'] = $this->dotapp->generate_strong_password(64, "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789");
            $this->dotapp->dsm->set('_bridge.exchange', $this->keyExchange);
        }
        $this->clear_chain();
        $this->keyExchanger();
        // Resolve the bridge route and function calls
        $this->bridge_resolve();
        $this->input_filter_default(); // Fill built in filters
    }

    function __destruct() {
        $this->dotapp->dsm->set('_bridge.key', $this->key);
        $this->dotapp->dsm->set('_bridge.objects', $this->objects);
        $this->dotapp->dsm->set('_bridge.exchange', $this->keyExchange);
    }

    private function keyExchanger() {
        // dotapp.js kniznica aby sa akutalizovala zaroven s aktualizaciou dotappu tak nech je tu.
        $this->dotapp->router->get("/assets/dotapp/dotapp.js", function($request) {
            // Put your hands up and make some noooise !
            $noiseRegex = '[#{.*\-()@!]'; // ide do JS
            $addNoise = function($base64Key) {
                $noiseChars = ['#', '{', '.', '*', '-', '(', ')', '@', '!'];
                $obfuscatedKey = '';
                for ($i = 0; $i < strlen($base64Key); $i++) {
                    if ($base64Key[$i] === '=') {
                        $obfuscatedKey .= '}';
                    } else {
                        $noise = '';
                        for ($j = 0; $j < rand(1, 3); $j++) {
                            $noise .= $noiseChars[array_rand($noiseChars)];
                        }
                        $obfuscatedKey .= $noise . $base64Key[$i];
                    }
                }
                return $obfuscatedKey;
            };

            $kluc = bin2hex(random_bytes(64));

            $zakoduj = function ($udaje, $kluc) {
                $result = '';
                // Vytvor hash kľúča (súčet ASCII hodnôt znakov)
                $keyHash = array_sum(array_map('ord', str_split($kluc)));
                
                // Šifruj dáta pomocou XOR s keyHash a pozíciou
                for ($i = 0; $i < strlen($udaje); $i++) {
                    $charCode = ord($udaje[$i]) ^ ($keyHash + $i) % 255;
                    $result .= chr($charCode);
                }
                
                // Pridaj kontrolný súčet (hex hodnota keyHash % 251, doplnená na 2 znaky)
                $checksum = str_pad(dechex($keyHash % 251), 2, '0', STR_PAD_LEFT);
                
                // Zakóduj výsledok do Base64
                return base64_encode($result . $checksum);
            };

            $dotappjsfile = __ROOTDIR__."/app/parts/dotapp.js";
            if (file_exists($dotappjsfile)) {
                header("Content-Type: text/javascript");
                $dotappJS = @file_get_contents($dotappjsfile);
                if (!$this->keyExchange['exchanged']) {
                    $exchangeThisKey = $zakoduj($this->dotapp->encKey(),$kluc);
                    $exChange = '#exchangenoise() {} '."\n".' #exchange() { localStorage.setItem("__key2","'.$kluc.'"); localStorage.setItem("ckey",atob(this.#exchangenoise("'.$addNoise(base64_encode($exchangeThisKey)).'"))); fetch("/assets/dotapp/dotapp.js",{method:"PUT",headers:{"Content-Type":"application/json"}}).then(e=>{e.ok||console.error("Error at key exchange:",error)}).catch(e=>{console.error("Error at key exchange:",e)}); }';
                    $dotappJS = str_replace("#exchange() {}",$exChange,$dotappJS);
                    $exChange2 = 'exchangenoise($key) { return $key.replace(/}/g, "=").replace(/' . $noiseRegex . '/g, ""); }';
                    $dotappJS = str_replace("exchangenoise() {}",$exChange2,$dotappJS);
                }
                echo $dotappJS;
            } else {
                header("Content-Type: text/javascript");
                if (!$this->keyExchange['exchanged']) {
                    $exchangeThisKey = $zakoduj($this->dotapp->encKey(),$kluc);
                    $exChange = 'exchangenoise() {} '."\n".'function dotAppExchangeKeys() { localStorage.setItem("__key2","'.$kluc.'"); localStorage.setItem("ckey",atob(exchangenoise("'.$addNoise(base64_encode($this->dotapp->encKey())).'"))); fetch("/assets/dotapp/dotapp.js",{method:"PUT",headers:{"Content-Type":"application/json"}}).then(e=>{e.ok||console.error("Error at key exchange:",error)}).catch(e=>{console.error("Error at key exchange:",e)}); } dotAppExchangeKeys();';
                    $exChange2 = 'exchangenoise($key) { return $key.replace(/}/g, "=").replace(/' . $noiseRegex . '/g, ""); }';
                    $exChange = str_replace("exchangenoise() {}",$exChange2,$exChange);
                }
                echo $exChange;
            }
        });

        $this->dotapp->router->put("/assets/dotapp/dotapp.js", function($request) {
            $this->keyExchange['exchanged'] = true;
            echo "1";
        });

        $this->dotApp->router->reserved[] = "/assets/dotapp/dotapp.js";
    }

    

    // Set maximal number of stored keys per session 
    public function max_keys($max) {
        $this->max_keys = $max;
    }

    public function generate_id() {
        return($this->dotapp->generate_strong_password(48,"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789").date("Ymdsisisihhis").md5(microtime().rand(5000,10000)));
    }

    private function input_filter_default() {
        /*
            --> INPUT FILTERS <--
			Input Filters allow you to validate or modify user input in real-time using predefined functions, which can trigger various visual feedback based on the validity of the input.
			
            General format:
			<input type="text" {{ dotbridge:input="inputname(filter_name, arg1, arg2, ...)" }}>
			
            Example:
			<input type="text" {{ dotbridge:input="street(filter)" }}>
        */

        /*
            --> EMAIL FILTER <--
			Description:
			This filter validates whether the input value is a correctly formatted email and provides visual feedback by applying CSS classes.
			
            Example:
			<input type="text" {{ dotbridge:input="newsletter.email(email, start_checking_length, class_ok, class_bad)" }}>
			
            Arguments:
			- `email`: Client-side email validation filter
			- `start_checking_length`: Minimum number of characters required to start validation (-1 means no validation)
			- `class_ok`: CSS class applied when the input is valid - only ' are allowed, not "
			- `class_bad`: CSS class applied when the input is invalid - only ' are allowed, not "

			Example usage:
			<input type="text" {{ dotbridge:input="newsletter.email(email, 5, 'valid-email', 'invalid-email')" }}>
        */
        
        $this->input_filter_add("email",function($params) {
            // Check pattern ktory nam urci ci premenna splna poziadavky
            $pattern = '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$';

            $replacement = "";

            // Vytvorime replacement
			$replacement .= ' dotbridge-pattern="'.base64_encode($pattern).'"';
			if (isSet($params[1])) $replacement .= ' dotbridge-min="'.intval($params[1]).'"';
			
			if (isSet($params[2])) {
				$params[2] = trim(str_replace("'","",$params[2]));
				$replacement .= ' dotbridge-ok="'.$params[2].'"';
			}
    
            if (isSet($params[3])) {
				$params[3] = trim(str_replace("'","",$params[3]));
				$replacement .= ' dotbridge-bad="'.$params[3].'"';
			}
            return($replacement);
        });

        /*
            --> URL FILTER <--
			Description:
			This filter validates whether the input value is a correctly formatted URL and provides visual feedback by applying CSS classes.
			
            Example:
			<input type="text" {{ dotbridge:input="web.url(url, start_checking_length, class_ok, class_bad)" }}>
			
            Arguments:
			- `url`: Client-side URL validation filter
			- `start_checking_length`: Minimum number of characters required to start validation (-1 means no validation)
			- `class_ok`: CSS class applied when the input is valid - only ' are allowed, not "
			- `class_bad`: CSS class applied when the input is invalid - only ' are allowed, not "

			Example usage:
			<input type="text" {{ dotbridge:input="web.url(url, 5, 'valid-url', 'invalid-url')" }}>
        */


        $this->input_filter_add("url",function($params) {
            // Check pattern ktory nam urci ci premenna splna poziadavky
            $pattern = '^(https?:\\/\\/)?([a-z0-9-]+\\.)+[a-z]{2,}(\\/[^\\s]*)?$';

            $replacement = "";
            
            // Vytvorime replacement
			$replacement .= ' dotbridge-pattern="'.base64_encode($pattern).'"';
			if (isSet($params[1])) $replacement .= ' dotbridge-min="'.intval($params[1]).'"';
			
			if (isSet($params[2])) {
				$params[2] = trim(str_replace("'","",$params[2]));
				$replacement .= ' dotbridge-ok="'.$params[2].'"';
			}
    
            if (isSet($params[3])) {
				$params[3] = trim(str_replace("'","",$params[3]));
				$replacement .= ' dotbridge-bad="'.$params[3].'"';
			}
            return($replacement);
        });

        /*
            --> PHONE NUMBER FILTER <--
			Description:
			This filter validates whether the input value is a correctly formatted phone number and provides visual feedback by applying CSS classes.
			
            Example:
			<input type="text" {{ dotbridge:input="contact.phone(phone, start_checking_length, class_ok, class_bad)" }}>
			
            Arguments:
			- `phone`: Client-side phone number validation filter
			- `start_checking_length`: Minimum number of characters required to start validation (-1 means no validation)
			- `class_ok`: CSS class applied when the input is valid - only ' are allowed, not "
			- `class_bad`: CSS class applied when the input is invalid - only ' are allowed, not "

			Example usage:
			<input type="text" {{ dotbridge:input="contact.phone(phone, 5, 'valid-phone', 'invalid-phone')" }}>
        */


        $this->input_filter_add("phone",function($params) {
            // Check pattern ktory nam urci ci premenna splna poziadavky
            $pattern = '^\+?[1-9]\d{1,14}$';

            $replacement = "";
            
            // Vytvorime replacement
			$replacement .= ' dotbridge-pattern="'.base64_encode($pattern).'"';
			if (isSet($params[1])) $replacement .= ' dotbridge-min="'.intval($params[1]).'"';
			
			if (isSet($params[2])) {
				$params[2] = trim(str_replace("'","",$params[2]));
				$replacement .= ' dotbridge-ok="'.$params[2].'"';
			}
    
            if (isSet($params[3])) {
				$params[3] = trim(str_replace("'","",$params[3]));
				$replacement .= ' dotbridge-bad="'.$params[3].'"';
			}
            return($replacement);
        });

        /*
            --> PASSWORD FILTER <--

			Description:
			Validates a password that contains at least 8 characters, one uppercase letter, one lowercase letter, and one number.
			
            Example:
			<input type="password" {{ dotbridge:input="user.password(password, start_checking_length, class_ok, class_bad)" }}>
			
            Arguments:
			- `password`: Client-side password validation filter
			- `start_checking_length`: Minimum number of characters required to start validation (-1 means no validation)
			- `class_ok`: CSS class applied when the input is valid - only ' are allowed, not "
			- `class_bad`: CSS class applied when the input is invalid - only ' are allowed, not "

			Example usage:
			<input type="password" {{ dotbridge:input="user.password(password, 8, 'valid-password', 'invalid-password')" }}>
        */


        $this->input_filter_add("password",function($params) {
            // Check pattern ktory nam urci ci premenna splna poziadavky
            $pattern = '^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d@$!%*?&]{8,}$';

            $replacement = "";
            
            // Vytvorime replacement
			$replacement .= ' dotbridge-pattern="'.base64_encode($pattern).'"';
			if (isSet($params[1])) $replacement .= ' dotbridge-min="'.intval($params[1]).'"';
			
			if (isSet($params[2])) {
				$params[2] = trim(str_replace("'","",$params[2]));
				$replacement .= ' dotbridge-ok="'.$params[2].'"';
			}
    
            if (isSet($params[3])) {
				$params[3] = trim(str_replace("'","",$params[3]));
				$replacement .= ' dotbridge-bad="'.$params[3].'"';
			}
            return($replacement);
        });

        /*
            --> DATE FILTER <--

			Description:
			Validates a date in the format YYYY-MM-DD.
			
            Example:
			<input type="text" {{ dotbridge:input="event.date(date, start_checking_length, class_ok, class_bad)" }}>
			
            Arguments:
			- `date`: Client-side date validation filter
			- `start_checking_length`: Minimum number of characters required to start validation (-1 means no validation)
			- `class_ok`: CSS class applied when the input is valid - only ' are allowed, not "
			- `class_bad`: CSS class applied when the input is invalid - only ' are allowed, not "

			Example usage:
			<input type="text" {{ dotbridge:input="event.date(date, 10, 'valid-date', 'invalid-date')" }}>
        */


        $this->input_filter_add("date",function($params) {
            // Check pattern ktory nam urci ci premenna splna poziadavky
            $pattern = '^\d{4}-\d{2}-\d{2}$';

            $replacement = "";
            
            // Vytvorime replacement
			$replacement .= ' dotbridge-pattern="'.base64_encode($pattern).'"';
			if (isSet($params[1])) $replacement .= ' dotbridge-min="'.intval($params[1]).'"';
			
			if (isSet($params[2])) {
				$params[2] = trim(str_replace("'","",$params[2]));
				$replacement .= ' dotbridge-ok="'.$params[2].'"';
			}
    
            if (isSet($params[3])) {
				$params[3] = trim(str_replace("'","",$params[3]));
				$replacement .= ' dotbridge-bad="'.$params[3].'"';
			}
            return($replacement);
        });

        /*
            --> TIME FILTER <--

			Description:
			Validates a time in 24-hour format (HH:MM).
			
            Example:
			<input type="text" {{ dotbridge:input="event.time(time, start_checking_length, class_ok, class_bad)" }}>
			
            Arguments:
			- `time`: Client-side time validation filter
			- `start_checking_length`: Minimum number of characters required to start validation (-1 means no validation)
			- `class_ok`: CSS class applied when the input is valid - only ' are allowed, not "
			- `class_bad`: CSS class applied when the input is invalid - only ' are allowed, not "

			Example usage:
			<input type="text" {{ dotbridge:input="event.time(time, 5, 'valid-time', 'invalid-time')" }}>
        */



        $this->input_filter_add("time",function($params) {
            // Check pattern ktory nam urci ci premenna splna poziadavky
            $pattern = '^([01]?[0-9]|2[0-3]):([0-5][0-9])$';

            $replacement = "";
            
            // Vytvorime replacement
			$replacement .= ' dotbridge-pattern="'.base64_encode($pattern).'"';
			if (isSet($params[1])) $replacement .= ' dotbridge-min="'.intval($params[1]).'"';
			
			if (isSet($params[2])) {
				$params[2] = trim(str_replace("'","",$params[2]));
				$replacement .= ' dotbridge-ok="'.$params[2].'"';
			}
    
            if (isSet($params[3])) {
				$params[3] = trim(str_replace("'","",$params[3]));
				$replacement .= ' dotbridge-bad="'.$params[3].'"';
			}
            return($replacement);
        });

        /*
            --> CREDIT CARD FILTER <--

			Description:
			Validates major credit card formats (Visa, MasterCard, American Express, etc.) using Luhn's algorithm.
			
            Example:
			<input type="text" {{ dotbridge:input="payment.card(creditcard, start_checking_length, class_ok, class_bad)" }}>
			
            Arguments:
			- `creditcard`: Client-side credit card validation filter
			- `start_checking_length`: Minimum number of characters required to start validation (-1 means no validation)
			- `class_ok`: CSS class applied when the input is valid - only ' are allowed, not "
			- `class_bad`: CSS class applied when the input is invalid - only ' are allowed, not "

			Example usage:
			<input type="text" {{ dotbridge:input="payment.card(creditcard, 16, 'valid-card', 'invalid-card')" }}>
        */



        $this->input_filter_add("creditcard",function($params) {
            // Check pattern ktory nam urci ci premenna splna poziadavky
            $pattern = '^(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|3[47][0-9]{13}|6011[0-9]{12}|2(?:22[1-9][0-9]{12}|[2-7][0-9]{14}))$';

            $replacement = "";
            
            // Vytvorime replacement
			$replacement .= ' dotbridge-pattern="'.base64_encode($pattern).'"';
			if (isSet($params[1])) $replacement .= ' dotbridge-min="'.intval($params[1]).'"';
			
			if (isSet($params[2])) {
				$params[2] = trim(str_replace("'","",$params[2]));
				$replacement .= ' dotbridge-ok="'.$params[2].'"';
			}
    
            if (isSet($params[3])) {
				$params[3] = trim(str_replace("'","",$params[3]));
				$replacement .= ' dotbridge-bad="'.$params[3].'"';
			}
            return($replacement);
        });

        /*
            --> USERNAME FILTER <--

			Description:
			Validates usernames consisting of 3 to 16 alphanumeric characters, underscores, or hyphens.
			
            Example:
			<input type="text" {{ dotbridge:input="user.username(username, start_checking_length, class_ok, class_bad)" }}>
			
            Arguments:
			- `username`: Client-side username validation filter
			- `start_checking_length`: Minimum number of characters required to start validation (-1 means no validation)
			- `class_ok`: CSS class applied when the input is valid - only ' are allowed, not "
			- `class_bad`: CSS class applied when the input is invalid - only ' are allowed, not "

			Example usage:
			<input type="text" {{ dotbridge:input="user.username(username, 3, 'valid-username', 'invalid-username')" }}>
        */



        $this->input_filter_add("username",function($params) {
            // Check pattern ktory nam urci ci premenna splna poziadavky
            $pattern = '^[a-zA-Z0-9_-]{3,16}$';

            $replacement = "";
            
            // Vytvorime replacement
			$replacement .= ' dotbridge-pattern="'.base64_encode($pattern).'"';
			if (isSet($params[1])) $replacement .= ' dotbridge-min="'.intval($params[1]).'"';
			
			if (isSet($params[2])) {
				$params[2] = trim(str_replace("'","",$params[2]));
				$replacement .= ' dotbridge-ok="'.$params[2].'"';
			}
    
            if (isSet($params[3])) {
				$params[3] = trim(str_replace("'","",$params[3]));
				$replacement .= ' dotbridge-bad="'.$params[3].'"';
			}
            return($replacement);
        });

        /*
            --> IPV4 FILTER <--

			Description:
			Validates IPv4 addresses in the format X.X.X.X, where X is a number between 0 and 255.
			
            Example:
			<input type="text" {{ dotbridge:input="network.ipv4(ipv4, start_checking_length, class_ok, class_bad)" }}>
			
            Arguments:
			- `ipv4`: Client-side IPv4 validation filter
			- `start_checking_length`: Minimum number of characters required to start validation (-1 means no validation)
			- `class_ok`: CSS class applied when the input is valid - only ' are allowed, not "
			- `class_bad`: CSS class applied when the input is invalid - only ' are allowed, not "

			Example usage:
			<input type="text" {{ dotbridge:input="network.ipv4(ipv4, 7, 'valid-ip', 'invalid-ip')" }}>
        */

        $this->input_filter_add("ipv4",function($params) {
            // Check pattern ktory nam urci ci premenna splna poziadavky
            $pattern = '^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$';

            $replacement = "";
            
            // Vytvorime replacement
			$replacement .= ' dotbridge-pattern="'.base64_encode($pattern).'"';
			if (isSet($params[1])) $replacement .= ' dotbridge-min="'.intval($params[1]).'"';
			
			if (isSet($params[2])) {
				$params[2] = trim(str_replace("'","",$params[2]));
				$replacement .= ' dotbridge-ok="'.$params[2].'"';
			}
    
            if (isSet($params[3])) {
				$params[3] = trim(str_replace("'","",$params[3]));
				$replacement .= ' dotbridge-bad="'.$params[3].'"';
			}
            return($replacement);
        });

    }

    public function input_filter_run($params) {
        $filtername = $params[0];

        if (isSet($this->input_filters[$filtername]) && is_callable($this->input_filters[$filtername])) {
            return($this->input_filters[$filtername]($params));
        }

        return("");
    }

    public function input_filter_add($filter_name,$callback) {
        $this->input_filters[$filter_name] = $callback;
    }

    private function regenerate_data($internalID) {
        $newdata['dotbridge-regenerate'] = 1;
        $newdata['dotbridge-id'] = $this->generate_id();
        if (strlen($newdata['dotbridge-id']) < 20) $newdata['dotbridge-id'] = $this->generate_id();
        $newdata['dotbridge-data'] = $this->dotapp->encrypt($this->objects['settings'][$internalID]['function'],$newdata['dotbridge-id']);
        $newdata['dotbridge-data-id'] = $this->dotapp->encrypt($internalID,$newdata['dotbridge-id']);
        $this->objects['settings'][$internalID]['valid'][$newdata['dotbridge-id']] = $newdata['dotbridge-id'];
        $this->clean_array($this->objects['settings'][$internalID]['valid']);
        return($newdata);
    }

    private function clean_array(&$array) {
        try {
            if (is_array($array)) {
                while( count($array) > $this->max_keys ) {
                    $array = array_shift($array);
                }
            }            
        } catch (Exception $e) {

        }        
    }

    public function register_listener($key,$regenerateId,$oneTimeUse,$ratelimiters,$internalID,$expireAt,$function) {
        try {
            $this->objects['settings'][$internalID]['function'] = $function;
            $this->objects['settings'][$internalID]['regenerateId'] = $regenerateId;
            $this->objects['settings'][$internalID]['oneTimeUse'] = $oneTimeUse;
            $this->objects['settings'][$internalID]['expireAt'] = intval($expireAt);
            if (!is_array($this->objects['settings'][$internalID]['valid'])) {
                $this->objects['settings'][$internalID]['valid'] = array();
            }                
            $this->objects['settings'][$internalID]['valid'][$key] = $key;
            $this->clean_array($this->objects['settings'][$internalID]['valid']);
            $this->objects['limits'][$internalID]['used'] = 0;
            $this->objects['limits'][$internalID]['created'] = time();
            /*
                $this->objects['limits'][$internalID]['minute'] = intval($ratelimitm);
                $this->objects['limits'][$internalID]['hour'] = intval($ratelimith);
            */
            // Stare riesenie, prechadzame na nove...
            $limiters=array();
            foreach ($ratelimiters as $ratelimiter) $limiters[$ratelimiter['seconds']] = $ratelimiter['count'];
            

            if (!empty($limiters)) {
                $this->objects['limitersLimits'][$internalID] = $limiters;
            } else $this->objects['limitersLimits'][$internalID] = array();

        } catch(Exception $e) {

        }
    }

    public function limitPerMinute($limit) {
        if ($limit < 0) $limit = 0;
        $this->objects['limits']['global']['minute'] = $limit;
        return($this);
    }

    public function limitPerHour($limit) {
        if ($limit < 0) $limit = 0;
        $this->objects['limits']['global']['hour'] = $limit;
        return($this);
    }

    private function check_clicks_in_time(&$timelist,$time,$clear=0) {
        if (! is_array($timelist)) return(0);

        // Vypocitame si cas od ktoreho chceme spicitat pocet kliknuti az po sucasnost.
        $time_start = ceil(microtime(true)*100) - $time*100;

        $clickCount = array_filter($timelist, function($clicktime) use ($time_start) {
            return $clicktime > $time_start;
        }, ARRAY_FILTER_USE_KEY);

        // Clear old clicks list...
        if ($clear == 1 ) {
            $timelist = $clickCount;
        }

        // Count the number of clicks in the last hour
        return(count($clickCount));
    }

    private function increase_counters($internalID,$doit=true) {
        if ($doit == false) return "";
        $timekey = ceil(microtime(true)*100);
        $this->objects['limits']['global']['usedAt'][$timekey] = 1;
        $this->objects['limits']['global']['used'] = $this->objects['limits']['global']['used'] + 1;
        $this->objects['limits'][$internalID]['usedAt'][$timekey] = 1;
        $this->objects['limits'][$internalID]['used'] = $this->objects['limits'][$internalID]['used']+1;
        
        if ($this->objects['settings'][$internalID]['oneTimeUse']) {
            unset($this->objects['settings'][$internalID]);
            unset($this->objects['limits'][$internalID]);
        }
    }

    
    private function check_rate($internalID) {
        $limits = $this->objects['limitersLimits'][$internalID] ?? array();
        if (empty($limits)) {
            $this->dotapp->router->request->response->limiter = false;
            return true;
        }

        if (!is_object($this->newlimiters[$internalID])) $this->newlimiters[$internalID] = new Limiter($limits,$internalID,$this->dotapp->limiter['getter'],$this->dotapp->limiter['setter']);

        $this->dotapp->router->request->response->limiter = $this->newlimiters[$internalID];
        if ($this->newlimiters[$internalID]->isAllowed("/dotapp/bridge")) {
            return true;
        }
        return false;        
    }
    // Tato funkcia je este z dob ked sme brutalne presne riesili pocet kliknuti v case. Ale takto to netreba prehanat lebo je to pomalsie potom.
    // 2021 - Vyradena funkcia, prechadzame na novu verziu. V kode ju nechavame, az bude cas precistime kod.
    private function check_rateOLD($internalID) {
       
        if ($this->objects['settings'][$internalID]['expireAt'] > 0) {
            $kokotina = time();
            if (time() > $this->objects['settings'][$internalID]['expireAt']) {
                unset($this->objects['settings'][$internalID]);
                unset($this->objects['limits'][$internalID]);
            }
        }

        // Neocakavane ID !
        if (!isSet($this->objects['settings'][$internalID]['regenerateId'])) return(false);
        
        // Global clicks in last minute
        $globalMbool = false;
        
        if ($this->objects['limits']['global']['minute'] == 0) { $globalMbool = true; $clear = true; }
        if (!$globalMbool) {
            $lastMinuteClicks = $this->check_clicks_in_time($this->objects['limits']['global']['usedAt'],60);
            if ($lastMinuteClicks < ($this->objects['limits']['global']['minute'])) $globalMbool = true;
            if (!$globalMbool) {
                $this->increase_counters($internalID,$globalMbool);
                return(false);
            }
        }

        // Global clicks in last hour
        $globalHbool = false;
        
        if ($this->objects['limits']['global']['hour'] == 0) { $globalHbool = true; $clear2 = true; }
        if (!$globalHbool) {
            $lastHourClicks = $this->check_clicks_in_time($this->objects['limits']['global']['usedAt'],3600,1);
            if ($lastHourClicks < ($this->objects['limits']['global']['hour'])) $globalHbool = true;
            if (!$globalHbool) {
                $this->increase_counters($internalID,$globalHbool);
                return(false);
            }
        }

        if ($clear && $clear2) $this->objects['limits']['global']['usedAt'] = [];

        // Local clicks in last minute
        $globalMbool = false;
        
        if ($this->objects['limits'][$internalID]['minute'] == 0) { $globalMbool = true; $clear3 = true; }
        if (!$globalMbool) {
            $lastMinuteClicks = $this->check_clicks_in_time($this->objects['limits'][$internalID]['usedAt'],60);
            if ($lastMinuteClicks < ($this->objects['limits'][$internalID]['minute'])) $globalMbool = true;
            if (!$globalMbool) {
                $this->increase_counters($internalID,$globalMbool);
                return(false);
            }
        }

        // Local clicks in last hour
        $globalHbool = false;
        
        if ($this->objects['limits'][$internalID]['hour'] == 0) { $globalHbool = true; $clear4 = true; }
        if (!$globalHbool) {
            $lastHourClicks = $this->check_clicks_in_time($this->objects['limits'][$internalID]['usedAt'],3600,1);
            if ($lastHourClicks < ($this->objects['limits'][$internalID]['hour'])) $globalHbool = true;
            if (!$globalHbool) {
                $this->increase_counters($internalID,$globalHbool);
                return(false);
            }
        }

        if ($clear3 && $clear4) $this->objects['limits'][$internalID]['usedAt'] = [];
        
        $this->increase_counters($internalID,true);
        return(true);
    }

    /**
     * Resolves bridge requests coming from the JS side through AJAX.
     * It validates the key, checks the function, runs any before and after 
     * callbacks, and returns the response.
     */
    private function bridge_resolve() {

        $bridge_used = $this->dotapp->router->request->getPath();
        if ($bridge_used == "/dotapp/bridge") {
            $postdata = $_POST['data'];
            $postdata['dotbridge-id'] = $this->dotapp->unprotect_data($postdata['dotbridge-id']);
            $key = $postdata['dotbridge-id'];
            if ($this->dotapp->crc_check($key, $_POST['crc'], $postdata)) {
                $function_enc = $this->dotapp->unprotect_data($postdata['dotbridge-data']);
                $function_name = $this->dotapp->decrypt($function_enc, $key);
                $this->register_function = (string)$function_name;
                $this->register_functions = true;
            } else $this->register_functions = false;
            
        } else $this->register_functions = false;

		$this->dotapp->router->any("/dotapp/bridge", function() {
            header('X-Answered-By: dotbridge');
			$postdata = $_POST['data'];
            $bridge_key = $this->dotapp->dsm->get('_bridge.key');
            // Check if rate limits was not exceeded
                  
            // Verify if the session bridge key matches the received bridge key
            if ($bridge_key == $postdata['dotbridge-key']) {
                $postdata['dotbridge-id'] = $this->dotapp->unprotect_data($postdata['dotbridge-id']);
                $key = $postdata['dotbridge-id'];

                    // Check the CRC for validation
                if ($this->dotapp->crc_check($key, $_POST['crc'], $postdata)) {

                    $postdata['dotbridge-id'] = $this->dotapp->unprotect_data($postdata['dotbridge-id']);
                    $internalID = $this->dotapp->decrypt($postdata['dotbridge-data-id'], $postdata['dotbridge-id']);
                    if ( $internalID != false && isSet($this->objects['settings'][$internalID]['valid'][$key]) && $this->check_rate($internalID)) {
                        
                        // Decrypt the function name
                        $function_enc = $postdata['dotbridge-data'];
                        $function_name = $this->dotapp->decrypt($function_enc, $key);

                        // Check if the function exists and is callable
                        if (is_callable($this->bridge['fn'][$function_name])) {
                                
                            // Execute any 'before' callbacks for the function
                            foreach ($this->bridge['before'][$function_name] as $beforefn) {
                                // PHP 7 a viac.
								$postdata = call_user_func($beforefn,$postdata,$this->dotapp->router->request) ?? $postdata;
                            }
                                
                            // Execute the main function and capture the return data
                            $return_data = call_user_func($this->bridge['fn'][$function_name],$postdata,$this->dotapp->router->request);

                            if ( $this->objects['settings'][$internalID]['regenerateId'] ) {
                                unset($this->objects['settings'][$internalID]['valid'][$key]);
                                $return_data = array_merge($return_data,$this->regenerate_data($internalID));
                            }

                            // Execute any 'after' callbacks for the function
                            foreach ($this->bridge['after'][$function_name] as $afterfn) {
								// PHP 7 a viac.
                                $return_data = $afterfn($return_data,$this->dotapp->router->request) ?? $return_data;
                            }

                            // Prepare the response data
                            $default_data['status'] = 1;
                            if (is_array($return_data)) {
                                $return_data = array_merge($default_data, $return_data);
                            } else {
                                $return_data = $default_data;
                            }
                                
                            // Return the AJAX reply with the function result
                            return $this->dotapp->ajax_reply($return_data,200);
                        } else {
                            // Error: Function not found
                            $data['status'] = 0;
                            $data['error_code'] = 3;
                            $data['status_txt'] = "Function not found!";
                            return $this->dotapp->ajax_reply($data,404);
                        }   
                    } else {
                        // Error: Rate limit exceeded
                        $data['status'] = 0;
                        $data['error_code'] = 4;
                        $data['status_txt'] = "Rate limit exceeded !";
                        return $this->dotapp->ajax_reply($data,429);
                    }
                } else {
                    // Error: CRC check failed
                    $data['status'] = 0;
                    $data['error_code'] = 1;
                    $data['status_txt'] = "CRC check failed!";
                    return $this->dotapp->ajax_reply($data,400);
                }
            } else {
                // Error: Bridge key does not match
                $data['status'] = 0;
                $data['error_code'] = 2;
                $data['status_txt'] = "Bridge key does not match!";
                return $this->dotapp->ajax_reply($data,403);
            }
    	});

        // Add the route as reserved in the router
		$this->dotapp->router->reserved[] = "dotapp/bridge";
		return($this);
	}

    /**
     * Checks if a callback function is defined.
     *
     * @param string $function_name The name of the function to check
     * @return bool Returns true if the function exists, false otherwise
     */
    public function isfn($function_name) {
        return isset($this->bridge['fn'][$function_name]);
    }

    public function clear_chain() {
		$this->chain = null;
		return $this;
	}

    /**
     * Defines a callable function in the bridge.
     *
     * @param string $function_name The name of the function to define
     * @param callable $callback The function to associate with the name
     * @return object Returns the current instance of the bridge for chaining
     * @throws Exception Throws an exception if the callback is not callable
     */
	public function fn($function_name,$callback) {
        $this->chain = $function_name;
        // Trocha optimalizacie do toho dotbridzovania...
        // Ak nesedi routa, neregistrujeme absolutne nic
        if ($this->register_functions === false) return $this->chainMe(null); // Hodime prazdnu retaz aby sme nerozbili chaining 
        // Ak sedi routa, povolime zaregistrovat len funkciu na ktoru routa odkazuje cim setrime zdroje
        if ($this->register_function !== $function_name && $this->register_function !== true) return $this->chainMe(null); // Hodime prazdnu retaz aby sme nerozbili chaining

        if (!is_callable($callback)) $callback = $this->dotapp->stringToCallable($callback);

        if (is_callable($callback)) {
            $this->bridge['fn'][$function_name] = $callback;
        } else {
            throw new \Exception("Callback is not a function!");
        }		
		return $this->chainMe($function_name);
	}
	
	public function chainMe($function_name) {
		// Retazime po novom, opustame php 5.6 nadobro po rokoch. Ideme na podporu uz len php >= 7.4
        // Urveme mu anonymnu triedu naspat na retazenie
		$obj = $this;
		return new class($obj,$function_name) {
            private $parentObj;
			private $fnName;
    
            public function __construct($parent,$function_name) {
                $this->parentObj = $parent;
				$this->fnName = $function_name;
            }
			
			public function before($callback) {
                if (!is_callable($callback)) $callback = $this->dotapp->stringToCallable($callback);
                // Drop da callback, nech nezabera miesto
				if (isSet($this->fnName)) $this->parentObj->before($this->fnName,$callback);
				return $this;
			}

            public function throttle($limity) {
                $this->limiter = new Limiter($limity,$this->metoda,$this->router->dotapp->limiter['getter'],$this->router->dotapp->limiter['setter']);
            }
			
			public function after($callback) {
                if (!is_callable($callback)) $callback = $this->dotapp->stringToCallable($callback);
                // Drop da callback, nech nezabera miesto
				if (isSet($this->fnName)) $this->parentObj->after($this->fnName,$callback);
				return $this;
			}
			
		};
		
	}


    /**
     * Defines a "before" callback to be executed before the main function.
     * 
     * If one argument is passed, the function will use the last registered function name from the chain.
     * If two arguments are passed, the first argument is the function name, and the second is the callback.
     *
     * @param mixed ...$args Either a single callable (for chained usage) or a function name and a callable.
     *                       - If one argument: callable $callback (uses the last function name from the chain)
     *                       - If two arguments: string $function_name, callable $callback
     * @return object Returns the current instance of the bridge for chaining
     * @throws Exception Throws an exception if the callback is not callable
     */
    public function before(...$args) {
		// Ostava definovana po starom, kvoli starsim aplikaciam, ale v chaine sa uz vyuziva novy objekt
        if (count($args) == 1) {
            $function_name = $this->chain;
            $callback = $args[0];
        } else {
            $function_name = $args[0];
            $callback = $args[1];
        }
        $this->chain = $function_name;
        if ($this->register_functions == false) return $this->dotapp->bridge;
        if (is_callable($callback)) {
            $this->bridge['before'][$function_name][] = $callback;
        } else {
            throw new \Exception("Callback is not a function!");
        }
		return $this;
	}

    /**
     * Defines an "after" callback to be executed after the main function.
     * 
     * If one argument is passed, the function will use the last registered function name from the chain.
     * If two arguments are passed, the first argument is the function name, and the second is the callback.
     *
     * @param mixed ...$args Either a single callable (for chained usage) or a function name and a callable.
     *                       - If one argument: callable $callback (uses the last function name from the chain)
     *                       - If two arguments: string $function_name, callable $callback
     * @return object Returns the current instance of the bridge for chaining
     * @throws Exception Throws an exception if the callback is not callable
     */
    public function after(...$args) {
		// Ostava definovana po starom, kvoli starsim aplikaciam, ale v chaine sa uz vyuziva novy objekt
        if (count($args) == 1) {
            $function_name = $this->chain;
            $callback = $args[0];
        } else {
            $function_name = $args[0];
            $callback = $args[1];
        }
        $this->chain = $function_name;
        if ($this->register_functions == false) return $this->dotapp->bridge;
        if (is_callable($callback)) {
            $this->bridge['after'][$function_name][] = $callback;
        } else {
            throw new \Exception("Callback is not a function!");
        }		
		return $this;
	}
}

?>