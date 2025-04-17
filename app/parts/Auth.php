<?php
namespace Dotsystems\App\Parts;
class Auth {
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
        $this->authData = $this->dsm->get("request.auth") ?? [
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
            'permissions'  => []
        ];
    }

    /**
     * Deštruktor ukladá autentifikačné dáta do relácie.
     */
    public function __destruct() {
        $this->dsm->set("request.auth", $this->authData);
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

    /**
     * Overí, či je používateľ prihlásený.
     * @return bool
     */
    public function logged(): bool {
        return $this->authData['logged'] === true && $this->authData['logged_stage'] === 1;
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

    public function isLocked() {
        // Je zamknuty?
        return $this->lock;
    }

    public function isLogged() {
        // Je zamknuty?
        return ($this->authData['logged_stage'] === 1 && $this->authData['logged'] === true);
    }

    public function loggedStage() {
        return $this->authData['logged_stage'];
    }

    public function userId() {
        return $this->authData['user_id'];
    }

    public function username(): ?string {
        return $this->authData['username'];
    }

    public function roles(): array {
        return $this->authData['roles'];
    }

    public function token(): ?string {
        return $this->authData['token'];
    }

    /**
     * Prihlási používateľa s danými údajmi.
     * Ukážka ako použiť prihlásenie, preto je to private lebo je to len ukážka
     */
    private function loginExample(array $credentials, bool $fullAuth = true): bool {
        // Simulácia overenia (v reálnej app by si overil proti DB)
        $username = $credentials['username'] ?? null;
        $password = $credentials['password'] ?? null;

        // Tu by bolo overenie v databáze, napr. cez $this->dotapp->db
        // Kazda trieda odvodena od vstavanych tried obsahuje $this->dotapp
        // Takze takto nastavime auth parametre a zavolame lock().
        if ($username && $password) { // Nahraď reálnou logikou
            $this->dotApp->request->auth->logged = true;
            $this->dotApp->request->auth->user_id = 123; // Príklad ID
            $this->dotApp->request->auth->username = $username;
            $this->dotApp->request->auth->logged_stage = $fullAuth ? 2 : 1;
            $this->dotApp->request->auth->roles = ['user']; // Príklad rolí
            $this->dotApp->request->auth->permissions = ['module.settings.edit','module.users.create']; // Príklad povolenia
            $this->dotApp->request->auth->token = bin2hex(random_bytes(16)); // Generovanie tokenu
            $this->dotApp->request->auth->last_login = time();
            $this->dotApp->request->auth->last_activity = time();
            $this->dotApp->request->auth->session_id = session_id();
            $this->dotApp->request->auth->attributes = [];
            // Zamkneme upravu niektorych vlastnosti, ak nechceme nic zamykat hodine tam vlastnost ktora neexistuje ale pole prazdne byt nesmie
            $this->dotApp->request->auth->lock();
            return true;
        }

        return false;
    }

    public function login($data) {
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
        $username = $data['username'] ?? null;
        $passwordHash = $data['password'] ?? null;
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
            $splnenePodmienky = $username && $passwordHash && $stage === 0;
            if (!$splnenePodmienky) {
                trigger_error("'username', 'password' must be set, 'stage' must be set to (int) 0 for login attempt !", E_USER_WARNING);
                return false;
            }

            $firewallCheckOk = true;
            // Ideme sa prihalsovat
            $this->dotApp
                ->DB
                ->driver('mysqli')
                ->return('ORM')
                ->selectDb("main")
                ->q(function ($qb) use ($username,$passwordHash) {
                    $qb
                    ->select('*', 'erp_users')
                    ->where('username','=',$username)
                    ->andWhere('password', '=', $passwordHash);
                })
                ->execute(
                    function ($result, $db, $debug) use (&$loginData,$checkIp, &$firewallCheckOk, &$reply) {
                        if ($user = $result->first()) {
                            $this->authData['attributes'] = $user->toArray();
                            $this->authData['session_id'] = session_id();

                            if ($user->get('2fa_firewall') == 1) {
                                $firewallRules = $user->hasMany('erp_users_firewall', 'user_id', 'id', function ($qb) {
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
                                $reply['logged'] = false;
                                $reply['error'] = 1;
                                $reply['error_txt'] = "Firewall rule blocked login.";
                                return false;
                            }

                            $this->authData['logged'] = 1;
                            $this->authData['user_id'] = $user->id;
                            $this->authData['username'] = $user->username;

                            /* Vyplname AUTH data */
                            if ($user->get('2fa_auth') == 1) {
                                $this->authData['logged_stage'] = 2;
                            } else {
                                $this->authData['logged_stage'] = 1;
                                $this->authData['last_login'] = time();
                                $this->authData['last_activity'] = time();
                                $user->last_logged_at = date("Y-m-d H:i:s");
                                $user->save();
                            }

                            $rights = $user->hasMany('erp_users_rights', 'user_id');
                            $rightIds = $rights->Pluck('right_id')->All();

                            $rightDetails = [];
                            $this->dotApp->DB->return('RAW')
                                ->q(function ($qb) use ($rightIds) {
                                    $qb->Select('*', 'erp_users_rights_list')
                                    ->WhereIn('id', $rightIds)
                                    ->andWhere('active', '=', 1);
                                })->execute(function ($result) use (&$rightDetails) {
                                    $rightDetails = $result;
                                });

                            $rightDetails = $rightDetails;
                            $permissions = [];
                            if (!empty($rightDetails)) {
                                foreach ($rightDetails as $right) {
                                    $permissions[] = $right['module'].".".$right['rightname'];
                                }
                            }

                            $this->authData['permissions'] = $permissions;
                            $this->authData['token'] = bin2hex(random_bytes(32));
                            //$rightDetails->Pluck('right_id')->All();
                            //$right = $rights->all();
                            /*
                                ALTER TABLE erp_users_firewall
                                ADD CONSTRAINT users_vs_firewall
                                FOREIGN KEY (user_id)
                                REFERENCES erp_users(id)
                                ON DELETE CASCADE;
                            */
                            /*$this->authData = [
                                MAM 'logged' => false,
                                MAM 'user_id' => null,
                                MAM 'username' => null,
                                MAM 'logged_stage' => 0, // 0 = neprihlaseny, 1 - prihlaseny, 2 = cakame na 2FA potvrdenie
                                'roles' => [],
                                MAM 'token' => null,
                                MAM 'last_login' => null,
                                MAM 'last_activity' => null,
                                MAM 'session_id' => null,
                                MAM 'attributes' => [],
                                MAM 'permissions'  => []
                            ];*/
                            
                            
                            $rights = $user->hasMany('erp_users_rights', 'user_id');
                            $right = $rights->all();
                            
                            /*$right[0]->kolovratok = 99;
                            $rights->setItem(0,$right[0]);
                            $rights->saveAll(function ($result, $db, $debug){
                                $result = $result;
                            },
                            function ($result, $db, $debug){
                                $result = $result;
                            });

                            $firewall->kolovratzok = 9;
                            $firewall->save(function ($result, $db, $debug){
                                $result = $result;
                            },
                            function ($result, $db, $debug){
                                $result = $result;
                            });
                            //$rights = $user->hasMany('erp_users_rights', 'user_id')->all();
                            $firewall = $firewall;*/
                            
                            $reply['logged'] = true;
                            $reply['error'] = 1;
                            $reply['error_txt'] = "Firewall rule blocked login.";
                            return $reply;
                        } else {
                            return false;
                        }
                    },
                    function ($error, $db, $debug) {
                        echo "Error: {$error['error']} (code: {$error['errno']})\n";
                    }
                );

        }
    
        $this->lock();
        return true;
        $this->dotApp->db;
    }

    /**
     * Odhlási používateľa a resetuje autentifikačné údaje.
     */
    public function logout(): void {
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
        // Regeneruj reláciu pre väčšiu bezpečnosť
        session_regenerate_id(true);
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

    public function getAuthData(): array {
        return $this->authData;
    }

    public function setAttribute(string $key, $value): void {
        $this->authData['attributes'][$key] = $value;
    }

    public function getAttribute(string $key) {
        return $this->authData['attributes'][$key] ?? null;
    }
}
?>