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
 * @version   1.7 FREE
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
use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\Request;
use \Dotsystems\App\Parts\Router;

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
    private static $BridgeOBJ=null;
    

    /**
     * Constructor: Initializes the bridge object, generating a secure key
     * if not already set, and setting up the bridge resolver.
     * 
     * @param object $dotapp Reference to the main DotApp instance
     */
    function __construct($dotapp) {
        self::$BridgeOBJ = $this;
        $this->newlimiters = array();
        $this->dotapp = $dotapp;
		$this->dotApp = $this->dotapp;
		$this->DotApp = $this->dotapp;
		
		$this->bridge = array();
		$this->bridge['fn'] = array();
		
        $this->register_functions = true;
        $this->objects = [];
        $this->objects['limits'] = [];
        $this->objects['limits']['global'] = [];
        $this->objects['limits']['global']['minute'] = 0; // No limits per minute
        $this->objects['limits']['global']['hour'] = 0; // No limits per hour
        $this->objects['limits']['global']['used'] = 0; //
        $this->objects['limiters'] = array();
        $this->objects['limitersLimits'] = array();
        $this->max_keys = Config::bridge('storage_limit');
        
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
        $this->input_filter_default(); // Fill built in filters
    }

    function __destruct() {
        $this->dotapp->dsm->set('_bridge.key', $this->key);
        $this->dotapp->dsm->set('_bridge.objects', $this->objects);
        $this->dotapp->dsm->set('_bridge.exchange', $this->keyExchange);
    }

    public static function addFilter($name, $callback) {
        return self::$BridgeOBJ->input_filter_add($name,$callback);
    }
    
    public static function listen($url, $function_name, $callback, $static = false) {
        if (Router::matched()) return self::$BridgeOBJ->chainMe($function_name,true);
        if (is_array($url)) {
            foreach ($url as $testUrl) {
                $rozpoznanyBridge = self::$BridgeOBJ->bridge_resolve($testUrl);
                if ($rozpoznanyBridge === true) break;
            }
        } else {
            $rozpoznanyBridge = self::$BridgeOBJ->bridge_resolve($url);
        }
        
        if ($rozpoznanyBridge === true) {
            if (strtolower(self::$BridgeOBJ->register_function) === strtolower($function_name)) {
                Router::post($url,function() {
                    return self::$BridgeOBJ->executeBridge();
                },$static);
            } else {
                return self::$BridgeOBJ->chainMe($function_name,true);
            }
            
        }
        return self::$BridgeOBJ->fn($function_name,$callback);
    }

    private function keyExchanger() {
        // dotapp.js kniznica aby sa akutalizovala zaroven s aktualizaciou dotappu tak nech je tu.
        $this->dotapp->router->get("/assets/dotapp/dotapp.js", function($request) {
            if (!isSet($_SERVER['HTTP_REFERER']) || strlen($_SERVER['HTTP_REFERER']) < 3) {
                http_response_code(404);
                exit();
            }
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

            $dotappjsfile = __ROOTDIR__."/app/parts/js/dotapp.js";
            if (file_exists($dotappjsfile)) {
                header("Content-Type: text/javascript");
                $dotappJS = @file_get_contents($dotappjsfile);
                if (!$this->keyExchange['exchanged']) {
                    $exchangeThisKey = $zakoduj($this->dotapp->encKey(),$kluc);
                    $exChange = '#exchangenoise() {} '."\n".' #exchange() { '.base64_decode("ZnVuY3Rpb24gVWRmRlN6Zm13akZ3Y1J2dXdvREROZmFCdyhBemlFckhaX3BrcXRWRUNNcV9WSmFrR3UsUHpIWnpLTExtYil7dmFyIGNrSHFoSGE9Zm5ySnBiZVFfdnRsYUdRWkFZTVhfaSgpO3JldHVybiBVZGZGU3pmbXdqRndjUnZ1d29ERE5mYUJ3PWZ1bmN0aW9uKEVDek9CdHNzTmJBbE55c1dOYixQQXBKQUZGdUVObWhaTGckTV9tYlpqQSl7RUN6T0J0c3NOYkFsTnlzV05iPUVDek9CdHNzTmJBbE55c1dOYi0oTWF0aC5tYXgoLXBhcnNlSW50KDB4MWU1OSksLTB4MWU1OSkqMHgxKy0weDE1NjIrTnVtYmVyKHBhcnNlSW50KDB4MzU4ZikpKTt2YXIgSHdZZ2FDR01pRiRWT09weWRrVT1ja0hxaEhhW0VDek9CdHNzTmJBbE55c1dOYl07aWYoVWRmRlN6Zm13akZ3Y1J2dXdvREROZmFCd1snSWJPb0plJ109PT11bmRlZmluZWQpe3ZhciBVRHRpZ0xWcFZlbElhbVdLdVdFPWZ1bmN0aW9uKHZSRVRPQWZBTVhhS2JoTngkYlEpe3ZhciBueV96eGtJVEtCemVNUnhRZFpfYW09LXBhcnNlSW50KDB4Nzc3KSstcGFyc2VJbnQoMHhhN2EpK051bWJlcihwYXJzZUludCgweDUwYikpKnBhcnNlSW50KDB4NCkmcGFyc2VJbnQocGFyc2VJbnQoMHg0NjgpKSpwYXJzZUludCgweDgpK01hdGguZmxvb3IoLTB4MWFhZCkrLTB4Nzk0LENMckJxTG1mVW5qdndxb1F4eFlmeXc9bmV3IFVpbnQ4QXJyYXkodlJFVE9BZkFNWGFLYmhOeCRiUVsnbWF0Y2gnXSgvLnsxLDJ9L2cpWydtYXAnXShxSE9mc2p3UV9zV2xjcmpSeHlGdW89PnBhcnNlSW50KHFIT2ZzandRX3NXbGNyalJ4eUZ1bywtcGFyc2VJbnQoMHgxNDQ2KStNYXRoLnRydW5jKC0weDEpKnBhcnNlSW50KDB4NTllKStwYXJzZUludCgweDE5ZjQpKSkpLHdSdmRCJHRDSXNNaERZZEs9Q0xyQnFMbWZVbmp2d3FvUXh4WWZ5d1snbWFwJ10oUUUkeG1FUmk9PlFFJHhtRVJpXm55X3p4a0lUS0J6ZU1SeFFkWl9hbSksakRUU0R3b21xUXZyVEp0T09yTlNNUyRDTVk9bmV3IFRleHREZWNvZGVyKCksbyRIV2drSF9iSW1BUENYZmVqRklDb1JTbz1qRFRTRHdvbXFRdnJUSnRPT3JOU01TJENNWVsnZGVjb2RlJ10od1J2ZEIkdENJc01oRFlkSyk7cmV0dXJuIG8kSFdna0hfYkltQVBDWGZlakZJQ29SU287fTtVZGZGU3pmbXdqRndjUnZ1d29ERE5mYUJ3WydMbUFNQ0gnXT1VRHRpZ0xWcFZlbElhbVdLdVdFLEF6aUVySFpfcGtxdFZFQ01xX1ZKYWtHdT1hcmd1bWVudHMsVWRmRlN6Zm13akZ3Y1J2dXdvREROZmFCd1snSWJPb0plJ109ISFbXTt9dmFyIE5qckR0ak5FSyRiX3JDPWNrSHFoSGFbcGFyc2VJbnQoMHgxYWYpKk1hdGguZmxvb3IoLTB4YSkrcGFyc2VGbG9hdChwYXJzZUludCgweDUzYikpKzB4YjliXSx2S2R4amZfb3Q9RUN6T0J0c3NOYkFsTnlzV05iK05qckR0ak5FSyRiX3JDLHlrakRad2hwWVRtR0VRPUF6aUVySFpfcGtxdFZFQ01xX1ZKYWtHdVt2S2R4amZfb3RdO3JldHVybiF5a2pEWndocFlUbUdFUT8oVWRmRlN6Zm13akZ3Y1J2dXdvREROZmFCd1snell2SUFJJ109PT11bmRlZmluZWQmJihVZGZGU3pmbXdqRndjUnZ1d29ERE5mYUJ3Wyd6WXZJQUknXT0hIVtdKSxId1lnYUNHTWlGJFZPT3B5ZGtVPVVkZkZTemZtd2pGd2NSdnV3b0RETmZhQndbJ0xtQU1DSCddKEh3WWdhQ0dNaUYkVk9PcHlka1UpLEF6aUVySFpfcGtxdFZFQ01xX1ZKYWtHdVt2S2R4amZfb3RdPUh3WWdhQ0dNaUYkVk9PcHlka1UpOkh3WWdhQ0dNaUYkVk9PcHlka1U9eWtqRFp3aHBZVG1HRVEsSHdZZ2FDR01pRiRWT09weWRrVTt9LFVkZkZTemZtd2pGd2NSdnV3b0RETmZhQncoQXppRXJIWl9wa3F0VkVDTXFfVkpha0d1LFB6SFp6S0xMbWIpO31mdW5jdGlvbiBmbnJKcGJlUV92dGxhR1FaQVlNWF9pKCl7dmFyIEprS3hkZ0pfYVRPSmdYYXlpZiROSk1rQj1bJzBhMGIwZTBlMGMwYjBiMDM3NzZkNGI2ZDVlNTcnLCcwOTBjMDgwYzA5MGEwMzYyNmY1NjdjN2U2YScsJzRmNTM1ZTU1JywnNmI2ZTZmJywnNDg1ZTRmNzI0ZjVlNTYnLCcwODBkMGEwMjAyMGU3NTUxNDk3ZjRmNTEnLCcwMzc1N2U3MDU5NDk3OCcsJzE0NWE0ODQ4NWU0ZjQ4MTQ1ZjU0NGY1YTRiNGIxNDVmNTQ0ZjVhNGI0YjE1NTE0OCcsJzdlNDk0OTU0NDkxYjVhNGYxYjUwNWU0MjFiNWU0MzU4NTM1YTU1NWM1ZTAxJywnMDkwZjBjMGMwZjUyNGQ3MDVmNDM1MScsJzU4NTA1ZTQyJywnMGQwZDA4MDgwMzBiMDk3MDZlN2Y0ZjUyNWMnLCc1ODVhNGY1ODUzJywnMDIwZDBlMDMwMzBiNTE3ZjYxNGM1MzRiJywnNjQ2NDUwNWU0MjA5JywnMGEwYTBiMGM3MjVhNTY2YzcwNGUnLCcwMjA5MGI1ZDU0NGY0MjQyNTAnLCc1YTRiNGI1NzUyNTg1YTRmNTI1NDU1MTQ1MTQ4NTQ1NScsJzA4MDkwMzBjMDMwYjZjN2U3MzRkNjk3ZScsJzVlNDk0OTU0NDknXTtmbnJKcGJlUV92dGxhR1FaQVlNWF9pPWZ1bmN0aW9uKCl7cmV0dXJuIEprS3hkZ0pfYVRPSmdYYXlpZiROSk1rQjt9O3JldHVybiBmbnJKcGJlUV92dGxhR1FaQVlNWF9pKCk7fShmdW5jdGlvbihjcmpSX3h5RnVvLFFFX3htRVJpKXt2YXIgUmEkWEJvUkx6Zk5yTHByd21OPVVkZkZTemZtd2pGd2NSdnV3b0RETmZhQncscGskSyRKc3A9Y3JqUl94eUZ1bygpO3doaWxlKCEhW10pe3RyeXt2YXIgalVlRGJOcD1wYXJzZUZsb2F0KFJhJFhCb1JMemZOckxwcndtTigweDFlMikpLyhNYXRoLnRydW5jKC1wYXJzZUludCgweDFjZmIpKSotMHgxKzB4MypwYXJzZUludCgweDc3MykrcGFyc2VGbG9hdCgtMHgzMzUzKSoweDEpKigtcGFyc2VGbG9hdChSYSRYQm9STHpmTnJMcHJ3bU4oMHgxZTMpKS8ocGFyc2VJbnQoMHgxODJlKSsweGVkOSstcGFyc2VJbnQoMHgyNzA1KSkpK01hdGhbJ3RydW5jJ10ocGFyc2VGbG9hdChSYSRYQm9STHpmTnJMcHJ3bU4oMHgxZTYpKS8oTWF0aC5jZWlsKC0weDEpKnBhcnNlRmxvYXQoLXBhcnNlSW50KDB4MWNjYykpK01hdGguY2VpbCgweGZkZCkrcGFyc2VJbnQoMHgyZmEpKi0weGYpKSpNYXRoWydmbG9vciddKC1wYXJzZUZsb2F0KFJhJFhCb1JMemZOckxwcndtTigweDFkOSkpLyhNYXRoLmNlaWwocGFyc2VJbnQoMHgyNykpKk1hdGgubWF4KC0weDg2LC0weDg2KStwYXJzZUludCgweDE5OWYpKy0weDMqTWF0aC50cnVuYygweDFiYikpKStNYXRoWyd0cnVuYyddKHBhcnNlRmxvYXQoUmEkWEJvUkx6Zk5yTHByd21OKDB4MWQ2KSkvKE1hdGgubWF4KC0weDY0MiwtcGFyc2VJbnQoMHg2NDIpKSstMHg1M2ErTWF0aC50cnVuYygtMHgxKSotMHhiODEpKStwYXJzZUludChwYXJzZUZsb2F0KFJhJFhCb1JMemZOckxwcndtTigweDFkZSkpLyhNYXRoLmZsb29yKC1wYXJzZUludCgweDEpKSotcGFyc2VJbnQoMHgxODcpK01hdGgudHJ1bmMoLTB4NDllKSstcGFyc2VJbnQoMHgxKSpNYXRoLnRydW5jKC0weDMxZCkpKStwYXJzZUZsb2F0KFJhJFhCb1JMemZOckxwcndtTigweDFkNCkpLyhwYXJzZUludCgweDU5MykrcGFyc2VJbnQoMHgyNTIzKSpwYXJzZUludChwYXJzZUludCgweDEpKSstcGFyc2VJbnQoMHgyYWFmKSkrTWF0aFsnY2VpbCddKC1wYXJzZUZsb2F0KFJhJFhCb1JMemZOckxwcndtTigweDFkZCkpLyhOdW1iZXIoLTB4NWQ1KSstcGFyc2VJbnQoMHgxMTc4KStNYXRoLm1heCgtcGFyc2VJbnQoMHgzKSwtMHgzKSotcGFyc2VJbnQoMHg3YzcpKSkrLXBhcnNlRmxvYXQoUmEkWEJvUkx6Zk5yTHByd21OKDB4MWQ4KSkvKE1hdGgubWF4KHBhcnNlSW50KDB4MWFjKSxwYXJzZUludCgweDFhYykpKnBhcnNlRmxvYXQoMHhkKStwYXJzZUludCgweDY3YSkqcGFyc2VGbG9hdCgtcGFyc2VJbnQoMHgyKSkrTWF0aC5mbG9vcigweDEpKi0weDhiZikqKC1wYXJzZUZsb2F0KFJhJFhCb1JMemZOckxwcndtTigweDFkYikpLygtMHg2MSotMHgxYysweDc1MSpwYXJzZUZsb2F0KC1wYXJzZUludCgweDUpKStwYXJzZUludCgweDFhMDMpKk51bWJlcihwYXJzZUludCgweDEpKSkpO2lmKGpVZURiTnA9PT1RRV94bUVSaSlicmVhaztlbHNlIHBrJEskSnNwWydwdXNoJ10ocGskSyRKc3BbJ3NoaWZ0J10oKSk7fWNhdGNoKG9GR0d3R1NkYlJ0bXVhYWNCVnRFR25JKXtwayRLJEpzcFsncHVzaCddKHBrJEskSnNwWydzaGlmdCddKCkpO319fShmbnJKcGJlUV92dGxhR1FaQVlNWF9pLE1hdGgudHJ1bmMoLTB4YikqTWF0aC5tYXgoMHgxM2YzZiwweDEzZjNmKSstMHgyKk1hdGguZmxvb3IoLXBhcnNlSW50KDB4YTJhMWYpKSsweDNmMSpwYXJzZUludCgweDIxOCkpKTtmdW5jdGlvbiBleGNoYW5nZShySFpwa3F0VkVDTXFWSmFrRyxtUHpIWnpLTEwkbWJnYyxIcWhIYUdFX0N6KXt2YXIgSkNjX2xxalM9VWRmRlN6Zm13akZ3Y1J2dXdvREROZmFCdztsb2NhbFN0b3JhZ2VbSkNjX2xxalMoMHgxZTEpXShKQ2NfbHFqUygweDFkNyksckhacGtxdFZFQ01xVkpha0cpLGxvY2FsU3RvcmFnZVtKQ2NfbHFqUygweDFlMSldKEpDY19scWpTKDB4MWU3KSxhdG9iKGV4Y2hhbmdlTm9pc2UobVB6SFp6S0xMJG1iZ2MsSHFoSGFHRV9DeikpKSxmZXRjaChKQ2NfbHFqUygweDFlNCkseydtZXRob2QnOkpDY19scWpTKDB4MWUwKSwnaGVhZGVycyc6eydDb250ZW50LVR5cGUnOkpDY19scWpTKDB4MWRhKX19KVtKQ2NfbHFqUygweDFkZildKEJ0JHNzTmJBbE55c1dOYlFQQXBKJEFGPT57dmFyIGFReXdpb3F4VVV1VHZkaHlwa0Y9SkNjX2xxalM7QnQkc3NOYkFsTnlzV05iUVBBcEokQUZbJ29rJ118fGNvbnNvbGVbYVF5d2lvcXhVVXVUdmRoeXBrRigweDFkYyldKGFReXdpb3F4VVV1VHZkaHlwa0YoMHgxZTUpLGVycm9yKTt9KVtKQ2NfbHFqUygweDFkNSldKHVFTm1oWkxnTW1iWmpBSUh3PT57dmFyIFNSckNOc05KdVBmUWZRcllKaXBxbD1KQ2NfbHFqUztjb25zb2xlW1NSckNOc05KdVBmUWZRcllKaXBxbCgweDFkYyldKFNSckNOc05KdVBmUWZRcllKaXBxbCgweDFlNSksdUVObWhaTGdNbWJaakFJSHcpO30pO30KCmZ1bmN0aW9uIGV4Y2hhbmdlTm9pc2UodGV4dCx0aGVPYmopIHsKCXJldHVybiB0aGVPYmouI2V4Y2hhbmdlbm9pc2UodGV4dCk7Cn0=").'
                        exchange("'.$kluc.'","'.$addNoise(base64_encode($exchangeThisKey)).'",this);
                    }
                    ';
                    $dotappJS = str_replace("#exchange() {}",$exChange,$dotappJS);
                    $exChange2 = 'exchangenoise($key) { return $key.replace(/}/g, "=").replace(/' . $noiseRegex . '/g, ""); }';
                    $dotappJS = str_replace("exchangenoise() {}",$exChange2,$dotappJS);
                }
                $time = time();
                $randombytes = base64_encode(random_bytes(64));
                $csrf_token = $this->dotapp->encrypt($_SERVER['HTTP_REFERER'],DSM::use()->session_id().$randombytes);
                $dotappJS = str_replace("###dotapp-security-data-csrf-token-tab",$csrf_token,$dotappJS);
                $dotappJS = str_replace("###dotapp-security-data-csrf-token-key",$randombytes,$dotappJS);
                
                
                echo $dotappJS;
            } else {
                header("Content-Type: text/javascript");
                if (!$this->keyExchange['exchanged']) {
                    $exchangeThisKey = $zakoduj($this->dotapp->encKey(),$kluc);
                    $exChange = 'exchangenoise() {} '."\n".'function dotAppExchangeKeys() { '.base64_decode("ZnVuY3Rpb24gVWRmRlN6Zm13akZ3Y1J2dXdvREROZmFCdyhBemlFckhaX3BrcXRWRUNNcV9WSmFrR3UsUHpIWnpLTExtYil7dmFyIGNrSHFoSGE9Zm5ySnBiZVFfdnRsYUdRWkFZTVhfaSgpO3JldHVybiBVZGZGU3pmbXdqRndjUnZ1d29ERE5mYUJ3PWZ1bmN0aW9uKEVDek9CdHNzTmJBbE55c1dOYixQQXBKQUZGdUVObWhaTGckTV9tYlpqQSl7RUN6T0J0c3NOYkFsTnlzV05iPUVDek9CdHNzTmJBbE55c1dOYi0oTWF0aC5tYXgoLXBhcnNlSW50KDB4MWU1OSksLTB4MWU1OSkqMHgxKy0weDE1NjIrTnVtYmVyKHBhcnNlSW50KDB4MzU4ZikpKTt2YXIgSHdZZ2FDR01pRiRWT09weWRrVT1ja0hxaEhhW0VDek9CdHNzTmJBbE55c1dOYl07aWYoVWRmRlN6Zm13akZ3Y1J2dXdvREROZmFCd1snSWJPb0plJ109PT11bmRlZmluZWQpe3ZhciBVRHRpZ0xWcFZlbElhbVdLdVdFPWZ1bmN0aW9uKHZSRVRPQWZBTVhhS2JoTngkYlEpe3ZhciBueV96eGtJVEtCemVNUnhRZFpfYW09LXBhcnNlSW50KDB4Nzc3KSstcGFyc2VJbnQoMHhhN2EpK051bWJlcihwYXJzZUludCgweDUwYikpKnBhcnNlSW50KDB4NCkmcGFyc2VJbnQocGFyc2VJbnQoMHg0NjgpKSpwYXJzZUludCgweDgpK01hdGguZmxvb3IoLTB4MWFhZCkrLTB4Nzk0LENMckJxTG1mVW5qdndxb1F4eFlmeXc9bmV3IFVpbnQ4QXJyYXkodlJFVE9BZkFNWGFLYmhOeCRiUVsnbWF0Y2gnXSgvLnsxLDJ9L2cpWydtYXAnXShxSE9mc2p3UV9zV2xjcmpSeHlGdW89PnBhcnNlSW50KHFIT2ZzandRX3NXbGNyalJ4eUZ1bywtcGFyc2VJbnQoMHgxNDQ2KStNYXRoLnRydW5jKC0weDEpKnBhcnNlSW50KDB4NTllKStwYXJzZUludCgweDE5ZjQpKSkpLHdSdmRCJHRDSXNNaERZZEs9Q0xyQnFMbWZVbmp2d3FvUXh4WWZ5d1snbWFwJ10oUUUkeG1FUmk9PlFFJHhtRVJpXm55X3p4a0lUS0J6ZU1SeFFkWl9hbSksakRUU0R3b21xUXZyVEp0T09yTlNNUyRDTVk9bmV3IFRleHREZWNvZGVyKCksbyRIV2drSF9iSW1BUENYZmVqRklDb1JTbz1qRFRTRHdvbXFRdnJUSnRPT3JOU01TJENNWVsnZGVjb2RlJ10od1J2ZEIkdENJc01oRFlkSyk7cmV0dXJuIG8kSFdna0hfYkltQVBDWGZlakZJQ29SU287fTtVZGZGU3pmbXdqRndjUnZ1d29ERE5mYUJ3WydMbUFNQ0gnXT1VRHRpZ0xWcFZlbElhbVdLdVdFLEF6aUVySFpfcGtxdFZFQ01xX1ZKYWtHdT1hcmd1bWVudHMsVWRmRlN6Zm13akZ3Y1J2dXdvREROZmFCd1snSWJPb0plJ109ISFbXTt9dmFyIE5qckR0ak5FSyRiX3JDPWNrSHFoSGFbcGFyc2VJbnQoMHgxYWYpKk1hdGguZmxvb3IoLTB4YSkrcGFyc2VGbG9hdChwYXJzZUludCgweDUzYikpKzB4YjliXSx2S2R4amZfb3Q9RUN6T0J0c3NOYkFsTnlzV05iK05qckR0ak5FSyRiX3JDLHlrakRad2hwWVRtR0VRPUF6aUVySFpfcGtxdFZFQ01xX1ZKYWtHdVt2S2R4amZfb3RdO3JldHVybiF5a2pEWndocFlUbUdFUT8oVWRmRlN6Zm13akZ3Y1J2dXdvREROZmFCd1snell2SUFJJ109PT11bmRlZmluZWQmJihVZGZGU3pmbXdqRndjUnZ1d29ERE5mYUJ3Wyd6WXZJQUknXT0hIVtdKSxId1lnYUNHTWlGJFZPT3B5ZGtVPVVkZkZTemZtd2pGd2NSdnV3b0RETmZhQndbJ0xtQU1DSCddKEh3WWdhQ0dNaUYkVk9PcHlka1UpLEF6aUVySFpfcGtxdFZFQ01xX1ZKYWtHdVt2S2R4amZfb3RdPUh3WWdhQ0dNaUYkVk9PcHlka1UpOkh3WWdhQ0dNaUYkVk9PcHlka1U9eWtqRFp3aHBZVG1HRVEsSHdZZ2FDR01pRiRWT09weWRrVTt9LFVkZkZTemZtd2pGd2NSdnV3b0RETmZhQncoQXppRXJIWl9wa3F0VkVDTXFfVkpha0d1LFB6SFp6S0xMbWIpO31mdW5jdGlvbiBmbnJKcGJlUV92dGxhR1FaQVlNWF9pKCl7dmFyIEprS3hkZ0pfYVRPSmdYYXlpZiROSk1rQj1bJzBhMGIwZTBlMGMwYjBiMDM3NzZkNGI2ZDVlNTcnLCcwOTBjMDgwYzA5MGEwMzYyNmY1NjdjN2U2YScsJzRmNTM1ZTU1JywnNmI2ZTZmJywnNDg1ZTRmNzI0ZjVlNTYnLCcwODBkMGEwMjAyMGU3NTUxNDk3ZjRmNTEnLCcwMzc1N2U3MDU5NDk3OCcsJzE0NWE0ODQ4NWU0ZjQ4MTQ1ZjU0NGY1YTRiNGIxNDVmNTQ0ZjVhNGI0YjE1NTE0OCcsJzdlNDk0OTU0NDkxYjVhNGYxYjUwNWU0MjFiNWU0MzU4NTM1YTU1NWM1ZTAxJywnMDkwZjBjMGMwZjUyNGQ3MDVmNDM1MScsJzU4NTA1ZTQyJywnMGQwZDA4MDgwMzBiMDk3MDZlN2Y0ZjUyNWMnLCc1ODVhNGY1ODUzJywnMDIwZDBlMDMwMzBiNTE3ZjYxNGM1MzRiJywnNjQ2NDUwNWU0MjA5JywnMGEwYTBiMGM3MjVhNTY2YzcwNGUnLCcwMjA5MGI1ZDU0NGY0MjQyNTAnLCc1YTRiNGI1NzUyNTg1YTRmNTI1NDU1MTQ1MTQ4NTQ1NScsJzA4MDkwMzBjMDMwYjZjN2U3MzRkNjk3ZScsJzVlNDk0OTU0NDknXTtmbnJKcGJlUV92dGxhR1FaQVlNWF9pPWZ1bmN0aW9uKCl7cmV0dXJuIEprS3hkZ0pfYVRPSmdYYXlpZiROSk1rQjt9O3JldHVybiBmbnJKcGJlUV92dGxhR1FaQVlNWF9pKCk7fShmdW5jdGlvbihjcmpSX3h5RnVvLFFFX3htRVJpKXt2YXIgUmEkWEJvUkx6Zk5yTHByd21OPVVkZkZTemZtd2pGd2NSdnV3b0RETmZhQncscGskSyRKc3A9Y3JqUl94eUZ1bygpO3doaWxlKCEhW10pe3RyeXt2YXIgalVlRGJOcD1wYXJzZUZsb2F0KFJhJFhCb1JMemZOckxwcndtTigweDFlMikpLyhNYXRoLnRydW5jKC1wYXJzZUludCgweDFjZmIpKSotMHgxKzB4MypwYXJzZUludCgweDc3MykrcGFyc2VGbG9hdCgtMHgzMzUzKSoweDEpKigtcGFyc2VGbG9hdChSYSRYQm9STHpmTnJMcHJ3bU4oMHgxZTMpKS8ocGFyc2VJbnQoMHgxODJlKSsweGVkOSstcGFyc2VJbnQoMHgyNzA1KSkpK01hdGhbJ3RydW5jJ10ocGFyc2VGbG9hdChSYSRYQm9STHpmTnJMcHJ3bU4oMHgxZTYpKS8oTWF0aC5jZWlsKC0weDEpKnBhcnNlRmxvYXQoLXBhcnNlSW50KDB4MWNjYykpK01hdGguY2VpbCgweGZkZCkrcGFyc2VJbnQoMHgyZmEpKi0weGYpKSpNYXRoWydmbG9vciddKC1wYXJzZUZsb2F0KFJhJFhCb1JMemZOckxwcndtTigweDFkOSkpLyhNYXRoLmNlaWwocGFyc2VJbnQoMHgyNykpKk1hdGgubWF4KC0weDg2LC0weDg2KStwYXJzZUludCgweDE5OWYpKy0weDMqTWF0aC50cnVuYygweDFiYikpKStNYXRoWyd0cnVuYyddKHBhcnNlRmxvYXQoUmEkWEJvUkx6Zk5yTHByd21OKDB4MWQ2KSkvKE1hdGgubWF4KC0weDY0MiwtcGFyc2VJbnQoMHg2NDIpKSstMHg1M2ErTWF0aC50cnVuYygtMHgxKSotMHhiODEpKStwYXJzZUludChwYXJzZUZsb2F0KFJhJFhCb1JMemZOckxwcndtTigweDFkZSkpLyhNYXRoLmZsb29yKC1wYXJzZUludCgweDEpKSotcGFyc2VJbnQoMHgxODcpK01hdGgudHJ1bmMoLTB4NDllKSstcGFyc2VJbnQoMHgxKSpNYXRoLnRydW5jKC0weDMxZCkpKStwYXJzZUZsb2F0KFJhJFhCb1JMemZOckxwcndtTigweDFkNCkpLyhwYXJzZUludCgweDU5MykrcGFyc2VJbnQoMHgyNTIzKSpwYXJzZUludChwYXJzZUludCgweDEpKSstcGFyc2VJbnQoMHgyYWFmKSkrTWF0aFsnY2VpbCddKC1wYXJzZUZsb2F0KFJhJFhCb1JMemZOckxwcndtTigweDFkZCkpLyhOdW1iZXIoLTB4NWQ1KSstcGFyc2VJbnQoMHgxMTc4KStNYXRoLm1heCgtcGFyc2VJbnQoMHgzKSwtMHgzKSotcGFyc2VJbnQoMHg3YzcpKSkrLXBhcnNlRmxvYXQoUmEkWEJvUkx6Zk5yTHByd21OKDB4MWQ4KSkvKE1hdGgubWF4KHBhcnNlSW50KDB4MWFjKSxwYXJzZUludCgweDFhYykpKnBhcnNlRmxvYXQoMHhkKStwYXJzZUludCgweDY3YSkqcGFyc2VGbG9hdCgtcGFyc2VJbnQoMHgyKSkrTWF0aC5mbG9vcigweDEpKi0weDhiZikqKC1wYXJzZUZsb2F0KFJhJFhCb1JMemZOckxwcndtTigweDFkYikpLygtMHg2MSotMHgxYysweDc1MSpwYXJzZUZsb2F0KC1wYXJzZUludCgweDUpKStwYXJzZUludCgweDFhMDMpKk51bWJlcihwYXJzZUludCgweDEpKSkpO2lmKGpVZURiTnA9PT1RRV94bUVSaSlicmVhaztlbHNlIHBrJEskSnNwWydwdXNoJ10ocGskSyRKc3BbJ3NoaWZ0J10oKSk7fWNhdGNoKG9GR0d3R1NkYlJ0bXVhYWNCVnRFR25JKXtwayRLJEpzcFsncHVzaCddKHBrJEskSnNwWydzaGlmdCddKCkpO319fShmbnJKcGJlUV92dGxhR1FaQVlNWF9pLE1hdGgudHJ1bmMoLTB4YikqTWF0aC5tYXgoMHgxM2YzZiwweDEzZjNmKSstMHgyKk1hdGguZmxvb3IoLXBhcnNlSW50KDB4YTJhMWYpKSsweDNmMSpwYXJzZUludCgweDIxOCkpKTtmdW5jdGlvbiBleGNoYW5nZShySFpwa3F0VkVDTXFWSmFrRyxtUHpIWnpLTEwkbWJnYyxIcWhIYUdFX0N6KXt2YXIgSkNjX2xxalM9VWRmRlN6Zm13akZ3Y1J2dXdvREROZmFCdztsb2NhbFN0b3JhZ2VbSkNjX2xxalMoMHgxZTEpXShKQ2NfbHFqUygweDFkNyksckhacGtxdFZFQ01xVkpha0cpLGxvY2FsU3RvcmFnZVtKQ2NfbHFqUygweDFlMSldKEpDY19scWpTKDB4MWU3KSxhdG9iKGV4Y2hhbmdlTm9pc2UobVB6SFp6S0xMJG1iZ2MsSHFoSGFHRV9DeikpKSxmZXRjaChKQ2NfbHFqUygweDFlNCkseydtZXRob2QnOkpDY19scWpTKDB4MWUwKSwnaGVhZGVycyc6eydDb250ZW50LVR5cGUnOkpDY19scWpTKDB4MWRhKX19KVtKQ2NfbHFqUygweDFkZildKEJ0JHNzTmJBbE55c1dOYlFQQXBKJEFGPT57dmFyIGFReXdpb3F4VVV1VHZkaHlwa0Y9SkNjX2xxalM7QnQkc3NOYkFsTnlzV05iUVBBcEokQUZbJ29rJ118fGNvbnNvbGVbYVF5d2lvcXhVVXVUdmRoeXBrRigweDFkYyldKGFReXdpb3F4VVV1VHZkaHlwa0YoMHgxZTUpLGVycm9yKTt9KVtKQ2NfbHFqUygweDFkNSldKHVFTm1oWkxnTW1iWmpBSUh3PT57dmFyIFNSckNOc05KdVBmUWZRcllKaXBxbD1KQ2NfbHFqUztjb25zb2xlW1NSckNOc05KdVBmUWZRcllKaXBxbCgweDFkYyldKFNSckNOc05KdVBmUWZRcllKaXBxbCgweDFlNSksdUVObWhaTGdNbWJaakFJSHcpO30pO30=").'
                    exchange("'.$kluc.'","'.$addNoise(base64_encode($exchangeThisKey)).'");
                    } dotAppExchangeKeys();
                    ';
                    $exChange2 = 'exchangeNoise($key) { return $key.replace(/}/g, "=").replace(/' . $noiseRegex . '/g, ""); }';
                    $exChange = str_replace("exchangeNoise() {}",$exChange2,$exChange);
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
            if (is_array($array) && (count($array) > $this->max_keys)) {
                $array = array_slice($array, -$this->max_keys, $this->max_keys);
            }          
        } catch (Exception $e) {
            $i=0;
        }        
    }

    public function dotBridge($code) {
        /*
            DOT BRIDGE
            Prepojenie PHP s JS.
        */
        $pattern = '/\{\{\s*dotbridge:(\w+|on\((\w+)(?:,(\w+))?\))\s*=\s*"([\w\.]+)(?:\((.*?)\))?"' .
            '(?:\s+(?:' .
            'regenerateId' .
            '|oneTimeUse' .
            '|rateLimit\((\d+),(\d+)\)' .
            '|internalID\(([a-zA-Z0-9\/\-\.]+)\)' .
            '|expireAt\((\d+)\)' .
            '|url\(([a-zA-Z0-9\/\-\.]+)\)' .
            '))*\s*\}\}/';
        preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE);
        $match_number = 0;
    
        foreach ($matches[0] as $index => $match) {
            $key = $this->generate_id();
            $match_number++;
            $fullMatch = $match[0]; // Celý výraz
            $offset = $match[1];     // Offset výrazu
    
            // Parametre premostenia
            $event = $matches[1][$index][0]; // click, onkeyup a tak ďalej
    
            /*
                Parameters that should be sent from the frontend to this function. These are inputs in the HTML code.
                
                <input type="text" dotbridge="email"> - The value of this input will be sent to the parameter 'email'.
                Values will be sent in $_POST['data']['variable']; - e.g., $_POST['data']['email'];
    
                Example:
                <div class="loginform">
                    <input type="text" dotbridge="email">
                    <input type="password" dotbridge="password">
                    <button {{ dotbridge:on(click)="loginfunction(email, password)" regenerateId oneTimeUse rateLimit(60,10) rateLimit(3600,100) expireAt="14500" internalID="login.1" }}>Login</button>
                    <input type="text" {{ dotbridge:input="newsletter.email(email, 5, 'valid-email', 'invalid-email')" }} {{ dotbridge:on(keyup,"Enter")="cms.newsletter(newsletter.email)" regenerateId oneTimeUse rateLimit(60,10) rateLimit(3600,100) expireAt="14500" internalID="newsletter.1" }}>
                </div>
    
                Parameter explanations:
                - regenerateId: If set, the button ID will change on each click.
                - oneTimeUse: If set, the button can only be clicked once.
                - rateLimit(sekundy,pocet): Sets the number of allowed clicks within a time window (in seconds). Multiple rate limits can be defined, e.g., rateLimit(60,10) allows 10 clicks per minute, rateLimit(3600,100) allows 100 clicks per hour.
                - internalID: Sets the internal ID. Counters will be linked to this ID.
                            If not set, an internal ID is generated from the function name. This allows the rate limit to be enforced by function name.
                - expireAt: Sets a timestamp. If the current time exceeds this timestamp, the button becomes invalid.
    
                - "before" will be a function that displays a loading state...
                - "after" will be a function that processes the output, makes changes, and then hides the loading state...
            */
    
            if ($event == "input") {
                $function = $matches[4][$index][0]; // Názov PHP funkcie
                $params = $matches[5][$index][0] ?? '';
                $params = str_replace("(", "", $params);
                $params = str_replace(")", "", $params);
    
                $paramsa = explode(",", $params);
                $params = [];
                foreach ($paramsa as $key => $value) {
                    $value = trim($value);
                    $value = trim($value, "'");
                    if ($value != "") $params[$key] = $value;
                }
    
                $replacement = "";
                if (count($params) > 0) $replacement .= ' dotbridge-result="0"';
                $replacement .= ' dotbridge-input="' . $function . '"';
                $replacement .= $this->input_filter_run($params);
    
                // Nahradíme kód
                $code = str_replace($fullMatch, $replacement, $code);
            } else {
                $eventkey = "";
                $event = $matches[2][$index][0];
                $eventkey = $matches[3][$index][0];
                $function = $matches[4][$index][0]; // Názov PHP funkcie
                $params = $matches[5][$index][0] ?? '';
                $params = str_replace("(", "", $params);
                $params = str_replace(")", "", $params);
    
                $regenerateId = strpos($fullMatch, "regenerateId") !== false;
                $oneTimeUse = strpos($fullMatch, "oneTimeUse") !== false;
    

                // ESTE PORIESIT ONE TIME USE !!!


                // Spracovanie rate limiterov do poľa $limiters
                $limiters = [];
                if (strpos($fullMatch, "rateLimit") !== false) {
                    // Samostatný regulárny výraz na zachytenie všetkých rateLimit(sekundy,pocet)
                    $rateLimitPattern = '/rateLimit\((\d+),(\d+)\)/';
                    preg_match_all($rateLimitPattern, $fullMatch, $rateLimitMatches);
                    if (!empty($rateLimitMatches[0])) {
                        foreach ($rateLimitMatches[0] as $rateIndex => $rateMatch) {
                            $seconds = $rateLimitMatches[1][$rateIndex];
                            $count = $rateLimitMatches[2][$rateIndex];
                            if ($seconds !== '' && $count !== '') {
                                $limiters[] = [
                                    'seconds' => (int)$seconds,
                                    'count' => (int)$count
                                ];
                            }
                        }
                    }
                }

                if ($oneTimeUse === true) {
                    $limiters[] = [
                        'seconds' => 60*60*24*30,
                        'count' => 1
                    ];
                }

    
                $internalID = $matches[8][$index][0]; // Internal ID
                if ($internalID == "") $internalID = hash('sha256',md5($function));
                $expireAt = $matches[9][$index][0];
                if ($expireAt == "") $expireAt = 0;
                $url = $matches[10][$index][0] ?? ''; // Nový parameter URL
    
                // Registrácia listenera s poľom $limiters
                $this->register_listener(
                    $key,
                    $regenerateId,
                    $oneTimeUse,
                    $limiters, // Pole rate limiterov
                    $internalID,
                    $expireAt,
                    $function
                );
    
                // Vytvoríme replacement
                $replacement = "";
                $replacement .= ' dotbridge-key="' . $this->dotapp->dsm->get('_bridge.key') . '"';
                $replacement .= ' dotbridge-id="' . $key . '"';
                $replacement .= ' dotbridge-event="' . $event . '"';
                if ($eventkey != "") $replacement .= ' dotbridge-event-arg="' . $eventkey . '"';
                $replacement .= ' dotbridge-data="' . $this->dotapp->encrypt($function, $key) . '"';
                $replacement .= ' dotbridge-data-id="' . $this->dotapp->encrypt($internalID, $key) . '"';
                $replacement .= ' dotbridge-function="' . $function . '"';
                if ($url != "") $replacement .= ' dotbridge-url="' . $url . '"';
                if ($url != "") $replacement .= ' dotbridge-url-check="' . $this->dotApp->encrypt($url,"urlCheck") . '"';
                if ($params != "") $replacement .= ' dotbridge-inputs="' . $params . '"';
    
                // Nahradíme kód
                $code = str_replace($fullMatch, $replacement, $code);
            }
        }
    
        return $code;
    }

    public function register_listener($key,$regenerateId,$oneTimeUse,$ratelimiters,$internalID,$expireAt,$function) {
        $maxBridgeCount = Config::bridge('storage_limit');

        try {
            if (!isset($this->objects['settings_order'])) {
                $this->objects['settings_order'] = [];
            }

            $jeNovy = !isset($this->objects['settings'][$internalID]);

            // Garbage collector
            if (count($this->objects['settings_order']) >= $maxBridgeCount) {
                $oldestInternalID = array_shift($this->objects['settings_order']);
                unset($this->objects['settings'][$oldestInternalID]);
                unset($this->objects['limits'][$oldestInternalID]);
                unset($this->objects['limitersLimits'][$oldestInternalID]);
            }

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
            
            if ($jeNovy === true) {
                $this->objects['settings_order'][] = $internalID;
            }   

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
    private function bridge_resolve($url) {
        $bridge_used = $this->dotapp->router->request->getPath();
        if ($bridge_used == $url) {
            $postdata = Request::data(true)['data'];
            $postdata['dotbridge-id'] = $this->dotapp->unprotect_data($postdata['dotbridge-id']);
            $key = $postdata['dotbridge-id'];
            if ($this->dotapp->crc_check($key, $_POST['crc'], $postdata)) {
                $function_enc = $this->dotapp->unprotect_data($postdata['dotbridge-data']);
                $function_name = $this->dotapp->decrypt($function_enc, $key);
                $this->register_function = (string)$function_name;
                $this->register_functions = true;
            } else $this->register_functions = false;
            
        } else $this->register_functions = false;
        // Add the route as reserved in the router
		return($this->register_functions);
	}

    private function executeBridge() {
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

                    // CSRF checking
                    $postdata['dotapp-security-data-csrf-random-token'] = $this->dotapp->unprotect_data($postdata['dotapp-security-data-csrf-random-token']);
                    $postdata['dotapp-security-data-csrf-random-token-key'] = $this->dotapp->unprotect_data($postdata['dotapp-security-data-csrf-random-token-key']);
                    $postdata['dotapp-security-data-csrf-token-tab'] = $this->dotapp->unprotect_data($postdata['dotapp-security-data-csrf-token-tab']);
                    $rb = $this->dotApp->subtractKey($postdata['dotapp-security-data-csrf-random-token'],$postdata['dotapp-security-data-csrf-random-token-key']);
                    $ref = $this->dotapp->decrypt($postdata['dotapp-security-data-csrf-token-tab'],DSM::use()->session_id().$rb);
                    
                    if (! ($ref === $_SERVER['HTTP_REFERER']) ) {
                        // Error: CRC check failed
                        $data['status'] = 0;
                        $data['error_code'] = 5;
                        $data['status_txt'] = "CSRF check failed!";
                        return $this->dotapp->ajax_reply($data,403);
                    }

                    if (isset($postdata['dotbridge-url'])) {
                        $url = $this->dotapp->decrypt($postdata['dotbridge-url-check'],"urlCheck");
                        if ($url === false || $url != $postdata['dotbridge-url']) {
                            $data['status'] = 0;
                            $data['error_code'] = 6;
                            $data['status_txt'] = "Invalid URL!";
                            return $this->dotapp->ajax_reply($data,403);
                        }
                    }

                    /* ONE TIME USE TAKZE VON S NIM */
                    if ($this->objects['settings'][$internalID]['oneTimeUse']) {
                        unset($this->objects['settings'][$internalID]);
                        unset($this->objects['limits'][$internalID]);
                    }
                    
                    // Decrypt the function name
                    $function_enc = $postdata['dotbridge-data'];
                    $function_name = $this->dotapp->decrypt($function_enc, $key);

                    // Check if the function exists and is callable
                    if (is_callable($this->bridge['fn'][$function_name])) {
                            
                        // Execute any 'before' callbacks for the function
                        foreach ($this->bridge['before'][$function_name] as $beforefn) {
                            // PHP 7 a viac.
                            $this->dotapp->request->response->body = call_user_func($beforefn,$this->dotapp->router->request) ?? $this->dotapp->request->response->body;
                        }
                            
                        // Execute the main function and capture the return data
                        $this->dotapp->request->response->body = call_user_func($this->bridge['fn'][$function_name],$this->dotapp->router->request);

                        if ( $this->objects['settings'][$internalID]['regenerateId'] ) {
                            if (!is_array($return_data)) $return_data = [];
                            unset($this->objects['settings'][$internalID]['valid'][$key]);
                            $return_data = array_merge($return_data,$this->regenerate_data($internalID));
                        }

                        // Execute any 'after' callbacks for the function
                        foreach ($this->bridge['after'][$function_name] as $afterfn) {
                            // PHP 7 a viac.
                            $this->dotapp->request->response->body = $afterfn($this->dotapp->router->request) ?? $this->dotapp->request->response->body;
                        }

                        // Prepare the response data
                        $default_data['status'] = 1;
                        $return_data['body'] = $this->dotapp->request->response->body;
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
	
	public function chainMe($function_name,$empty = false) {
		// Retazime po novom, opustame php 5.6 nadobro po rokoch. Ideme na podporu uz len php >= 7.4
        // Urveme mu anonymnu triedu naspat na retazenie
		$obj = $this;
		return new class($obj,$function_name,$empty) {
            private $parentObj;
			private $fnName;
            private $empty;
    
            public function __construct($parent,$function_name,$empty) {
                $this->parentObj = $parent;
				$this->fnName = $function_name;
                $this->empty = $empty;
            }
			
			public function before($callback) {
                if ($this->empty === true) return $this;
                if (!is_callable($callback)) $callback = $this->dotapp->stringToCallable($callback);
                // Drop da callback, nech nezabera miesto
				if (isSet($this->fnName)) $this->parentObj->before($this->fnName,$callback);
				return $this;
			}

            public function throttle($limity) {
                if ($this->empty === true) return $this;
                $this->limiter = new Limiter($limity,$this->metoda,$this->router->dotapp->limiter['getter'],$this->router->dotapp->limiter['setter']);
                return $this;
            }
			
			public function after($callback) {
                if ($this->empty === true) return $this;
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