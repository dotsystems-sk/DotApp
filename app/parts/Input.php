<?php
/**
 * CLASS Input
 *
 * This class provides a fluent API for generating secure HTML form elements
 * within the DotApp framework. It supports various input types and automatic
 * escaping, optimized for modern JavaScript-driven applications.
 *
 * Key Features:
 * - Fluent interface for creating form elements (select, input, textarea, checkbox, radio, etc.).
 * - Specialized builder classes (InputSelect, InputTextarea, etc.) for context-specific method chaining.
 * - Automatic escaping of values to prevent XSS attacks.
 * - Optional encryption of sensitive values using DotApp's global encrypt method.
 * - Customizable attributes and validation rules.
 * - Render individual elements (`render()`) or all elements (`renderAll()`) from main class or subclasses.
 * - Seamless chaining between input types using magic `__call()` to eliminate need for `end()`.
 * - Automatic frontend-backend form processing linkage using `fnName` and hidden fields.
 * - Optimized for modern JavaScript-driven applications (no CSRF tokens, no fallback for disabled JS).
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.7
 * @license   MIT License
 * @date      2014-2025
 * @compatibility Tested on PHP 7.4
 *
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the
 * following condition: You **must** retain this header in all copies or
 * substantial portions of the code, including the author and company information.
 */

namespace Dotsystems\App\Parts;

use \Dotsystems\App\DotApp;

class Input {
    public $dotapp;
    public $dotApp;
    public $DotApp;
    private $elements = []; // Array of form elements
    private $currentElement = null; // Current element being built
    private $escape = true; // Auto-escape values by default
    private $validationRules = []; // Validation rules for inputs

    /**
     * Constructor
     *
     * @param DotApp $dotapp Framework instance
     */
    public function __construct()
    {
        $this->dotapp = DotApp::dotApp();
        $this->dotApp = $this->dotapp;
        $this->DotApp = $this->dotapp;
    }

    /**
     * Disable automatic escaping (use with caution)
     *
     * @return $this
     */
    public function disableEscape()
    {
        $this->escape = false;
        return $this;
    }

    /**
     * Enable automatic escaping
     *
     * @return $this
     */
    public function enableEscape()
    {
        $this->escape = true;
        return $this;
    }

    /**
     * Add a select element
     *
     * @param string $name Input name
     * @param array $attributes Additional HTML attributes
     * @return InputSelect
     */
    public function select($name, array $attributes = [])
    {
        $this->addElement('select', $name, $attributes);
        return new InputSelect($this, $this->currentElement);
    }

    /**
     * Add a text input element
     *
     * @param string $name Input name
     * @param string|null $value Default value
     * @param array $attributes Additional HTML attributes
     * @param bool $encrypt Whether to encrypt the value
     * @return InputText
     */
    public function text($name, $value = null, array $attributes = [], $encrypt = false)
    {
        $value = $this->encryptValue($value, $encrypt);
        $value = $value !== null && $this->escape ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
        $attributes['value'] = $value;
        $this->addElement('text', $name, $attributes);
        return new InputText($this, $this->currentElement);
    }

    /**
     * Add a textarea element
     *
     * @param string $name Input name
     * @param string|null $value Default value
     * @param array $attributes Additional HTML attributes
     * @return InputTextarea
     */
    public function textarea($name, $value = null, array $attributes = [])
    {
        $value = $value !== null && $this->escape ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
        $this->addElement('textarea', $name, $attributes, $value);
        return new InputTextarea($this, $this->currentElement);
    }

    /**
     * Add a checkbox element
     *
     * @param string $name Input name
     * @param string $value Checkbox value
     * @param bool $checked Whether the checkbox is checked
     * @param array $attributes Additional HTML attributes
     * @param bool $encrypt Whether to encrypt the value
     * @return InputCheckbox
     */
    public function checkbox($name, $value, $checked = false, array $attributes = [], $encrypt = false)
    {
        $value = $this->encryptValue($value, $encrypt);
        $value = $this->escape ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
        if ($checked) {
            $attributes['checked'] = 'checked';
        }
        $attributes['value'] = $value;
        $this->addElement('checkbox', $name, $attributes);
        return new InputCheckbox($this, $this->currentElement);
    }

    /**
     * Add a radio button element
     *
     * @param string $name Input name
     * @param string $value Radio value
     * @param bool $checked Whether the radio is checked
     * @param array $attributes Additional HTML attributes
     * @param bool $encrypt Whether to encrypt the value
     * @return InputRadio
     */
    public function radio($name, $value, $checked = false, array $attributes = [], $encrypt = false)
    {
        $value = $this->encryptValue($value, $encrypt);
        $value = $this->escape ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
        if ($checked) {
            $attributes['checked'] = 'checked';
        }
        $attributes['value'] = $value;
        $this->addElement('radio', $name, $attributes);
        return new InputRadio($this, $this->currentElement);
    }

    /**
     * Add a hidden input element
     *
     * @param string $name Input name
     * @param string $value Hidden value
     * @param array $attributes Additional HTML attributes
     * @param bool $encrypt Whether to encrypt the value
     * @return InputHidden
     */
    public function hidden($name, $value, array $attributes = [], $encrypt = false)
    {
        $value = $this->encryptValue($value, $encrypt);
        $value = $this->escape ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
        $attributes['value'] = $value;
        $this->addElement('hidden', $name, $attributes);
        return new InputHidden($this, $this->currentElement);
    }

    /**
     * Add a submit button to the form
     *
     * Creates an input element with type="submit" and the specified text/value.
     * The button can be customized with additional HTML attributes.
     *
     * @param string $name The button text/value to be displayed
     * @param array $attributes Additional HTML attributes for the submit button (e.g., ['class' => 'btn btn-primary'])
     * @return InputSubmit Returns an InputSubmit instance for method chaining
     */
    public function submit($name, array $attributes = [])
    {
        $value = $this->escape ? htmlspecialchars($name, ENT_QUOTES, 'UTF-8') : $name;
        $attributes['value'] = $value;
        $this->addElement('submit', null, $attributes);
        return new InputSubmit($this, $this->currentElement);
    }

    /**
     * Add validation rules for the current element
     *
     * Assigns validation rules to the current form element identified by its name.
     * These rules can be used for client-side and server-side validation.
     *
     * @param array $rules An array of validation rules (e.g., ['required', 'email', 'min:5'])
     *                     Each rule can be a simple string or a string with parameters (rule:param)
     * @return $this Returns the current instance for method chaining
     * @throws \Exception If called when no current element exists or the element has no name
     */
    public function validate(array $rules)
    {
        if ($this->currentElement && $this->currentElement['name']) {
            $this->validationRules[$this->currentElement['name']] = $rules;
        }
        return $this;
    }

    /**
     * Generate secure hidden fields for automatic form processing
     *
     * Creates encrypted hidden fields that securely link a form to a backend function.
     * This method generates random encryption keys and encrypts the function name,
     * action URL, and HTTP method to prevent tampering.
     *
     * @param string $action The form action URL to be encrypted
     * @param string $method The HTTP method (GET, POST, PUT, DELETE, etc.) to be used
     * @param string $functionName The backend function name to be called when form is submitted
     * @param object $caller The object instance that called this method (used to determine output format)
     * @return string|void Returns HTML for hidden fields if called externally, or void if called internally
     * @throws \Exception If an invalid HTTP method is provided
     */
    public function formFunction($action,$method,$functionName,$caller) {
        $method = strtoupper($method);
        if (!in_array(strtoupper($method), ['GET', 'POST']) && $functionName == '') {
            throw new \Exception('Invalid form method. Use GET or POST.');
        } else if ($functionName != '') {
            if (!in_array(strtoupper($method), ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'])) {
                throw new \Exception('Invalid automatic form method !');
            }
            $overWriteMethod = $method;
            $method = "POST";
        }
        // Generate encryption key
        $randomEncKeyPerForm = base64_encode(random_bytes(32));
        $randomEncKeyPerFormPublic = $this->dotApp->encrypt($randomEncKeyPerForm);
        $encryptedFnName = $this->dotApp->encrypt($functionName, $randomEncKeyPerForm);
        $encryptedAction = $this->dotApp->encrypt($action, $randomEncKeyPerForm);
        $encryptedMethod = !empty($overWriteMethod) ? $this->dotApp->encrypt($overWriteMethod, $randomEncKeyPerForm) : '';

        if ($caller === $this) {
            // Hidden fields
            $this->hidden('dotapp-secure-auto-fnname', $encryptedFnName, [], false);
            $this->hidden('dotapp-secure-auto-fnname-action', $encryptedAction, [], false);
            $this->hidden('dotapp-secure-auto-fnname-method', $encryptedMethod, [], false);
            $this->hidden('dotapp-secure-auto-fnname-public', $randomEncKeyPerFormPublic, [], false);
        } else {
            return '
                <input type="hidden" name="dotapp-secure-auto-fnname" value="'.$encryptedFnName.'">
                <input type="hidden" name="dotapp-secure-auto-fnname-action" value="'.$encryptedAction.'">
                <input type="hidden" name="dotapp-secure-auto-fnname-method" value="'.$encryptedMethod.'">
                <input type="hidden" name="dotapp-secure-auto-fnname-public" value="'.$randomEncKeyPerFormPublic.'">
            ';
        }
    }

    /**
     * Start a form with automatic hidden fields for secure backend processing
     *
     * Creates a form with the specified action, method, attributes, and optional fnName
     * for linking to backend processing. Automatically adds encrypted hidden fields
     * for secure form validation and processing.
     *
     * @param string $action Form action URL
     * @param string $method Form method (POST, GET, PUT, DELETE, PATCH, OPTIONS, HEAD)
     * @param array|string $attributes Additional HTML attributes or fnName (if string)
     * @param string $fnName Function name for backend processing (optional)
     * @return $this
     * @throws \Exception If parameters are invalid
     */
    public function form($action, $method = 'POST', $attributes = [], $fnName = '') {
        $method = strtoupper($method);

        // Handle attributes and fnName based on third parameter type
        $formAttributes = [];
        $functionName = '';
        if (is_string($attributes) && empty($fnName)) {
            // Third parameter is fnName, no attributes
            $functionName = $attributes;
        } elseif (is_array($attributes)) {
            // Third parameter is attributes, fourth is fnName
            $formAttributes = $attributes;
            $functionName = $fnName;
        } else {
            throw new \Exception('Third parameter must be an array of attributes or a string fnName.');
        }

        // Set form attributes
        $formAttributes['action'] = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
        $formAttributes['method'] = $method;
        if (isset($formAttributes['data-ajax']) && $formAttributes['data-ajax']) {
            $formAttributes['class'] = isset($formAttributes['class']) ? $formAttributes['class'] . ' ajax-form' : 'ajax-form';
        }

        // Add form element
        $this->elements[] = [
            'type' => 'form',
            'name' => null,
            'attributes' => $formAttributes,
            'options' => [],
            'value' => null
        ];

        // Add hidden fields if fnName is specified
        if (!empty($functionName)) {
            $this->formFunction($action,$method,$functionName,$this);
        }

        return $this;
    }

    /**
     * Close the form
     *
     * @return $this
     */
    public function endForm()
    {
        $this->elements[] = [
            'type' => 'form_end',
            'name' => null,
            'attributes' => [],
            'options' => [],
            'value' => null
        ];
        return $this;
    }

    /**
     * Render the current element as HTML
     *
     * @return string
     */
    public function render()
    {
        if (!$this->currentElement) {
            return '';
        }
        return $this->renderElement($this->currentElement);
    }

    /**
     * Render all form elements as HTML
     *
     * @return string
     */
    public function renderAll()
    {
        $html = '';
        foreach ($this->elements as $element) {
            $html .= $this->renderElement($element) . "\n";
        }
        return $html;
    }

    /**
     * Get validation rules
     *
     * @return array
     */
    public function getValidationRules()
    {
        return $this->validationRules;
    }

    /**
     * Add a generic element
     *
     * @param string $type Element type
     * @param string|null $name Element name
     * @param array $attributes HTML attributes
     * @param string|null $value Element value
     */
    private function addElement($type, $name, array $attributes = [], $value = null)
    {
        $this->currentElement = [
            'type' => $type,
            'name' => $name,
            'attributes' => $attributes,
            'options' => [],
            'value' => $value
        ];
        $this->elements[] = $this->currentElement;
    }

    /**
     * Render a single element
     *
     * @param array $element Element data
     * @return string
     */
    private function renderElement(array $element)
    {
        switch ($element['type']) {
            case 'form':
                return '<form ' . $this->renderAttributes($element['attributes']) . '>';
            case 'form_end':
                return '</form>';
            case 'select':
                $html = '<select name="' . htmlspecialchars($element['name'], ENT_QUOTES, 'UTF-8') . '" ' . $this->renderAttributes($element['attributes']) . '>';
                foreach ($element['options'] as $option) {
                    $html .= '<option value="' . htmlspecialchars($option['value'], ENT_QUOTES, 'UTF-8') . '" ' . $this->renderAttributes($option['attributes']) . '>' . $option['text'] . '</option>';
                }
                $html .= '</select>';
                return $html;
            case 'text':
            case 'hidden':
            case 'submit':
                $type = $element['type'] === 'text' ? 'text' : $element['type'];
                return '<input type="' . $type . '" name="' . htmlspecialchars($element['name'] ?? '', ENT_QUOTES, 'UTF-8') . '" ' . $this->renderAttributes($element['attributes']) . '>';
            case 'checkbox':
            case 'radio':
                return '<input type="' . $element['type'] . '" name="' . htmlspecialchars($element['name'], ENT_QUOTES, 'UTF-8') . '" ' . $this->renderAttributes($element['attributes']) . '>';
            case 'textarea':
                return '<textarea name="' . htmlspecialchars($element['name'], ENT_QUOTES, 'UTF-8') . '" ' . $this->renderAttributes($element['attributes']) . '>' . ($element['value'] ?? '') . '</textarea>';
            default:
                return '';
        }
    }

    /**
     * Render HTML attributes
     *
     * @param array $attributes Attributes array
     * @return string
     */
    private function renderAttributes(array $attributes)
    {
        $attrs = [];
        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($value === true) {
                $attrs[] = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            } else {
                $attrs[] = htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
            }
        }
        return implode(' ', $attrs);
    }

    /**
     * Encrypt a value using DotApp's encrypt method
     *
     * @param mixed $value Value to encrypt
     * @param bool $encrypt Whether to encrypt
     * @return mixed
     */
    public function encryptValue($value, $encrypt)
    {
        if (!$encrypt || $value === null) {
            return $value;
        }

        if ($this->dotapp && method_exists($this->dotapp, 'encrypt')) {
            return $this->dotapp->encrypt($value);
        }

        throw new \Exception('DotApp::encrypt method is not available.');
    }

    /**
     * Debug info
     *
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'publicData' => 'Input for DotApp Framework'
        ];
    }
}

/**
 * CLASS InputSelect
 *
 * Builder class for select elements within Input.
 *
 * @method InputSelect select(string $name, array $attributes = [])
 * @method InputText text(string $name, ?string $value = null, array $attributes = [], bool $encrypt = false)
 * @method InputTextarea textarea(string $name, ?string $value = null, array $attributes = [])
 * @method InputCheckbox checkbox(string $name, string $value, bool $checked = false, array $attributes = [], bool $encrypt = false)
 * @method InputRadio radio(string $name, string $value, bool $checked = false, array $attributes = [], bool $encrypt = false)
 * @method InputHidden hidden(string $name, string $value, array $attributes = [], bool $encrypt = true)
 * @method InputSubmit submit(string $value, array $attributes = [])
 * @method Input form(string $action, string $method = 'POST', array|string $attributes = [], string $fnName = '')
 * @method Input endForm()
 * @method Input validate(array $rules)
 * @method Input disableEscape()
 * @method Input enableEscape()
 */
class InputSelect
{
    private $input;
    private $selectElement;

    /**
     * Constructor
     *
     * @param Input $input Parent Input instance
     * @param array $selectElement The select element being built
     */
    public function __construct(Input $input, array &$selectElement)
    {
        $this->input = $input;
        $this->selectElement = &$selectElement;
    }

    /**
     * Add options to the select element from a data array
     *
     * @param array $data Array of items
     * @param array $fields Fields to use for value and text [value_field, text_field]
     * @param bool $encrypt Whether to encrypt option values
     * @param array $attributes Additional HTML attributes for options
     * @return $this
     */
    public function add(array $data, array $fields, array $attributes = [], $encrypt = false)
    {
        if (count($fields) !== 2) {
            throw new \Exception('Fields array must contain exactly two elements: [value_field, text_field].');
        }

        $valueField = $fields[0];
        $textField = $fields[1];
        $options = [];

        foreach ($data as $item) {
            $value = is_object($item) ? ($item->{$valueField} ?? null) : ($item[$valueField] ?? null);
            $text = is_object($item) ? ($item->{$textField} ?? null) : ($item[$textField] ?? null);
            if ($value !== null && $text !== null) {
                $options[] = [
                    'value' => $this->input->encryptValue($value, $encrypt),
                    'text' => $this->input->escape ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8') : $text,
                    'attributes' => $attributes
                ];
            }
        }

        $this->selectElement['options'] = array_merge($this->selectElement['options'], $options);
        return $this;
    }

    /**
     * Add an option to the select element
     *
     * @param string $value Option value
     * @param string $text Display text
     * @param array $attributes Additional HTML attributes
     * @param bool $encrypt Whether to encrypt the value
     * @return $this
     */
    public function option($value, $text, array $attributes = [], $encrypt = false)
    {
        $value = $this->input->encryptValue($value, $encrypt);
        $text = $this->input->escape ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8') : $text;
        $this->selectElement['options'][] = [
            'value' => $value,
            'text' => $text,
            'attributes' => $attributes
        ];
        return $this;
    }

    /**
     * Return to the parent Input (optional)
     *
     * @return Input
     */
    public function end()
    {
        return $this->input;
    }

    /**
     * Render the current select element as HTML
     *
     * @return string
     */
    public function render()
    {
        return $this->input->renderElement($this->selectElement);
    }

    /**
     * Render all form elements as HTML
     *
     * @return string
     */
    public function renderAll()
    {
        return $this->input->renderAll();
    }

    /**
     * Magic method to handle undefined methods by delegating to the parent Input
     *
     * @param string $name Method name
     * @param array $arguments Method arguments
     * @return mixed
     * @throws \Exception If the method does not exist in Input
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->input, $name)) {
            return call_user_func_array([$this->input, $name], $arguments);
        }
        throw new \Exception("Method '$name' does not exist in " . get_class($this) . " or Input.");
    }
}

/**
 * CLASS InputText
 *
 * Builder class for text input elements within Input.
 *
 * @method InputSelect select(string $name, array $attributes = [])
 * @method InputText text(string $name, ?string $value = null, array $attributes = [], bool $encrypt = false)
 * @method InputTextarea textarea(string $name, ?string $value = null, array $attributes = [])
 * @method InputCheckbox checkbox(string $name, string $value, bool $checked = false, array $attributes = [], bool $encrypt = false)
 * @method InputRadio radio(string $name, string $value, bool $checked = false, array $attributes = [], bool $encrypt = false)
 * @method InputHidden hidden(string $name, string $value, array $attributes = [], bool $encrypt = true)
 * @method InputSubmit submit(string $value, array $attributes = [])
 * @method Input form(string $action, string $method = 'POST', array|string $attributes = [], string $fnName = '')
 * @method Input endForm()
 * @method Input validate(array $rules)
 * @method Input disableEscape()
 * @method Input enableEscape()
 */
class InputText
{
    private $input;
    private $element;

    /**
     * Constructor
     *
     * @param Input $input Parent Input instance
     * @param array $element The text element being built
     */
    public function __construct(Input $input, array &$element)
    {
        $this->input = $input;
        $this->element = &$element;
    }

    /**
     * Return to the parent Input (optional)
     *
     * @return Input
     */
    public function end()
    {
        return $this->input;
    }

    /**
     * Render the current text input element as HTML
     *
     * @return string
     */
    public function render()
    {
        return $this->input->renderElement($this->element);
    }

    /**
     * Render all form elements as HTML
     *
     * @return string
     */
    public function renderAll()
    {
        return $this->input->renderAll();
    }

    /**
     * Magic method to handle undefined methods by delegating to the parent Input
     *
     * @param string $name Method name
     * @param array $arguments Method arguments
     * @return mixed
     * @throws \Exception If the method does not exist in Input
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->input, $name)) {
            return call_user_func_array([$this->input, $name], $arguments);
        }
        throw new \Exception("Method '$name' does not exist in " . get_class($this) . " or Input.");
    }
}

/**
 * CLASS InputTextarea
 *
 * Builder class for textarea elements within Input.
 *
 * @method InputSelect select(string $name, array $attributes = [])
 * @method InputText text(string $name, ?string $value = null, array $attributes = [], bool $encrypt = false)
 * @method InputTextarea textarea(string $name, ?string $value = null, array $attributes = [])
 * @method InputCheckbox checkbox(string $name, string $value, bool $checked = false, array $attributes = [], bool $encrypt = false)
 * @method InputRadio radio(string $name, string $value, bool $checked = false, array $attributes = [], bool $encrypt = false)
 * @method InputHidden hidden(string $name, string $value, array $attributes = [], bool $encrypt = true)
 * @method InputSubmit submit(string $value, array $attributes = [])
 * @method Input form(string $action, string $method = 'POST', array|string $attributes = [], string $fnName = '')
 * @method Input endForm()
 * @method Input validate(array $rules)
 * @method Input disableEscape()
 * @method Input enableEscape()
 */
class InputTextarea
{
    private $input;
    private $element;

    /**
     * Constructor
     *
     * @param Input $input Parent Input instance
     * @param array $element The textarea element being built
     */
    public function __construct(Input $input, array &$element)
    {
        $this->input = $input;
        $this->element = &$element;
    }

    /**
     * Return to the parent Input (optional)
     *
     * @return Input
     */
    public function end()
    {
        return $this->input;
    }

    /**
     * Render the current textarea element as HTML
     *
     * @return string
     */
    public function render()
    {
        return $this->input->renderElement($this->element);
    }

    /**
     * Render all form elements as HTML
     *
     * @return string
     */
    public function renderAll()
    {
        return $this->input->renderAll();
    }

    /**
     * Magic method to handle undefined methods by delegating to the parent Input
     *
     * @param string $name Method name
     * @param array $arguments Method arguments
     * @return mixed
     * @throws \Exception If the method does not exist in Input
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->input, $name)) {
            return call_user_func_array([$this->input, $name], $arguments);
        }
        throw new \Exception("Method '$name' does not exist in " . get_class($this) . " or Input.");
    }
}

/**
 * CLASS InputCheckbox
 *
 * Builder class for checkbox elements within Input.
 *
 * @method InputSelect select(string $name, array $attributes = [])
 * @method InputText text(string $name, ?string $value = null, array $attributes = [], bool $encrypt = false)
 * @method InputTextarea textarea(string $name, ?string $value = null, array $attributes = [])
 * @method InputCheckbox checkbox(string $name, string $value, bool $checked = false, array $attributes = [], bool $encrypt = false)
 * @method InputRadio radio(string $name, string $value, bool $checked = false, array $attributes = [], bool $encrypt = false)
 * @method InputHidden hidden(string $name, string $value, array $attributes = [], bool $encrypt = true)
 * @method InputSubmit submit(string $value, array $attributes = [])
 * @method Input form(string $action, string $method = 'POST', array|string $attributes = [], string $fnName = '')
 * @method Input endForm()
 * @method Input validate(array $rules)
 * @method Input disableEscape()
 * @method Input enableEscape()
 */
class InputCheckbox
{
    private $input;
    private $element;

    /**
     * Constructor
     *
     * @param Input $input Parent Input instance
     * @param array $element The checkbox element being built
     */
    public function __construct(Input $input, array &$element)
    {
        $this->input = $input;
        $this->element = &$element;
    }

    /**
     * Return to the parent Input (optional)
     *
     * @return Input
     */
    public function end()
    {
        return $this->input;
    }

    /**
     * Render the current checkbox element as HTML
     *
     * @return string
     */
    public function render()
    {
        return $this->input->renderElement($this->element);
    }

    /**
     * Render all form elements as HTML
     *
     * @return string
     */
    public function renderAll()
    {
        return $this->input->renderAll();
    }

    /**
     * Magic method to handle undefined methods by delegating to the parent Input
     *
     * @param string $name Method name
     * @param array $arguments Method arguments
     * @return mixed
     * @throws \Exception If the method does not exist in Input
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->input, $name)) {
            return call_user_func_array([$this->input, $name], $arguments);
        }
        throw new \Exception("Method '$name' does not exist in " . get_class($this) . " or Input.");
    }
}

/**
 * CLASS InputRadio
 *
 * Builder class for radio elements within Input.
 *
 * @method InputSelect select(string $name, array $attributes = [])
 * @method InputText text(string $name, ?string $value = null, array $attributes = [], bool $encrypt = false)
 * @method InputTextarea textarea(string $name, ?string $value = null, array $attributes = [])
 * @method InputCheckbox checkbox(string $name, string $value, bool $checked = false, array $attributes = [], bool $encrypt = false)
 * @method InputRadio radio(string $name, string $value, bool $checked = false, array $attributes = [], bool $encrypt = false)
 * @method InputHidden hidden(string $name, string $value, array $attributes = [], bool $encrypt = true)
 * @method InputSubmit submit(string $value, array $attributes = [])
 * @method Input form(string $action, string $method = 'POST', array|string $attributes = [], string $fnName = '')
 * @method Input endForm()
 * @method Input validate(array $rules)
 * @method Input disableEscape()
 * @method Input enableEscape()
 */
class InputRadio
{
    private $input;
    private $element;

    /**
     * Constructor
     *
     * @param Input $input Parent Input instance
     * @param array $element The radio element being built
     */
    public function __construct(Input $input, array &$element)
    {
        $this->input = $input;
        $this->element = &$element;
    }

    /**
     * Return to the parent Input (optional)
     *
     * @return Input
     */
    public function end()
    {
        return $this->input;
    }

    /**
     * Render the current radio element as HTML
     *
     * @return string
     */
    public function render()
    {
        return $this->input->renderElement($this->element);
    }

    /**
     * Render all form elements as HTML
     *
     * @return string
     */
    public function renderAll()
    {
        return $this->input->renderAll();
    }

    /**
     * Magic method to handle undefined methods by delegating to the parent Input
     *
     * @param string $name Method name
     * @param array $arguments Method arguments
     * @return mixed
     * @throws \Exception If the method does not exist in Input
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->input, $name)) {
            return call_user_func_array([$this->input, $name], $arguments);
        }
        throw new \Exception("Method '$name' does not exist in " . get_class($this) . " or Input.");
    }
}

/**
 * CLASS InputHidden
 *
 * Builder class for hidden input elements within Input.
 *
 * @method InputSelect select(string $name, array $attributes = [])
 * @method InputText text(string $name, ?string $value = null, array $attributes = [], bool $encrypt = false)
 * @method InputTextarea textarea(string $name, ?string $value = null, array $attributes = [])
 * @method InputCheckbox checkbox(string $name, string $value, bool $checked = false, array $attributes = [], bool $encrypt = false)
 * @method InputRadio radio(string $name, string $value, bool $checked = false, array $attributes = [], bool $encrypt = false)
 * @method InputHidden hidden(string $name, string $value, array $attributes = [], bool $encrypt = true)
 * @method InputSubmit submit(string $value, array $attributes = [])
 * @method Input form(string $action, string $method = 'POST', array|string $attributes = [], string $fnName = '')
 * @method Input endForm()
 * @method Input validate(array $rules)
 * @method Input disableEscape()
 * @method Input enableEscape()
 */
class InputHidden
{
    private $input;
    private $element;

    /**
     * Constructor
     *
     * @param Input $input Parent Input instance
     * @param array $element The hidden element being built
     */
    public function __construct(Input $input, array &$element)
    {
        $this->input = $input;
        $this->element = &$element;
    }

    /**
     * Return to the parent Input (optional)
     *
     * @return Input
     */
    public function end()
    {
        return $this->input;
    }

    /**
     * Render the current hidden input element as HTML
     *
     * @return string
     */
    public function render()
    {
        return $this->input->renderElement($this->element);
    }

    /**
     * Render all form elements as HTML
     *
     * @return string
     */
    public function renderAll()
    {
        return $this->input->renderAll();
    }

    /**
     * Magic method to handle undefined methods by delegating to the parent Input
     *
     * @param string $name Method name
     * @param array $arguments Method arguments
     * @return mixed
     * @throws \Exception If the method does not exist in Input
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->input, $name)) {
            return call_user_func_array([$this->input, $name], $arguments);
        }
        throw new \Exception("Method '$name' does not exist in " . get_class($this) . " or Input.");
    }
}

/**
 * CLASS InputSubmit
 *
 * Builder class for submit button elements within Input.
 *
 * @method InputSelect select(string $name, array $attributes = [])
 * @method InputText text(string $name, ?string $value = null, array $attributes = [], bool $encrypt = false)
 * @method InputTextarea textarea(string $name, ?string $value = null, array $attributes = [])
 * @method InputCheckbox checkbox(string $name, string $value, bool $checked = false, array $attributes = [], bool $encrypt = false)
 * @method InputRadio radio(string $name, string $value, bool $checked = false, array $attributes = [], bool $encrypt = false)
 * @method InputHidden hidden(string $name, string $value, array $attributes = [], bool $encrypt = true)
 * @method InputSubmit submit(string $value, array $attributes = [])
 * @method Input form(string $action, string $method = 'POST', array|string $attributes = [], string $fnName = '')
 * @method Input endForm()
 * @method Input validate(array $rules)
 * @method Input disableEscape()
 * @method Input enableEscape()
 */
class InputSubmit
{
    private $input;
    private $element;

    /**
     * Constructor
     *
     * @param Input $input Parent Input instance
     * @param array $element The submit element being built
     */
    public function __construct(Input $input, array &$element)
    {
        $this->input = $input;
        $this->element = &$element;
    }

    /**
     * Return to the parent Input (optional)
     *
     * @return Input
     */
    public function end()
    {
        return $this->input;
    }

    /**
     * Render the current submit button element as HTML
     *
     * @return string
     */
    public function render()
    {
        return $this->input->renderElement($this->element);
    }

    /**
     * Render all form elements as HTML
     *
     * @return string
     */
    public function renderAll()
    {
        return $this->input->renderAll();
    }

    /**
     * Magic method to handle undefined methods by delegating to the parent Input
     *
     * @param string $name Method name
     * @param array $arguments Method arguments
     * @return mixed
     * @throws \Exception If the method does not exist in Input
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->input, $name)) {
            return call_user_func_array([$this->input, $name], $arguments);
        }
        throw new \Exception("Method '$name' does not exist in " . get_class($this) . " or Input.");
    }
}

?>