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
    private $storageKeyPrefix = '_dotlimiter'; // Prefix podobný Laravelu
    private $storageGetter;    // Callable na získanie dát
    private $storageSetter;    // Callable na uloženie dát
    private $identifier;       // Identifikátor (napr. IP, user ID)

    /**
     * Konštruktor - nastaví limity, identifikátor a úložisko
     * @param array $limits Asociatívne pole [interval_v_sekundách => max_požiadaviek]
     * @param string|null $identifier Identifikátor požiadavky (predvolene IP)
     * @param callable|null $getter Callback na získanie dát (predvolene $_SESSION)
     * @param callable|null $setter Callback na uloženie dát (predvolene $_SESSION)
     */
    public function __construct(array $limits = [60 => 10], $identifier = null, $getter = null, $setter = null) {
        $this->limits = $limits;

        // Identifikátor (podobne ako Laravel používa IP alebo user ID)
        $this->identifier = $identifier !== null ? $identifier : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'default');

        // Predvolené úložisko používa $_SESSION
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

    /**
     * Skontroluje, či je požiadavka povolená pre danú routu
     * @param string|array $route Routa alebo pole rout
     * @return bool Vracia true, ak je požiadavka povolená, false ak nie
     * @throws Exception Ak je konfigurácia neplatná
     */
    public function isAllowed($route) {
        if (empty($this->limits)) {
            throw new Exception('No limits defined !');
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