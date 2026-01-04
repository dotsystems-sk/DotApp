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
 * @version   1.8 FREE
 * @date      2014 - 2026
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

/*
	$limiter = new Limiter(
		[60 => 3, 3600 => 10], // 3 za minútu, 10 za hodinu ( defaultne je 10 poziadaviek za minutu )
		'bridge', // Identifikátor napriklad. IP adresa alebo uz co uzname za vhodne pri redise a pod
        getter, // Funkcia pre nacitanie z uloziska - defaultne je to session
		setter // Funkcia pre ukladanie do uloziska - defaultne je to session
	);
*/

class Limiter {
    private $limits;           // Pole limitov [interval => max_požiadaviek]
    private $storageKeyPrefix = '_dotlimiter'; //
    private $storageGetter;    // Callable na získanie dát
    private $storageSetter;    // Callable na uloženie dát
    private $identifier;       // Identifikátor (napr. IP, user ID)

    /**
     * Constructs a new Limiter instance with specified limits, identifier, and storage handlers.
     *
     * @param array $limits An associative array where keys are time intervals in seconds and values are the maximum number of allowed requests within that interval.
     * @param string|null $identifier An optional identifier for the request, defaulting to the client's IP address if not provided.
     * @param callable|null $getter An optional callable function to retrieve data from storage, defaulting to session storage if not provided.
     * @param callable|null $setter An optional callable function to store data, defaulting to session storage if not provided.
     */
    public function __construct(array $limits = [60 => 10], $identifier = null, $getter = null, $setter = null) {
        $this->limits = $limits;

        $this->identifier = $identifier !== null ? $identifier : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'default');

        /**
         * Sets the default storage mechanism using PHP sessions if no custom getter or setter is provided.
         * Initializes session if not already started and assigns default session-based getter and setter.
         * 
         * @param callable|null $getter An optional callable function to retrieve data from storage. Defaults to session storage if not provided.
         * @param callable|null $setter An optional callable function to store data. Defaults to session storage if not provided.
         */
        if ($getter === null || $setter === null) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $this->storageGetter = function ($key) {
                return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
            };
            $this->storageSetter = function ($key, $value) {
                $_SESSION[$key] = $value;
            };
        } else {
            $this->storageGetter = $getter;
            $this->storageSetter = $setter;
        }
    }

    public function identifier() {
        return $this->identifier;
    }

    /**
     * Skontroluje, či je požiadavka povolená pre danú routu
     * @param string|array $route Routa alebo pole rout
     * @return bool Vracia true, ak je požiadavka povolená, false ak nie
     * @throws Exception Ak je konfigurácia neplatná
     */
    public function isAllowed($route) {
        if (empty($this->limits)) {
            throw new \Exception('No limits defined !');
        }

        $key = $this->generateKey($route);
        $allowed = true;

        foreach ($this->limits as $interval => $limit) {
            $storageKey = $key . ':' . $interval;
            $this->initializeStorage($storageKey, $interval);

            $data = call_user_func($this->storageGetter, $storageKey, $this->identifier);
            $currentTime = time();

            // Reset, ak interval vypršal
            if ($currentTime >= $data['reset_time']) {
                $this->reset($storageKey, $interval);
                $data = call_user_func($this->storageGetter, $storageKey, $this->identifier);
            }

            // Ak je limit prekročený, zablokuj
            if ($data['count'] >= $limit) {
                $allowed = false;
                break;
            }
        }

        // Ak je povolené, inkrementuj všetky počítadlá
        if ($allowed) {
            foreach ($this->limits as $interval => $limit) {
                $storageKey = $key . ':' . $interval;
                $data = call_user_func($this->storageGetter, $storageKey, $this->identifier);
                $data['count']++;
                call_user_func($this->storageSetter, $storageKey, $data,$this->identifier);
            }
        }

        return $allowed;
    }

    /**
     * Vygeneruje unikátny kľúč pre routu a identifikátor
     * @param string|array $route Routa alebo pole rout
     * @return string Vygenerovaný kľúč
     */
    private function generateKey($route) {
        $routeKey = is_array($route) ? json_encode($route) : (string) $route;
        return $this->storageKeyPrefix . sha1($this->identifier . ':' . $routeKey);
    }

    /**
     * Inicializuje úložisko pre daný kľúč a interval
     * @param string $key Kľúč v úložisku
     * @param int $interval Interval v sekundách
     */
    private function initializeStorage($key, $interval) {
        $data = call_user_func($this->storageGetter, $key, $this->identifier);
        if ($data === null) {
            $this->reset($key, $interval);
        }
    }

    /**
     * Resetuje limiter pre daný kľúč a interval
     * @param string $key Kľúč v úložisku
     * @param int $interval Interval v sekundách
     */
    private function reset($key, $interval) {
        $data = [
            'count' => 0,
            'reset_time' => time() + $interval
        ];
        call_user_func($this->storageSetter, $key, $data, $this->identifier);
    }

    /**
     * Získa zostávajúci počet požiadaviek pre routu a interval
     * @param string|array $route Routa alebo pole rout
     * @param int $interval Interval v sekundách
     * @return int Zostávajúci počet
     */
    public function getRemaining($route, $interval) {
        $key = $this->generateKey($route) . ':' . $interval;
        $this->initializeStorage($key, $interval);

        $data = call_user_func($this->storageGetter, $key, $this->identifier);
        return max(0, $this->limits[$interval] - $data['count']);
    }

    /**
     * Získa čas do resetu v sekundách pre routu a interval
     * @param string|array $route Routa alebo pole rout
     * @param int $interval Interval v sekundách
     * @return int Zostávajúci čas do resetu
     */
    public function getResetTime($route, $interval) {
        $key = $this->generateKey($route) . ':' . $interval;
        $this->initializeStorage($key, $interval);

        $data = call_user_func($this->storageGetter, $key, $this->identifier);
        return max(0, $data['reset_time'] - time());
    }

    /**
     * Získa informácie o limite pre hlavičky
     * @param string|array $route Routa alebo pole rout
     * @return array Pole s informáciami o limitoch
     */
    public function getLimitHeaders($route) {
        $headers = [];
        foreach ($this->limits as $interval => $limit) {
            $remaining = $this->getRemaining($route, $interval);
            $reset = $this->getResetTime($route, $interval);
            $headers[$interval] = [
                'X-Rate-Limit-Limit' => $limit,
                'X-Rate-Limit-Remaining' => $remaining,
                'X-Rate-Limit-Reset' => time() + $reset
            ];
        }
        return $headers;
    }
}

?>
