<?php
/**
 * Core Renderer tests for Tester.
 *
 * Copy this file to app/tests/Renderer.test.php, then run:
 *   php dotapper.php --test
 *
 * Do not execute this file as a standalone script. Tester already boots DotApp
 * and loads app/parts/Renderer.php.
 *
 * @package DotApp Framework
 */

namespace Dotsystems\App\Tests;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Renderer;
use Dotsystems\App\Parts\RendererSyntaxException;
use Dotsystems\App\Parts\RenderingIsolator;
use Dotsystems\App\Parts\Tester;

if (!class_exists(Tester::class)) {
    fwrite(STDERR, "Copy Renderer.test.php to app/tests/ and run: php dotapper.php --test\n");
    return;
}

/**
 * Helpers shared by Renderer Tester callbacks.
 */
final class RendererTesterSupport
{
    const PROBE_MODULE = '_RendererTesterProbe';

    /**
     * @return Renderer
     */
    public static function renderer()
    {
        return new Renderer();
    }

    /**
     * @param string $tpl
     * @param array  $vars
     * @param bool   $eval
     * @return string
     */
    public static function run($tpl, $vars = array(), $eval = true)
    {
        return self::renderer()->renderCode($tpl, $vars, $eval);
    }

    /**
     * @param Renderer $renderer
     * @param string   $tpl
     * @param array    $vars
     * @return string
     */
    public static function runWithRenderers(Renderer $renderer, $tpl, $vars = array())
    {
        foreach ($renderer->customRenderers() as $fn) {
            $tpl = call_user_func($fn, $tpl, $vars);
        }
        return $renderer->renderCode($tpl, $vars, true);
    }

    /**
     * @param string $tpl
     * @param array  $vars
     * @return string
     */
    public static function runLayout($tpl, $vars = array())
    {
        $renderer = self::renderer();
        $renderer->module(self::PROBE_MODULE);
        $dir = __ROOTDIR__ . '/app/modules/' . self::PROBE_MODULE . '/views/layouts';
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create renderer tester layout directory.');
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
     * @param string $dir
     * @return void
     */
    public static function rrmdir($dir)
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
                self::rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * @param callable   $fn
     * @param string     $directive
     * @param int|null   $line
     * @return true|string
     */
    public static function throwsDirective($fn, $directive, $line = null)
    {
        try {
            call_user_func($fn);
            return 'no exception';
        } catch (RendererSyntaxException $e) {
            $ok = $e->getDirective() === $directive
                && ($line === null || $e->getTemplateLine() === $line);
            return $ok ? true : ($e->getDirective() . '@' . $e->getTemplateLine());
        }
    }

    /**
     * @param array  $failed
     * @param string $okInfo
     * @param string $testName
     * @return array<string, mixed>
     */
    public static function result(array $failed, $okInfo, $testName)
    {
        $passed = $failed === array();
        return array(
            'status' => $passed ? 1 : 0,
            'info' => $passed ? $okInfo : implode('; ', $failed),
            'test_name' => $testName,
            'context' => array('core' => true, 'renderer' => true),
        );
    }
}

Tester::addTest('test_renderer_named_instances_and_vars', function () {
    $failed = array();
    $a = Renderer::new('ds-renderer-named-one');
    $b = Renderer::new('ds-renderer-named-one');
    if ($a !== $b) {
        $failed[] = 'named renderer instances are reused';
    }

    $r = RendererTesterSupport::renderer();
    $r->setView('page');
    $r->setViewVar('title', 'Hello');
    if ($r->getViewVar('title') !== 'Hello') {
        $failed[] = 'setViewVar/getViewVar';
    }
    $r->setLayout('shell');
    $r->setLayoutVar('aside', 'Side');
    if ($r->getLayoutVar('aside') !== 'Side') {
        $failed[] = 'setLayoutVar/getLayoutVar';
    }

    return RendererTesterSupport::result($failed, 'Named instances and view/layout vars work', 'Renderer named instances and vars');
});

Tester::addTest('test_renderer_var_if_foreach_translate_enc', function () {
    $failed = array();
    $dotApp = DotApp::dotApp();
    $previousTranslator = isset($GLOBALS['translator']) ? $GLOBALS['translator'] : null;
    $GLOBALS['translator'] = function ($text) {
        return 'T(' . (string) $text . ')';
    };

    try {
        if (RendererTesterSupport::run('{{ var: $title }}', array('title' => '<b>x</b>')) !== '<b>x</b>') {
            $failed[] = 'unfiltered var is raw echo';
        }
        if (RendererTesterSupport::run('{{ var:$title }}', array('title' => 'OK')) !== 'OK') {
            $failed[] = 'var without space after colon';
        }
        if (RendererTesterSupport::run('{{ var: $user[\'name\'] }}', array('user' => array('name' => 'Ann'))) !== 'Ann') {
            $failed[] = 'var array path';
        }
        if (RendererTesterSupport::run('{{ if $on }}Y{{ else }}N{{ /if }}', array('on' => 1)) !== 'Y') {
            $failed[] = 'if $x style';
        }
        if (RendererTesterSupport::run('{{ if ($flag == 1) }}Y{{ else }}N{{ /if }}', array('flag' => 1)) !== 'Y') {
            $failed[] = 'if ($x == 1) style';
        }
        if (RendererTesterSupport::run('{{ if ($n == 1) }}A{{ elseif ($n == 2) }}B{{ else }}C{{ /if }}', array('n' => 2)) !== 'B') {
            $failed[] = 'elseif branch';
        }

        $nested = RendererTesterSupport::run(
            '{{ foreach $groups as $g }}[{{ var: $g[\'n\'] }}:' .
            '{{ foreach $g[\'items\'] as $it }}{{ var: $it }}{{ /foreach }}]{{ /foreach }}',
            array(
                'groups' => array(
                    array('n' => 'A', 'items' => array('1', '2')),
                    array('n' => 'B', 'items' => array('3')),
                ),
            )
        );
        if ($nested !== '[A:12][B:3]') {
            $failed[] = 'nested foreach';
        }

        if (RendererTesterSupport::run('{{_ "Login" }}') !== 'T(Login)') {
            $failed[] = 'translation literal';
        }
        if (RendererTesterSupport::run('{{_ var: $msg }}', array('msg' => 'Save')) !== 'T(Save)') {
            $failed[] = 'translation var';
        }

        if ($dotApp->decrypt(RendererTesterSupport::run('{{ enc: "secret" }}')) !== 'secret') {
            $failed[] = 'enc literal decrypts';
        }
        if ($dotApp->decrypt(RendererTesterSupport::run('{{ enc(DACore.role.id): $id }}', array('id' => '7')), 'DACore.role.id') !== '7') {
            $failed[] = 'enc dotted context key decrypts';
        }

        $compiledIf = RendererTesterSupport::run('{{ if $x }}Z{{ /if }}', array(), false);
        if (strpos($compiledIf, 'if (') === false || strpos($compiledIf, 'endif') === false) {
            $failed[] = 'if compiles to PHP if';
        }
    } finally {
        if ($previousTranslator === null) {
            unset($GLOBALS['translator']);
        } else {
            $GLOBALS['translator'] = $previousTranslator;
        }
    }

    return RendererTesterSupport::result($failed, 'Var/if/foreach/translate/enc compile and render', 'Renderer var if foreach translate enc');
});

Tester::addTest('test_renderer_foreach_loop_break_continue', function () {
    $failed = array();

    if (RendererTesterSupport::run('{{ foreach ($items as $item) }}{{ var: $item }}{{ /foreach }}', array('items' => array('a', 'b'))) !== 'ab') {
        $failed[] = 'parenthesized foreach';
    }
    if (RendererTesterSupport::run(
        '{{ foreach $items as $k => $v }}{{ var: $k }}={{ var: $v }};{{ /foreach }}',
        array('items' => array('x' => '1', 'y' => '2'))
    ) !== 'x=1;y=2;') {
        $failed[] = 'key-value foreach';
    }
    if (RendererTesterSupport::run(
        '{{ foreach ($items as $k => $v) }}{{ var: $k }}{{ var: $v }}{{ /foreach }}',
        array('items' => array('a' => '1'))
    ) !== 'a1') {
        $failed[] = 'parenthesized key-value foreach';
    }

    $deep = array('rows' => array(array('name' => 'N'), array('name' => 'M')));
    if (RendererTesterSupport::run('{{ foreach $data[\'rows\'] as $row }}{{ var: $row[\'name\'] }}{{ /foreach }}', array('data' => $deep)) !== 'NM') {
        $failed[] = 'foreach deep quoted key';
    }
    if (RendererTesterSupport::run('{{ foreach $rows[0] as $v }}{{ var: $v }}{{ /foreach }}', array('rows' => array(array('q', 'r')))) !== 'qr') {
        $failed[] = 'foreach numeric index';
    }
    if (RendererTesterSupport::run(
        '{{ foreach $bags[$which] as $v }}{{ var: $v }}{{ /foreach }}',
        array('bags' => array('sel' => array('Z')), 'which' => 'sel')
    ) !== 'Z') {
        $failed[] = 'foreach dynamic index';
    }
    if (RendererTesterSupport::run(
        '{{ foreach ($bags[")"] as $v) }}{{ var: $v }}{{ /foreach }}',
        array('bags' => array(')' => array('P')))
    ) !== 'P') {
        $failed[] = 'parenthesized foreach ignores parentheses inside quoted keys';
    }

    $loopOut = RendererTesterSupport::run(
        '{{ foreach $items as $item }}{{ var: $loop[\'index\'] }}:{{ var: $loop[\'iteration\'] }}:{{ var: $loop[\'first\'] }}:{{ var: $loop[\'last\'] }}:{{ var: $loop[\'count\'] }};{{ /foreach }}',
        array('items' => array('a', 'b', 'c'))
    );
    if ($loopOut !== '0:1:1:0:3;1:2:0:0:3;2:3:0:1:3;') {
        $failed[] = 'loop metadata';
    }

    $parentLoop = RendererTesterSupport::run(
        '{{ foreach $outer as $o }}{{ foreach $o as $i }}{{ var: $loop[\'parent\'][\'index\'] }}-{{ var: $loop[\'index\'] }};{{ /foreach }}{{ /foreach }}',
        array('outer' => array(array('a', 'b'), array('c')))
    );
    if ($parentLoop !== '0-0;0-1;1-0;') {
        $failed[] = 'nested loop parent index';
    }

    if (RendererTesterSupport::run('{{ foreach $items as $item }}Y{{ else }}N{{ /foreach }}', array('items' => array())) !== 'N') {
        $failed[] = 'foreach else empty';
    }
    if (RendererTesterSupport::run('{{ foreach $items as $item }}Y{{ else }}N{{ /foreach }}', array('items' => array(1))) !== 'Y') {
        $failed[] = 'foreach else skipped when items exist';
    }
    if (RendererTesterSupport::run('{{ foreach $items as $n }}{{ var: $n }}{{ break }}{{ /foreach }}', array('items' => array('1', '2', '3'))) !== '1') {
        $failed[] = 'break';
    }
    if (RendererTesterSupport::run('{{ foreach $items as $n }}{{ var: $n }}{{ break $n == 2 }}{{ /foreach }}', array('items' => array(1, 2, 3))) !== '12') {
        $failed[] = 'conditional break';
    }
    if (RendererTesterSupport::run('{{ foreach $items as $n }}{{ continue $n == 2 }}{{ var: $n }}{{ /foreach }}', array('items' => array(1, 2, 3))) !== '13') {
        $failed[] = 'continue';
    }

    $breakOutside = RendererTesterSupport::throwsDirective(function () {
        RendererTesterSupport::run('{{ break }}');
    }, 'break', 1);
    if ($breakOutside !== true) {
        $failed[] = 'break outside loop throws:' . $breakOutside;
    }

    return RendererTesterSupport::result($failed, 'Foreach forms, $loop, break and continue work', 'Renderer foreach loop break continue');
});

Tester::addTest('test_renderer_filters', function () {
    $failed = array();
    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

    if (RendererTesterSupport::run('{{ var: $title | escape }}', array('title' => '<b>')) !== htmlspecialchars('<b>', ENT_QUOTES, 'UTF-8')) {
        $failed[] = 'escape filter';
    }
    if (RendererTesterSupport::run('{{ var: $title | escape }}', array('title' => "\xC3")) === '') {
        $failed[] = 'escape filter substitutes malformed UTF-8';
    }
    if (RendererTesterSupport::run('{{ var: $title | default("n/a") }}', array('title' => '')) !== 'n/a') {
        $failed[] = 'default filter';
    }
    if (RendererTesterSupport::run('{{ var: $n | number(2) }}', array('n' => 12.5)) !== number_format(12.5, 2, '.', ',')) {
        $failed[] = 'number filter';
    }
    if (RendererTesterSupport::run('{{ var: $ts | date("Y-m-d") }}', array('ts' => strtotime('2020-01-02 00:00:00'))) !== '2020-01-02') {
        $failed[] = 'date filter';
    }
    if (RendererTesterSupport::run('{{ var: $a | join(";") }}', array('a' => array('p', 'q'))) !== 'p;q') {
        $failed[] = 'join filter';
    }
    if (RendererTesterSupport::run('{{ var: $a | json }}', array('a' => array('k' => 'v'))) !== json_encode(array('k' => 'v'), $jsonFlags)) {
        $failed[] = 'json filter';
    }
    $xssJson = RendererTesterSupport::run('{{ var: $a | json }}', array('a' => '</script><img src=x onerror=1>'));
    if (
        $xssJson !== json_encode('</script><img src=x onerror=1>', $jsonFlags)
        || strpos($xssJson, '</script>') !== false
        || strpos($xssJson, '<') !== false
    ) {
        $failed[] = 'json filter hex-encodes HTML';
    }
    if (RendererTesterSupport::run('{{ var: $s | urlencode }}', array('s' => 'a b')) !== rawurlencode('a b')) {
        $failed[] = 'urlencode filter';
    }
    if (RendererTesterSupport::run('{{ var: $s | upper }}-{{ var: $s | lower }}', array('s' => 'Ab')) !== 'AB-ab') {
        $failed[] = 'upper/lower filters';
    }

    $rf = RendererTesterSupport::renderer();
    $rf->addFilter('ds_wrap', function ($value, $mark = '*') {
        return $mark . $value . $mark;
    });
    if ($rf->renderCode('{{ var: $s | ds_wrap("#") }}', array('s' => 'Q'), true) !== '#Q#') {
        $failed[] = 'custom filter registration';
    }
    if (!is_callable($rf->getFilter('ds_wrap'))) {
        $failed[] = 'getFilter returns callable';
    }
    $other = RendererTesterSupport::renderer();
    $rf->addFilter('ds_suffix', function ($value) {
        return $value . '!';
    });
    if ($other->renderCode('{{ var: $s | ds_suffix }}', array('s' => 'ok'), true) !== 'ok!' || !is_callable($other->getFilter('ds_suffix'))) {
        $failed[] = 'custom filter is request-global';
    }

    $unknown = RendererTesterSupport::throwsDirective(function () {
        RendererTesterSupport::run('{{ var: $s | not_a_filter }}', array('s' => 'x'));
    }, 'not_a_filter', 1);
    if ($unknown !== true) {
        $failed[] = 'unknown filter throws:' . $unknown;
    }
    if (RendererTesterSupport::run('{{ var: $title }}', array('title' => '<i>')) !== '<i>') {
        $failed[] = 'unfiltered var still raw after filters exist';
    }

    return RendererTesterSupport::result($failed, 'Built-in and custom filters work', 'Renderer filters');
});

Tester::addTest('test_renderer_raw_comment_blocks', function () {
    $failed = array();

    if (RendererTesterSupport::run('{{ raw }}{{ var: $title }}{{ /raw }}', array('title' => 'NO')) !== '{{ var: $title }}') {
        $failed[] = 'raw preserves tags';
    }
    if (RendererTesterSupport::run('{{ raw }}A{{ raw }}B{{ /raw }}C{{ /raw }}') !== 'ABC') {
        $failed[] = 'nested raw';
    }
    if (RendererTesterSupport::run('A{{ comment }}secret{{ /comment }}B') !== 'AB') {
        $failed[] = 'comment removed';
    }
    if (RendererTesterSupport::run('A{{ comment }}x{{ comment }}y{{ /comment }}z{{ /comment }}B') !== 'AB') {
        $failed[] = 'nested comment';
    }

    $rb = RendererTesterSupport::renderer();
    $rb->addBlock('ds_wrap', function ($inner, $args) {
        $p = isset($args[0]) ? $args[0] : '';
        return '[' . $p . $inner . $p . ']';
    });
    $rb->addBlock('ds_note', function ($inner, $args) {
        $arg = isset($args[0]) ? $args[0] : '';
        return '{' . $arg . ':' . $inner . '}';
    });
    if (RendererTesterSupport::runWithRenderers($rb, '{{ block:ds_wrap(a) }}{{ block:ds_wrap(b) }}X{{ /block:ds_wrap }}{{ /block:ds_wrap }}') !== '[a[bXb]a]') {
        $failed[] = 'nested same-name blocks';
    }
    if (RendererTesterSupport::runWithRenderers($rb, '{{ block:ds_note("hello, world") }}Z{{ /block:ds_note }}') !== '{hello, world:Z}') {
        $failed[] = 'quoted block args keep commas';
    }
    $missing = RendererTesterSupport::runWithRenderers($rb, '{{ block:missing }}X{{ /block:missing }}');
    if (strpos($missing, 'blockerror:missing') === false) {
        $failed[] = 'undefined block uses block name not undefined $block';
    }
    if (RendererTesterSupport::runWithRenderers($rb, '{{ block:ds_wrap(a) }}{{ block:ds_note("z") }}X{{ /block:ds_note }}{{ /block:ds_wrap }}') !== '[a{z:X}a]') {
        $failed[] = 'nested different-name blocks';
    }

    $orphan = RendererTesterSupport::throwsDirective(function () use ($rb) {
        RendererTesterSupport::runWithRenderers($rb, '{{ /block:ds_wrap }}');
    }, '/block', 1);
    if ($orphan !== true) {
        $failed[] = 'orphan block close throws:' . $orphan;
    }
    $mismatch = RendererTesterSupport::throwsDirective(function () use ($rb) {
        RendererTesterSupport::runWithRenderers($rb, '{{ block:ds_wrap }}X{{ /block:ds_note }}');
    }, '/block', 1);
    if ($mismatch !== true) {
        $failed[] = 'mismatched block close throws:' . $mismatch;
    }
    $crossing = RendererTesterSupport::throwsDirective(function () use ($rb) {
        RendererTesterSupport::runWithRenderers($rb, '{{ block:ds_wrap }}{{ block:ds_note }}X{{ /block:ds_wrap }}{{ /block:ds_note }}');
    }, '/block');
    if ($crossing !== true) {
        $failed[] = 'crossing blocks throw:' . $crossing;
    }
    $unclosed = RendererTesterSupport::throwsDirective(function () use ($rb) {
        RendererTesterSupport::runWithRenderers($rb, "{{ block:ds_wrap }}\nX");
    }, 'block', 1);
    if ($unclosed !== true) {
        $failed[] = 'unclosed block throws:' . $unclosed;
    }

    return RendererTesterSupport::result($failed, 'Raw, comment and block directives work', 'Renderer raw comment blocks');
});

Tester::addTest('test_renderer_php_islands_and_sandbox', function () {
    $failed = array();

    $island = RendererTesterSupport::run("<?php echo '{{ var: \$title }}'; ?>{{ var: \$title }}", array('title' => 'Hello'));
    if ($island !== '{{ var: $title }}Hello') {
        $failed[] = 'PHP island not rewritten';
    }
    $islandClose = RendererTesterSupport::run("<?php echo '?>'; echo '{{ var: \$x }}'; ?>DONE{{ var: \$x }}", array('x' => 'Z'));
    if ($islandClose !== '?>{{ var: $x }}DONEZ') {
        $failed[] = 'PHP island keeps ?> inside quoted string';
    }
    $islandComment = RendererTesterSupport::run("<?php /* ?> {{ var: \$x }} */ echo 'Q'; ?>{{ var: \$x }}", array('x' => 'Z'));
    if ($islandComment !== 'QZ') {
        $failed[] = 'PHP island keeps ?> inside comment';
    }

    $kept = RendererTesterSupport::run(
        '{{ foreach $rows as $row }}{{ var: $row[\'time\'] }}-{{ var: $row[\'key\'] }}-{{ var: $row[\'count\'] }}-{{ var: $row[\'copy\'] }};{{ /foreach }}',
        array(
            'rows' => array(
                array('time' => 'morning', 'key' => 'k', 'count' => '2', 'copy' => 'c'),
            ),
        )
    );
    if ($kept !== 'morning-k-2-c;') {
        $failed[] = 'callable-looking strings kept';
    }
    if (RendererTesterSupport::run('{{ var: $stamp }}', array('stamp' => 'time')) !== 'time') {
        $failed[] = 'plain string time kept as value';
    }

    $closureBag = RendererTesterSupport::run(
        '{{ foreach $rows as $row }}X{{ else }}EMPTY{{ /foreach }}',
        array('rows' => array(function () {
            return 1;
        }))
    );
    if ($closureBag !== 'EMPTY') {
        $failed[] = 'actual Closure dropped';
    }

    $inv = new class {
        public function __invoke()
        {
            return 1;
        }
    };
    if (RendererTesterSupport::run('{{ if isset($obj) }}Y{{ else }}N{{ /if }}', array('obj' => $inv)) !== 'N') {
        $failed[] = 'invokable object dropped';
    }

    $r = RendererTesterSupport::renderer();
    $pair = RendererTesterSupport::run('{{ if isset($cb) }}Y{{ else }}N{{ /if }}', array('cb' => array($r, 'renderCode')));
    if ($pair !== 'N') {
        $failed[] = 'callable array dropped';
    }
    if (RendererTesterSupport::run('{{ var: $pair[0] }}', array('pair' => array('time', 'count'))) !== 'time') {
        $failed[] = 'ordinary two-string list kept';
    }
    if (RenderingIsolator::isRejectedExtractValue('1abc', true) !== true || RenderingIsolator::isRejectedExtractValue('title', true) !== false) {
        $failed[] = 'invalid extract key skipped';
    }

    return RendererTesterSupport::result($failed, 'PHP islands and extract sandbox behave', 'Renderer PHP islands and sandbox');
});

Tester::addTest('test_renderer_syntax_errors', function () {
    $failed = array();

    try {
        RendererTesterSupport::run("{{ if \$x }}\nhello");
        $failed[] = 'unclosed if throws: no exception';
    } catch (RendererSyntaxException $e) {
        if ($e->getDirective() !== 'if' || $e->getTemplateLine() !== 1 || strpos($e->getMessage(), 'secret') !== false) {
            $failed[] = 'unclosed if throws with line';
        }
    }

    $cases = array(
        array("line1\n{{ foreach \$a as \$b }}\n{{ /if }}", '/if', 3, 'mismatched /if reports line 3'),
        array("{{ foreach \$items as \$item }}\n{{ var: \$item }}", 'foreach', 1, 'unclosed foreach throws'),
        array('{{ foreach ($items as $item) }}x', 'foreach', 1, 'unclosed parenthesized foreach throws'),
        array("{{ comment }}\nstill open", 'comment', 1, 'unclosed comment reports open line'),
        array("{{ raw }}\nstill open", 'raw', 1, 'unclosed raw reports open line'),
        array('{{ var: $1abc }}', 'var', 1, 'digit-leading var path throws'),
        array('{{ foreach $1abc as $item }}x{{ /foreach }}', 'foreach', 1, 'digit-leading foreach path throws'),
        array('{{ var: $_hidden }}', 'var', 1, 'underscore var path still rejected'),
        array('{{ foreach $items as $item }}Y{{ else }}{{ break }}N{{ /foreach }}', 'break', 1, 'break in foreach-else throws'),
        array('{{ foreach $items as $item }}Y{{ else }}{{ continue }}N{{ /foreach }}', 'continue', 1, 'continue in foreach-else throws'),
    );
    foreach ($cases as $case) {
        $got = RendererTesterSupport::throwsDirective(function () use ($case) {
            RendererTesterSupport::run($case[0], array('items' => array()));
        }, $case[1], $case[2]);
        if ($got !== true) {
            $failed[] = $case[3] . ':' . $got;
        }
    }

    if (RendererTesterSupport::run(
        '{{ foreach $outer as $o }}{{ var: $o }}{{ foreach $empty as $i }}x{{ else }}{{ break }}{{ /foreach }}{{ /foreach }}',
        array('outer' => array(1, 2), 'empty' => array())
    ) !== '1') {
        $failed[] = 'break in foreach-else allowed when outer loop is active';
    }
    if (RendererTesterSupport::run(
        '{{ foreach $outer as $o }}{{ var: $o }}{{ foreach $empty as $i }}x{{ else }}{{ continue }}{{ /foreach }}Z{{ /foreach }}',
        array('outer' => array(1, 2), 'empty' => array())
    ) !== '12') {
        $failed[] = 'continue in foreach-else uses outer loop';
    }
    if (RendererTesterSupport::run('{{ while $i < 3 }}{{ var: $i }}<?php $i++; ?>{{ /while }}', array('i' => 0)) !== '012') {
        $failed[] = 'legacy while';
    }
    if (RendererTesterSupport::run('{{ var: $user["name"] }}', array('user' => array('name' => 'Ann'))) !== 'Ann') {
        $failed[] = 'double-quoted var path';
    }
    if (RendererTesterSupport::run(
        '{{ foreach $items as $item }}{{ if $item == 1 }}A{{ else }}B{{ /if }}{{ /foreach }}',
        array('items' => array(1, 2))
    ) !== 'AB') {
        $failed[] = 'nested if/else inside foreach';
    }

    return RendererTesterSupport::result($failed, 'Syntax errors report directive and line', 'Renderer syntax errors');
});

Tester::addTest('test_renderer_privateblock_csrf_form', function () {
    $failed = array();
    $dotApp = DotApp::dotApp();

    $orphan = RendererTesterSupport::throwsDirective(function () {
        RendererTesterSupport::runLayout('{{ /privateblock }}');
    }, '/privateblock', 1);
    if ($orphan !== true) {
        $failed[] = 'orphan privateblock close throws:' . $orphan;
    }
    $nested = RendererTesterSupport::throwsDirective(function () {
        RendererTesterSupport::runLayout('{{ privateblock:row }}A{{ privateblock:cell }}B{{ /privateblock }}{{ /privateblock }}');
    }, 'privateblock');
    if ($nested !== true) {
        $failed[] = 'nested privateblock throws:' . $nested;
    }
    $unclosed = RendererTesterSupport::throwsDirective(function () {
        RendererTesterSupport::runLayout("{{ privateblock:row }}\nX");
    }, 'privateblock', 1);
    if ($unclosed !== true) {
        $failed[] = 'unclosed privateblock throws:' . $unclosed;
    }
    try {
        $privateDashed = RendererTesterSupport::runLayout('{{ privateblock:user-card }}X{{ /privateblock }}OK');
        if ($privateDashed !== 'OK') {
            $failed[] = 'dashed privateblock name remains compatible';
        }
    } catch (RendererSyntaxException $e) {
        $failed[] = 'dashed privateblock name remains compatible:' . $e->getMessage();
    }
    $invalidName = RendererTesterSupport::throwsDirective(function () {
        RendererTesterSupport::runLayout('{{ privateblock:bad name }}X{{ /privateblock }}');
    }, 'privateblock', 1);
    if ($invalidName !== true) {
        $failed[] = 'invalid privateblock name throws:' . $invalidName;
    }

    $unknown = RendererTesterSupport::run('{{ CSRF }}{{ mystery: 1 }}{{ formName(x) }}', array(), false);
    if (
        strpos($unknown, (string) $dotApp->CSRF) === false
        || strpos($unknown, '{{ mystery: 1 }}') === false
        || strpos($unknown, '{{ formName(x) }}') === false
    ) {
        $failed[] = 'unknown tags remain for later pipeline stages';
    }

    $form = RendererTesterSupport::run('<form method="POST" action="/save">{{ formName(saveItem) }}</form>');
    if (strpos($form, 'dotapp-secure-auto-fnname') === false) {
        $failed[] = 'formName inside form';
    }
    $formLeave = RendererTesterSupport::run('{{ formName(saveItem) }}');
    if (strpos($formLeave, '{{ formName(saveItem) }}') === false) {
        $failed[] = 'formName without form left unchanged';
    }

    return RendererTesterSupport::result($failed, 'Privateblock, CSRF and formName behave', 'Renderer privateblock csrf form');
});

Tester::addTest('test_renderer_view_layout_custom_renderer_once', function () {
    $failed = array();
    $modRoot = __ROOTDIR__ . '/app/modules/' . RendererTesterSupport::PROBE_MODULE . '/views';
    $layRoot = $modRoot . '/layouts';
    if (!is_dir($layRoot) && !mkdir($layRoot, 0777, true) && !is_dir($layRoot)) {
        return RendererTesterSupport::result(array('Cannot create probe module views'), 'n/a', 'Renderer view layout custom renderer once');
    }

    try {
        file_put_contents($modRoot . '/page.view.php', '<v>{{ MARK }}{{ content }}</v>');
        file_put_contents($layRoot . '/shell.layout.php', '<l>{{ MARK }}{{ var: $name }}</l>');

        $hits = 0;
        $rv = RendererTesterSupport::renderer();
        $rv->module(RendererTesterSupport::PROBE_MODULE);
        $rv->addRenderer('ds.once.counter', function ($code, $vars = array()) use (&$hits) {
            unset($vars);
            $hits++;
            return str_replace('{{ MARK }}', 'HIT', $code);
        });
        $rv->setView('page');
        $rv->setLayout('shell');
        $rv->setViewVar('name', 'Ada');
        $html = $rv->renderView();
        if ($hits !== 1) {
            $failed[] = 'custom renderer runs exactly once on view+layout:hits=' . $hits;
        }
        if ($html !== '<v>HIT<l>HITAda</l></v>') {
            $failed[] = 'content substitution on view+layout:' . $html;
        }

        $hitsView = 0;
        $rv2 = RendererTesterSupport::renderer();
        $rv2->module(RendererTesterSupport::PROBE_MODULE);
        $rv2->addRenderer('ds.once.counter.view', function ($code, $vars = array()) use (&$hitsView) {
            unset($vars);
            $hitsView++;
            return $code;
        });
        $rv2->setView('page');
        $rv2->setViewVar('name', 'Ada');
        $rv2->renderView();
        if ($hitsView !== 1) {
            $failed[] = 'custom renderer once on view-only:hits=' . $hitsView;
        }
    } finally {
        RendererTesterSupport::rrmdir(__ROOTDIR__ . '/app/modules/' . RendererTesterSupport::PROBE_MODULE);
    }

    return RendererTesterSupport::result($failed, 'Custom renderer runs once on view and layout', 'Renderer view layout custom renderer once');
});

Tester::addTest('test_renderer_production_views_compile', function () {
    $failed = array();
    $prodRoot = realpath(__ROOTDIR__ . DIRECTORY_SEPARATOR . 'app');
    $prodChecked = 0;
    $prodFailed = array();
    if ($prodRoot !== false && is_dir($prodRoot)) {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($prodRoot, \FilesystemIterator::SKIP_DOTS));
        $compiler = RendererTesterSupport::renderer();
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
    if ($prodFailed !== array()) {
        $failed[] = 'production view/layout compile (' . $prodChecked . ' files): ' . implode('; ', array_slice($prodFailed, 0, 8));
    }

    return RendererTesterSupport::result(
        $failed,
        'Production views/layouts compile (' . $prodChecked . ' files)',
        'Renderer production views compile'
    );
});
