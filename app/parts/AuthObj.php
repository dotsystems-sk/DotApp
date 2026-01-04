<?php
namespace Dotsystems\App\Parts;

class AuthObj {
    private $authData = [];
    private $dsm; // Session manager (Dotsystems Session Manager)
    private $lock = false;
    private $whatToLock = [];
    public $dotapp;
    public $dotApp;
    public $DotApp;

    public static $And = 'AND';
    public static $Or = 'OR';


    /**
     * Konštruktor inicializuje triedu s referenciami na DotApp a DSM.
     * Ak je DSM null, tak vytvorime novy, nakolko niekto moze volat auth aj mimo injektor zadefinovanim novej. Blbuvzdornost...
     */
    public function __construct($dotapp, $requestDSM=null) {
        $this->dotapp = $dotapp;
        $this->dotApp = $this->dotapp;
        $this->DotApp = $this->dotapp;
        if ($requestDSM instanceof DSM) {
            $this->dsm = $requestDSM;
        } else {
            $this->dsm = $this->dotapp->request->getDsm();
        }

        // Načítaj existujúce autentifikačné dáta z relácie, ak existujú
        $this->authData = $this->dsm->get("_request.auth") ?? $this->resetAuthData();
    }

    private function resetAuthData() {
        $this->authData = $this->dsm->get("_request.auth") ?? [
            'logged' => false,
            'user_id' => null,
            'username' => null,
            'logged_stage' => 0, // 0 = neprihlaseny, 1 - prihlaseny, 2 = cakame na 2FA potvrdenie
            'roles' => [],
            'token' => null,
            'last_login' => null,
            'last_activity' => null,
            'session_id' => null,
            'attributes' => [],
            'permissions'  => [],
            'tfa' => null, // 2 factor TOTP
            'tfa_sms' => null, // 2 factor SMS
            'tfa_email' => null, // 2 factor Email
            'rm' => null
        ];
        return $this->authData;
    }

    /**
     * Deštruktor ukladá autentifikačné dáta do relácie.
     */
    public function __destruct() {
        $this->dsm->set("_request.auth", $this->authData);
    }

    /**
     * Setter pre dynamické nastavenie autentifikačných údajov.
     * Vratime FALSE ak je parameter zamknuty
     */
    public function __set($name, $value) {
        if ( !in_array($name,$this->whatToLock) ) {
            $this->authData[$name] = $value;
            return true;
        } else {
            if (!$this->lock) {
                $this->authData[$name] = $value;
                return true;
            }
            return false;
        }
    }

    /**
     * Getter pre prístup k autentifikačným údajom.
     */
    public function __get($name) {
        return $this->authData[$name] ?? null;
    }

    // Nastavime ktore polozky, ktore chceme zamknut. Zamok nie je mozne odomknut !
    public function lock(array $whatToLock=array()) {
        // Zamkneme moznosti zmeny ID uzivatela, povoleni a podobne po prihlaseni. 
        if ($this->lock == true) return false; // Dame vediet, ze lock sa nepodaril
        $this->whatToLock = ['permissions','logged','user_id'];
        if (!empty($whatToLock)) $this->whatToLock = $whatToLock;
        $this->lock = true;
        return true;
    }

    /**
     * Checks if the authentication data is currently locked.
     *
     * This function determines whether the authentication data has been locked,
     * preventing further modifications to certain fields.
     *
     * @return bool True if the authentication data is locked, false otherwise.
     */
    public function isLocked() {
        return $this->lock;
    }

    /**
     * Checks if the user is currently logged in.
     *
     * This function verifies the user's authentication status by checking the
     * 'logged_stage' and 'logged' fields in the authentication data.
     *
     * @return bool True if the user is logged in, false otherwise.
     */
    public function isLogged() {
        // Je zamknuty?
        return ($this->authData['logged_stage'] === 1 && ($this->authData['logged'] === true || $this->authData['logged'] === 1));
    }

    public function loggedStage() {
        return $this->authData['logged_stage'];
    }

    public function tfaTotp() {
        if ($this->loggedStage() == 2) {
            return TOTP::generate($this->authData['tfa']);
        } else {
            return null;
        }
    }

    public function tfaSms() {
        if ($this->loggedStage() == 2) {
            return $this->authData['tfa_sms'] ?? null;
        } else {
            return null;
        }
    }

    public function tfaEmail() {
        if ($this->loggedStage() == 2) {
            return $this->authData['tfa_email'] ?? null;
        } else {
            return null;
        }
    }

    public function userId() {
        return $this->authData['user_id'];
    }

    public function username() {
        return $this->authData['username'];
    }

    public function roles() {
        return $this->authData['roles'];
    }

    public function token() {
        return $this->authData['token'];
    }

    /**
     * Retrieves the timestamp of the user's last login.
     *
     * @return int|null The timestamp of the last login, or null if not set.
     */
    public function lastLogin() {
        return $this->authData['last_login'];
    }

    /**
     * Retrieves the timestamp of the user's last activity.
     *
     * @return int|null The timestamp of the last activity, or null if not set.
     */
    public function lastActivity() {
        return $this->authData['last_activity'];
    }

    /**
     * Retrieves the attributes associated with the authenticated user.
     *
     * @return array An array of attributes assigned to the user.
     */
    public function attributes() {
        return $this->authData['attributes'];
    }

    /**
     * Retrieves the permissions associated with the authenticated user.
     *
     * @return array An array of permissions assigned to the user.
     */
    public function permissions() {
        return $this->authData['permissions'];
    }

    /**
     * Creates a new user in the system.
     *
     * This function checks if a user with the given username or email already exists.
     * If not, it creates a new user with the provided details.
     *
     * @param string $username The username for the new user.
     * @param string $password The password for the new user.
     * @param string|null $email The email address for the new user. Must be a valid email if provided.
     * @param array|null $otherData Additional data to be stored for the user.
     * 
     * @return array An associative array containing the result of the operation.
     *               'error' key indicates the error code (0 for success, 1 for user exists, 99 for database related errors).
     *               'error_txt' key provides a textual description of the error, if any.
     */
    public function createUser($username, $password, $email = null, $otherData = null) {
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("Invalid email address.");
        }
        $vysledok = [];
        $vysledok['error'] = 0;
        // Aby sme zabazpecili funkcnost na roznych typoch databaz, nebudeme riesit ci uzivatel existuje sledoovanim chyby a duplicate entry ale manualnme ho skontrolujeme
        $najdenazhoda = null;
        DB::module("RAW")
        ->q(function ($qb) use ($username,$email) {
                if ($email === null) {
                    $qb
                    ->select('id', Config::get("db","prefix").'users')
                    ->where('username','=',$username);
                } else {
                    $qb
                    ->select('id', Config::get("db","prefix").'users')
                    ->where('email','=',$email);     
                }            
            }
        )->execute(
            function ($result, $db, $debug) use (&$najdenazhoda) {
                if (empty($result)) $najdenazhoda = false; else $najdenazhoda = true;
            },
            function ($error, $db, $debug) use (&$vysledok) {
                $vysledok['error'] = 99;
                $vysledok['error_txt'] = "Error: {$error['error']} (code: {$error['errno']})";
                $vysledok['error_data'] = $error;
            }
        );

        if ($vysledok['error'] != 0) return $vysledok;

        if ($najdenazhoda) {
            $vysledok['error'] = 1;
            $vysledok['error_txt'] = "User with this username or email already exists.";
            return $vysledok;
        }

        DB::module("RAW")->q(function ($qb) use ($username, $email, $password, $otherData) {
            $cu = [];
            $cu['username'] = $username;
            $cu['password'] = $this->DotApp->generatePasswordHash($password);
            if ($email) $cu['email'] = $email;
            $cu['created_at'] = date('Y-m-d H:i:s');
            if ($otherData) $cu = array_merge($cu, $otherData);
            $qb->insert(Config::get("db", "prefix") . 'users', $cu);
        })
        ->execute(function($result, $db, $debug) use (&$vysledok) {
            $vysledok['error'] = 0;
        }, function ($error, $db, $debug) use (&$vysledok) {
            $vysledok['error'] = 99;
            $vysledok['error_txt'] = "Error: {$error['error']} (code: {$error['errno']})";
            $vysledok['error_data'] = $error;
        });

        return $vysledok;
    }    

    
    /**
     * Validates the second stage of the login process using two-factor authentication.
     *
     * This function checks the provided two-factor authentication codes against the stored values
     * to determine if the second stage of login can be completed successfully.
     *
     * @param array $tfa An associative array containing the two-factor authentication codes:
     *                   - 'tfa' (string|null): The TOTP code for two-factor authentication.
     *                   - 'tfa_sms' (string|null): The SMS code for two-factor authentication.
     *                   - 'tfa_email' (string|null): The email code for two-factor authentication.
     *
     * @return array An associative array containing the result of the validation:
     *               - 'error' (int): Error code (0 for success, other values for specific errors).
     *               - 'error_txt' (string|null): A textual description of the error, if any.
     */
    public function confirmTwoFactor($tfa) {
        if ($this->authData['logged_stage'] == 2) {
            if (isset($tfa['tfa']) && $tfa['tfa'] !== null) {
                if ($tfa['tfa'] == Auth::tfaTotp()) {
                    $reply['confirmed'] = true;
                    $reply['error'] = 0;
                    $this->authData['logged_stage'] = 1;
                    if ($this->authData['logged_stage'] === true || Config::session("rm_always_use") === true) $this->setRmToken();
                    return $reply;
                } else {
                    $reply['error'] = 2;
                    $reply['confirmed'] = false;
                    $reply['error_txt'] = "Invalid two-factor totp authentication code.";
                    return $reply;
                }
            }
            if (isset($tfa['tfa_sms']) && $tfa['tfa_sms'] !== null && $this->authData['tfa_sms'] !== null) {
                if ($tfa['tfa_sms'] == $this->authData['tfa_sms']) {
                    $this->authData['logged_stage'] = 1;
                    if ($this->authData['logged_stage'] === true || Config::session("rm_always_use") === true) $this->setRmToken();
                    $reply['error'] = 0;
                    $reply['confirmed'] = true;
                    return $reply;
                } else {
                    $reply['error'] = 3;
                    $reply['confirmed'] = false;
                    $reply['error_txt'] = "Invalid two-factor sms authentication code.";
                    return $reply;
                }
            }
            if (isset($tfa['tfa_email']) && $tfa['tfa_email'] !== null && $this->authData['tfa_email'] !== null) {
                if ($tfa['tfa_email'] == $this->authData['tfa_email']) {
                    $this->authData['logged_stage'] = 1;
                    if ($this->authData['rm'] === true || Config::session("rm_always_use") === true) $this->setRmToken();
                    $reply['error'] = 0;
                    $reply['confirmed'] = true;
                    return $reply;
                } else {
                    $reply['error'] = 4;
                    $reply['confirmed'] = false;
                    $reply['error_txt'] = "Invalid two-factor email authentication code.";
                    return $reply;
                }
            }
            $reply['error'] = 5;
            $reply['confirmed'] = false;
            $reply['error_txt'] = "Two-factor authentication method not recognized.";
            return $reply;
        } else {
            $reply['error'] = 1;
            $reply['confirmed'] = false;
            $reply['error_txt'] = "Invalid login stage.";
            return $reply;
        }
    }

    /**
     * Logs in a user based on provided credentials and manages session and remember-me functionality.
     *
     * @param array $data An associative array containing login credentials and options:
     *                    - 'email' (string|null): The user's email address.
     *                    - 'username' (string|null): The user's username.
     *                    - 'password' (string|null): The user's password.
     *                    - 'passwordHash' (string|null): The hashed password.
     *                    - 'stage' (int|null): The login stage, typically 0 for initial login.
     *                    - 'authCode' (string|null): The authentication code for 2FA, if applicable.
     * @param bool $rememberMe Indicates whether to enable the remember-me functionality for persistent login.
     * @param bool $fromRM Indicates if the login attempt is from a remember-me token.
     *
     * @return array An associative array containing the result of the login attempt:
     *               - 'logged' (bool): True if login was successful, false otherwise.
     *               - 'error' (int): Error code (0 for success, other values for specific errors).
     *               - 'error_txt' (string|null): A textual description of the error, if any.
     */
    public function login($data, $rememberMe = null, $fromRM = false) {
        if (Config::session("rm_always_use") === true) {
            $rememberMe = true;
        }
        $reply = [];
        $reply['logged'] = false;
        $reply['error'] = 0;
        $reply['error_txt'] = null;

        $loginData = [];
        $loginData['return'] = false;
        /*
            Ak pouzivame vstavane tabulky DOT HUB-u, tak tato funkcia je plne funkcna a pouzitelna.
            Uzivatel si moze uz sam robit modul, ktory bude prihlasovat a nie je odkazany na modul users.
        */
        $email = $data['email'] ?? null;
        $username = $data['username'] ?? null;
        if ($email && $username) {
            $reply['logged'] = false;
            $reply['error'] = 5;
            $reply['error_txt'] = "Both email and username are specified! Please specify only one!";
            return $reply;
        }
        $passwordHash = $data['password'] ?? $data['passwordHash'] ?? null;
        $stage = $data['stage'] ?? null;
        $authCode = $data['authCode'] ?? null;

        $checkIp = function($ip, $cidr) {
			if (strpos($cidr, '/') !== false) {
				list($subnet, $mask) = explode('/', $cidr);
				$subnet = ip2long($subnet);
				$ip = ip2long($ip);
				$mask = -1 << (32 - $mask);

				return ($ip & $mask) == ($subnet & $mask);
			} else {
				return ip2long($ip) == ip2long($cidr);
			}
		};

        // Prihlasujeme sa, prvy krok
        if ($this->authData['logged'] === false) {
            // Nie sme vobec prihalseny, ideme prvy krat...
            $splnenePodmienky = ($email || $username) && $passwordHash && ( $stage === 0 || $stage === null);
            if (!$splnenePodmienky) {
                trigger_error("'username', 'password' must be set, 'stage' must be set to (int) 0 for login attempt !", E_USER_WARNING);
                return false;
            }

            $firewallCheckOk = true;
            // Ideme sa prihalsovat
            DB::module("ORM")
                ->q(function ($qb) use ($username,$email) {
                    if ($username) {
                        $qb
                        ->select('*', Config::get("db","prefix").'users')
                        ->where('username','=',$username);
                    }             
                    if ($email) {
                        $qb
                        ->select('*', Config::get("db","prefix").'users')
                        ->where('email','=',$email);
                    }       
                })
                ->execute(
                    function ($result, $db, $debug) use (&$loginData,$checkIp, &$firewallCheckOk, &$reply, &$data, &$fromRM, $rememberMe) {
                        if ($result === null) {
                            $this->resetAuthData();
                            $reply['logged'] = false;
                            $reply['error'] = 3;
                            $reply['error_txt'] = "User not found !";
                            return false;
                        }
                        if ($user = $result->first()) {
                            // Callback hell start
                            $this->authData['attributes'] = $user->toArray();
                            $this->authData['session_id'] = $_COOKIE[Config::session("cookie_name")] ?? DSM::use()->session_id();

                            // Popriesit na requeste ze ak je uzivatel prihalseny a cookies session id nie je zhodny so session_id tak autologin

                            if ($user->get('tfa_firewall') == 1) {
                                $firewallRules = $user->hasMany(Config::get("db","prefix").'users_firewall', 'user_id', 'id', function ($qb) {
                                    $qb
                                    ->where('active','=','1')
                                    ->orderBy('ordering','ASC');
                                })->all();
                                
                                $checkrules = function() use (&$firewallRules, $checkIp) {
                                    foreach ($firewallRules as $rule) {
                                        $platne = $checkIp($_SERVER['REMOTE_ADDR'],$rule->rule);
                                        if ($platne) {
                                            if ($rule->action == 0) return(false);
                                            if ($rule->action == 1) return(true);
                                        }
                                    }
                                    return(true);
                                };

                                $firewallCheckOk = $checkrules();
                            }

                            // Nepokracujeme ak sme nepresli cez firewall
                            if (!$firewallCheckOk) {
                                $this->resetAuthData();
                                $reply['logged'] = false;
                                $reply['error'] = 1;
                                $reply['error_txt'] = "Firewall rule blocked login.";
                                return false;
                            }

                            $passwordIsCorrect = false;
                            if (isSet($data['password'])) {
                                $passwordIsCorrect = $this->dotapp->verifyPassword($data['password'], $user->password);
                            } else if (isSet($data['passwordHash'])) {
                                if ($data['passwordHash'] == $user->password) $passwordIsCorrect = true;
                            } 
                            if ($passwordIsCorrect === false) {
                                $this->resetAuthData();
                                $reply['logged'] = false;
                                $reply['error'] = 2;
                                $reply['error_txt'] = "Incorrect password";
                                return false;
                            }

                            $this->authData['logged'] = 1;
                            $this->authData['user_id'] = $user->id;
                            $this->authData['username'] = $user->username;
                            $this->authData['rm'] = $rememberMe;

                            $reply['logged'] = true;

                            /* Vyplname AUTH data */
                            if ( ( $user->get('tfa_auth') || $user->get('tfa_sms') || $user->get('tfa_email') ) == 1 && $fromRM === false) {
                                $this->authData['logged_stage'] = 2;
                                if ($user->get('tfa_auth') == 1) {
                                    $this->authData['tfa'] = $user->get('tfa_auth_secret');
                                }
                                if ($user->get('tfa_sms') == 1) {
                                    $this->authData['tfa_sms'] = rand(100000, 999999);
                                } 
                                if ($user->get('tfa_auth') == 1) {
                                    $this->authData['tfa_email'] = rand(100000, 999999);
                                } 
                            } else {
                                $this->authData['logged_stage'] = 1;
                                $this->authData['last_login'] = time();
                                $this->authData['last_activity'] = time();
                                $user->last_logged_at = date("Y-m-d H:i:s");
                                $user->save();
                            }

                            $rights = $user->hasMany(Config::get("db","prefix").'users_rights', 'user_id');
                            $rightIds = $rights->Pluck('right_id')->All();

                            $rightDetails = [];
                            if (!empty($rightIds)) {
                                DB::module("RAW")
                                    ->q(function ($qb) use ($rightIds) {
                                        $qb->Select('*', Config::get("db","prefix").'users_rights_list')
                                        ->WhereIn('id', $rightIds)
                                        ->andWhere('active', '=', 1);
                                    })->execute(function ($result) use (&$rightDetails,&$reply) {
                                        $rightDetails = $result;
                                    }, function($error, $db, $debug) {
                                        $this->resetAuthData();
                                        $reply['logged'] = false;
                                        $reply['error'] = 4;
                                        $reply['error_txt'] = "Problem with fetching user rights.";
                                    });
                            }

                            $permissions = [];
                            if (!empty($rightDetails)) {
                                foreach ($rightDetails as $right) {
                                    $permissions[] = $right['module'].".".$right['rightname'];
                                }
                            }

                            $this->authData['permissions'] = $permissions;
                            $this->authData['token'] = bin2hex(random_bytes(32));
                        } else {
                            return false;
                        }
                    },
                    function ($error, $db, $debug) use ($reply) {
                        $reply['logged'] = false;
                        $reply['error'] = 99;
                        $reply['error_txt'] = "Error: {$error['error']} (code: {$error['errno']})";
                        return false;
                    }
                );

        }

        // Zmazeme vsetko co sa tyka remember me
        foreach ($_COOKIE as $name => $value) {
            if (strncmp($name, 'dotapp_rm', 9) === 0) {
                $matchingCookies[$name] = $value;
                setcookie($name, "", [
                    'expires' => time() - 3600,
                    'path' => Config::session("path")
                ]);
            }
        }

        if ($reply['error'] == 0 && $this->authData['logged_stage'] == 1 && $rememberMe === true) {
            $this->setRmToken();
            $this->lock();
        }
        return $reply;
    }

    /**
     * Sets a remember-me token for the user session.
     *
     * This function generates a secure remember-me token and stores it in the database
     * and as cookies. The token is used to automatically log in the user in future sessions.
     *
     * @throws \RuntimeException If the application name is not set in the configuration.
     */
    private function setRmToken() {
        $appname = Config::get("app","name");
            if ($appname === null) {
                throw new \RuntimeException('Set app name ! Example: Config::set("app","name","AppName125")');
            } else {
                $kluc = bin2hex(random_bytes(32));
                $klucEnc = $this->dotapp->encrypt($kluc,hash('sha256',Config::get("app","name")).hash('sha256',Config::get("app","name")."dotApp :)"),true);
                $rmtoken = $klucEnc.":".$this->dotapp->encrypt($this->getBrowserIdentifier(),$kluc, true);
                DB::module("RAW")
                ->q(function ($qb) use ($rmtoken,$appname) {
                    $zivotnost = time() + Config::session("rm_lifetime");
                    $zivotnost = date("Y-m-d H:i:s");
                    $qb->insert(Config::get("db","prefix").'users_rmtokens', ['user_id' => $this->authData['user_id'], 'token' => $rmtoken, 'expires_at' => $zivotnost]);
                })
                ->execute(function($result, $db) use ($appname) {
                    if (isSet($_COOKIE['dotapp_'.Config::get("app","name_hash")])) {
                        $this->deleteAutologinTokenFromDatabase();
                    }
                });
                $randomRmCookieName = bin2hex(random_bytes(16));

                setcookie('dotapp_rm', $this->dotapp->encrypt($randomRmCookieName, "RememberMe :)", true), [
                    'expires' => time() + 3600*24*365,
                    'path' => Config::session("path"),
					'secure' => Config::session("secure"),
					'httponly' => Config::session("httponly"),
					'samesite' => Config::session("samesite")
                ]);
                setcookie('dotapp_rm'.hash('sha256',$randomRmCookieName), $this->dotapp->encrypt(hash('sha256',$randomRmCookieName.$rmtoken), "RandomCookieName :)", true), [
                    'expires' => time() + 3600*24*365,
                    'path' => Config::session("path"),
                    'secure' => Config::session("secure"),
                    'httponly' => Config::session("httponly"),
                    'samesite' => Config::session("samesite")
                ]);
                setcookie('dotapp_'.Config::get("app","name_hash"), $rmtoken, [
                    'expires' => time() + Config::session("rm_lifetime"),
                    'path' => Config::session("path"),
                    'secure' => Config::session("secure"),
                    'httponly' => Config::session("httponly"),
                    'samesite' => Config::session("samesite")
                ]);
            }
    }

    /**
     * Deletes the autologin token from the database.
     *
     * This function removes the autologin token associated with the current session
     * from the database, ensuring that the token cannot be used for future automatic logins.
     */
    private function deleteAutologinTokenFromDatabase() {
        $appname = Config::get("app","name");
        $token = $_COOKIE['dotapp_'.Config::get("app","name_hash")];
        $this->dotApp->unprotect($token);
        if (strlen($token) > 10) {
            DB::module("RAW")
                ->q(function ($qb) use ($token) {
                    $qb->delete(Config::get("db","prefix").'users_rmtokens')->where('token','=',$token);
                })
                ->execute();
        }
    }

    /**
     * Attempts to automatically log in a user using a remember-me token.
     *
     * This function checks for the presence of a remember-me cookie and validates
     * the associated token. If the token is valid and matches the stored token in
     * the database, the user is logged in automatically.
     *
     * @return bool True if the auto-login was successful, false otherwise.
     */
    public function autoLogin() {
        if ($this->authData['logged_stage'] != 0) return false;
        $appname = Config::get("app","name");
        if (!isSet($_COOKIE['dotapp_rm'])) {
            return false;
        } else {
            $rmCookieName = $_COOKIE['dotapp_rm'];
            $rmCookieName = $this->dotapp->decrypt($rmCookieName, "RememberMe :)", true);
            if ($rmCookieName === false) return false;
            $rmCookieNameSha256 = hash('sha256',$rmCookieName);
            if (!isSet($_COOKIE['dotapp_rm'.$rmCookieNameSha256])) {
                return false;
            } else {
                $rmCookieValue = $_COOKIE['dotapp_rm'.$rmCookieNameSha256];
            }

        }

        if (isSet($_COOKIE['dotapp_'.Config::get("app","name_hash")])) {
            $token = $_COOKIE['dotapp_'.Config::get("app","name_hash")];
            $this->dotApp->unprotect($token);
            if ($this->validateRmToken($token) === true) {
                $correctRmForToken = $this->dotapp->decrypt($rmCookieValue, "RandomCookieName :)", true);
                if ($correctRmForToken === false) return false;
                if ($correctRmForToken !== hash('sha256',$rmCookieName.$token)) {
                    return false;
                }

                // Token validny, porovname s DB a prihlasime.
                
                DB::module("RAW")
                ->q(function ($qb) use ($token) {
                    $qb
                    ->select('user_id', Config::get("db","prefix").'users_rmtokens')
                    ->where('token','=',$token);
                })
                ->execute(
                    function ($result, $db, $debug) use (&$data,$appname) {
                        if ($result === null || $result === []) {
                            $data = [];
                            setcookie('dotapp_'.Config::get("app","name_hash"), "", [
                                'expires' => time() - 3600,
                                'path' => Config::session("path"),
                            ]);
                            // Nenasli sme token...
                        } else {
                            $db->q(function ($qb) use (&$data,$result) {
                                $qb
                                ->select(['username','password'], Config::get("db","prefix").'users')
                                ->where('id','=',$result['user_id']);
                            })->execute(function ($result, $db, $debug) {
                                $data = [];
                                $data['username'] = $result[0]['username'];
                                $data['passwordHash'] = $result[0]['password'];
                                $data['stage'] = 0;
                                $this->login($data,true,true);
                            });
                        }
                    });
            } else {
                $this->logout();
                return false;
            }            
        } else {
            return false;
        }
    }

    /**
     * Validates a remember-me token to ensure it matches the expected format and content.
     *
     * This function decrypts the provided remember-me token and verifies that it matches
     * the expected browser identifier for the current session.
     *
     * @param string $rmToken The remember-me token to validate.
     *
     * @return bool True if the token is valid and matches the current browser identifier, false otherwise.
     */
    private function validateRmToken($rmToken) {
        $this->dotapp->unprotect($rmToken);
        $rmTokenA = explode(":",$rmToken);
        $kluc = $this->dotapp->decrypt($rmTokenA[0],hash('sha256',Config::get("app","name")).hash('sha256',Config::get("app","name")."dotApp :)"), true);
        if ($kluc === false) return false;
        $browser = $this->dotapp->decrypt($rmTokenA[1],$kluc, true);
        if ($browser === false) return false;
        if ($browser === $this->getBrowserIdentifier()) return true;
        return false;
    }

    /**
     * Odhlási používateľa a resetuje autentifikačné údaje.
     */
    public function logout($clearSessionCookie=false) {
        $this->authData = [
            'logged' => false,
            'user_id' => null,
            'username' => null,
            'logged_stage' => 0,
            'roles' => [],
            'token' => null,
            'last_login' => null,
            'last_activity' => null,
            'session_id' => null,
            'attributes' => [],
            'permissions'  => []
        ];
        $appname = Config::get("app","name");
        $this->deleteAutologinTokenFromDatabase();
        setcookie('dotapp_'.Config::get("app","name_hash"), "", [
            'expires' => time() - 3600,
            'path' => Config::session("path"),
        ]);
        if ($clearSessionCookie === true) {
            setcookie(Config::session("cookie_name"), "", [
                'expires' => time() - 3600,
                'path' => Config::session("path"),
            ]);
			session_regenerate_id(true);
			setcookie(Config::session("cookie_name"), DSM::use()->session_id(), [
				'expires' => time() + Config::session("lifetime"),
				'path' => Config::session("path"),
				'secure' => Config::session("secure"),
				'httponly' => Config::session("httponly"),
				'samesite' => Config::session("samesite")
			]);
        }        
    }

    public function hasRole(string $role): bool {
        return in_array($role, $this->authData['roles']);
    }

    public function can($permission, $logic = null) {
        $logic = $logic ?? self::$Or;

        $logic = strtoupper($logic);
        if (!in_array($logic, [self::$And, self::$Or])) {
            $logic = self::$Or;
        }

        if (is_string($permission)) {
            return in_array($permission, $this->authData['permissions']);
        }

        if (is_array($permission)) {
            if ($logic === self::$Or) {
                // Jedno musi platit
                foreach ($permission as $perm) {
                    if (in_array($perm, $this->authData['permissions'])) {
                        return true;
                    }
                }
                return false;
            }

            if ($logic === self::$And) {
                // Vsetky musia platit
                foreach ($permission as $perm) {
                    if (!in_array($perm, $this->authData['permissions'])) {
                        return false;
                    }
                }
                return true;
            }
        }

        return false;
    }

    public function refreshToken(): string {
        $this->authData['token'] = bin2hex(random_bytes(16));
        return $this->authData['token'];
    }

    public function updateActivity(): void {
        $this->authData['last_activity'] = time();
    }

    public function &getAuthData($pointer = false) {
        if ($pointer === false) {
            $authdata = $this->authData;
            return $authdata;
        } else {
            return $this->authData;
        }
    }

    public function setAttribute(string $key, $value): void {
        $this->authData['attributes'][$key] = $value;
    }

    public function getAttribute(string $key) {
        return $this->authData['attributes'][$key] ?? null;
    }

    private function getBrowserIdentifier() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $browser = 'Unknown';
        $os = 'Unknown';
    
        // Detekcia prehliadača
        if (stripos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (stripos($userAgent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (stripos($userAgent, 'Safari') !== false && stripos($userAgent, 'Chrome') === false) {
            $browser = 'Safari';
        } elseif (stripos($userAgent, 'Edg/') !== false) {
            $browser = 'Edge';
        } elseif (stripos($userAgent, 'Opera') !== false || stripos($userAgent, 'OPR/') !== false) {
            $browser = 'Opera';
        }
    
        // Detekcia operačného systému
        if (stripos($userAgent, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (stripos($userAgent, 'Macintosh') !== false) {
            $os = 'macOS';
        } elseif (stripos($userAgent, 'Linux') !== false && stripos($userAgent, 'Android') === false) {
            $os = 'Linux';
        } elseif (stripos($userAgent, 'Android') !== false) {
            $os = 'Android';
        } elseif (stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false) {
            $os = 'iOS';
        }
    
        return "$browser:$os"; // Napr. 'Firefox:Windows'
    }
}
?>
