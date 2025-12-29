<?php

namespace Dotsystems\App\Parts;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Validator;

/**
 * Class Input
 *
 * Secure Form Builder & Validator with Template Injection support.
 *
 * =============================================================================
 * POUŽITIE (MANUÁL)
 * =============================================================================
 *
 * 1. PHP - Definícia formulára (Builder):
 * ---------------------------------------
 * $form = Input::group('register_form');
 * $form->text('username', ['class' => 'input'], 'required|alpha_num|min:3');
 * $form->password('pass', [], 'required|strong_password');
 * $securityKeys = $form->export(); // Vráti hidden inputy (ak nepoužívate Template)
 *
 *
 * 2. HTML Šablóna (Template Syntax):
 * ----------------------------------
 * <form method="POST">
 * {{ InputKeys('register_form') }}
 * <label>Login:</label>
 * {{ input:text name="username" rules="required|alpha_num" group="register_form" }}
 * <button type="submit">Odoslať</button>
 * </form>
 *
 *
 * 3. Spracovanie Requestu (Backend cez RequestObj):
 * ----------------------------------
 * V rámci DotApp frameworku neriešite $_POST manuálne. Použite Request objekt:
 *
 * // V Controlleri:
 * $result = $this->request->validateInputs('register_form');
 *
 * if ($result === true) {
 * // Validácia úspešná
 * $data = $this->request->data(); // Obsahuje čisté dáta
 * // ... registrácia užívateľa ...
 * } else {
 * // Validácia zlyhala
 * // $result obsahuje pole ['status' => 0, 'errors' => [...]]
 * // Môžete ho rovno vrátiť ako JSON odpoveď pre DotApp JS
 * return $this->response->json($result);
 * }
 *
 *
 * 4. Dostupné Pravidlá:
 * ---------------------------------------
 * - required, email, numeric, integer, between:min,max
 * - min:x, max:x, alpha, alpha_num, strong_password
 * - match:field, in:a,b,c, unique:table.col (ak implementované)
 */

class Input {
    
    /** @var Input[] Multiton instances */
    private static $instances = [];

    /** @var array<string, callable> Global custom validation filters */
    private static $customFilters = [];
    
    /** @var string */
    private $groupName;
    
    /** @var array Field definitions */
    private $fields = [];
    
    /** @var array Data values (current state) */
    private $data = [];

    /** @var array Validation errors */
    private $errors = [];
    
    /** @var DotApp */
    private $dotApp;

    private function __construct($groupName) {
        $this->groupName = $groupName;
        $this->dotApp = DotApp::dotApp();
    }

    /**
     * Získa alebo vytvorí inštanciu skupiny.
     */
    public static function group($groupName) {
        if (!isset(self::$instances[$groupName])) {
            self::$instances[$groupName] = new self($groupName);
        }
        return self::$instances[$groupName];
    }

    public static function addGlobalFilter($name, callable $callback) {
        self::$customFilters[$name] = $callback;
    }

    // =========================================================================
    // BUILDER METÓDY
    // =========================================================================

    private function add($type, $name, $attrs = [], $rules = '') {
        $this->fields[$name] = [
            'type' => $type,
            'name' => $name,
            'attributes' => $attrs,
            'rules' => $rules,
            'options' => $attrs['options'] ?? []
        ];
        return $this;
    }

    public function text($name, $attrs = [], $rules = '') { return $this->add('text', $name, $attrs, $rules); }
    public function password($name, $attrs = [], $rules = '') { return $this->add('password', $name, $attrs, $rules); }
    public function email($name, $attrs = [], $rules = '') { return $this->add('email', $name, $attrs, $rules); }
    public function number($name, $attrs = [], $rules = '') { return $this->add('number', $name, $attrs, $rules); }
    public function file($name, $attrs = [], $rules = '') { return $this->add('file', $name, $attrs, $rules); }
    
    public function hidden($name, $value, $attrs = []) { 
        $this->data[$name] = $value;
        return $this->add('hidden', $name, $attrs, ''); 
    }
    
    public function textarea($name, $attrs = [], $rules = '') { return $this->add('textarea', $name, $attrs, $rules); }

    public function select($name, array $options, $attrs = [], $rules = '') {
        $attrs['options'] = $options;
        return $this->add('select', $name, $attrs, $rules);
    }

    public function checkbox($name, $value = 1, $attrs = [], $rules = '') {
        $attrs['value'] = $value;
        return $this->add('checkbox', $name, $attrs, $rules);
    }

    public function radio($name, $value, $attrs = [], $rules = '') {
        $attrs['value'] = $value;
        $uniqueKey = $name . '_' . $value;
        $this->fields[$uniqueKey] = [
            'type' => 'radio',
            'name' => $name,
            'attributes' => $attrs,
            'rules' => $rules
        ];
        return $this;
    }

    // =========================================================================
    // STATE MANAGEMENT
    // =========================================================================

    public function setValues(array $data) {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    public function getValue($name) {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    // =========================================================================
    // RENDERER
    // =========================================================================

    public function render($fieldKey) {
        if (!isset($this->fields[$fieldKey])) return "";

        $field = $this->fields[$fieldKey];
        $type = $field['type'];
        $name = $field['name'];
        $value = $this->getValue($name);
        
        $attrs = $field['attributes'];
        unset($attrs['options']);

        if (!isset($attrs['id'])) $attrs['id'] = $name;
        $attrs['groupName'] = $this->groupName;

        if (isset($this->errors[$name])) {
            $attrs['class'] = isset($attrs['class']) ? $attrs['class'] . ' is-invalid' : 'is-invalid';
        }

        if (strpos($field['rules'], 'required') !== false) {
            $attrs['required'] = 'required';
        }

        $htmlAttrs = $this->buildAttrs($attrs);

        switch ($type) {
            case 'textarea':
                return "<textarea name=\"$name\" $htmlAttrs>" . htmlspecialchars((string)$value) . "</textarea>";
            case 'select':
                $html = "<select name=\"$name\" $htmlAttrs>";
                $options = $field['options'] ?? [];
                if (isset($attrs['placeholder'])) {
                    $html .= '<option value="">' . htmlspecialchars($attrs['placeholder']) . '</option>';
                }
                foreach ($options as $val => $text) {
                    $selected = ((string)$val === (string)$value) ? 'selected' : '';
                    $html .= "<option value=\"" . htmlspecialchars($val) . "\" $selected>" . htmlspecialchars($text) . "</option>";
                }
                $html .= "</select>";
                return $html;
            case 'checkbox':
                $checkVal = $attrs['value'] ?? 1;
                $checked = ((string)$value === (string)$checkVal) ? 'checked' : '';
                return "<input type=\"checkbox\" name=\"$name\" value=\"" . htmlspecialchars($checkVal) . "\" $htmlAttrs $checked>";
            case 'radio':
                $radioVal = $attrs['value'];
                $checked = ((string)$value === (string)$radioVal) ? 'checked' : '';
                return "<input type=\"radio\" name=\"$name\" value=\"" . htmlspecialchars($radioVal) . "\" $htmlAttrs $checked>";
            default:
                $valAttr = ($type !== 'password' && $type !== 'file') ? 'value="' . htmlspecialchars((string)$value) . '"' : '';
                return "<input type=\"$type\" name=\"$name\" $valAttr $htmlAttrs>";
        }
    }

    private function buildAttrs($attrs) {
        $html = [];
        foreach ($attrs as $k => $v) {
            if ($v === true) $html[] = $k;
            else $html[] = $k . '="' . htmlspecialchars($v) . '"';
        }
        return implode(' ', $html);
    }

    // =========================================================================
    // SECURITY CORE
    // =========================================================================

    public function export() {
        $schema = [];
        foreach ($this->fields as $key => $f) {
            $name = $f['name'];
            if (!isset($schema[$name])) {
                $schema[$name] = $f['rules'];
            }
        }

        $payload = [
            'group' => $this->groupName,
            'schema' => $schema,
            'ts' => time()
        ];
        
        $json = json_encode($payload);
        $randomSalt = bin2hex(random_bytes(64));
        $keyPayload = $randomSalt . ':' . $this->groupName;

        $derivedKey = $this->dotApp->encrypt($keyPayload, "InputKey");
        $encryptedData = $this->dotApp->encrypt($json, $derivedKey);
        
        return '
        <input type="hidden" name="DotAppInputGroupKey" groupName="'.$this->groupName.'" value="' . $derivedKey . '">
        <input type="hidden" name="DotAppInputGroupData" groupName="'.$this->groupName.'" value="' . $encryptedData . '">
        ';
    }

    public function handleRequest($requestData) {
        $this->data = $requestData;

        if (!isset($requestData['DotAppInputGroupKey']) || !isset($requestData['DotAppInputGroupData'])) {
            return $this; 
        }

        $derivedKey = $requestData['DotAppInputGroupKey'];
        $encryptedData = $requestData['DotAppInputGroupData'];

        $decryptedKeyPayload = $this->dotApp->decrypt($derivedKey, "InputKey");
        if (!$decryptedKeyPayload) throw new \Exception("Security Error: Invalid Form Key Structure.");

        $parts = explode(':', $decryptedKeyPayload);
        if (count($parts) < 2) throw new \Exception("Security Error: Malformed Key Payload.");
        
        array_shift($parts);
        $keyGroupName = implode(':', $parts);

        if ($keyGroupName !== $this->groupName) {
            throw new \Exception("Security Error: Form Group Mismatch.");
        }

        $json = $this->dotApp->decrypt($encryptedData, $derivedKey);
        if (!$json) throw new \Exception("Security Error: Data Decryption Failed.");

        $payload = json_decode($json, true);

        if ($payload && isset($payload['schema'])) {
            foreach ($payload['schema'] as $name => $rules) {
                if (isset($this->fields[$name])) {
                    $this->fields[$name]['rules'] = $rules;
                }
            }
        } else {
            throw new \Exception("Security Error: Invalid Payload Structure.");
        }
        
        return $this;
    }

    // =========================================================================
    // VALIDATION ENGINE (Delegating to Validator Class)
    // =========================================================================

    public function validate() {
        $this->errors = [];
        $data = $this->data;
        $uniqueFields = [];
        // Zabezpečenie, aby sme radio buttony nekontrolovali viackrát pre to isté meno
        foreach ($this->fields as $f) {
            $uniqueFields[$f['name']] = $f['rules'];
        }

        foreach ($uniqueFields as $name => $rulesString) {
            $rules = explode('|', $rulesString);
            $value = isset($data[$name]) ? trim($data[$name]) : null;

            foreach ($rules as $ruleItem) {
                if (empty($ruleItem)) continue;
                $parts = explode(':', $ruleItem, 2);
                $rule = trim($parts[0]);
                $paramString = $parts[1] ?? null;

                // Skip ak je prázdny a nie je required/present
                if (!in_array($rule, ['required', 'present', 'set']) && ($value === null || $value === '')) {
                    continue;
                }

                if (!$this->checkRule($rule, $value, $paramString, $data)) {
                    $this->addError($name, $rule, $paramString);
                    break; // Prvá chyba pre dané pole stačí
                }
            }
        }
        return empty($this->errors);
    }

    /**
     * Kontrola pravidiel.
     * Väčšinu deleguje na statické metódy triedy Validator.
     */
    private function checkRule($rule, $value, $paramString, $allData) {
        
        // 1. Pravidlá špecifické pre Input (vyžadujú kontext iných polí)
        if ($rule === 'match') {
            return isset($allData[$paramString]) && $value === $allData[$paramString];
        }

        // 2. Parsovanie parametrov pre Validator
        $params = $paramString ? array_map('trim', explode(',', $paramString)) : [];

        // 3. Delegovanie na Validator triedu
        switch ($rule) {
            case 'required':        return Validator::isRequired($value);
            case 'present':          
            case 'set':             return Validator::isSet($value);
            case 'email':           return Validator::isEmail($value);
            case 'numeric':          
            case 'number':          return Validator::isNumber($value);
            case 'integer':         return Validator::isInteger($value);
            case 'positive_number': return Validator::isPositiveNumber($value);
            
            case 'between': // Očakáva 2 parametre: min,max
                if (count($params) < 2) return false;
                return Validator::isInRange($value, (float)$params[0], (float)$params[1]);
            
            case 'min': // V novej Validator triede je min/max chápané ako dĺžka reťazca
                if (count($params) < 1) return false;
                return Validator::isMinLength($value, (int)$params[0]);
            
            case 'max':
                if (count($params) < 1) return false;
                return Validator::isMaxLength($value, (int)$params[0]);

            case 'alpha':           return Validator::isAlpha($value);
            case 'alpha_num':
            case 'alphanumeric':    return Validator::isAlphanumeric($value);
            case 'url':             return Validator::isUrl($value);
            case 'ip':              return Validator::isIpAddress($value);
            case 'json':            return Validator::isJson($value);
            case 'boolean':         return Validator::isBoolean($value);
            case 'uuid':            return Validator::isUuid($value);
            case 'date':            return Validator::isDate($value);
            case 'hex_color':       return Validator::isHexColor($value);
            case 'credit_card':     return Validator::isCreditCard($value);
            case 'valid_file_name': return Validator::isValidFileName($value);
            
            case 'strong_password':
                $special = isset($params[0]) ? filter_var($params[0], FILTER_VALIDATE_BOOLEAN) : false;
                return Validator::isStrongPassword($value, $special);

            case 'in':
            case 'one_of':
                return Validator::isOneOf($value, $params);

            case 'regex':
                return Validator::isMatchingRegex($value, $paramString); // Regex sa nesmie explodovať čiarkou

            case 'username':
                 // username:min,max,dash,dot
                 $min = isset($params[0]) ? (int)$params[0] : 3;
                 $max = isset($params[1]) ? (int)$params[1] : 20;
                 $dash = isset($params[2]) ? filter_var($params[2], FILTER_VALIDATE_BOOLEAN) : false;
                 $dot = isset($params[3]) ? filter_var($params[3], FILTER_VALIDATE_BOOLEAN) : false;
                 return Validator::isUsername($value, $min, $max, $dash, $dot);

            default:
                // Custom filtre definované globálne
                if (isset(self::$customFilters[$rule])) {
                    return call_user_func(self::$customFilters[$rule], $value, $paramString, $allData);
                }
                return true; // Neznáme pravidlá ignorujeme
        }
    }

    private function addError($name, $rule, $param) {
        // Tieto hlášky by mali byť v ideálnom svete v prekladovom súbore
        // Tu používame jednoduché mapovanie pre rýchlu odozvu
        $msg = "Validation error ($rule).";

        switch ($rule) {
            case 'required':        $msg = 'This field is required.'; break;
            case 'present':         $msg = 'This field must be present.'; break;
            case 'email':           $msg = 'Please enter a valid email address.'; break;
            case 'numeric':         $msg = 'Please enter a valid number.'; break;
            case 'integer':         $msg = 'Must be an integer.'; break;
            case 'positive_number': $msg = 'Must be a positive number.'; break;
            case 'between':         $msg = "Must be between $param."; break;
            case 'min':             $msg = "Must be at least $param characters long."; break;
            case 'max':             $msg = "Must be no more than $param characters."; break;
            case 'alpha':           $msg = 'Only alphabetic characters allowed.'; break;
            case 'alpha_num':       $msg = 'Only alphanumeric characters allowed.'; break;
            case 'match':           $msg = "Fields do not match."; break;
            case 'url':             $msg = "Invalid URL format."; break;
            case 'ip':              $msg = "Invalid IP address."; break;
            case 'date':            $msg = "Invalid date format."; break;
            case 'strong_password': $msg = "Password is not strong enough."; break;
            case 'in':              $msg = "Value is not allowed."; break;
        }

        $this->errors[$name] = $msg;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function getGroupName() {
        return $this->groupName;
    }

    public static function loadFromRequest($requestData) {
        if (!isset($requestData['DotAppInputGroupKey'])) return null;
        $derivedKey = $requestData['DotAppInputGroupKey'];
        $dotApp = DotApp::dotApp();
        $decryptedKeyPayload = $dotApp->decrypt($derivedKey, "InputKey");
        if (!$decryptedKeyPayload) return null;
        
        $parts = explode(':', $decryptedKeyPayload);
        if (count($parts) < 2) return null;
        
        array_shift($parts);
        $groupName = implode(':', $parts);
        
        $instance = self::group($groupName);
        try {
            $instance->handleRequest($requestData);
        } catch (\Exception $e) {
            return null;
        }
        return $instance;
    }

    // =========================================================================
    // TEMPLATE RENDERER
    // =========================================================================

    public static function registerRenderer($targetGroups = null) {
        $dotApp = DotApp::dotApp();
        if ($dotApp->router && $dotApp->router->renderer) {
            $suffix = is_array($targetGroups) ? implode('_', $targetGroups) : ($targetGroups ?? 'all');
            $dotApp->router->renderer->addRenderer('input_form_' . $suffix, function($html) use ($targetGroups) {
                return self::parseTemplate($html, $targetGroups);
            });
        }
    }

    public static function parseTemplate($html, $targetGroups = null) {
        $groupMap = null;
        if ($targetGroups !== null) {
            if (is_array($targetGroups)) {
                $groupMap = array_flip($targetGroups);
            } else {
                $groupMap = [$targetGroups => true];
            }
        }

        $html = preg_replace_callback('/\{\{\s*input:(\w+)\s*(.*?)\s*\}\}/is', function($matches) use ($groupMap) {
            $type = strtolower($matches[1]);
            $paramsString = $matches[2];
            
            $attrs = self::parseAttributesString($paramsString);
            $group = $attrs['group'] ?? 'default';
            
            if ($groupMap !== null && !isset($groupMap[$group])) {
                return $matches[0];
            }

            // Získame inštanciu formulára
            $inputObj = self::group($group);
            
            $name  = $attrs['name'] ?? uniqid('input_');
            
            $existingField = $inputObj->fields[$name] ?? null;

            // 1. PRAVIDLÁ (RULES):
            // Ak sú v HTML, majú prednosť. Ak nie, použijeme tie z PHP. Ak nie sú nikde, tak prázdny string.
            if (isset($attrs['rules'])) {
                $rules = $attrs['rules'];
            } elseif ($existingField && isset($existingField['rules'])) {
                $rules = $existingField['rules'];
            } else {
                $rules = '';
            }

            // Odstránime technické atribúty z poľa atribútov pre HTML
            unset($attrs['group'], $attrs['rules'], $attrs['name']);
            
            // Ak existujú atribúty definované v PHP (napr. class), môžeme ich tu mergnúť, 
            // ale pre jednoduchosť necháme HTML atribúty vyhrať nad PHP atribútmi.

            // 2. SPRACOVANIE PODĽA TYPU
            if ($type === 'select') {
                $options = [];
                
                // Priorita 1: Options definované priamo v HTML (string)
                if (isset($attrs['options'])) {
                    $options = self::parseOptionsString($attrs['options']);
                    unset($attrs['options']);
                } 
                // Priorita 2: Options definované v PHP (pole)
                elseif ($existingField && isset($existingField['options'])) {
                    $options = $existingField['options'];
                }

                $inputObj->select($name, $options, $attrs, $rules);
            } 
            elseif ($type === 'checkbox') {
                $val = $attrs['value'] ?? ($existingField['attributes']['value'] ?? 1);
                $inputObj->checkbox($name, $val, $attrs, $rules);
            }
            elseif ($type === 'radio') {
                $val = $attrs['value'] ?? ($existingField['attributes']['value'] ?? 1);
                $inputObj->radio($name, $val, $attrs, $rules);
            }
            elseif ($type === 'textarea') {
                $inputObj->textarea($name, $attrs, $rules);
            }
            else {
                // Text, password, email, atď.
                if (method_exists($inputObj, $type)) {
                    $inputObj->$type($name, $attrs, $rules);
                } else {
                    $inputObj->add($type, $name, $attrs, $rules);
                }
            }

            return $inputObj->render($name);

        }, $html);

        $html = preg_replace_callback('/\{\{\s*InputKeys\(([\'"]?)(\w+)\1\)\s*\}\}/i', function($matches) use ($groupMap) {
            $group = $matches[2];
            if ($groupMap !== null && !isset($groupMap[$group])) {
                return $matches[0];
            }
            return self::group($group)->export();
        }, $html);

        return $html;
    }

    private static function parseAttributesString($string) {
        $attributes = [];
        preg_match_all('/(\w+)(?:=(?:"([^"]*)"|\'([^\']*)\'|(\S+)))?/', $string, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $key = $match[1];
            if (isset($match[2]) && $match[2] !== '') $val = $match[2];
            elseif (isset($match[3]) && $match[3] !== '') $val = $match[3];
            elseif (isset($match[4]) && $match[4] !== '') $val = $match[4];
            else $val = true;
            $attributes[$key] = $val;
        }
        return $attributes;
    }

    private static function parseOptionsString($string) {
        $options = [];
        foreach (explode(',', $string) as $pair) {
            $parts = explode(':', $pair, 2);
            if (count($parts) == 2) {
                $options[trim($parts[0])] = trim($parts[1]);
            } else {
                $options[trim($pair)] = trim($pair);
            }
        }
        return $options;
    }
}
?>