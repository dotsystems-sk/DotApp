<?php
/**
 * Standalone regression harness for the project-root Renderer.php candidate.
 *
 * Loads C:\wamp\tester\Renderer.php only. Does not include app/parts/Renderer.php
 * and does not write under app/.
 *
 * Run: php Renderer.test.php
 */

namespace Dotsystems\App {
    class DotApp
    {
        /** @var DotApp|null */
        private static $instance = null;

        public $CSRF = 'test-csrf';
        public $logger;
        public $bridge;
        public $customRenderer;
        public $router;

        public static function dotApp()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public static function resetHarness()
        {
            self::$instance = null;
        }

        public function __construct()
        {
            $this->logger = new HarnessLogger();
            $this->bridge = new HarnessBridge();
            $this->customRenderer = new HarnessCustomRenderer();
            $this->router = new HarnessRouter();
        }

        public function encrypt($text, $key2 = '')
        {
            return 'ENC(' . (string) $key2 . ':' . (string) $text . ')';
        }
    }

    class HarnessLogger
    {
        public $warnings = array();

        public function warning($message, $context = array())
        {
            $this->warnings[] = array('message' => $message, 'context' => $context);
        }
    }

    class HarnessBridge
    {
        public $calls = 0;

        public function dotBridge($code)
        {
            $this->calls++;
            return $code;
        }
    }

    class HarnessRouter
    {
        public $request;

        public function __construct()
        {
            $this->request = new HarnessRequest();
        }
    }

    class HarnessRequest
    {
        public function getPath()
        {
            return '/harness/path';
        }
    }

    class HarnessCustomRenderer
    {
        public $renderers = array();
        public $blockMap = array();

        public function addRenderer($name, $renderer)
        {
            $this->renderers[$name] = $renderer;
        }

        public function getRenderer($name)
        {
            return isset($this->renderers[$name]) ? $this->renderers[$name] : false;
        }

        public function renderWith($name, $code)
        {
            if (!isset($this->renderers[$name]) || !is_callable($this->renderers[$name])) {
                throw new \Exception('Renderer ' . $name . ' does not exist');
            }
            return call_user_func($this->renderers[$name], $code);
        }

        public function customRenderers()
        {
            return $this->renderers;
        }

        public function addBlock($name, $blockFn)
        {
            $this->blockMap[$name] = $blockFn;
        }

        public function blocks($name)
        {
            return isset($this->blockMap[$name]) ? $this->blockMap[$name] : null;
        }
    }
}

namespace Dotsystems\App\Parts {
    class Input
    {
        public function formFunction($action, $method, $formName, $renderer)
        {
            return 'FORM[' . $formName . '|' . $method . '|' . $action . ']';
        }
    }
}

namespace {
    $translator = function ($text) {
        return 'T(' . (string) $text . ')';
    };

    $rootDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dotapp_renderer_candidate_' . getmypid();
    if (!is_dir($rootDir) && !mkdir($rootDir, 0777, true) && !is_dir($rootDir)) {
        fwrite(STDERR, "Cannot create temp root\n");
        exit(2);
    }
    if (!defined('__ROOTDIR__')) {
        define('__ROOTDIR__', $rootDir);
    }

    require __DIR__ . DIRECTORY_SEPARATOR . 'Renderer.php';

    use Dotsystems\App\DotApp;
    use Dotsystems\App\Parts\Renderer;
    use Dotsystems\App\Parts\RendererSyntaxException;
    use Dotsystems\App\Parts\RenderingIsolator;

    $failures = array();
    $passes = 0;

    /**
     * @param string $name
     * @param bool   $ok
     * @param string $detail
     * @return void
     */
    function ds_check($name, $ok, $detail = '')
    {
        global $failures, $passes;
        if ($ok) {
            $passes++;
            echo "PASS  $name\n";
            return;
        }
        $failures[] = $name . ($detail !== '' ? (': ' . $detail) : '');
        echo "FAIL  $name" . ($detail !== '' ? (': ' . $detail) : '') . "\n";
    }

    /**
     * @return Renderer
     */
    function ds_renderer()
    {
        return new Renderer();
    }

    /**
     * @param string $tpl
     * @param array  $vars
     * @param bool   $eval
     * @return string
     */
    function ds_run($tpl, $vars = array(), $eval = true)
    {
        return ds_renderer()->renderCode($tpl, $vars, $eval);
    }

    /**
     * Custom renderers (including the built-in block processor) run on the
     * view/layout path, not on renderCode(). This helper applies them first.
     *
     * @param Renderer $renderer
     * @param string   $tpl
     * @param array    $vars
     * @return string
     */
    function ds_run_with_renderers($renderer, $tpl, $vars = array())
    {
        foreach ($renderer->customRenderers() as $fn) {
            $tpl = call_user_func($fn, $tpl, $vars);
        }
        return $renderer->renderCode($tpl, $vars, true);
    }

    /**
     * Runs a layout string through renderLayout() so privateblock is processed.
     *
     * @param string $tpl
     * @param array  $vars
     * @return string
     */
    function ds_run_layout($tpl, $vars = array())
    {
        $renderer = ds_renderer();
        $renderer->module('Harness');
        $dir = __ROOTDIR__ . '/app/modules/Harness/views/layouts';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $name = 't' . str_replace('.', '', uniqid('', true));
        file_put_contents($dir . '/' . $name . '.layout.php', $tpl);
        $renderer->setLayout($name);
        foreach ($vars as $key => $value) {
            $renderer->setLayoutVar($key, $value);
        }
        return $renderer->renderLayout();
    }

    /**
     * Recursively delete a directory created for this harness.
     *
     * @param string $dir
     * @return void
     */
    function ds_rrmdir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                ds_rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    // --- Public API / named instances -------------------------------------------------

    $a = Renderer::new('named-one');
    $b = Renderer::new('named-one');
    ds_check('named renderer instances are reused', $a === $b);

    $r = ds_renderer();
    $r->setView('page');
    $r->setViewVar('title', 'Hello');
    ds_check('setViewVar/getViewVar', $r->getViewVar('title') === 'Hello');
    $r->setLayout('shell');
    $r->setLayoutVar('aside', 'Side');
    ds_check('setLayoutVar/getLayoutVar', $r->getLayoutVar('aside') === 'Side');

    // --- Legacy output / if / foreach / translation / enc -----------------------------

    ds_check(
        'unfiltered var is raw echo',
        ds_run('{{ var: $title }}', array('title' => '<b>x</b>')) === '<b>x</b>'
    );
    ds_check(
        'var without space after colon',
        ds_run('{{ var:$title }}', array('title' => 'OK')) === 'OK'
    );
    ds_check(
        'var array path',
        ds_run('{{ var: $user[\'name\'] }}', array('user' => array('name' => 'Ann'))) === 'Ann'
    );
    ds_check(
        'if $x style',
        ds_run('{{ if $on }}Y{{ else }}N{{ /if }}', array('on' => 1)) === 'Y'
    );
    ds_check(
        'if ($x == 1) style',
        ds_run('{{ if ($flag == 1) }}Y{{ else }}N{{ /if }}', array('flag' => 1)) === 'Y'
    );
    ds_check(
        'elseif branch',
        ds_run('{{ if ($n == 1) }}A{{ elseif ($n == 2) }}B{{ else }}C{{ /if }}', array('n' => 2)) === 'B'
    );

    $nested = ds_run(
        '{{ foreach $groups as $g }}[{{ var: $g[\'n\'] }}:' .
        '{{ foreach $g[\'items\'] as $it }}{{ var: $it }}{{ /foreach }}]{{ /foreach }}',
        array(
            'groups' => array(
                array('n' => 'A', 'items' => array('1', '2')),
                array('n' => 'B', 'items' => array('3')),
            ),
        )
    );
    ds_check('nested foreach', $nested === '[A:12][B:3]');

    ds_check(
        'translation literal',
        ds_run('{{_ "Login" }}') === 'T(Login)'
    );
    ds_check(
        'translation var',
        ds_run('{{_ var: $msg }}', array('msg' => 'Save')) === 'T(Save)'
    );

    ds_check(
        'enc literal is compile-time',
        ds_run('{{ enc: "secret" }}') === 'ENC(:secret)'
    );
    ds_check(
        'enc dotted context key',
        ds_run('{{ enc(DACore.role.id): $id }}', array('id' => '7')) === 'ENC(DACore.role.id:7)'
    );

    $compiledIf = ds_run('{{ if $x }}Z{{ /if }}', array(), false);
    ds_check(
        'if compiles to PHP if',
        strpos($compiledIf, 'if (') !== false && strpos($compiledIf, 'endif') !== false
    );

    // --- New foreach forms / $loop / else / break / continue --------------------------

    ds_check(
        'parenthesized foreach',
        ds_run('{{ foreach ($items as $item) }}{{ var: $item }}{{ /foreach }}', array('items' => array('a', 'b'))) === 'ab'
    );
    ds_check(
        'key-value foreach',
        ds_run(
            '{{ foreach $items as $k => $v }}{{ var: $k }}={{ var: $v }};{{ /foreach }}',
            array('items' => array('x' => '1', 'y' => '2'))
        ) === 'x=1;y=2;'
    );
    ds_check(
        'parenthesized key-value foreach',
        ds_run(
            '{{ foreach ($items as $k => $v) }}{{ var: $k }}{{ var: $v }}{{ /foreach }}',
            array('items' => array('a' => '1'))
        ) === 'a1'
    );

    $deep = array('rows' => array(array('name' => 'N'), array('name' => 'M')));
    ds_check(
        'foreach deep quoted key',
        ds_run('{{ foreach $data[\'rows\'] as $row }}{{ var: $row[\'name\'] }}{{ /foreach }}', array('data' => $deep)) === 'NM'
    );
    ds_check(
        'foreach numeric index',
        ds_run('{{ foreach $rows[0] as $v }}{{ var: $v }}{{ /foreach }}', array('rows' => array(array('q', 'r')))) === 'qr'
    );
    ds_check(
        'foreach dynamic index',
        ds_run(
            '{{ foreach $bags[$which] as $v }}{{ var: $v }}{{ /foreach }}',
            array('bags' => array('sel' => array('Z')), 'which' => 'sel')
        ) === 'Z'
    );
    ds_check(
        'parenthesized foreach ignores parentheses inside quoted keys',
        ds_run(
            '{{ foreach ($bags[")"] as $v) }}{{ var: $v }}{{ /foreach }}',
            array('bags' => array(')' => array('P')))
        ) === 'P'
    );

    $loopOut = ds_run(
        '{{ foreach $items as $item }}{{ var: $loop[\'index\'] }}:{{ var: $loop[\'iteration\'] }}:{{ var: $loop[\'first\'] }}:{{ var: $loop[\'last\'] }}:{{ var: $loop[\'count\'] }};{{ /foreach }}',
        array('items' => array('a', 'b', 'c'))
    );
    ds_check(
        'loop metadata',
        $loopOut === '0:1:1:0:3;1:2:0:0:3;2:3:0:1:3;'
    );

    $parentLoop = ds_run(
        '{{ foreach $outer as $o }}{{ foreach $o as $i }}{{ var: $loop[\'parent\'][\'index\'] }}-{{ var: $loop[\'index\'] }};{{ /foreach }}{{ /foreach }}',
        array('outer' => array(array('a', 'b'), array('c')))
    );
    ds_check('nested loop parent index', $parentLoop === '0-0;0-1;1-0;');

    ds_check(
        'foreach else empty',
        ds_run('{{ foreach $items as $item }}Y{{ else }}N{{ /foreach }}', array('items' => array())) === 'N'
    );
    ds_check(
        'foreach else skipped when items exist',
        ds_run('{{ foreach $items as $item }}Y{{ else }}N{{ /foreach }}', array('items' => array(1))) === 'Y'
    );

    ds_check(
        'break',
        ds_run('{{ foreach $items as $n }}{{ var: $n }}{{ break }}{{ /foreach }}', array('items' => array('1', '2', '3'))) === '1'
    );
    ds_check(
        'conditional break',
        ds_run('{{ foreach $items as $n }}{{ var: $n }}{{ break $n == 2 }}{{ /foreach }}', array('items' => array(1, 2, 3))) === '12'
    );
    ds_check(
        'continue',
        ds_run('{{ foreach $items as $n }}{{ continue $n == 2 }}{{ var: $n }}{{ /foreach }}', array('items' => array(1, 2, 3))) === '13'
    );

    try {
        ds_run('{{ break }}');
        ds_check('break outside loop throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check(
            'break outside loop throws',
            $e->getDirective() === 'break' && $e->getTemplateLine() === 1
        );
    }

    // --- Filters ----------------------------------------------------------------------

    ds_check(
        'escape filter',
        ds_run('{{ var: $title | escape }}', array('title' => '<b>')) === htmlspecialchars('<b>', ENT_QUOTES, 'UTF-8')
    );
    ds_check(
        'escape filter substitutes malformed UTF-8',
        ds_run('{{ var: $title | escape }}', array('title' => "\xC3")) !== ''
    );
    ds_check(
        'default filter',
        ds_run('{{ var: $title | default("n/a") }}', array('title' => '')) === 'n/a'
    );
    ds_check(
        'number filter',
        ds_run('{{ var: $n | number(2) }}', array('n' => 12.5)) === number_format(12.5, 2, '.', ',')
    );
    ds_check(
        'date filter',
        ds_run('{{ var: $ts | date("Y-m-d") }}', array('ts' => strtotime('2020-01-02 00:00:00'))) === '2020-01-02'
    );
    ds_check(
        'join filter',
        ds_run('{{ var: $a | join(";") }}', array('a' => array('p', 'q'))) === 'p;q'
    );
    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    ds_check(
        'json filter',
        ds_run('{{ var: $a | json }}', array('a' => array('k' => 'v'))) === json_encode(array('k' => 'v'), $jsonFlags)
    );
    $xssJson = ds_run('{{ var: $a | json }}', array('a' => '</script><img src=x onerror=1>'));
    ds_check(
        'json filter hex-encodes HTML',
        $xssJson === json_encode('</script><img src=x onerror=1>', $jsonFlags)
        && strpos($xssJson, '</script>') === false
        && strpos($xssJson, '<') === false
    );
    ds_check(
        'urlencode filter',
        ds_run('{{ var: $s | urlencode }}', array('s' => 'a b')) === rawurlencode('a b')
    );
    ds_check(
        'upper/lower filters',
        ds_run('{{ var: $s | upper }}-{{ var: $s | lower }}', array('s' => 'Ab')) === 'AB-ab'
    );

    $rf = ds_renderer();
    $rf->addFilter('wrap', function ($value, $mark = '*') {
        return $mark . $value . $mark;
    });
    ds_check(
        'custom filter registration',
        $rf->renderCode('{{ var: $s | wrap("#") }}', array('s' => 'Q'), true) === '#Q#'
    );
    ds_check('getFilter returns callable', is_callable($rf->getFilter('wrap')));
    $other = ds_renderer();
    $rf->addFilter('suffix', function ($value) {
        return $value . '!';
    });
    ds_check(
        'custom filter is request-global',
        $other->renderCode('{{ var: $s | suffix }}', array('s' => 'ok'), true) === 'ok!'
        && is_callable($other->getFilter('suffix'))
    );

    try {
        ds_run('{{ var: $s | not_a_filter }}', array('s' => 'x'));
        ds_check('unknown filter throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check('unknown filter throws', $e->getDirective() === 'not_a_filter' && $e->getTemplateLine() === 1);
    }

    ds_check(
        'unfiltered var still raw after filters exist',
        ds_run('{{ var: $title }}', array('title' => '<i>')) === '<i>'
    );

    // --- raw / comment ----------------------------------------------------------------

    ds_check(
        'raw preserves tags',
        ds_run('{{ raw }}{{ var: $title }}{{ /raw }}', array('title' => 'NO')) === '{{ var: $title }}'
    );
    ds_check(
        'nested raw',
        ds_run('{{ raw }}A{{ raw }}B{{ /raw }}C{{ /raw }}') === 'ABC'
    );
    ds_check(
        'comment removed',
        ds_run('A{{ comment }}secret{{ /comment }}B') === 'AB'
    );
    ds_check(
        'nested comment',
        ds_run('A{{ comment }}x{{ comment }}y{{ /comment }}z{{ /comment }}B') === 'AB'
    );

    // --- Blocks -----------------------------------------------------------------------

    $rb = ds_renderer();
    $rb->addBlock('wrap', function ($inner, $args) {
        $p = isset($args[0]) ? $args[0] : '';
        return '[' . $p . $inner . $p . ']';
    });
    $rb->addBlock('note', function ($inner, $args) {
        $arg = isset($args[0]) ? $args[0] : '';
        return '{' . $arg . ':' . $inner . '}';
    });
    ds_check(
        'nested same-name blocks',
        ds_run_with_renderers($rb, '{{ block:wrap(a) }}{{ block:wrap(b) }}X{{ /block:wrap }}{{ /block:wrap }}') === '[a[bXb]a]'
    );
    ds_check(
        'quoted block args keep commas',
        ds_run_with_renderers($rb, '{{ block:note("hello, world") }}Z{{ /block:note }}') === '{hello, world:Z}'
    );

    $missing = ds_run_with_renderers($rb, '{{ block:missing }}X{{ /block:missing }}');
    ds_check(
        'undefined block uses block name not undefined $block',
        strpos($missing, 'blockerror:missing') !== false
    );
    ds_check(
        'nested different-name blocks',
        ds_run_with_renderers($rb, '{{ block:wrap(a) }}{{ block:note("z") }}X{{ /block:note }}{{ /block:wrap }}') === '[a{z:X}a]'
    );

    try {
        ds_run_with_renderers($rb, '{{ /block:wrap }}');
        ds_check('orphan block close throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check('orphan block close throws', $e->getDirective() === '/block' && $e->getTemplateLine() === 1);
    }
    try {
        ds_run_with_renderers($rb, '{{ block:wrap }}X{{ /block:note }}');
        ds_check('mismatched block close throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check('mismatched block close throws', $e->getDirective() === '/block' && $e->getTemplateLine() === 1);
    }
    try {
        ds_run_with_renderers($rb, '{{ block:wrap }}{{ block:note }}X{{ /block:wrap }}{{ /block:note }}');
        ds_check('crossing blocks throw', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check('crossing blocks throw', $e->getDirective() === '/block');
    }
    try {
        ds_run_with_renderers($rb, "{{ block:wrap }}\nX");
        ds_check('unclosed block throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check('unclosed block throws', $e->getDirective() === 'block' && $e->getTemplateLine() === 1);
    }

    // --- PHP islands ------------------------------------------------------------------

    $island = ds_run("<?php echo '{{ var: \$title }}'; ?>{{ var: \$title }}", array('title' => 'Hello'));
    ds_check('PHP island not rewritten', $island === '{{ var: $title }}Hello');
    $islandClose = ds_run("<?php echo '?>'; echo '{{ var: \$x }}'; ?>DONE{{ var: \$x }}", array('x' => 'Z'));
    ds_check(
        'PHP island keeps ?> inside quoted string',
        $islandClose === '?>{{ var: $x }}DONEZ'
    );
    $islandComment = ds_run("<?php /* ?> {{ var: \$x }} */ echo 'Q'; ?>{{ var: \$x }}", array('x' => 'Z'));
    ds_check(
        'PHP island keeps ?> inside comment',
        $islandComment === 'QZ'
    );

    // --- Sandbox extract --------------------------------------------------------------

    $kept = ds_run(
        '{{ foreach $rows as $row }}{{ var: $row[\'time\'] }}-{{ var: $row[\'key\'] }}-{{ var: $row[\'count\'] }}-{{ var: $row[\'copy\'] }};{{ /foreach }}',
        array(
            'rows' => array(
                array('time' => 'morning', 'key' => 'k', 'count' => '2', 'copy' => 'c'),
            ),
        )
    );
    ds_check('callable-looking strings kept', $kept === 'morning-k-2-c;');

    ds_check(
        'plain string time kept as value',
        ds_run('{{ var: $stamp }}', array('stamp' => 'time')) === 'time'
    );

    $closureBag = ds_run(
        '{{ foreach $rows as $row }}X{{ else }}EMPTY{{ /foreach }}',
        array('rows' => array(function () {
            return 1;
        }))
    );
    ds_check('actual Closure dropped', $closureBag === 'EMPTY');

    $inv = new class {
        public function __invoke()
        {
            return 1;
        }
    };
    ds_check(
        'invokable object dropped',
        ds_run('{{ if isset($obj) }}Y{{ else }}N{{ /if }}', array('obj' => $inv)) === 'N'
    );

    $pair = ds_run(
        '{{ if isset($cb) }}Y{{ else }}N{{ /if }}',
        array('cb' => array($r, 'renderCode'))
    );
    ds_check('callable array dropped', $pair === 'N');

    ds_check(
        'ordinary two-string list kept',
        ds_run('{{ var: $pair[0] }}', array('pair' => array('time', 'count'))) === 'time'
    );

    ds_check(
        'invalid extract key skipped',
        RenderingIsolator::isRejectedExtractValue('1abc', true) === true
        && RenderingIsolator::isRejectedExtractValue('title', true) === false
    );

    // --- Syntax errors / line reporting / no data leak --------------------------------

    try {
        ds_run("{{ if \$x }}\nhello");
        ds_check('unclosed if throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check(
            'unclosed if throws with line',
            $e->getDirective() === 'if' && $e->getTemplateLine() === 1
            && strpos($e->getMessage(), 'secret') === false
        );
    }

    try {
        ds_run("line1\n{{ foreach \$a as \$b }}\n{{ /if }}");
        ds_check('mismatched /if throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check(
            'mismatched /if reports line 3',
            $e->getDirective() === '/if' && $e->getTemplateLine() === 3
        );
    }

    try {
        ds_run("{{ foreach \$items as \$item }}\n{{ var: \$item }}");
        ds_check('unclosed foreach throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check(
            'unclosed foreach throws',
            $e->getDirective() === 'foreach' && $e->getTemplateLine() === 1
        );
    }

    try {
        ds_run('{{ foreach ($items as $item) }}x');
        ds_check('unclosed parenthesized foreach throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check(
            'unclosed parenthesized foreach throws',
            $e->getDirective() === 'foreach' && $e->getTemplateLine() === 1
        );
    }

    try {
        ds_run("{{ comment }}\nstill open");
        ds_check('unclosed comment throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check(
            'unclosed comment reports open line',
            $e->getDirective() === 'comment' && $e->getTemplateLine() === 1
        );
    }

    try {
        ds_run("{{ raw }}\nstill open");
        ds_check('unclosed raw throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check(
            'unclosed raw reports open line',
            $e->getDirective() === 'raw' && $e->getTemplateLine() === 1
        );
    }

    try {
        ds_run('{{ var: $1abc }}');
        ds_check('digit-leading var path throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check(
            'digit-leading var path throws',
            $e->getDirective() === 'var' && $e->getTemplateLine() === 1
        );
    }
    try {
        ds_run('{{ foreach $1abc as $item }}x{{ /foreach }}');
        ds_check('digit-leading foreach path throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check(
            'digit-leading foreach path throws',
            $e->getDirective() === 'foreach' && $e->getTemplateLine() === 1
        );
    }
    try {
        ds_run('{{ var: $_hidden }}');
        ds_check('underscore var path still rejected', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check(
            'underscore var path still rejected',
            $e->getDirective() === 'var' && $e->getTemplateLine() === 1
        );
    }

    try {
        ds_run('{{ foreach $items as $item }}Y{{ else }}{{ break }}N{{ /foreach }}', array('items' => array()));
        ds_check('break in foreach-else throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check('break in foreach-else throws', $e->getDirective() === 'break' && $e->getTemplateLine() === 1);
    }
    try {
        ds_run('{{ foreach $items as $item }}Y{{ else }}{{ continue }}N{{ /foreach }}', array('items' => array()));
        ds_check('continue in foreach-else throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check('continue in foreach-else throws', $e->getDirective() === 'continue' && $e->getTemplateLine() === 1);
    }
    ds_check(
        'break in foreach-else allowed when outer loop is active',
        ds_run(
            '{{ foreach $outer as $o }}{{ var: $o }}{{ foreach $empty as $i }}x{{ else }}{{ break }}{{ /foreach }}{{ /foreach }}',
            array('outer' => array(1, 2), 'empty' => array())
        ) === '1'
    );
    ds_check(
        'continue in foreach-else uses outer loop',
        ds_run(
            '{{ foreach $outer as $o }}{{ var: $o }}{{ foreach $empty as $i }}x{{ else }}{{ continue }}{{ /foreach }}Z{{ /foreach }}',
            array('outer' => array(1, 2), 'empty' => array())
        ) === '12'
    );

    ds_check(
        'legacy while',
        ds_run('{{ while $i < 3 }}{{ var: $i }}<?php $i++; ?>{{ /while }}', array('i' => 0)) === '012'
    );
    ds_check(
        'double-quoted var path',
        ds_run('{{ var: $user["name"] }}', array('user' => array('name' => 'Ann'))) === 'Ann'
    );
    ds_check(
        'nested if/else inside foreach',
        ds_run(
            '{{ foreach $items as $item }}{{ if $item == 1 }}A{{ else }}B{{ /if }}{{ /foreach }}',
            array('items' => array(1, 2))
        ) === 'AB'
    );

    try {
        ds_run_layout('{{ /privateblock }}');
        ds_check('orphan privateblock close throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check('orphan privateblock close throws', $e->getDirective() === '/privateblock' && $e->getTemplateLine() === 1);
    }
    try {
        ds_run_layout('{{ privateblock:row }}A{{ privateblock:cell }}B{{ /privateblock }}{{ /privateblock }}');
        ds_check('nested privateblock throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check('nested privateblock throws', $e->getDirective() === 'privateblock');
    }
    try {
        ds_run_layout("{{ privateblock:row }}\nX");
        ds_check('unclosed privateblock throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check('unclosed privateblock throws', $e->getDirective() === 'privateblock' && $e->getTemplateLine() === 1);
    }
    try {
        $privateDashed = ds_run_layout('{{ privateblock:user-card }}X{{ /privateblock }}OK');
        ds_check('dashed privateblock name remains compatible', $privateDashed === 'OK');
    } catch (RendererSyntaxException $e) {
        ds_check('dashed privateblock name remains compatible', false, $e->getMessage());
    }
    try {
        ds_run_layout('{{ privateblock:bad name }}X{{ /privateblock }}');
        ds_check('invalid privateblock name throws', false, 'no exception');
    } catch (RendererSyntaxException $e) {
        ds_check('invalid privateblock name throws', $e->getDirective() === 'privateblock' && $e->getTemplateLine() === 1);
    }

    $unknown = ds_run('{{ CSRF }}{{ mystery: 1 }}{{ formName(x) }}', array(), false);
    ds_check(
        'unknown tags remain for later pipeline stages',
        strpos($unknown, 'test-csrf') !== false
        && strpos($unknown, '{{ mystery: 1 }}') !== false
        && strpos($unknown, '{{ formName(x) }}') !== false
    );

    $form = ds_run('<form method="POST" action="/save">{{ formName(saveItem) }}</form>');
    ds_check('formName inside form', strpos($form, 'FORM[saveItem|POST|/save]') !== false);

    $formLeave = ds_run('{{ formName(saveItem) }}');
    ds_check('formName without form left unchanged', strpos($formLeave, '{{ formName(saveItem) }}') !== false);

    // --- layout / content + custom renderer exactly-once ------------------------------

    $modRoot = __ROOTDIR__ . '/app/modules/Harness/views';
    $layRoot = $modRoot . '/layouts';
    if (!is_dir($layRoot)) {
        mkdir($layRoot, 0777, true);
    }
    file_put_contents($modRoot . '/page.view.php', '<v>{{ MARK }}{{ content }}</v>');
    file_put_contents($layRoot . '/shell.layout.php', '<l>{{ MARK }}{{ var: $name }}</l>');

    $hits = 0;
    $rv = ds_renderer();
    $rv->module('Harness');
    $rv->addRenderer('once.counter', function ($code, $vars = array()) use (&$hits) {
        $hits++;
        return str_replace('{{ MARK }}', 'HIT', $code);
    });
    $rv->setView('page');
    $rv->setLayout('shell');
    $rv->setViewVar('name', 'Ada');
    $html = $rv->renderView();
    ds_check('custom renderer runs exactly once on view+layout', $hits === 1, 'hits=' . $hits);
    ds_check(
        'content substitution on view+layout',
        $html === '<v>HIT<l>HITAda</l></v>',
        $html
    );

    $hitsView = 0;
    $rv2 = ds_renderer();
    $rv2->module('Harness');
    $rv2->addRenderer('once.counter.view', function ($code, $vars = array()) use (&$hitsView) {
        $hitsView++;
        return $code;
    });
    $rv2->setView('page');
    $rv2->setViewVar('name', 'Ada');
    $rv2->renderView();
    ds_check('custom renderer once on view-only', $hitsView === 1, 'hits=' . $hitsView);

    // --- Production templates compile pass (read-only) --------------------------------

    $prodRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'app');
    $prodChecked = 0;
    $prodFailed = array();
    if ($prodRoot !== false && is_dir($prodRoot)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($prodRoot, FilesystemIterator::SKIP_DOTS));
        $compiler = ds_renderer();
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $name = $file->getFilename();
            if (substr($name, -9) !== '.view.php' && substr($name, -11) !== '.layout.php') {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            if ($src === false) {
                continue;
            }
            $prodChecked++;
            try {
                $compiler->renderCode($src, array(), false);
            } catch (RendererSyntaxException $e) {
                $rel = str_replace($prodRoot . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $prodFailed[] = $rel . ' [' . $e->getDirective() . ' @ ' . $e->getTemplateLine() . ']';
            } catch (\Throwable $e) {
                $rel = str_replace($prodRoot . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $prodFailed[] = $rel . ' [' . get_class($e) . ']';
            } catch (\Exception $e) {
                $rel = str_replace($prodRoot . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $prodFailed[] = $rel . ' [' . get_class($e) . ']';
            }
        }
    }
    $failPreview = empty($prodFailed) ? '' : implode('; ', array_slice($prodFailed, 0, 8));
    ds_check(
        'production view/layout compile (' . $prodChecked . ' files)',
        empty($prodFailed),
        $failPreview
    );

    ds_rrmdir($rootDir);

    echo "\n$passes passed, " . count($failures) . " failed\n";
    if (!empty($failures)) {
        echo "Failed tests:\n- " . implode("\n- ", $failures) . "\n";
        exit(1);
    }
    exit(0);
}
