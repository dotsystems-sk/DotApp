<?php

/**
 * Class RENDERER
 * 
 * This class is responsible for rendering the final HTML output in the DotApp framework. 
 * It acts as the template system, processing template files and dynamically generating 
 * the corresponding HTML code based on provided data. 
 * 
 * The renderer class provides functionality for managing layouts, partials, and other components
 * necessary for building a robust templating system within the application. You can also create custom renderers.
 * 
 * 
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   2.0 FREE
 * @license   MIT License
 * @date      2014 - 2026
 * 
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the 
 * following condition: You **must** retain this header in all copies or 
 * substantial portions of the code, including the author and company information.
 */

/*
    Renderer Class Usage:

    Check documentation on https://dotsystems.sk/
*/


namespace Dotsystems\App\Parts;

use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\Input;

/**
 * Thrown when a template contains a known malformed, mismatched, or unclosed
 * control directive. Messages include the directive name and template line only.
 * Template data (variable values, source snippets) is never included.
 */
class RendererSyntaxException extends \Exception
{
    /** @var string */
    private $directive;

    /** @var int */
    private $templateLine;

    /**
     * @param string $directive Directive name such as "foreach" or "/if"
     * @param int    $templateLine 1-based line in the template source
     * @param string $message Human-readable error without template data
     */
    public function __construct($directive, $templateLine, $message)
    {
        $this->directive = (string) $directive;
        $this->templateLine = (int) $templateLine;
        parent::__construct($message);
    }

    /**
     * @return string
     */
    public function getDirective()
    {
        return $this->directive;
    }

    /**
     * @return int
     */
    public function getTemplateLine()
    {
        return $this->templateLine;
    }
}

class Renderer
{
    private static $instancie = array();
    /*
		*
		* @dotapp - Vybrany layout
		*
	*/
    private $dotapp;
    private $dotApp;
    private $DotApp;
    /*
		*
		* @layout - Vybrany layout
		*
	*/
    private $layout = "";
    /*
		*
		* @view - Vybrany VIEW
		*
	*/
    private $view = "";
    /*
		*
		* @viewData - UDAJE, KTORE CHCEM SPRISTUPNIT DO VIEW a LAYOUT
		* Data mozem spristupnit aj priamo vo VIEW. Nemusim ich predat predtym. Ak vobec nejake chcem.
	*/
    private $viewData;
    /*
		*
		* @viewVars - Niekedy mozem potrebovat nie jedno pole s udajmi, ale aby mi do view ci sablony sla premenna s presnym nazvom.
		* Pomocou setViewVar a getViewVar ( alebo getViewVars pre vratenie vsetkych premennych ) je to mozne spravit.
		* Tato premenna sa pouziva na ukladanie udajov.
	*/
    public $viewVars;
    /*
		*
		* @layoutVars - Obdoba ako viewData ale plati pre layouty. 
		Pri renderingu layoutu sa uplatnuju tieto premenne. Pozor, pri renderingu VIEW aj ked je nasledne vlozeny layout ako content,
		uplatnuju sa premenne pre view !!! A teda $viewData
	*/
    public $layoutVars;

    /*
     * @viewFallbacks - Pole s fallback view pre každý view
     */
    private $viewFallbacks = [];

    /*
     * @layoutFallbacks - Pole s fallback layout pre každý layout
     */
    private $layoutFallbacks = [];

    /*
		*
		* @renderedCode - Kod, ktory upravujeme pocas renderingu...
		*
	*/

    private $renderedCode;
    /*
		*
		* @useCache - Pouzijeme cache ? + objekt cache
		*
	*/
    private $useCache = false;
    /*
		*
		* @useCssCache - Pouzijeme cache pre CSS ? + objekt cache
		*
	*/
    private $useCssCache = false;
    private $renderedCssFiles = array();
    /*
		*
		* @renderedCssFiles - Pole so zoznamom vyrenderovanych a minimalizovanych CSS suborov pripojenych do aktualnej sablony CSS
		*
	*/
    private $removeUnusedCss = false;
    /*
		*
		* @removeUnusedCss - Odstranime nepouzite triedy v CSS subore...
		*
	*/

    private $cache;

    /*
		*
		* @dirl - Potrebujeme vediet v ktorom priecinku su layouty.
		*
	*/
    private $dirl;

    /*
		*
		* @dirw - Potrebujeme vediet v ktorom priecinku su views.
		*
	*/
    private $dirw;

    /**
     * Request-global value filters for {{ var: $x | filter }} pipelines.
     * Shared across Renderer instances, same as addRenderer/addBlock.
     * Built-ins are seeded once; addFilter() adds or replaces names.
     *
     * @var array<string, callable>
     */
    private static $valueFilters = array();

    /**
     * @var bool
     */
    private static $builtinFiltersReady = false;

    function __construct($dotapp = null, $name = false)
    {
        $this->dotapp = DotApp::dotApp();
        $this->module("");
        $this->dotApp = DotApp::dotApp();
        $this->DotApp = DotApp::dotApp();
        $this->blocks_renderer(1);
        $this->registerBuiltinFilters();
        if (is_string($name) && !isset(self::$instancie[$name])) self::$instancie[$name] = $this;
    }

    public function __debugInfo()
    {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }

    public static function new($name = false)
    {
        if ($name === false) return new self();
        if (is_string($name)) {
            if (isset(self::$instancie[$name])) {
                return self::$instancie[$name];
            }
            return new self(null, $name);
        }
        return new self();
    }

    public static function add($name, $renderer)
    {
        return DotApp::DotApp()->customRenderer->addRenderer($name, $renderer);
    }

    public function addRenderer($name, $renderer)
    {
        return DotApp::DotApp()->customRenderer->addRenderer($name, $renderer);
    }

    public function add_renderer($name, $renderer)
    {
        return DotApp::DotApp()->customRenderer->addRenderer($name, $renderer);
    }

    public function getRenderer($name)
    {
        return DotApp::DotApp()->customRenderer->getRenderer($name);
    }

    public function get_renderer($name)
    {
        return DotApp::DotApp()->customRenderer->getRenderer($name);
    }

    public function renderWith($name, $code)
    {
        return DotApp::DotApp()->customRenderer->renderWith($name, $code);
    }

    public function render_with($name, $code)
    {
        return $this->renderWith($name, $code);
    }

    /**
     * Registers custom logic for a standard block tag.
     * Acts as a "user-friendly" version of custom renderers.
     * * Example Usage:
     * HTML: {{ block:alert(danger) }} Warning message! {{ /block:alert }}
     * PHP:  $dotapp->renderer->addBlock("alert", function($content, $params) {
     * return "<div class='alert-{$params[0]}'>{$content}</div>";
     * });
     */
    public function addBlock($name, $blockFn)
    {
        DotApp::DotApp()->customRenderer->addBlock($name, $blockFn);
    }

    public function add_block($name, $blockFn)
    {
        DotApp::DotApp()->customRenderer->addBlock($name, $blockFn);
    }

    /**
     * Registers a value filter used by {{ var: $x | name }} pipelines.
     * Complementary to addRenderer(): filters transform a single value at
     * echo-time; renderers rewrite whole template documents earlier in the pipeline.
     *
     * Names must match [A-Za-z_][A-Za-z0-9_]* and are stored in a case-sensitive
     * request-global allowlist shared by every Renderer instance. Duplicate names
     * replace the previous callable. The callable receives the current value as
     * the first argument, then any parsed filter arguments. It must not be
     * invoked via eval() or by interpolating the filter name into generated PHP
     * as a function call.
     *
     * @param string   $name     Filter name as used after the pipe
     * @param callable $filterFn function ($value, ...$args)
     * @return $this
     */
    public function addFilter($name, $filterFn)
    {
        return $this->add_filter($name, $filterFn);
    }

    /**
     * Snake_case alias of addFilter().
     *
     * @param string   $name
     * @param callable $filterFn
     * @return $this
     */
    public function add_filter($name, $filterFn)
    {
        if (!is_string($name) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('Filter name must be a PHP identifier.');
        }
        if (!is_callable($filterFn)) {
            throw new \InvalidArgumentException('Filter must be callable.');
        }
        self::$valueFilters[$name] = $filterFn;
        return $this;
    }

    /**
     * Returns a registered filter callable, or false when unknown.
     *
     * @param string $name
     * @return callable|false
     */
    public function getFilter($name)
    {
        return $this->get_filter($name);
    }

    /**
     * Snake_case alias of getFilter().
     *
     * @param string $name
     * @return callable|false
     */
    public function get_filter($name)
    {
        if (!is_string($name) || !isset(self::$valueFilters[$name])) {
            return false;
        }
        return self::$valueFilters[$name];
    }

    /**
     * Seeds the built-in value filters once per request. Later Renderer
     * instances share this table; addFilter() on one instance is visible to others.
     *
     * @return void
     */
    private function registerBuiltinFilters()
    {
        if (self::$builtinFiltersReady) {
            return;
        }
        self::$builtinFiltersReady = true;
        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        self::$valueFilters['escape'] = function ($value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        self::$valueFilters['default'] = function ($value, $fallback = '') {
            if ($value === null || $value === false || $value === '') {
                return $fallback;
            }
            return $value;
        };
        self::$valueFilters['number'] = function ($value, $decimals = 0) {
            return number_format((float) $value, (int) $decimals, '.', ',');
        };
        self::$valueFilters['date'] = function ($value, $format = 'Y-m-d H:i:s') {
            if ($value === null || $value === '') {
                return '';
            }
            if (!is_numeric($value)) {
                $parsed = strtotime((string) $value);
                if ($parsed === false) {
                    return '';
                }
                $value = $parsed;
            }
            return date((string) $format, (int) $value);
        };
        self::$valueFilters['join'] = function ($value, $separator = ',') {
            if (is_array($value)) {
                return implode((string) $separator, $value);
            }
            return (string) $value;
        };
        self::$valueFilters['json'] = function ($value) use ($jsonFlags) {
            $json = json_encode($value, $jsonFlags);
            return $json === false ? 'null' : $json;
        };
        self::$valueFilters['urlencode'] = function ($value) {
            return rawurlencode((string) $value);
        };
        self::$valueFilters['upper'] = function ($value) {
            if (function_exists('mb_strtoupper')) {
                return mb_strtoupper((string) $value, 'UTF-8');
            }
            return strtoupper((string) $value);
        };
        self::$valueFilters['lower'] = function ($value) {
            if (function_exists('mb_strtolower')) {
                return mb_strtolower((string) $value, 'UTF-8');
            }
            return strtolower((string) $value);
        };
    }

    public function custom_renderers()
    {
        return (DotApp::DotApp()->customRenderer->customRenderers());
    }

    public function customRenderers()
    {
        return (DotApp::DotApp()->customRenderer->customRenderers());
    }

    public function escapePHP($code)
    {
        if (empty($code) || !is_string($code)) {
            return '';
        }

        $protected = [];
        $counter = 0;

        $code = preg_replace_callback(
            '/<\?xml\s[^>]*\?>/i',
            function ($matches) use (&$protected, &$counter) {
                $key = '%%XML_' . $counter . '%%';
                $protected[$key] = $matches[0];
                $counter++;
                return $key;
            },
            $code
        );

        $code = preg_replace('/<\?php\b.*?\?>/is', '', $code);
        $code = preg_replace('/<\?=.*?\?>/is', '', $code);
        $code = preg_replace('/<\?\s+.*?\?>/is', '', $code);
        $code = preg_replace('/<\?(?!xml).*?\?>/is', '', $code);
        $code = preg_replace('/<script\s+[^>]*language\s*=\s*["\']?php["\']?[^>]*>.*?<\/script>/is', '', $code);
        $code = preg_replace('/<%.*?%>/is', '', $code);

        $code = str_replace(array_keys($protected), array_values($protected), $code);

        return $code;
    }

    public function blocksRenderer($activate = 0)
    {
        return $this->blocks_renderer($activate);
    }

    public function blocks_renderer($activate = 0)
    {
        /*
            Activates system-wide block processing via regex.
            Searches for: {{ block:name(args) }} content {{ /block:name }}
            
            Example:
            {{ block:gallery(vacation, 3) }} My summer photos {{ /block:gallery }}
            This will call the function registered for "gallery" and pass 
            "My summer photos" as content and ['vacation', '3'] as parameters.
        */
        /*
			Block syntax:
			{{ block:block_name(var1,var2) }}Inner Content{{ /block:block_name }}
			{{ block:block_name }}Inner Content{{ /block:block_name }}

			-> call block_function($innerContent,$blockVariables - if defined,$variables - view variables);
		*/
        if ($activate == 0) {
        } else {

            $this->add_renderer("dotapp.block", function ($code, $variables = []) {
                return $this->processStandardBlocks($code, $variables);
            });
        }
        return ($this);
    }

    /*
		*
		* @module($name) - Ak potrebujeme pouzit MODUL a VIEW a template z modulu, tak musime zmenit priecinok...
		$name - nazov modulu teda priecinok s modulom
		*
	*/
    public function module($name)
    {
        if (strlen($name) > 1) {
            $this->dirl = __ROOTDIR__ . "/app/modules/" . $name . "/views/layouts/";
            $this->dirw = __ROOTDIR__ . "/app/modules/" . $name . "/views/";
        } else {
            $this->dirl = __ROOTDIR__ . "/app/parts/views/layouts/";
            $this->dirw = __ROOTDIR__ . "/app/parts/views/";
        }
        return $this;
    }

    public function removeUnusedCss($setting)
    {
        $this->removeUnusedCss = $setting;
        return $this;
    }

    public function useCache($setting)
    {
        $this->useCache = $setting;
        if ($setting) {
            if (! is_object($this->cache)) $this->cache = Cache::use();
        }
        return $this;
    }

    public function useCssCache($setting)
    {
        $this->useCssCache = $setting;
        if ($setting) {
            if (! is_object($this->cache)) $this->cache = Cache::use();
        }
        return $this;
    }

    public function setLayout($layout, $fallbackLayout = null)
    {
        $this->layout = $layout;
        if (!isset($this->layoutVars[$this->layout])) {
            $this->layoutVars[$this->layout] = array();
        }
        // Store fallback layout
        $this->layoutFallbacks[$this->layout] = $fallbackLayout;

        // Check for moduleName:layoutName syntax
        if (strpos($layout, ':') !== false) {
            list($module, $layoutPath) = explode(':', $layout, 2);
            $this->dirl = __ROOTDIR__ . "/app/modules/" . $module . "/views/layouts/";
            $this->layout = $layoutPath; // Store only the layout path
        } else {
            // Use default layouts directory or module set by module()
            $this->dirl = $this->dirl ?: __ROOTDIR__ . "/app/parts/views/layouts/";
        }

        return $this;
    }

    private function getLayout($layout)
    {
        $dir = $this->dirl ?: __ROOTDIR__ . "/app/parts/views/layouts/";

        if (strpos($layout, ':') !== false) {
            list($module, $layoutPath) = explode(':', $layout, 2);
            $dir = __ROOTDIR__ . "/app/modules/" . $module . "/views/layouts/";
            $layout = $layoutPath;
        }

        // Load layout if it exists
        if ($layout !== "" && file_exists($dir . $layout . ".layout.php")) {
            return file_get_contents($dir . $layout . ".layout.php");
        }

        // Log warning if primary layout doesn't exist
        if ($layout !== "") {
            $this->dotApp->logger->warning("Failed to load layout: " . $dir . $layout . ".layout.php", [
                'layout' => $layout,
                'directory' => $dir
            ]);
        }

        // Try fallback layout if defined
        if (isset($this->layoutFallbacks[$layout]) && $this->layoutFallbacks[$layout] !== null) {
            $fallbackLayout = $this->layoutFallbacks[$layout];
            $fallbackDir = $dir;

            if (strpos($fallbackLayout, ':') !== false) {
                list($fModule, $fPath) = explode(':', $fallbackLayout, 2);
                $fallbackDir = __ROOTDIR__ . "/app/modules/" . $fModule . "/views/layouts/";
                $fallbackLayout = $fPath;
            }

            if (file_exists($fallbackDir . $fallbackLayout . ".layout.php")) {
                return file_get_contents($fallbackDir . $fallbackLayout . ".layout.php");
            }

            $this->dotApp->logger->warning("Failed to load fallback layout: " . $fallbackDir . $fallbackLayout . ".layout.php", [
                'fallbackLayout' => $fallbackLayout,
                'directory' => $fallbackDir
            ]);
        }

        return "";
    }

    public function setLayoutVar($varname, $data)
    {
        $this->layoutVars[$this->layout][$varname] = $data;
        return $this;
    }

    public function getLayoutVar($varname)
    {
        if (isset($this->layoutVars[$this->layout][$varname])) {
            return $this->layoutVars[$this->layout][$varname];
        } else {
            return ("");
        }
    }

    public function getLayoutVars()
    {
        if (isset($this->layoutVars[$this->layout])) {
            return $this->layoutVars[$this->layout];
        } else {
            return (array());
        }
    }

    public function setView($view, $fallbackView = null)
    {
        $this->view = $view;
        if (!isset($this->viewVars[$this->view])) {
            $this->viewVars[$this->view] = array();
        }

        // Store fallback view
        $this->viewFallbacks[$this->view] = $fallbackView;

        // Check for moduleName:viewPath syntax
        if (strpos($view, ':') !== false) {
            list($module, $viewPath) = explode(':', $view, 2);
            $this->dirw = __ROOTDIR__ . "/app/modules/" . $module . "/views/";
            $this->view = $viewPath; // Store only the view path
        } else {
            // Use default views directory or module set by module()
            $this->dirw = $this->dirw ?? __ROOTDIR__ . "/app/parts/views/";
        }

        return $this;
    }

    public function setViewVar($varname, $data)
    {
        $this->viewVars[$this->view][$varname] = $data;
        return $this;
    }

    public function getViewVar($varname)
    {
        if (isset($this->viewVars[$this->view][$varname])) {
            return $this->viewVars[$this->view][$varname];
        } else {
            return ("");
        }
    }

    public function getViewVars()
    {
        if (isset($this->viewVars[$this->view])) {
            return $this->viewVars[$this->view];
        } else {
            return (array());
        }
    }

    function minimizeHTML($html)
    {
        if (empty($html) || !is_string($html)) {
            return '';
        }

        $protected = [];
        $protectedCounter = 0;

        $protectedTags = ['script', 'style', 'pre', 'code', 'textarea'];

        foreach ($protectedTags as $tag) {
            $pattern = '/<' . $tag . '\b[^>]*>.*?<\/' . $tag . '>/is';
            $html = preg_replace_callback(
                $pattern,
                function ($matches) use (&$protected, &$protectedCounter) {
                    $key = '%%PROTECTED_' . $protectedCounter . '%%';
                    $protected[$key] = $matches[0];
                    $protectedCounter++;
                    return $key;
                },
                $html
            );
        }

        $html = preg_replace_callback(
            '/&[a-zA-Z0-9#]+;/',
            function ($matches) use (&$protected, &$protectedCounter) {
                $key = '%%ENTITY_' . $protectedCounter . '%%';
                $protected[$key] = $matches[0];
                $protectedCounter++;
                return $key;
            },
            $html
        );

        $html = preg_replace_callback(
            '/="[^"]*"|=\'[^\']*\'/',
            function ($matches) use (&$protected, &$protectedCounter) {
                $key = '%%ATTR_' . $protectedCounter . '%%';
                $protected[$key] = $matches[0];
                $protectedCounter++;
                return $key;
            },
            $html
        );

        $html = preg_replace('/<!--(?!\s*(?:\[if\s|<!|<!\[CDATA\[)).*?-->/s', '', $html);

        $html = preg_replace('/>\s+</', '><', $html);

        $html = preg_replace('/\s+/', ' ', $html);

        $html = preg_replace('/\s*<\s*/', '<', $html);
        $html = preg_replace('/\s*>\s*/', '>', $html);

        $html = preg_replace('/\s*=\s*/', '=', $html);

        $html = trim($html);

        $html = str_replace(array_keys($protected), array_values($protected), $html);

        return $html;
    }


    public function minimizeCSS($css)
    {
        if (is_array($css)) {
            $result = [];
            foreach ($css as $cssString) {
                if (!is_string($cssString) || empty($cssString)) {
                    $result[] = '';
                    continue;
                }
                $result[] = $this->minimizeSingleCSS($cssString);
            }
            return $result;
        }

        if (!is_string($css) || empty($css)) {
            return '';
        }

        return $this->minimizeSingleCSS($css);
    }

    private function minimizeSingleCSS($css)
    {
        $protected = [];
        $counter = 0;

        $css = preg_replace_callback(
            '/(["\'])(?:(?=(\\\\?))\2.)*?\1/',
            function ($matches) use (&$protected, &$counter) {
                $key = '%%STR_' . $counter . '%%';
                $protected[$key] = $matches[0];
                $counter++;
                return $key;
            },
            $css
        );

        $css = preg_replace_callback(
            '/url\s*\([^)]*\)/i',
            function ($matches) use (&$protected, &$counter) {
                $key = '%%URL_' . $counter . '%%';
                $protected[$key] = $matches[0];
                $counter++;
                return $key;
            },
            $css
        );

        $css = preg_replace('/\/\*.*?\*\//s', '', $css);
        $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);
        $css = preg_replace('/;\s*}/', '}', $css);
        $css = preg_replace('/\s*!\s*important/i', ' !important', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/^\s+|\s+$/', '', $css);
        $css = preg_replace('/;}/', '}', $css);
        $css = preg_replace('/0+(\d+)/', '$1', $css);
        $css = preg_replace('/(\d)\.0+(?=\D)/', '$1', $css);
        $css = preg_replace('/:0 0 0 0([;}])/', ':0$1', $css);
        $css = preg_replace('/:0 0 0([;}])/', ':0$1', $css);
        $css = preg_replace('/:0 0([;}])/', ':0$1', $css);
        $css = preg_replace('/([: ])0\./', '$1.', $css);

        $css = preg_replace_callback(
            '/rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/',
            function ($matches) {
                return sprintf('#%02x%02x%02x', $matches[1], $matches[2], $matches[3]);
            },
            $css
        );

        $css = preg_replace('/#([0-9a-f])\1([0-9a-f])\2([0-9a-f])\3/i', '#$1$2$3', $css);

        $css = str_replace(array_keys($protected), array_values($protected), $css);

        return $css;
    }

    public function minimizeJS($js)
    {
        if (empty($js) || !is_string($js)) {
            return '';
        }

        $protected = [];
        $counter = 0;

        $js = preg_replace_callback(
            '/(["\'])(?:(?=(\\\\?))\2.)*?\1/',
            function ($matches) use (&$protected, &$counter) {
                $key = '%%STR_' . $counter . '%%';
                $protected[$key] = $matches[0];
                $counter++;
                return $key;
            },
            $js
        );

        $js = preg_replace_callback(
            '/`(?:[^`\\\\]|\\\\.)*`/',
            function ($matches) use (&$protected, &$counter) {
                $key = '%%TEMP_' . $counter . '%%';
                $protected[$key] = $matches[0];
                $counter++;
                return $key;
            },
            $js
        );

        $js = preg_replace_callback(
            '/\/(?:[^\/\\\\\r\n]|\\\\.)+\/[gimuy]*/',
            function ($matches) use (&$protected, &$counter) {
                $key = '%%REGEX_' . $counter . '%%';
                $protected[$key] = $matches[0];
                $counter++;
                return $key;
            },
            $js
        );

        $js = preg_replace('/\/\*.*?\*\//s', '', $js);
        $js = preg_replace('/^\s*\/\/.*$/m', '', $js);
        $js = preg_replace('/([^:])\/\/.*$/m', '$1', $js);

        $js = preg_replace('/\s*([{}();,=+\-*\/%&|!<>?:])\s*/', '$1', $js);
        $js = preg_replace('/\s*([\[\]])\s*/', '$1', $js);
        $js = preg_replace('/;\s*}/', '}', $js);
        $js = preg_replace('/,\s*}/', '}', $js);
        $js = preg_replace('/,\s*\]/', ']', $js);
        $js = preg_replace('/\s+/', ' ', $js);
        $js = preg_replace('/^\s+|\s+$/', '', $js);
        $js = preg_replace('/;\s*;+/', ';', $js);

        $js = str_replace(array_keys($protected), array_values($protected), $js);

        return $js;
    }

    public function prepareCss($file, $path, $tagAt, $tagAfter)
    {
        $sourceCSS = $file;
        $filea = explode("/", $file);
        $filenamesource = end($filea);
        $filenamea = explode(".", $filenamesource);
        /*
			Nazov suboru BEZ pripony...
		*/
        $filename = $filenamea[0];
        $filesrcpath = str_replace($filenamesource, "", $file);

        $cachepath = $filesrcpath . "cache";
        if (! file_exists($cachepath)) {
            mkdir($cachepath, 0755);
        }

        $savefilename = $filename . "_cache_" . md5($this->layout) . ".css";

        $phpsavefullpathwithfilename = $cachepath . "/" . $savefilename;

        if ($this->useCssCache == false || ($this->useCssCache == true && (!file_exists($phpsavefullpathwithfilename)))) {
            if (file_exists($sourceCSS)) {
                $csscode = $this->concatCSS($sourceCSS);
                $csscode = $this->minimizeCSS($csscode);
                $this->renderedCssFiles[] = $phpsavefullpathwithfilename;
            } else $csscode = "/* SOURCE CSS FILE '" . $sourceCSS . "' NOT FOUND */";

            /*
				* Ulozime vygenerovany kod do CSS...
			*/
            file_put_contents($phpsavefullpathwithfilename, $csscode);
        }

        $path = str_replace("<filename>", "cache/" . $savefilename, $path);
        $tagAfter = str_replace("<filename>", "cache/" . $savefilename, $tagAfter);

        $vrat = '<link href="' . $path . '" ' . $tagAt . '>';
        $vrat .= $tagAfter;
        echo $vrat;
    }

    private function concatCSS($sourceCSSfile, $relativePath = '../')
    {
        /*
			@ $sourceCSSfile - Kompletna cesta k suboru CSS ktory budeme citat
		*/
        /*
			@ $relativePath - Relativna cesta ktorou nahradime ./ pripadne ktoru pridame k ../
			--> zakladna hodnota je ../ kedze cache sa zapisuje do priecinka cache, naspat sa dostaneme s ../
		*/
        // Osetrime oba vstupy proti dvojitym lomkam
        $relativePath = str_replace("//", "/", $relativePath);
        $relativePath = str_replace("//", "/", $relativePath);
        $relativePath = str_replace("././", "./", $relativePath);
        $relativePath = str_replace("././", "./", $relativePath);

        $sourceCSSfile = str_replace("//", "/", $sourceCSSfile);
        $sourceCSSfile = str_replace("//", "/", $sourceCSSfile);
        $sourceCSSfile = str_replace("././", "./", $sourceCSSfile);
        $relativePath = str_replace("././", "./", $relativePath);

        // Podelime si cestu k suboru na SUBOR a CESTU k nemu.		
        $sourceCSSfileA = explode("/", $sourceCSSfile);
        $sourceCSSfileName = end($sourceCSSfileA);
        $sourceCSSfilePath = str_replace($sourceCSSfileName, "", $sourceCSSfile);
        $csscode = file_get_contents($sourceCSSfile);
        $csscodeMem = $csscode;
        /*
			* RELATIVNE CESTY V CSS sa musia upravit. A to cesty uzavrete v " " aj v ''
		*/
        $csscode = str_replace('"../', "####999QQQ999###", $csscode);
        $csscode = str_replace('"./', '"' . $relativePath, $csscode);
        $csscode = str_replace("####999QQQ999###", '"../' . $relativePath, $csscode);

        $csscode = str_replace("'../", "####999QQQ999###", $csscode);
        $csscode = str_replace("'./", "'" . $relativePath, $csscode);
        $csscode = str_replace("####999QQQ999###", "'../" . $relativePath, $csscode);

        $csscode = str_replace("url(./", "url(./" . $relativePath, $csscode);
        $csscode = str_replace("url(../", "url(../" . $relativePath, $csscode);

        /*
			* @ Vyhladame vsetky IMPORT CSS
		*/
        $prvkyREPLACE = $this->searchBetween($csscode, '@import', ';');
        $prvky = $this->searchBetween($csscodeMem, '@import', ';');
        foreach ($prvky as $kluc => $prvok) {
            $prvokMem = $prvok;
            $prvok = str_replace('"', "", $prvok);
            $prvok = str_replace("'", "", $prvok);
            $cssSubor = trim($prvok);
            $cssSuborA = explode("/", $cssSubor);
            $cssSuborName = end($cssSuborA);
            $cssSuborPath = str_replace($cssSuborName, "", $cssSubor);
            $csscodeIncluded = $this->concatCSS($sourceCSSfilePath . $cssSubor, $relativePath . "/" . $cssSuborPath);
            $csscode = str_replace('@import' . $prvkyREPLACE[$kluc] . ';', $csscodeIncluded, $csscode);
        }
        return ($csscode);
    }

    private function removeUnusedCssFromView()
    {
        if (count($this->renderedCssFiles) > 0) {
            $prvky = $this->searchBetween($this->renderedCode, 'class="', '"');
            $prvky2 = $this->searchBetween($this->renderedCode, "class='", "'");
            $prvky = array_merge($prvky, $prvky2);
            $HTMLclasses = array();
            foreach ($prvky as $kluc => $prvok) {
                $classesA = explode(" ", $prvok);
                foreach ($classesA as $class) {
                    $class = trim($class);
                    if ($class != "") $HTMLclasses["." . $class] = "." . $class;
                }
            }
            foreach ($this->renderedCssFiles as $file) {
                $this->removeUnusedClassesFromCssFile($file, $HTMLclasses);
            }
        }
    }

    private function isArrayPartInString($inputArray, $inputString)
    {
        foreach ($inputArray as $kluc => $hodnota) {
            if (strpos($inputString, $hodnota) !== false) return (true);
        }
        return (false);
    }

    private function removeUnusedClassesFromCssFile($file, $ignoreList)
    {
        $ignoreList[':root'] = ":root";
        $ignoreList['media'] = "media";
        // Ako hlboko v strome som ( max bude 2 a to pri media query ) ak je 0 som v ROOT css stromu
        $hlbka = 0;

        // Aby som vedel riesit stavy kedy prechadzam do inej hlbky
        $hlbkaMem = 0;

        // Aktualna pozicia parsera
        $actualPosition = 0;

        // Buffer nepreneseny do vysledku
        $actualBuffer = "";

        // Zapisujem aktualny znak alebo nie...
        $zapis = 1;

        $outputCss = "";

        $startIgnore = 0;

        $debugprvok = "col";
        $cssCode = " " . file_get_contents($file);
        $cssCode = str_replace("}", "} ", $cssCode);

        $cssCodea = str_split($cssCode);

        foreach ($cssCodea as $znak) {
            $actualBuffer .= $znak;

            // Vchadzam dovnutra classy
            if ($znak == "{") {
                $hlbka++;

                // som v media query alebo classe
                if ($this->isArrayPartInString($ignoreList, $actualBuffer) || (strpos($actualBuffer, ".") === false)) {
                    $outputCss .= $actualBuffer;
                    $actualBuffer = "";
                } else {
                    $startIgnore++;
                    $actualBuffer = "";
                }
            }

            // Vychadzam z classy
            if ($znak == "}") {
                $hlbka--;
                if ($startIgnore == 0) {
                    $outputCss .= trim($actualBuffer);
                    $actualBuffer = "";
                } else {
                    $startIgnore--;
                    $actualBuffer = "";
                }
            }



            $hlbkaMem = $hlbka;
            $actualPosition++;
        }

        file_put_contents($file, $outputCss);
    }

    public function renderLayoutCode(callable $renderer = null)
    {
        $this->renderedCode = "";
        $cachename = null;

        if (isset($this->layout) && $this->layout != "") {
            /*
				Spojime vsetky sablony dokopy
			*/
            $layoutcode = $this->concatInnerLayouts($this->layout);
        } else {
            $layoutcode = "";
        }

        if ($this->useCache) {
            /*
				* @ - Skusime najst subor v cache
				* Nazov cache suboru je layout nazov plus md5 kodu - Tym zabezpecime ze ak je sablona zmenena vygeneruje sa novy cache
			*/
            $layoutchangedname = str_replace("/", "-", $this->layout);
            $layoutchangedname = str_replace("\\", "-", $layoutchangedname);
            $viewchangedname = str_replace("/", "-", $this->view);
            $viewchangedname = str_replace("\\", "-", $viewchangedname);

            $cachename = "view_" . $viewchangedname . "_layout_" . $layoutchangedname . "_" . md5($layoutcode);

            if ($this->cache->cachePageExists($cachename)) {
                $this->renderedCode = $this->cache->cachePageRead($cachename, $this->getViewVars());
                return $this->renderedCode;
            }
        }
		
		/*
			* Nahradime layoutovy kod tym, cim potrebujeme. Nahradime content obsahom view
		*/
		/*
			Umoznime modulom zasah do renderovania. Vhodne je to v pripade ze mame napriklad modul DOT cms. Berme DOT CMS ako priklad v celom vysvetleni.
			Ten ma svoj module.init.php My programujeme modul ktory doplna nejake rendrovacie funkcie. Napriklad pridava galeriu alebo nieco podobne.
			Ale samotny vstavany renderer tuto funkciu nema. Ak by sme tuto funckiu doplnili samostatnym modulom, potom by pre modul DOT CMS nefungoval.
			Bolo by nutne nie len nainstalovat modul s novymi funkciami renderingu ale upravit vo vsetkych parent moduloch ich init. Co by bola hlupost.
			Preto umoznujeme aby dotapp renderer pred odoslanim vyrenderovaneho kodu spustil dalsie renderingy ktore si nejaky modul zadefinuje.
			
			funkciou add_renderer();
			priklad:
				$dotapp->router->renderer->add_renderer("nazov",function($code) {
					return $code."<br><br><br>Vytvoril Jozko Pucik";
				});
				
			Tymto dokazeme do kazdeho rendrovaneho kodu pridat na zaver text :)
			
			Takze nam staci ak si vyrobime modul schopny rendrovat nejaku galeriu a samotny modul si zaregistruje vlastny renderer.
			Cim sa automaticky prida funkcia galerie aj pre iny modul naprikald DOT CMS.
		*/
        /**
         * Core Engine for Superblocks (privateblock).
         * Extracts code fragments to be used as objects in PHP.
         * * Example in View:
         * {{ privateblock:item }} <li>{{ var: $name }}</li> {{ /privateblock }}
         * * Logic: This function "cuts" the block out of HTML and stores it in $block['item'].
         * Usage in same file: foreach($data as $d) echo $block['item']->set("name", $d)->html();
         */
        if ($renderer === null) {
            $this->renderedCode = $layoutcode;
            $this->renderedCode = $this->processPrivateBlocks($this->renderedCode);
            foreach ($this->custom_renderers() as $rkey => $custom_renderer) {
                $this->renderedCode = $custom_renderer($this->renderedCode, $this->getLayoutVars());
            }
        } else {
            $this->renderedCode = $renderer($layoutcode);
            $this->renderedCode = $this->processPrivateBlocks($this->renderedCode);
            foreach ($this->custom_renderers() as $rkey => $custom_renderer) {
                $this->renderedCode = $custom_renderer($this->renderedCode, $this->getViewVars());
            }
        }


        //$this->minimizeHTML();
        /*
			Az ked uz mame kod minimalizovany vlozime {{generatorinfo}}
		*/

        if ($this->useCache == true && $cachename !== null) {
            $this->cache->cachePageSave($cachename, $this->renderedCode);
        }

        // Vycistime nepouzite CSS triedy zo suborov
        if ($this->removeUnusedCss == true) $this->removeUnusedCssFromView();

        return ($this->renderedCode);
    }

    public function renderLayout()
    {
        $this->renderLayoutCode();
        $this->updateLayoutContentData();
        $this->renderedCode = $this->dotApp->bridge->dotBridge($this->renderedCode);
        // Najprv ho prezenieme cez staticky call, cim strati $this pristup
        $this->renderedCode = Renderer::phprender_isolated($this->getLayoutVars(), $this->renderedCode);
        return $this->renderedCode;
    }

    public function renderViewCode($customRenderrers = true)
    {
        if (isset($this->view) && $this->view != "") {
            $loadedviewcode = $this->loadView($this->view);
            $loadedviewcode = $this->concatInnerLayouts("", $loadedviewcode);

            if (isset($this->layout) && $this->layout != "") {
                // Content substitution only. Custom renderers run once afterwards
                // inside renderLayoutCode() on the combined document. Running them
                // here as well would process layout tags twice.
                $renderer = function ($code) use ($loadedviewcode) {
                    return (str_replace("{{ content }}", $code, $loadedviewcode));
                };
                $this->renderLayoutCode($renderer);
                return ($this->renderedCode);
            } else {
                foreach ($this->custom_renderers() as $rkey => $custom_renderer) {
                    $loadedviewcode = $custom_renderer($loadedviewcode, $this->getViewVars());
                }
                $this->renderedCode = $loadedviewcode;
                return ($this->renderedCode);
            }
        } else return ("");
    }

    public function renderView()
    {
        $this->renderViewCode();
        $this->updateLayoutContentData();
        $this->renderedCode = $this->dotApp->bridge->dotBridge($this->renderedCode);

        // Najprv ho prezenieme cez staticky call, cim strati $this pristup
        $this->renderedCode = Renderer::phprender_isolated($this->getViewVars(), $this->renderedCode);
        return $this->renderedCode;
    }

    public function renderCode($code, $vars = [], $render = true)
    {
        $this->renderedCode = $code;
        $this->updateLayoutContentData();
        $this->renderedCode = $this->dotApp->bridge->dotBridge($this->renderedCode);
        if ($render === false) return $this->renderedCode;
        // Najprv ho prezenieme cez staticky call, cim strati $this pristup
        $this->renderedCode = Renderer::phprender_isolated($vars, $this->renderedCode);
        return $this->renderedCode;
    }

    public function phprender_isolated($vars, $code)
    {
        $preneseneFn = array();
        $preneseneFn['encrypt'] = function ($text, $key2 = "") {
            return $this->dotApp->encrypt($text, $key2);
        };
        $filters = self::$valueFilters;
        $preneseneFn['filter'] = function ($value, $pipeline) use ($filters) {
            if (!is_array($pipeline)) {
                return $value;
            }
            foreach ($pipeline as $step) {
                if (!is_array($step) || !isset($step[0])) {
                    continue;
                }
                $name = $step[0];
                $args = (isset($step[1]) && is_array($step[1])) ? $step[1] : array();
                if (!isset($filters[$name]) || !is_callable($filters[$name])) {
                    continue;
                }
                array_unshift($args, $value);
                $value = call_user_func_array($filters[$name], $args);
            }
            return $value;
        };

        $isolated_renderer = new RenderingIsolator($preneseneFn);
        return ($isolated_renderer->render($vars, $code));
    }

    /*
		$max - Maximalna hlbka vnorenia. Sluzi pre pripad ze by sa generovanie zacyklilo. Ci uz z chyby display_error alebo z toho ze v inner layoute volame sameho seba.
				Takto by sa to cyklilo donekonecna a pre tento pripad mame MAX. Zaroven cislo 20 je maxialne dostacujuce na to aby pokrylo vsetky potreby.
		$actual - aktualna hlbka vnorenia.
	*/
    public function concatInnerLayouts($layout, $code = "", $actual = 0, $max = 20)
    {
        // Prevent infinite recursion
        if ($actual >= $max) {
            $this->dotApp->logger->warning("Maximum layout nesting depth reached", [
                'layout' => $layout,
                'depth' => $actual
            ]);
            return "";
        }
        $actual++;

        if ($code != "") {
            $layoutcode = $code;
        } else {
            $layoutcode = $this->getLayout($layout);
        }

        // Original regex (commented for easy rollback if needed):
        // $pattern = '/\{\{\s*layout\s*:\s*([^\}\s]+)\s*\}\}/';
        // New regex supports both {{ layout:name }} and {{ layout: name }} (with or without space after colon)
        $pattern = '/\{\{\s*layout\s*:\s*([^\}]+?)\s*\}\}/';
        if (preg_match_all($pattern, $layoutcode, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $found_layout = trim($match[1]); // Remove spaces from layout name
                $layoutcode = str_replace(
                    $match[0], // Use original match (supports both with and without space)
                    $this->concatInnerLayouts($found_layout, "", $actual, $max),
                    $layoutcode
                );
            }
        }

        // Original regex (commented for easy rollback if needed):
        // $pattern = '/\{\{\s*baselayout\s*:\s*([^\}\s]+)\s*\}\}/';
        // New regex supports both {{ baselayout:name }} and {{ baselayout: name }} (with or without space after colon)
        $pattern = '/\{\{\s*baselayout\s*:\s*([^\}]+?)\s*\}\}/';
        $remdirl = $this->dirl;
        $remdirw = $this->dirw;
        $this->dirl = __ROOTDIR__ . "/app/parts/views/layouts/";
        $this->dirw = __ROOTDIR__ . "/app/parts/views/";

        if (preg_match_all($pattern, $layoutcode, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $found_layout = trim($match[1]); // Remove spaces from layout name
                $layoutcode = str_replace(
                    $match[0], // Use original match (supports both with and without space)
                    $this->concatInnerLayouts($found_layout, "", $actual, $max),
                    $layoutcode
                );
            }
        }

        $this->dirl = $remdirl;
        $this->dirw = $remdirw;

        return $layoutcode;
    }



    public function updateLayoutContentData($layoutdata = null)
    {
        // Deterministic compile of known control/output directives. Unknown
        // {{ ... }} tags are left intact for CSRF, formName, Bridge, and custom renderers.
        if (isset($layoutdata)) {
            $layoutdata = $this->compileDirectives($layoutdata);
            $layoutdata = $this->dotApp->bridge->dotBridge($layoutdata);
            return $layoutdata;
        }

        $this->renderedCode = $this->compileDirectives($this->renderedCode);

        /*
            Ak niekomu staci obycajny CSRF token, aj ked ja to povazujem za zobracinu tak nech sa paci.
            token je ulozeny v $dotApp->CSRF; takze kludne aj v DotApp::dotApp()->CSRF;
        */
        $this->renderedCode = str_replace("{{ CSRF }}", '<input type="hidden" value="' . $this->dotApp->CSRF . '">', $this->renderedCode);

        // Form name
        $this->renderedCode = $this->processFormSecurityTags($this->renderedCode);

        return $this;
    }


    /**
     * Process HTML content to replace {{ formName(name) }} tags with encrypted hidden fields.
     * Extracts action and method from the enclosing <form> tag.
     * If no <form> tag is found, the tag is left unchanged.
     *
     * @param string $html The input HTML content
     * @return string The processed HTML content
     * <form action="/uzivatelia" method="POST">
     *   <input type="text" value="hodnota">
     *   {{ formName(janko) }}
     * </form>
     * 
     * nasledne volana funkcia bude:
     */
    private function processFormSecurityTags($html)
    {
        // Regex to match {{ formName(name) }} tags
        $pattern = '/\{\{\s*formName\(([^)]+)\)\s*\}\}/';
        
        // Novy 05/2026 - opravene poradie action
        return preg_replace_callback($pattern, function ($matches) use ($html) {
            $formName = trim($matches[1], '"\''); // Extract form name, remove quotes if present
            $originalTag = $matches[0]; // Store the original tag to return if processing fails

            // Otvárajúca značka: atribúty v jednej časti; method/action parsujeme samostatne (poradie atribútov nevadí)
            $formPattern = '/<(form|fo-rm)([^>]+)>.*?\{\{\s*formName\(' . preg_quote($formName, '/') . '\)\s*\}\}.*?<\/\1>/is';

            if (preg_match($formPattern, $html, $formMatches)) {
                $attrString = $formMatches[2];
                if (!preg_match('/\bmethod\s*=\s*["\']([^"\']*)["\']/i', $attrString, $mMeth)) {
                    return $originalTag;
                }
                $method = strtoupper($mMeth[1]);
                $action = $this->dotApp->router->request->getPath();
                if (preg_match('/\baction\s*=\s*["\']([^"\']*)["\']/i', $attrString, $mAct) && $mAct[1] !== '') {
                    $action = $mAct[1];
                }
                $input = new Input();
                return $input->formFunction($action, $method, $formName, $this);
            }

            // If no <form> or <fo-rm> tag is found, return the original tag
            return $originalTag;
        }, $html);
        // Stary do 05/2026
        /*return preg_replace_callback($pattern, function ($matches) use ($html) {
            $formName = trim($matches[1], '"\''); // Extract form name, remove quotes if present
            $originalTag = $matches[0]; // Store the original tag to return if processing fails

            // Regex to find the enclosing <form> or <fo-rm> tag, making action optional
            $formPattern = '/<(form|fo-rm)\s+[^>]*?(?:action\s*=\s*["\']([^"\']+)["\'])?[^>]*method\s*=\s*["\']([^"\']+)["\'][^>]*>.*?\{\{\s*formName\(' . preg_quote($formName, '/') . '\)\s*\}\}.*?(<\/\1>)/is';

            if (preg_match($formPattern, $html, $formMatches)) {
                // Use the action from the form, or fall back to the current request path
                $action = !empty($formMatches[2]) ? $formMatches[2] : $this->dotApp->router->request->getPath();
                $method = strtoupper($formMatches[3]);
                $input = new Input();
                return $input->formFunction($action, $method, $formName, $this);
            }

            // If no <form> or <fo-rm> tag is found, return the original tag
            return $originalTag;
        }, $html);*/
    }

    /**
     * Extracts {{ privateblock:name }} fragments. Nesting is rejected: the closer
     * has no name, so a second open while one is open cannot be paired safely.
     * Sequential (non-nested) privateblocks remain valid. Names use the same
     * safe dotted/dashed format as standard blocks and are written with var_export.
     *
     * @param string $code
     * @return string
     */
    private function processPrivateBlocks($code)
    {
        $tags = $this->dsCollectPrivateBlockTags($code);
        $pairs = array();
        $open = null;
        foreach ($tags as $tag) {
            if ($tag['kind'] === 'open') {
                if ($open !== null) {
                    $this->dsThrow('privateblock', $tag['line'], 'Nested "privateblock" is not supported.');
                }
                $open = $tag;
            } else {
                if ($open === null) {
                    $this->dsThrow('/privateblock', $tag['line'], 'Unexpected "/privateblock" with no open "privateblock".');
                }
                $pairs[] = array(
                    'name' => $open['name'],
                    'start' => $open['start'],
                    'end' => $tag['end'],
                    'inner' => substr($code, $open['end'], $tag['start'] - $open['end']),
                );
                $open = null;
            }
        }
        if ($open !== null) {
            $this->dsThrow('privateblock', $open['line'], 'Unclosed "privateblock" directive.');
        }
        if (empty($pairs)) {
            return $code;
        }
        for ($n = count($pairs) - 1; $n >= 0; $n--) {
            $pair = $pairs[$n];
            $replacement = '<?php $block[' . var_export($pair['name'], true) . '] = new \\Dotsystems\\App\\Parts\\PrivateBlock(base64_decode("' . base64_encode($pair['inner']) . '")); ?>';
            $code = substr($code, 0, $pair['start']) . $replacement . substr($code, $pair['end']);
        }
        return '<?php $block = array(); ?>' . "\n" . $code;
    }

    /**
     * @param string $code
     * @return array<int, array<string, mixed>>
     */
    private function dsCollectPrivateBlockTags($code)
    {
        $tags = array();
        $len = strlen($code);
        $i = 0;
        while ($i < $len) {
            if ($this->dsAtPhpOpen($code, $i, $len)) {
                $i = $this->dsSkipPhpIsland($code, $i, $len);
                continue;
            }
            if ($i + 1 < $len && $code[$i] === '{' && $code[$i + 1] === '{') {
                $end = $this->dsFindTagEnd($code, $i, $len);
                if ($end === false) {
                    break;
                }
                $inner = trim(substr($code, $i + 2, $end - 2 - ($i + 2)));
                $line = $this->dsLineAt($code, $i);
                if (preg_match('/^privateblock:(.+)$/s', $inner, $m)) {
                    $name = trim($m[1]);
                    if (!preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]*$/', $name)) {
                        $this->dsThrow('privateblock', $line, 'Malformed "privateblock" name.');
                    }
                    $tags[] = array(
                        'kind' => 'open',
                        'name' => $name,
                        'start' => $i,
                        'end' => $end,
                        'line' => $line,
                    );
                } elseif (preg_match('/^\/privateblock$/i', $inner)) {
                    $tags[] = array(
                        'kind' => 'close',
                        'start' => $i,
                        'end' => $end,
                        'line' => $line,
                    );
                }
                $i = $end;
                continue;
            }
            $i++;
        }
        return $tags;
    }

    /**
     * LIFO stack check for {{ block:name }} / {{ /block:name }}. Same-name and
     * different-name nesting is allowed; crossing, orphan, and unclosed tags throw.
     *
     * @param string $code
     * @return void
     */
    private function dsValidateBlockTags($code)
    {
        $tags = $this->dsCollectBlockTags($code);
        $stack = array();
        foreach ($tags as $tag) {
            if ($tag['kind'] === 'open') {
                $stack[] = $tag;
                continue;
            }
            if (empty($stack)) {
                $this->dsThrow('/block', $tag['line'], 'Unexpected "/block" with no open "block".');
            }
            $top = $stack[count($stack) - 1];
            if ($top['name'] !== $tag['name']) {
                $this->dsThrow('/block', $tag['line'], 'Unexpected "/block" (expected "/block:' . $top['name'] . '").');
            }
            array_pop($stack);
        }
        if (!empty($stack)) {
            $top = $stack[count($stack) - 1];
            $this->dsThrow('block', $top['line'], 'Unclosed "block" directive.');
        }
    }

    /**
     * @param string $code
     * @return array<int, array<string, mixed>>
     */
    private function dsCollectBlockTags($code)
    {
        $tags = array();
        $len = strlen($code);
        $i = 0;
        while ($i < $len) {
            if ($this->dsAtPhpOpen($code, $i, $len)) {
                $i = $this->dsSkipPhpIsland($code, $i, $len);
                continue;
            }
            if ($i + 1 < $len && $code[$i] === '{' && $code[$i + 1] === '{') {
                $end = $this->dsFindTagEnd($code, $i, $len);
                if ($end === false) {
                    break;
                }
                $inner = trim(substr($code, $i + 2, $end - 2 - ($i + 2)));
                $line = $this->dsLineAt($code, $i);
                if (preg_match('/^block:([A-Za-z0-9_.-]+)(?:\s*\((.*)\s*\)\s*)?$/s', $inner, $m)) {
                    $argStr = isset($m[2]) ? $m[2] : '';
                    $tags[] = array(
                        'kind' => 'open',
                        'name' => $m[1],
                        'args' => $this->dsParseBlockArgs($argStr),
                        'start' => $i,
                        'end' => $end,
                        'line' => $line,
                    );
                } elseif (preg_match('/^\/block:([A-Za-z0-9_.-]+)$/', $inner, $m)) {
                    $tags[] = array(
                        'kind' => 'close',
                        'name' => $m[1],
                        'start' => $i,
                        'end' => $end,
                        'line' => $line,
                    );
                }
                $i = $end;
                continue;
            }
            $i++;
        }
        return $tags;
    }

    /**
     * Stack-based {{ block:name }} processor. Innermost same-name pairs are
     * resolved first so nested identical block names work. Arguments honor
     * quotes, so commas and spaces inside "..." or '...' stay in one argument.
     * Handler signature is unchanged: fn($innerContent, $blockVariables, $variables).
     *
     * @param string $code
     * @param array  $variables
     * @return string
     */
    private function processStandardBlocks($code, $variables)
    {
        $guard = 0;
        while ($guard < 10000) {
            $guard++;
            $this->dsValidateBlockTags($code);
            $pair = $this->dsFindInnermostBlock($code);
            if ($pair === null) {
                break;
            }
            $blockName = $pair['name'];
            $handler = $this->dotApp->customRenderer->blocks($blockName);
            if (is_callable($handler)) {
                $replacement = $handler($pair['inner'], $pair['args'], $variables);
            } else {
                $replacement = "{{ blockerror:" . $blockName . " }} Undefined callable function ! {{ /blockerror:" . $blockName . " }}";
            }
            $code = substr($code, 0, $pair['start']) . $replacement . substr($code, $pair['end']);
        }
        return $code;
    }

    /**
     * Finds the innermost complete {{ block:name }} ... {{ /block:name }} pair.
     *
     * @param string $code
     * @return array<string, mixed>|null
     */
    private function dsFindInnermostBlock($code)
    {
        $tags = $this->dsCollectBlockTags($code);

        $pairs = array();
        $stack = array();
        foreach ($tags as $tag) {
            if ($tag['kind'] === 'open') {
                $stack[] = $tag;
            } else {
                if (empty($stack)) {
                    continue;
                }
                $open = $stack[count($stack) - 1];
                if ($open['name'] !== $tag['name']) {
                    continue;
                }
                array_pop($stack);
                $pairs[] = array(
                    'name' => $open['name'],
                    'args' => $open['args'],
                    'start' => $open['start'],
                    'end' => $tag['end'],
                    'inner' => substr($code, $open['end'], $tag['start'] - $open['end']),
                );
            }
        }
        foreach ($pairs as $pair) {
            $containsOther = false;
            foreach ($pairs as $other) {
                if ($other['start'] === $pair['start'] && $other['end'] === $pair['end']) {
                    continue;
                }
                if ($other['start'] >= $pair['start'] && $other['end'] <= $pair['end']) {
                    $containsOther = true;
                    break;
                }
            }
            if (!$containsOther) {
                return $pair;
            }
        }
        return null;
    }

    /**
     * Quote-aware split of block arguments on commas.
     *
     * @param string $argStr
     * @return array<int, string>
     */
    private function dsParseBlockArgs($argStr)
    {
        $argStr = trim($argStr);
        if ($argStr === '') {
            return array();
        }
        $args = array();
        $current = '';
        $quote = null;
        $escape = false;
        $len = strlen($argStr);
        for ($i = 0; $i < $len; $i++) {
            $ch = $argStr[$i];
            if ($escape) {
                $current .= $ch;
                $escape = false;
                continue;
            }
            if ($quote !== null) {
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === $quote) {
                    $quote = null;
                    continue;
                }
                $current .= $ch;
                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $quote = $ch;
                continue;
            }
            if ($ch === ',') {
                $args[] = trim($current);
                $current = '';
                continue;
            }
            $current .= $ch;
        }
        $args[] = trim($current);
        return $args;
    }

    /**
     * Compiles known DotApp directives into PHP. PHP islands, {{ raw }}, and
     * unknown tags are left unchanged. Throws RendererSyntaxException on
     * malformed, mismatched, or unclosed known control directives.
     *
     * @param string $code
     * @return string
     */
    private function compileDirectives($code)
    {
        if (!is_string($code) || $code === '') {
            return $code;
        }
        $len = strlen($code);
        $i = 0;
        $out = '';
        $stack = array();
        $rawDepth = 0;
        $commentDepth = 0;
        $loopSeq = 0;
        $rawOpenLine = 1;
        $commentOpenLine = 1;

        while ($i < $len) {
            if ($this->dsAtPhpOpen($code, $i, $len)) {
                $phpEnd = $this->dsSkipPhpIsland($code, $i, $len);
                if ($commentDepth === 0) {
                    $out .= substr($code, $i, $phpEnd - $i);
                }
                $i = $phpEnd;
                continue;
            }

            if ($i + 1 < $len && $code[$i] === '{' && $code[$i + 1] === '{') {
                $tagEnd = $this->dsFindTagEnd($code, $i, $len);
                if ($tagEnd === false) {
                    $out .= $code[$i];
                    $i++;
                    continue;
                }
                $innerRaw = substr($code, $i + 2, $tagEnd - 2 - ($i + 2));
                $inner = trim($innerRaw);
                $line = $this->dsLineAt($code, $i);

                if ($commentDepth > 0) {
                    if (preg_match('/^comment$/i', $inner)) {
                        $commentDepth++;
                    } elseif (preg_match('/^\/comment$/i', $inner)) {
                        $commentDepth--;
                    }
                    $i = $tagEnd;
                    continue;
                }

                if ($rawDepth > 0) {
                    if (preg_match('/^raw$/i', $inner)) {
                        $rawDepth++;
                        $i = $tagEnd;
                        continue;
                    }
                    if (preg_match('/^\/raw$/i', $inner)) {
                        $rawDepth--;
                        $i = $tagEnd;
                        continue;
                    }
                    $out .= substr($code, $i, $tagEnd - $i);
                    $i = $tagEnd;
                    continue;
                }

                $rawBefore = $rawDepth;
                $commentBefore = $commentDepth;
                $compiled = $this->dsCompileTag($inner, $line, $stack, $rawDepth, $commentDepth, $loopSeq);
                if ($rawBefore === 0 && $rawDepth > 0) {
                    $rawOpenLine = $line;
                }
                if ($commentBefore === 0 && $commentDepth > 0) {
                    $commentOpenLine = $line;
                }
                if ($compiled === null) {
                    $out .= substr($code, $i, $tagEnd - $i);
                } else {
                    $out .= $compiled;
                }
                $i = $tagEnd;
                continue;
            }

            if ($commentDepth === 0) {
                $out .= $code[$i];
            }
            $i++;
        }

        if ($commentDepth > 0) {
            $this->dsThrow('comment', $commentOpenLine, 'Unclosed "comment" directive.');
        }
        if ($rawDepth > 0) {
            $this->dsThrow('raw', $rawOpenLine, 'Unclosed "raw" directive.');
        }
        if (!empty($stack)) {
            $top = $stack[count($stack) - 1];
            $this->dsThrow($top['kind'], $top['line'], 'Unclosed "' . $top['kind'] . '" directive.');
        }
        return $out;
    }

    /**
     * Compiles one trimmed tag inner body, or returns null to keep the original tag.
     *
     * @param string $inner
     * @param int    $line
     * @param array  $stack
     * @param int    $rawDepth
     * @param int    $commentDepth
     * @param int    $loopSeq
     * @return string|null
     */
    private function dsCompileTag($inner, $line, array &$stack, &$rawDepth, &$commentDepth, &$loopSeq)
    {
        if (preg_match('/^raw$/i', $inner)) {
            $rawDepth++;
            return '';
        }
        if (preg_match('/^\/raw$/i', $inner)) {
            $this->dsThrow('raw', $line, 'Unexpected "/raw" with no open "raw".');
        }
        if (preg_match('/^comment$/i', $inner)) {
            $commentDepth++;
            return '';
        }
        if (preg_match('/^\/comment$/i', $inner)) {
            $this->dsThrow('comment', $line, 'Unexpected "/comment" with no open "comment".');
        }

        if (preg_match('/^var\s*:\s*(.+)$/s', $inner, $m)) {
            $parsed = $this->dsParseValueAndFilters(trim($m[1]), $line, 'var');
            return $this->dsEmitEcho($parsed['php'], $parsed['filters']);
        }

        if (preg_match('/^_\s*(.+)$/s', $inner, $m)) {
            $rest = trim($m[1]);
            if (preg_match('/^var\s*:\s*(.+)$/s', $rest, $vm)) {
                $parsed = $this->dsParseValueAndFilters(trim($vm[1]), $line, '_');
                return $this->dsEmitEcho('$translator(' . $parsed['php'] . ')', $parsed['filters']);
            }
            if ($rest === '' || $rest[0] !== '"') {
                return null;
            }
            $parsed = $this->dsParseValueAndFilters($rest, $line, '_');
            if ($parsed['kind'] !== 'literal') {
                $this->dsThrow('_', $line, 'Malformed translation directive.');
            }
            return $this->dsEmitEcho('$translator(' . $parsed['php'] . ')', $parsed['filters']);
        }

        if (preg_match('/^enc(\((.*)\))?\s*:\s*(.+)$/s', $inner, $m)) {
            $keyRaw = isset($m[2]) ? trim($m[2]) : '';
            $parsed = $this->dsParseValueAndFilters(trim($m[3]), $line, 'enc');
            $encName = '$dotapp236365b0b1631351e99daf046d18d2bcEcnrypt';
            if ($parsed['kind'] === 'literal') {
                $plain = $parsed['literal'];
                $encrypted = ($keyRaw === '')
                    ? $this->dotApp->encrypt($plain)
                    : $this->dotApp->encrypt($plain, $keyRaw);
                if (empty($parsed['filters'])) {
                    return (string) $encrypted;
                }
                return $this->dsEmitEcho(var_export((string) $encrypted, true), $parsed['filters']);
            }
            if ($keyRaw === '') {
                $expr = $encName . '(' . $parsed['php'] . ')';
            } else {
                $expr = $encName . '(' . $parsed['php'] . ', ' . var_export($keyRaw, true) . ')';
            }
            return $this->dsEmitEcho($expr, $parsed['filters']);
        }

        if (preg_match('/^if\s+(.+)$/s', $inner, $m)) {
            $stack[] = array('kind' => 'if', 'line' => $line, 'hasElse' => false, 'foreachElse' => false);
            return '<?php if (' . trim($m[1]) . '): ?>';
        }
        if (preg_match('/^elseif\s+(.+)$/s', $inner, $m)) {
            $this->dsExpectTop($stack, 'if', $line, 'elseif');
            $top = &$stack[count($stack) - 1];
            if (!empty($top['hasElse'])) {
                $this->dsThrow('elseif', $line, 'Unexpected "elseif" after "else".');
            }
            unset($top);
            return '<?php elseif (' . trim($m[1]) . '): ?>';
        }
        if (preg_match('/^else$/i', $inner)) {
            if (empty($stack)) {
                $this->dsThrow('else', $line, 'Unexpected "else" with no open "if" or "foreach".');
            }
            $top = &$stack[count($stack) - 1];
            if ($top['kind'] === 'foreach' && empty($top['foreachElse'])) {
                $top['foreachElse'] = true;
                $php = '<?php endforeach; if (empty(' . $top['anyRef'] . ')): ?>';
                unset($top);
                return $php;
            }
            if ($top['kind'] === 'if' && empty($top['hasElse'])) {
                $top['hasElse'] = true;
                unset($top);
                return '<?php else: ?>';
            }
            $this->dsThrow('else', $line, 'Unexpected "else".');
        }
        if (preg_match('/^\/if$/i', $inner)) {
            $this->dsExpectTop($stack, 'if', $line, '/if');
            array_pop($stack);
            return '<?php endif; ?>';
        }

        if (preg_match('/^foreach\s+(.+)$/s', $inner, $m)) {
            $parsed = $this->dsParseForeach(trim($m[1]));
            if ($parsed === false) {
                $this->dsThrow('foreach', $line, 'Malformed "foreach" directive.');
            }
            $id = $loopSeq++;
            $S = '$__ds_src_' . $id;
            $C = '$__ds_cnt_' . $id;
            $P = '$__ds_par_' . $id;
            $I = '$__ds_i_' . $id;
            $A = '$__ds_any_' . $id;
            $as = ($parsed['key'] !== null) ? ($parsed['key'] . ' => ' . $parsed['value']) : $parsed['value'];
            $stack[] = array(
                'kind' => 'foreach',
                'line' => $line,
                'hasElse' => false,
                'foreachElse' => false,
                'parentRef' => $P,
                'anyRef' => $A,
            );
            return '<?php ' . $S . ' = isset(' . $parsed['source'] . ') ? ' . $parsed['source'] . ' : array(); '
                . 'if (!is_array(' . $S . ') && !(' . $S . ' instanceof \\Traversable)) { ' . $S . ' = array(); } '
                . 'if (' . $S . ' instanceof \\Traversable && !is_array(' . $S . ')) { ' . $S . ' = iterator_to_array(' . $S . '); } '
                . $C . ' = count(' . $S . '); '
                . $P . ' = isset($loop) ? $loop : null; '
                . $I . ' = 0; '
                . $A . ' = false; '
                . '$loop = array(\'index\' => 0, \'iteration\' => 1, \'first\' => 1, \'last\' => (' . $C . ' <= 1 ? 1 : 0), \'count\' => ' . $C . ', \'parent\' => ' . $P . '); '
                . 'foreach (' . $S . ' as ' . $as . '): '
                . $A . ' = true; '
                . '$loop[\'index\'] = ' . $I . '; '
                . '$loop[\'iteration\'] = ' . $I . ' + 1; '
                . '$loop[\'first\'] = (' . $I . ' === 0) ? 1 : 0; '
                . '$loop[\'last\'] = (' . $I . ' === ' . $C . ' - 1) ? 1 : 0; '
                . '$loop[\'count\'] = ' . $C . '; '
                . '$loop[\'parent\'] = ' . $P . '; '
                . $I . '++; ?>';
        }
        if (preg_match('/^\/foreach$/i', $inner)) {
            $this->dsExpectTop($stack, 'foreach', $line, '/foreach');
            $top = array_pop($stack);
            if (!empty($top['foreachElse'])) {
                return '<?php endif; $loop = ' . $top['parentRef'] . '; ?>';
            }
            return '<?php endforeach; $loop = ' . $top['parentRef'] . '; ?>';
        }

        if (preg_match('/^while\s+(.+)$/s', $inner, $m)) {
            $stack[] = array('kind' => 'while', 'line' => $line, 'hasElse' => false, 'foreachElse' => false);
            return '<?php while (' . trim($m[1]) . '): ?>';
        }
        if (preg_match('/^\/while$/i', $inner)) {
            $this->dsExpectTop($stack, 'while', $line, '/while');
            array_pop($stack);
            return '<?php endwhile; ?>';
        }

        if (preg_match('/^break(?:\s+(.+))?$/s', $inner, $m)) {
            if (!$this->dsStackHasLoop($stack)) {
                $this->dsThrow('break', $line, '"break" is only valid inside a loop.');
            }
            if (!isset($m[1]) || trim($m[1]) === '') {
                return '<?php break; ?>';
            }
            return '<?php if (' . trim($m[1]) . ') break; ?>';
        }
        if (preg_match('/^continue(?:\s+(.+))?$/s', $inner, $m)) {
            if (!$this->dsStackHasLoop($stack)) {
                $this->dsThrow('continue', $line, '"continue" is only valid inside a loop.');
            }
            if (!isset($m[1]) || trim($m[1]) === '') {
                return '<?php continue; ?>';
            }
            return '<?php if (' . trim($m[1]) . ') continue; ?>';
        }

        return null;
    }

    /**
     * @param array  $stack
     * @param string $kind
     * @param int    $line
     * @param string $found
     * @return void
     */
    private function dsExpectTop(array $stack, $kind, $line, $found)
    {
        if (empty($stack)) {
            $this->dsThrow($found, $line, 'Unexpected "' . $found . '" with no open "' . $kind . '".');
        }
        $top = $stack[count($stack) - 1];
        if ($top['kind'] !== $kind) {
            $this->dsThrow($found, $line, 'Unexpected "' . $found . '" (expected "/' . $top['kind'] . '").');
        }
    }

    /**
     * @param array $stack
     * @return bool
     */
    private function dsStackHasLoop(array $stack)
    {
        for ($n = count($stack) - 1; $n >= 0; $n--) {
            if ($stack[$n]['kind'] === 'while') {
                return true;
            }
            if ($stack[$n]['kind'] === 'foreach' && empty($stack[$n]['foreachElse'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $directive
     * @param int    $line
     * @param string $message
     * @return void
     */
    private function dsThrow($directive, $line, $message)
    {
        throw new RendererSyntaxException(
            $directive,
            $line,
            $message . ' (directive "' . $directive . '", template line ' . (int) $line . ')'
        );
    }

    /**
     * @param string $code
     * @param int    $pos
     * @return int
     */
    private function dsLineAt($code, $pos)
    {
        if ($pos <= 0) {
            return 1;
        }
        if ($pos > strlen($code)) {
            $pos = strlen($code);
        }
        return substr_count($code, "\n", 0, $pos) + 1;
    }

    /**
     * Quote-aware search for the `}}` that closes a tag starting at `$start` (`{{`).
     *
     * @param string $code
     * @param int    $start
     * @param int    $len
     * @return int|false Position after closing braces
     */
    private function dsFindTagEnd($code, $start, $len)
    {
        $i = $start + 2;
        $quote = null;
        $escape = false;
        while ($i < $len - 1) {
            $ch = $code[$i];
            if ($quote !== null) {
                if ($escape) {
                    $escape = false;
                    $i++;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    $i++;
                    continue;
                }
                if ($ch === $quote) {
                    $quote = null;
                }
                $i++;
                continue;
            }
            if ($ch === "'" || $ch === '"') {
                $quote = $ch;
                $i++;
                continue;
            }
            if ($ch === '}' && $code[$i + 1] === '}') {
                return $i + 2;
            }
            $i++;
        }
        return false;
    }

    /**
     * @param string $code
     * @param int    $i
     * @param int    $len
     * @return bool
     */
    private function dsAtPhpOpen($code, $i, $len)
    {
        if ($i + 1 >= $len || $code[$i] !== '<' || $code[$i + 1] !== '?') {
            return false;
        }
        if ($i + 4 < $len && strtolower(substr($code, $i, 5)) === '<?xml') {
            return false;
        }
        return true;
    }

    /**
     * @param string $code
     * @param int    $i
     * @param int    $len
     * @return int
     */
    private function dsSkipPhpIsland($code, $i, $len)
    {
        if ($i >= $len) {
            return $len;
        }
        $slice = substr($code, $i);
        if ($slice === '') {
            return $len;
        }
        $adjust = 0;
        $scan = $slice;
        if (strncmp($slice, '<?php', 5) !== 0 && strncmp($slice, '<?=', 3) !== 0 && strncmp($slice, '<?', 2) === 0) {
            $scan = '<?php' . substr($slice, 2);
            $adjust = 3;
        }
        $tokens = @token_get_all($scan);
        if (!is_array($tokens) || $tokens === array()) {
            return $len;
        }
        $offset = 0;
        foreach ($tokens as $token) {
            if (is_array($token)) {
                $offset += strlen($token[1]);
                if ($token[0] === T_CLOSE_TAG) {
                    $mapped = $offset - $adjust;
                    if ($mapped < 2) {
                        $mapped = 2;
                    }
                    $end = $i + $mapped;
                    return ($end > $len) ? $len : $end;
                }
            } else {
                $offset += strlen((string) $token);
            }
        }
        return $len;
    }

    /**
     * @param string $s
     * @param int    $i
     * @param int    $len
     * @return void
     */
    private function dsSkipWs($s, &$i, $len)
    {
        while ($i < $len && ($s[$i] === ' ' || $s[$i] === "\t" || $s[$i] === "\n" || $s[$i] === "\r")) {
            $i++;
        }
    }

    /**
     * Parses $ident[index]... including quoted, numeric, and nested $dynamic indexes.
     *
     * @param string $s
     * @param int    $i
     * @param int    $len
     * @return string|false
     */
    private function dsParseVarPath($s, &$i, $len)
    {
        $start = $i;
        if ($i >= $len || $s[$i] !== '$') {
            return false;
        }
        $i++;
        if ($i >= $len || $s[$i] === '_') {
            $i = $start;
            return false;
        }
        $identStart = $s[$i];
        if (!(($identStart >= 'A' && $identStart <= 'Z') || ($identStart >= 'a' && $identStart <= 'z'))) {
            $i = $start;
            return false;
        }
        $i++;
        while ($i < $len) {
            $ch = $s[$i];
            if (($ch >= 'A' && $ch <= 'Z') || ($ch >= 'a' && $ch <= 'z') || ($ch >= '0' && $ch <= '9') || $ch === '_') {
                $i++;
                continue;
            }
            break;
        }
        while ($i < $len && $s[$i] === '[') {
            $i++;
            $this->dsSkipWs($s, $i, $len);
            if ($i < $len && $s[$i] === '$') {
                if ($this->dsParseVarPath($s, $i, $len) === false) {
                    $i = $start;
                    return false;
                }
            } elseif ($i < $len && ($s[$i] === "'" || $s[$i] === '"')) {
                if (!$this->dsSkipQuoted($s, $i, $len)) {
                    $i = $start;
                    return false;
                }
            } elseif ($i < $len && $s[$i] >= '0' && $s[$i] <= '9') {
                while ($i < $len && $s[$i] >= '0' && $s[$i] <= '9') {
                    $i++;
                }
            } else {
                $i = $start;
                return false;
            }
            $this->dsSkipWs($s, $i, $len);
            if ($i >= $len || $s[$i] !== ']') {
                $i = $start;
                return false;
            }
            $i++;
        }
        return substr($s, $start, $i - $start);
    }

    /**
     * @param string $s
     * @param int    $i
     * @param int    $len
     * @return bool
     */
    private function dsSkipQuoted($s, &$i, $len)
    {
        if ($i >= $len) {
            return false;
        }
        $q = $s[$i];
        if ($q !== "'" && $q !== '"') {
            return false;
        }
        $i++;
        $escape = false;
        while ($i < $len) {
            $ch = $s[$i];
            if ($escape) {
                $escape = false;
                $i++;
                continue;
            }
            if ($ch === '\\') {
                $escape = true;
                $i++;
                continue;
            }
            if ($ch === $q) {
                $i++;
                return true;
            }
            $i++;
        }
        return false;
    }

    /**
     * Parses a value (variable path or quoted literal) plus an optional filter pipeline.
     *
     * @param string $s
     * @param int    $line
     * @param string $directive
     * @return array<string, mixed>
     */
    private function dsParseValueAndFilters($s, $line, $directive)
    {
        $s = trim($s);
        $len = strlen($s);
        $i = 0;
        $kind = 'path';
        $php = '';
        $literal = null;
        if ($i < $len && $s[$i] === '"') {
            $start = $i;
            if (!$this->dsSkipQuoted($s, $i, $len)) {
                $this->dsThrow($directive, $line, 'Unclosed string in "' . $directive . '".');
            }
            $quoted = substr($s, $start, $i - $start);
            $literal = stripcslashes(substr($quoted, 1, -1));
            $php = var_export($literal, true);
            $kind = 'literal';
        } elseif ($i < $len && $s[$i] === '$') {
            $path = $this->dsParseVarPath($s, $i, $len);
            if ($path === false) {
                $this->dsThrow($directive, $line, 'Malformed variable path in "' . $directive . '".');
            }
            $php = $path;
        } else {
            $this->dsThrow($directive, $line, 'Malformed "' . $directive . '" value.');
        }
        $this->dsSkipWs($s, $i, $len);
        $filters = array();
        while ($i < $len && $s[$i] === '|') {
            $i++;
            $this->dsSkipWs($s, $i, $len);
            $nameStart = $i;
            if ($i >= $len || !preg_match('/[A-Za-z_]/', $s[$i])) {
                $this->dsThrow($directive, $line, 'Malformed filter name.');
            }
            $i++;
            while ($i < $len && preg_match('/[A-Za-z0-9_]/', $s[$i])) {
                $i++;
            }
            $fname = substr($s, $nameStart, $i - $nameStart);
            if (!isset(self::$valueFilters[$fname])) {
                $this->dsThrow($fname, $line, 'Unknown filter "' . $fname . '".');
            }
            $this->dsSkipWs($s, $i, $len);
            $args = array();
            if ($i < $len && $s[$i] === '(') {
                $i++;
                $args = $this->dsParseFilterArgs($s, $i, $len, $line, $fname);
            }
            $filters[] = array('name' => $fname, 'args' => $args);
            $this->dsSkipWs($s, $i, $len);
        }
        if ($i !== $len) {
            $this->dsThrow($directive, $line, 'Unexpected trailing content in "' . $directive . '".');
        }
        return array('kind' => $kind, 'php' => $php, 'filters' => $filters, 'literal' => $literal);
    }

    /**
     * @param string $s
     * @param int    $i
     * @param int    $len
     * @param int    $line
     * @param string $fname
     * @return array<int, array<string, mixed>>
     */
    private function dsParseFilterArgs($s, &$i, $len, $line, $fname)
    {
        $args = array();
        $this->dsSkipWs($s, $i, $len);
        if ($i < $len && $s[$i] === ')') {
            $i++;
            return $args;
        }
        while ($i < $len) {
            $this->dsSkipWs($s, $i, $len);
            if ($i < $len && ($s[$i] === '"' || $s[$i] === "'")) {
                $start = $i;
                if (!$this->dsSkipQuoted($s, $i, $len)) {
                    $this->dsThrow($fname, $line, 'Unclosed filter argument string.');
                }
                $quoted = substr($s, $start, $i - $start);
                $val = stripcslashes(substr($quoted, 1, -1));
                $args[] = array('t' => 'lit', 'v' => $val);
            } elseif ($i < $len && $s[$i] === '$') {
                $path = $this->dsParseVarPath($s, $i, $len);
                if ($path === false) {
                    $this->dsThrow($fname, $line, 'Malformed variable filter argument.');
                }
                $args[] = array('t' => 'php', 'v' => $path);
            } elseif ($i + 3 < $len && strtolower(substr($s, $i, 4)) === 'true' && ($i + 4 >= $len || !preg_match('/[A-Za-z0-9_]/', $s[$i + 4]))) {
                $args[] = array('t' => 'lit', 'v' => true);
                $i += 4;
            } elseif ($i + 4 < $len && strtolower(substr($s, $i, 5)) === 'false' && ($i + 5 >= $len || !preg_match('/[A-Za-z0-9_]/', $s[$i + 5]))) {
                $args[] = array('t' => 'lit', 'v' => false);
                $i += 5;
            } elseif ($i + 3 < $len && strtolower(substr($s, $i, 4)) === 'null' && ($i + 4 >= $len || !preg_match('/[A-Za-z0-9_]/', $s[$i + 4]))) {
                $args[] = array('t' => 'lit', 'v' => null);
                $i += 4;
            } elseif ($i < $len && ($s[$i] === '-' || ($s[$i] >= '0' && $s[$i] <= '9'))) {
                $numStart = $i;
                if ($s[$i] === '-') {
                    $i++;
                }
                while ($i < $len && $s[$i] >= '0' && $s[$i] <= '9') {
                    $i++;
                }
                if ($i < $len && $s[$i] === '.') {
                    $i++;
                    while ($i < $len && $s[$i] >= '0' && $s[$i] <= '9') {
                        $i++;
                    }
                    $args[] = array('t' => 'lit', 'v' => (float) substr($s, $numStart, $i - $numStart));
                } else {
                    $args[] = array('t' => 'lit', 'v' => (int) substr($s, $numStart, $i - $numStart));
                }
            } else {
                $this->dsThrow($fname, $line, 'Malformed filter argument.');
            }
            $this->dsSkipWs($s, $i, $len);
            if ($i < $len && $s[$i] === ',') {
                $i++;
                continue;
            }
            if ($i < $len && $s[$i] === ')') {
                $i++;
                return $args;
            }
            $this->dsThrow($fname, $line, 'Malformed filter argument list.');
        }
        $this->dsThrow($fname, $line, 'Unclosed filter argument list.');
        return $args;
    }

    /**
     * @param string $phpExpr
     * @param array  $filters
     * @return string
     */
    private function dsEmitEcho($phpExpr, array $filters)
    {
        if (empty($filters)) {
            return '<?php echo ' . $phpExpr . '; ?>';
        }
        return '<?php echo $__ds_filter(' . $phpExpr . ', ' . $this->dsExportFilters($filters) . '); ?>';
    }

    /**
     * @param array $filters
     * @return string
     */
    private function dsExportFilters(array $filters)
    {
        $chunks = array();
        foreach ($filters as $f) {
            $args = array();
            foreach ($f['args'] as $a) {
                if ($a['t'] === 'php') {
                    $args[] = $a['v'];
                } else {
                    $args[] = var_export($a['v'], true);
                }
            }
            $chunks[] = 'array(' . var_export($f['name'], true) . ', array(' . implode(', ', $args) . '))';
        }
        return 'array(' . implode(', ', $chunks) . ')';
    }

    /**
     * @param string $expr
     * @return array<string, mixed>|false
     */
    private function dsParseForeach($expr)
    {
        $expr = trim($expr);
        $len = strlen($expr);
        if ($len >= 2 && $expr[0] === '(' && substr($expr, -1) === ')') {
            $inner = substr($expr, 1, -1);
            $depth = 0;
            $ok = true;
            $il = strlen($inner);
            for ($k = 0; $k < $il; $k++) {
                if ($inner[$k] === "'" || $inner[$k] === '"') {
                    if (!$this->dsSkipQuoted($inner, $k, $il)) {
                        $ok = false;
                        break;
                    }
                    $k--;
                } elseif ($inner[$k] === '(') {
                    $depth++;
                } elseif ($inner[$k] === ')') {
                    $depth--;
                    if ($depth < 0) {
                        $ok = false;
                        break;
                    }
                }
            }
            if ($ok && $depth === 0) {
                $expr = trim($inner);
                $len = strlen($expr);
            }
        }
        $i = 0;
        $src = $this->dsParseVarPath($expr, $i, $len);
        if ($src === false) {
            return false;
        }
        $this->dsSkipWs($expr, $i, $len);
        if ($i + 2 > $len || strtolower(substr($expr, $i, 2)) !== 'as') {
            return false;
        }
        if ($i + 2 < $len) {
            $after = $expr[$i + 2];
            if (($after >= 'A' && $after <= 'Z') || ($after >= 'a' && $after <= 'z') || ($after >= '0' && $after <= '9') || $after === '_') {
                return false;
            }
        }
        $i += 2;
        $this->dsSkipWs($expr, $i, $len);
        $first = $this->dsParseVarPath($expr, $i, $len);
        if ($first === false || strpos($first, '[') !== false) {
            return false;
        }
        $this->dsSkipWs($expr, $i, $len);
        $key = null;
        $val = $first;
        if ($i + 1 < $len && $expr[$i] === '=' && $expr[$i + 1] === '>') {
            $i += 2;
            $this->dsSkipWs($expr, $i, $len);
            $second = $this->dsParseVarPath($expr, $i, $len);
            if ($second === false || strpos($second, '[') !== false) {
                return false;
            }
            $key = $first;
            $val = $second;
            $this->dsSkipWs($expr, $i, $len);
        }
        if ($i !== $len) {
            return false;
        }
        return array('source' => $src, 'key' => $key, 'value' => $val);
    }

    private function extract_code($code)
    {
        $pattern = '/(<\?(php|=)?(?:[^\'"\\\\]|\\\\.|\'(?:\\\\.|[^\'])*\'|"(?:\\\\.|[^"])*")*?\?>)/s';

        // Use preg_split to break the string at PHP tags, keeping the delimiters
        $segments = preg_split($pattern, $code, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        // Resulting array
        return $segments;
    }

    public function searchBetween($content, $startstr, $endstr)
    {
        /*
			* Hladame vsetky vyskyty nejakeho sova uzavreteho do inych slov
			* @ napriklad {{layout:hovno}} - vratime slovo "hovno" medzi "{{layout:" a "}}"
		*/
        $navrat = array();
        $startpos = 0;
        $doCycle = 1;
        $pozicia_end = false;
        while ($doCycle == 1 && (($pozicia = strpos($content, $startstr, $startpos)) !== false)) {
            $startpos = $pozicia + strlen($startstr) + 1;
            if ($startpos > strlen($content)) {
                $doCycle = 0;
            } else {
                $pozicia_end = strpos($content, $endstr, $startpos);
                if ($pozicia_end !== false && $pozicia_end > $startpos) {
                    $layoutname = substr($content, $pozicia + strlen($startstr), $pozicia_end - $pozicia - strlen($startstr));
                    $navrat[] = $layoutname;
                }
            }
            $startpos = $pozicia_end + 1;
            if ($pozicia_end === false) $doCycle = 0;
        }
        return $navrat;
    }

    public function searchBetweenPrecise($content, $startstr, $endstr)
    {
        /*
			* Hladame vsetky vyskyty nejakeho sova uzavreteho do inych slov
			* @ napriklad {{layout:hovno}} - vratime slovo "hovno" medzi "{{layout:" a "}}"
		*/
        $navrat = array();
        $startpos = 0;
        $doCycle = 1;
        $keyForArray = 0;
        $pozicia_end = false;
        while ($doCycle == 1 && (($pozicia = strpos($content, $startstr, $startpos)) !== false)) {
            $startpos = $pozicia + strlen($startstr) + 1;
            if ($startpos > strlen($content)) {
                $doCycle = 0;
            } else {
                $pozicia_end = strpos($content, $endstr, $startpos);
                if ($pozicia_end !== false && $pozicia_end > $startpos) {
                    $pozicia_checker = strpos($content, $startstr, $startpos);
                    if ($pozicia_checker !== false && $pozicia_checker < $pozicia_end && $pozicia_checker > $pozicia) {
                        $pozicia = $pozicia_checker;
                    }
                    $layoutname = substr($content, $pozicia + strlen($startstr), $pozicia_end - $pozicia - strlen($startstr));
                    $navrat['hodnoty'][$keyForArray] = $layoutname;
                    $navrat['pozicie'][$keyForArray] = $pozicia;
                    $keyForArray++;
                }
            }
            $startpos = $pozicia_end + 1;
            if ($pozicia_end === false) $doCycle = 0;
        }
        return $navrat;
    }

    private function removeBetween($text, $start, $end)
    {
        return preg_replace('/' . $start . '[\s\S]+?' . $end . '/', '', $text);
    }

    private function replaceBetween($text, $replaceTo, $start, $end)
    {
        return preg_replace('/' . $start . '[\s\S]+?' . $end . '/', $replaceTo, $text);
    }

    public function loadView($view)
    {
        $dir = $this->dirw ?: __ROOTDIR__ . "/app/parts/views/";

        // Load view if it exists
        if ($view !== "" && file_exists($dir . $view . ".view.php")) {
            return file_get_contents($dir . $view . ".view.php");
        }

        // Log warning if primary view doesn't exist
        if ($view !== "") {
            $this->dotApp->logger->warning("Failed to load view: " . $dir . $view . ".view.php", [
                'view' => $view,
                'directory' => $dir
            ]);
        }

        // Try fallback view if defined
        if (isset($this->viewFallbacks[$view]) && $this->viewFallbacks[$view] !== null) {
            $fallbackView = $this->viewFallbacks[$view];
            $fallbackDir = $this->dirw ?: __ROOTDIR__ . "/app/parts/views/";

            if (file_exists($fallbackDir . $fallbackView . ".view.php")) {
                return file_get_contents($fallbackDir . $fallbackView . ".view.php");
            }

            $this->dotApp->logger->warning("Failed to load fallback view: " . $fallbackDir . $fallbackView . ".view.php", [
                'fallbackView' => $fallbackView,
                'directory' => $fallbackDir
            ]);
        }

        return "";
    }

    public function loadViewStatic($view)
    {
        /*
			* @LEN VRATIME VYSTUP Z VIEW - Ak je view staticka stranka
		*/
        ob_start();
        foreach ($this->getViewVars() as $vkey => $vvalue) {
            $$vkey = $vvalue;
        }
        include $this->dirw . $view . ".view.php";
        return ob_get_clean();
    }
}

class RenderingIsolator
{
    private $preneseneFn;

    function __construct($preneseneFn)
    {
        $this->preneseneFn = $preneseneFn;
    }

    public function __debugInfo()
    {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }

    /**
     * Values that must not be extracted into the template sandbox.
     * Closures, invokable objects, and callable arrays are rejected.
     * Plain strings that happen to match PHP function names (time, key, count, copy)
     * are kept. Extract keys must be valid PHP variable names.
     *
     * @param mixed $var
     * @param bool  $isKey
     * @return bool True when the value/key should be skipped
     */
    public static function isRejectedExtractValue($var, $isKey = false)
    {
        if ($isKey) {
            if (is_int($var)) {
                return true;
            }
            if (!is_string($var) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $var)) {
                return true;
            }
            return false;
        }
        if ($var instanceof \Closure) {
            return true;
        }
        if (is_object($var)) {
            if (is_callable($var)) {
                return true;
            }
            foreach ((array) $var as $item) {
                if (self::isRejectedExtractValue($item, false)) {
                    return true;
                }
            }
            return false;
        }
        if (is_array($var)) {
            if (self::isCallableArrayPair($var)) {
                return true;
            }
            foreach ($var as $item) {
                if (self::isRejectedExtractValue($item, false)) {
                    return true;
                }
            }
            return false;
        }
        return false;
    }

    /**
     * True for [$object, 'method'] / ['Class', 'method'] callable pairs, not for
     * ordinary lists such as ['time', 'count'].
     *
     * @param array $var
     * @return bool
     */
    private static function isCallableArrayPair($var)
    {
        if (!is_array($var) || count($var) !== 2) {
            return false;
        }
        if (!array_key_exists(0, $var) || !array_key_exists(1, $var)) {
            return false;
        }
        if (!is_string($var[1])) {
            return false;
        }
        if (is_object($var[0]) && method_exists($var[0], $var[1])) {
            return true;
        }
        if (is_string($var[0]) && class_exists($var[0], false) && method_exists($var[0], $var[1])) {
            return true;
        }
        return false;
    }

    public function escapePHP($code)
    {
        if (empty($code) || !is_string($code)) {
            return '';
        }

        $protected = [];
        $counter = 0;

        $code = preg_replace_callback(
            '/<\?xml\s[^>]*\?>/i',
            function ($matches) use (&$protected, &$counter) {
                $key = '%%XML_' . $counter . '%%';
                $protected[$key] = $matches[0];
                $counter++;
                return $key;
            },
            $code
        );

        $code = preg_replace('/<\?php\b.*?\?>/is', '', $code);
        $code = preg_replace('/<\?=.*?\?>/is', '', $code);
        $code = preg_replace('/<\?\s+.*?\?>/is', '', $code);
        $code = preg_replace('/<\?(?!xml).*?\?>/is', '', $code);
        $code = preg_replace('/<script\s+[^>]*language\s*=\s*["\']?php["\']?[^>]*>.*?<\/script>/is', '', $code);
        $code = preg_replace('/<%.*?%>/is', '', $code);

        $code = str_replace(array_keys($protected), array_values($protected), $code);

        return $code;
    }

    public static function phpsandbox_disabled()
    {
        $disable = [
            'eval',
            'exec',
            'system',
            'shell_exec',
            'passthru',
            'proc_open',
            'popen',
            'pcntl_exec',
            'unlink',
            'rmdir',
            'rename',
            'copy',
            'chmod',
            'chown',
            'chgrp',
            'file_get_contents',
            'file_put_contents',
            'file',
            'fopen',
            'fread',
            'fwrite',
            'fclose',
            'fgets',
            'fputcsv',
            'file_exists',
            'is_readable',
            'is_writable',
            'is_executable',
            'mkdir',
            'touch',
            'move_uploaded_file',
            'symlink',
            'link',
            'readfile',
            'opendir',
            'readdir',
            'scandir',
            'dir',
            'glob',
            'parse_ini_file',
            'fileinfo',
            'fsockopen',
            'pfsockopen',
            'curl_exec',
            'curl_multi_exec',
            'curl_init',
            'curl_setopt',
            'curl_setopt_array',
            'stream_socket_client',
            'stream_socket_server',
            'stream_socket_enable_crypto',
            'get_headers',
            'socket_create',
            'socket_connect',
            'socket_write',
            'socket_read',
            'socket_recv',
            'socket_send',
            'phpinfo',
            'getenv',
            'putenv',
            'get_current_user',
            'getmyuid',
            'getmypid',
            'getmygid',
            'getrusage',
            'sys_getloadavg',
            'dl',
            'pcntl_fork',
            'pcntl_signal',
            'pcntl_wait',
            'pcntl_waitpid',
            'pcntl_wifexited',
            'pcntl_wifstopped',
            'pcntl_wifsignaled',
            'pcntl_wexitstatus',
            'pcntl_wtermsig',
            'pcntl_wstopsig',
            'pcntl_alarm',
            'pcntl_exec',
            'pcntl_getpriority',
            'pcntl_setpriority',
            'pcntl_sigprocmask',
            'pcntl_sigtimedwait',
            'pcntl_sigwaitinfo',
            'pcntl_strerror',
            'pcntl_unshare',
            'create_function',
            'call_user_func',
            'call_user_func_array',
            'register_shutdown_function',
            'register_tick_function',
            'mail',
            'header',
            'headers_list',
            'headers_sent',
            'extract',
            'parse_str',
            'http_response_code'
        ];
        return ($disable);
    }

    public function sanitizePHP($code)
    {
        $pattern = '/\b(' . implode('|', SELF::phpsandbox_disabled()) . ')\b\s*\(/i';
        return preg_replace($pattern, '', $code);
    }

    /*
		Pouzijeme vstavany PHP tokenizer aby sme zistili ktore funkcie sa pouzivaju a nebezpecne odstranili 
	*/
    private function sanitizeMIXED($html)
    {
        $tokens = token_get_all($html);
        $sanitizedContent = '';
        $insidePhp = false;
        $phpCode = '';

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $tokenType = $token[0];
                $tokenValue = $token[1];

                if ($tokenType == T_OPEN_TAG || $tokenType == T_OPEN_TAG_WITH_ECHO) {
                    if ($insidePhp) {
                        $sanitizedContent .= $this->sanitizePHP($phpCode);
                        $phpCode = '';
                    }
                    $insidePhp = true;
                    $sanitizedContent .= $tokenValue;
                } elseif ($tokenType == T_CLOSE_TAG) {
                    if ($insidePhp) {
                        $sanitizedContent .= $this->sanitizePHP($phpCode);
                        $phpCode = '';
                        $insidePhp = false;
                    }
                    $sanitizedContent .= $tokenValue;
                } elseif ($insidePhp) {
                    $phpCode .= $tokenValue;
                } else {
                    $sanitizedContent .= $tokenValue;
                }
            } else {
                if ($insidePhp) {
                    $phpCode .= $token;
                } else {
                    $sanitizedContent .= $token;
                }
            }
        }

        if ($insidePhp && $phpCode) {
            $sanitizedContent .= $this->sanitizePHP($phpCode);
        }

        return $sanitizedContent;
    }

    public function render($vars, $code)
    {
        ob_start();
        $code = $this->sanitizeMIXED($code);

        (function () use ($vars, $code) {
            global $translator; // Consider removing or passing via $vars

            $canrewrite = function ($filePath) {
                if (file_exists($filePath)) {
                    $ageoffile = time() - filemtime($filePath);
                    return $ageoffile >= 5;
                }
                return true;
            };

            $recursively_noncallable_check = function ($var, $isKey = false) use (&$recursively_noncallable_check) {
                return !RenderingIsolator::isRejectedExtractValue($var, $isKey);
            };

            foreach ($vars as $vkey => $vvalue) {
                if ($recursively_noncallable_check($vvalue, false) && $recursively_noncallable_check($vkey, true)) {
                    $$vkey = $vvalue;
                }
            }

            $namespace = "a" . md5(rand(120000, 800000)) . "\\b" . md5(rand(120000, 800000)) . "\\c" . md5(rand(120000, 800000)) . "\\d" . md5(rand(120000, 800000)) . "\\e" . md5(rand(120000, 800000));
            $namespace = '<?php namespace ' . $namespace . ';?>';

            $code = $namespace . $code;

            $dotapp236365b0b1631351e99daf046d18d2bcEcnrypt = $this->preneseneFn['encrypt'];
            $__ds_filter = isset($this->preneseneFn['filter']) ? $this->preneseneFn['filter'] : function ($v) {
                return $v;
            };

            $userkey = defined('__ENC_KEY__') ? constant('__ENC_KEY__') : '';
            $renderToFile = defined('__RENDER_TO_FILE__') && __RENDER_TO_FILE__;

            if ($renderToFile) {
                $file_i = 0;
                $sessionId = (string) DSM::use()->session_id();
                $renderHash = md5($userkey . $sessionId . $userkey);
                $filename = __ROOTDIR__ . '/app/runtime/generator/rendering_' . $renderHash . '_' . $file_i . '.php';
                while (!$canrewrite($filename)) {
                    $file_i++;
                    $filename = __ROOTDIR__ . '/app/runtime/generator/rendering_' . $renderHash . '_' . $file_i . '.php';
                }

                try {
                    file_put_contents($filename, $code);
                    chmod($filename, 0644);
                    include $filename;
                    unlink($filename);
                } catch (\Exception $e) {
                    ob_start();
                    $result = eval('?>' . $code . '<?php');
                    $output = ob_get_clean();
                    if ($result === false && $error = error_get_last()) {
                        echo 'ERROR WHILE EVAL: ' . $error['message'];
                    } else {
                        echo $output;
                    }
                }
            } else {
                ob_start();
                $result = eval('?>' . $code . '<?php');
                $output = ob_get_clean();
                if ($result === false && $error = error_get_last()) {
                    echo 'ERROR WHILE EVAL: ' . $error['message'];
                } else {
                    echo $output;
                }
            }
        })();

        return ob_get_clean();
    }
}

/**
 * Object representation of an HTML fragment (Superblock).
 * Uses unique IDs ($this->id) for variable sandboxing.
 * * Example:
 * echo $block['user_card']
 * ->set("name", "John")
 * ->set("role", "Admin")
 * ->html();
 */
class PrivateBlock
{
    private $block;
    private $variables;
    private $id;

    function __construct($block)
    {
        $this->block = $block;
        $this->variables = array();
        $this->id = "pb" . md5($block) . md5(rand(100000, 200000) . rand(100000, 200000) . rand(100000, 200000));
    }

    public function get($name)
    {
        if (isset($this->variables[$name])) return ($this->variables[$name]);
        else return null;
    }

    public function set($name, $value)
    {
        if (!RenderingIsolator::isRejectedExtractValue($value, false) && !RenderingIsolator::isRejectedExtractValue($name, true)) {
            $this->variables[$name] = $value;
        }
        return $this;
    }

    public function html($html = "")
    {
        if ($html != "") {
            $this->block = $html;
            return ($this);
        } else {
            $html = "";
            foreach ($this->variables as $name => $value) {
                if (is_array($value)) {
                    $html .= '<?php $' . $this->id . $name . ' = json_decode(base64_decode("' . base64_encode(json_encode($value)) . '"),true);?>';
                } elseif (is_object($value)) {
                    $html .= '<?php $' . $this->id . $name . ' = json_decode(base64_decode("' . base64_encode(json_encode($value)) . '"));?>';
                } else {
                    $html .= '<?php $' . $this->id . $name . ' = base64_decode("' . base64_encode($value) . '");?>';
                }
            }
            $html .= "\n" . str_replace("{{ var: $", "{{ var: $" . $this->id, $this->block);
            $html = str_replace("{{_ var: $", "{{_ var: $" . $this->id, $html);
            $html = str_replace("{{ foreach $", "{{ foreach $" . $this->id, $html);
            $html = str_replace("{{ foreach ($", "{{ foreach ($" . $this->id, $html);

            return ($html);
        }
    }
}
