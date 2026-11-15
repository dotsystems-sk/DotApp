<?php

/**
 * DotApp Framework
 *
 * This class is the backend part of the DotApp Connector ( dotapp.connector.js ).
 * A connector is a canvas of nodes joined by wires. A node is universal: it has any
 * number of inputs and outputs and one executor. The executor receives the values of
 * the inputs and returns the value of the output, which travels along the wires to
 * the next nodes. The executor is a JavaScript function, a controller, or both.
 *
 * @package   DotApp Framework
 * @category  Framework Parts
 * @author    Štefan Miščík <stefan@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   2.1 FREE
 * @date      2014 - 2026
 * @license   MIT License
 *
 * License Notice:
 * Permission is granted to use, modify, and distribute this code under the MIT License,
 * provided this header is retained in all copies or substantial portions of the file,
 * including author and company information.
 */

/*
    The Connector class describes a graph and renders it for dotapp.connector.js.
    The graph is written either with the fluent PHP API or with tags in a template.

    Key Features:
    - Universal nodes. A node is a source ( no inputs ), a processor ( both ) or a sink
      ( no outputs ) only because of the ports it was given. Nothing else is fixed.
    - Fluent PHP API:
        $graph->node('crop')->input('image')->output('image')->controller('Shop:Images@crop!')
    - <connector> tags in every view and layout, the same way as <fo-rm>.
    - The executor in JavaScript ( ->js() / <function> ), on the server ( ->controller() ), or both.
    - Own design: ->layout() / <layout> makes the whole node from the HTML of the module,
      ports are the elements marked with connector-input / connector-output. ->unstyled()
      switches the built-in look off, so every module can look the way it wants.
    - One route runs all server nodes: Connector::listen('/shop/pipeline')
    - The controller of a server node is encrypted and signed for the session when
      the graph is rendered. The browser can not ask for a controller that the
      graph did not declare.

    More:
    - template() makes a node a stamp: every drag from the palette makes a new copy of it.
    - designOnly() + execute(): the graph is only wired in the browser and the server runs it.
    - The canvas can be spread over the whole page or the whole screen ( buttons in its corner ).

    Usage in PHP:

        $graph = Connector::use('calc')->height(380);

        $graph->node('six')->title('Number')->at(40, 40)
            ->output('value', ['value' => 6]);

        $graph->node('typed')->title('Your number')->at(40, 180)
            ->output('value')
            ->content('<input type="number">')
            ->init('node.on("input", "input", function () { node.run(); });')
            ->js('var text = node.find("input").val();
                  return text === null || text === "" ? node.halt("Type a number") : Number(text);');

        $graph->node('sum')->title('Add')->at(320, 100)
            ->input('a', ['required' => true])
            ->input('b', ['required' => true])
            ->output('result')
            ->js('return inputs.a + inputs.b;');

        $graph->node('show')->title('Result')->at(600, 100)
            ->input('value')
            ->content('<b>?</b>')
            ->js('node.find("b").text(inputs.value === undefined ? "?" : inputs.value);');

        $graph->connect('six.value', 'sum.a')
              ->connect('typed.value', 'sum.b')
              ->connect('sum.result', 'show.value');

        echo $graph->render();          // or <connector use="calc"> in the view

    The same graph with tags in a template:

        <connector name="calc" height="380">
            <node name="six" title="Number" x="40" y="40">
                <output name="value" value="6">
            </node>
            <node name="sum" title="Add" x="320" y="100">
                <input name="a" required>
                <input name="b" required>
                <output name="result">
                <function>return inputs.a + inputs.b;</function>
            </node>
            <connect from="six.value" to="sum.a">
        </connector>

        Tags of a node: <input>, <output>, <content>, <layout>, <function>, <init>, <save>, <load>

    Server node ( a controller ). It receives the inputs and returns the value of the output;
    with more outputs an array keyed by their names:

        public static function crop($inputs, $node) {
            if (empty($inputs['image'])) return Connector::halt('No image.');
            return $croppedImage;
        }

    Route which runs the server nodes ( module.init.php ):

        Connector::listen('/shop/pipeline')->before('#Shop:Rights@check!');

    The graph as a configurator of logic which runs on the server, without a browser.
    The user wires the graph once; the saved wires decide what the server does later:

        // one place describes the nodes and their PHP executors
        public static function flow() {
            return Connector::use('mailflow')->designOnly()
                ->node('incoming')->title('Incoming e-mail')->output('email')
                ->node('filter')->title('Spam filter')->input('email', ['required' => true])
                    ->output('spam')->output('clean')
                    ->controller('Shop:Mail@filter!')
                ->node('inbox')->title('Inbox')->input('email', ['required' => true])
                    ->controller('Shop:Mail@toInbox!')
                ->connector();
        }

        // the page: the user wires it, JavaScript sends graph.save(true) to the module
        echo self::flow()->restore($savedState)->render();
        $savedState = Connector::unpack($posted['state']);      // store it ( database, file )

        // later, anywhere on the server - a cron job, a webhook, a listener:
        $run = self::flow()->restore($savedState)->execute(['incoming' => $email]);
        $run->ran('inbox');             // true when the e-mail reached the inbox node
        $run->output('filter.spam');    // value of one output
*/


namespace Dotsystems\App\Parts;
use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\Router;
use \Dotsystems\App\Parts\DSM;

/**
 * Returned by a server node which does not want to send anything further.
 * The reason is shown on the node.
 */
class ConnectorHalt {
    public $reason;

    function __construct($reason = "") {
        $this->reason = (string) $reason;
    }
}

/**
 * What happened when a graph ran on the server ( Connector::execute() ).
 */
class ConnectorRun {
    private $outputs;      // node => [output => value]
    private $ran;          // names of the nodes which produced something, in the order they ran
    private $halted;       // node => reason ( "" when a required input was empty )
    private $failed;       // node => message of the exception

    function __construct($outputs, $ran, $halted, $failed) {
        $this->outputs = $outputs;
        $this->ran = $ran;
        $this->halted = $halted;
        $this->failed = $failed;
    }

    /**
     * Value of one output: output('filter.spam'), or output('total') for a node with one output.
     */
    public function output($port) {
        $parts = explode(".", (string) $port);
        if (!isset($this->outputs[$parts[0]])) return null;
        if (count($parts) === 2) return $this->outputs[$parts[0]][$parts[1]] ?? null;
        return count($this->outputs[$parts[0]]) === 1 ? reset($this->outputs[$parts[0]]) : $this->outputs[$parts[0]];
    }

    /**
     * Every output of one node, or of all nodes.
     */
    public function outputs($node = null) {
        if ($node === null) return $this->outputs;
        return $this->outputs[$node] ?? [];
    }

    /**
     * ran('inbox') - true when the node ran and was not stopped. ran() - names of such nodes, in the order they ran.
     */
    public function ran($node = null) {
        if ($node === null) return $this->ran;
        return in_array($node, $this->ran, true);
    }

    /**
     * Nodes which were stopped: node => reason. The reason is "" when a required input was empty.
     * halted('filter') - true when this node was stopped.
     */
    public function halted($node = null) {
        if ($node === null) return $this->halted;
        return array_key_exists($node, $this->halted);
    }

    /**
     * Nodes whose controller threw an exception: node => message.
     */
    public function failed($node = null) {
        if ($node === null) return $this->failed;
        return array_key_exists($node, $this->failed);
    }

    /**
     * true when no controller threw an exception.
     */
    public function ok() {
        return empty($this->failed);
    }

    public function toArray() {
        return ['outputs' => $this->outputs, 'ran' => $this->ran, 'halted' => $this->halted, 'failed' => $this->failed];
    }
}

/**
 * One node of the graph. Every setter returns the node, so a node is described in one chain.
 */
class ConnectorNode {
    private $connector;
    private $name;
    private $title = null;
    private $x = null;
    private $y = null;
    private $width = null;
    private $inputs = [];      // name => options
    private $outputs = [];     // name => options
    private $content = "";
    private $custom = false;   // true = $content is the whole node ( own design ), false = it is placed into the built-in frame
    private $scripts = [];     // fn | init | save | load => body of the JavaScript function
    private $controller = null;
    private $manual = false;
    private $debounce = null;
    private $parked = false;
    private $template = false;  // a stamp: it stays in the palette, every drag onto the canvas makes a copy
    private $of = null;         // name of the template this node is a copy of
    private $data = [];
    private $cssClass = "";

    function __construct($connector, $name) {
        $this->connector = $connector;
        $this->name = $name;
    }

    /**
     * A copy of this node under another name. Used for copies of templates.
     */
    public function copyAs($name) {
        $copy = clone $this;
        $copy->name = $name;
        $copy->of = $this->name;
        $copy->template = false;
        $copy->parked = false;
        $copy->x = null;
        $copy->y = null;
        return $copy;
    }

    public function name() {
        return $this->name;
    }

    /**
     * "source" ( no inputs ), "sink" ( no outputs ) or "processor". It follows from the ports, it is not set.
     */
    public function type() {
        if (empty($this->inputs)) return "source";
        if (empty($this->outputs)) return "sink";
        return "processor";
    }

    /**
     * Returns the connector this node belongs to.
     */
    public function connector() {
        return $this->connector;
    }

    /**
     * Continues the chain with another node of the same connector:
     * $graph->node('a')->output('value')->node('b')->input('value')->connect('a.value', 'b.value');
     */
    public function node($name) {
        return $this->connector->node($name);
    }

    /**
     * Continues the chain with a wire. Returns the connector.
     */
    public function connect($from, $to) {
        return $this->connector->connect($from, $to);
    }

    /**
     * Ends the chain: renders the whole connector.
     */
    public function render() {
        return $this->connector->render();
    }

    /**
     * Text shown in the header of the built-in frame. Without it the name is used.
     */
    public function title($title) {
        $this->title = (string) $title;
        return $this;
    }

    /**
     * Position of the node on the canvas in pixels. Without it the nodes are laid out in columns.
     */
    public function at($x, $y) {
        $this->x = (int) $x;
        $this->y = (int) $y;
        return $this;
    }

    public function position($x, $y) {
        return $this->at($x, $y);
    }

    /**
     * Width of the node in pixels.
     */
    public function width($width) {
        $this->width = max(80, (int) $width);
        return $this;
    }

    /**
     * Adds an input. A node can have any number of them.
     *
     * @param string $name Name of the input. The executor reads it as inputs.name ( JavaScript ) or $inputs['name'] ( controller )
     * @param array $options label ( text next to the port ), type ( only ports of the same type can be joined ),
     *                       required ( true = the node waits until this input has a value; the executor is not called without it ),
     *                       multiple ( true = more wires may end on this input; the executor gets a list of the values which arrived )
     * @return ConnectorNode
     */
    public function input($name, $options = []) {
        $name = Connector::checkName($name, "input");
        $this->inputs[$name] = self::portOptions($options, true);
        return $this;
    }

    public function addInput($name, $options = []) {
        return $this->input($name, $options);
    }

    /**
     * Adds an output. A node can have any number of them.
     *
     * @param string $name Name of the output.
     * @param array $options label, type, value ( the output holds this value from the start )
     * @return ConnectorNode
     */
    public function output($name, $options = []) {
        $name = Connector::checkName($name, "output");
        $this->outputs[$name] = self::portOptions($options, false);
        return $this;
    }

    public function addOutput($name, $options = []) {
        return $this->output($name, $options);
    }

    /**
     * HTML shown inside of the built-in frame of the node. What it does is written in init() and js().
     */
    public function content($html) {
        $this->content = (string) $html;
        $this->custom = false;
        return $this;
    }

    /**
     * Own design: the HTML is the whole node. Nothing of the built-in frame is drawn, the
     * module brings its own markup and CSS. The ports are the elements marked with attributes
     * and they are added to the node automatically:
     *
     *      connector-input="name"      this element is the input port "name"
     *      connector-output="name"     this element is the output port "name"
     *      connector-type="number"     together with a port: only ports of the same type can be joined
     *      connector-required          together with connector-input: the node waits for this input
     *      connector-multiple          together with connector-input: more wires may end on this input
     *      connector-value="6"         together with connector-output: the output holds this value from the start
     *      connector-anchor="left"     where the wire touches the port: left | right | top | bottom | center
     *      connector-drag              the node is moved by this element ( without it by the whole node )
     *      connector-park              pressing this element moves the node into the palette
     *      connector-status            the element shows the message of the node ( error, halt )
     *
     *      ->layout('<div class="shop_card"><i connector-input="image"></i> Crop <i connector-output="image"></i></div>')
     *      ->layout(Renderer::new()->module('Shop')->setLayout('nodes/crop')->renderLayout())
     */
    public function layout($html) {
        $this->content = (string) $html;
        $this->custom = true;
        if (preg_match_all('/<[a-zA-Z][^<>]*\bconnector-(?:input|output)\s*=[^<>]*>/', $this->content, $tags)) {
            foreach ($tags[0] as $tag) {
                $attributes = Connector::attributes($tag);
                $options = [];
                if (isset($attributes['connector-type']) && is_string($attributes['connector-type'])) $options['type'] = $attributes['connector-type'];
                // A port described with input() / output() keeps its own options
                if (isset($attributes['connector-input']) && is_string($attributes['connector-input']) && !isset($this->inputs[$attributes['connector-input']])) {
                    if (isset($attributes['connector-required'])) $options['required'] = true;
                    if (isset($attributes['connector-multiple'])) $options['multiple'] = true;
                    $this->input($attributes['connector-input'], $options);
                }
                if (isset($attributes['connector-output']) && is_string($attributes['connector-output']) && !isset($this->outputs[$attributes['connector-output']])) {
                    unset($options['required']);
                    if (isset($attributes['connector-value']) && is_string($attributes['connector-value'])) {
                        $options['value'] = is_numeric($attributes['connector-value']) ? $attributes['connector-value'] + 0 : $attributes['connector-value'];
                    }
                    $this->output($attributes['connector-output'], $options);
                }
            }
        }
        return $this;
    }

    public function isCustom() {
        return $this->custom;
    }

    /**
     * The executor in JavaScript. Only the body of the function is written. It receives
     * "inputs" ( object keyed by the names of the inputs ) and "node", and returns the value
     * of the output ( with more outputs an object keyed by their names ).
     *
     *      ->js('return inputs.a + inputs.b;')
     *
     * node.find(selector), node.on(event, selector, fn), node.run(), node.set(output, value),
     * node.halt(reason), node.backend(inputs), node.status(text), node.store(key, value), node.data
     */
    public function js($body) {
        $this->scripts['fn'] = (string) $body;
        return $this;
    }

    /**
     * JavaScript called once when the node is created: listeners, first look. Receives "node".
     *
     *      ->init('node.on("input", "input", function () { node.run(); });')
     */
    public function init($body) {
        $this->scripts['init'] = (string) $body;
        return $this;
    }

    /**
     * JavaScript called before the state is saved. Put what should be kept into node.data. Receives "node".
     */
    public function onSave($body) {
        $this->scripts['save'] = (string) $body;
        return $this;
    }

    /**
     * JavaScript called after a state was loaded. node.data holds the saved values. Receives "node".
     */
    public function onLoad($body) {
        $this->scripts['load'] = (string) $body;
        return $this;
    }

    /**
     * The executor on the server. A callable string, the same as on a route: 'Shop:Images@crop!'
     * The controller receives ($inputs, $node) and returns the value of the output
     * ( with more outputs an array keyed by their names ), or Connector::halt('reason').
     * $node is ['connector' => name, 'node' => name, 'data' => values kept with the node, 'headless' => bool].
     *
     * The same controller is used in two ways:
     * - the browser asks for it when the node runs there ( a callable string only; needs endpoint() );
     *   together with js() the JavaScript executor decides when - return node.backend(inputs);
     * - Connector::execute() calls it when the graph runs on the server without a browser
     *   ( a callable string or a closure ).
     */
    public function controller($callable) {
        if (!($callable instanceof \Closure) && (!is_string($callable) || strpos($callable, "@") === false)) {
            throw new \InvalidArgumentException("Connector: controller of node '" . $this->name . "' must be a callable string like 'Module:Controller@method!' or a closure.");
        }
        $this->controller = $callable;
        return $this;
    }

    /**
     * A manual node does not run when its inputs change. It runs when its JavaScript calls node.run()
     * ( for example after a button was pressed ).
     */
    public function manual($setting = true) {
        $this->manual = ($setting === true);
        return $this;
    }

    /**
     * Waits this many milliseconds after the last change before the node runs.
     * Server nodes wait 250 ms by default, so typing does not send a request for every key.
     */
    public function debounce($milliseconds) {
        $this->debounce = max(0, (int) $milliseconds);
        return $this;
    }

    /**
     * The node starts in the palette ( an element with connector-palette="name of the connector" ).
     * The user drags it onto the canvas.
     */
    public function parked($setting = true) {
        $this->parked = ($setting === true);
        return $this;
    }

    /**
     * The node is a template ( a stamp ). It stays in the palette and every drag onto the canvas makes
     * a new copy of it - a node of its own, with its own wires and its own data. This is how a user gets
     * three "Subject contains" nodes with three different words. Copies are a part of the saved state;
     * execute() creates them from it. A copy declared in PHP: $graph->copy('keyword', 'keyword-1')
     */
    public function template($setting = true) {
        $this->template = ($setting === true);
        return $this;
    }

    public function isTemplate() {
        return $this->template;
    }

    /**
     * Name of the template this node is a copy of, or null.
     */
    public function of() {
        return $this->of;
    }

    /**
     * Continues the chain with a copy of a template: ->copy('keyword', 'keyword-1')->at(280, 70)
     */
    public function copy($template, $name) {
        return $this->connector->copy($template, $name);
    }

    /**
     * Values kept with the node. JavaScript reads and writes them as node.data, they are saved with the state.
     */
    public function data($data) {
        $this->data = is_array($data) ? $data : [];
        return $this;
    }

    /**
     * Extra CSS class of the node.
     */
    public function cssClass($class) {
        $this->cssClass = preg_replace('/[^a-zA-Z0-9_\- ]/', '', (string) $class);
        return $this;
    }

    public function hasInput($name) {
        return isset($this->inputs[$name]);
    }

    public function hasOutput($name) {
        return isset($this->outputs[$name]);
    }

    public function isMultiple($name) {
        return !empty($this->inputs[$name]['multiple']);
    }

    public function inputType($name) {
        return isset($this->inputs[$name]) ? $this->inputs[$name]['type'] : null;
    }

    public function outputType($name) {
        return isset($this->outputs[$name]) ? $this->outputs[$name]['type'] : null;
    }

    public function getContent() {
        return $this->content;
    }

    public function getScripts() {
        return $this->scripts;
    }

    public function getController() {
        return $this->controller;
    }

    public function getInputs() {
        return $this->inputs;
    }

    public function getOutputs() {
        return $this->outputs;
    }

    public function getData() {
        return $this->data;
    }

    public function isParked() {
        return $this->parked;
    }

    /**
     * Description of the node for dotapp.connector.js
     */
    public function toArray() {
        $inputs = [];
        foreach ($this->inputs as $name => $options) {
            $inputs[] = array_merge(['name' => $name], $options);
        }
        $outputs = [];
        foreach ($this->outputs as $name => $options) {
            $outputs[] = array_merge(['name' => $name], $options);
        }
        $node = [
            'name' => $this->name,
            'title' => ($this->title === null) ? $this->name : $this->title,
            'inputs' => $inputs,
            'outputs' => $outputs
        ];
        if ($this->x !== null) {
            $node['x'] = $this->x;
            $node['y'] = $this->y;
        }
        if ($this->width !== null) $node['width'] = $this->width;
        if ($this->manual) $node['manual'] = true;
        if ($this->debounce !== null) $node['debounce'] = $this->debounce;
        if ($this->parked) $node['parked'] = true;
        if ($this->template) $node['template'] = true;
        if ($this->of !== null) $node['of'] = $this->of;
        if (!empty($this->data)) $node['data'] = $this->data;
        if ($this->cssClass !== "") $node['cssClass'] = $this->cssClass;
        if ($this->custom) $node['custom'] = true;
        // The browser gets a signed ticket for the controller only when it is going to run the node itself
        if (is_string($this->controller) && !$this->connector->isDesignOnly()) {
            $node['backend'] = Connector::sign($this->connector->name(), $this->name, $this->controller);
        }
        return $node;
    }

    private static function portOptions($options, $isInput) {
        if (!is_array($options)) $options = [];
        $port = [];
        if (isset($options['label']) && is_string($options['label'])) $port['label'] = $options['label'];
        $port['type'] = (isset($options['type']) && is_string($options['type']) && $options['type'] !== "") ? $options['type'] : null;
        if ($isInput && !empty($options['required'])) $port['required'] = true;
        if ($isInput && !empty($options['multiple'])) $port['multiple'] = true;
        if (!$isInput && array_key_exists('value', $options)) $port['value'] = $options['value'];
        return $port;
    }
}

class Connector {
    private static $instances = [];      // Available connectors of this request ( one per name )
    private static $scriptPrinted = false;
    private static $autoScript = true;

    private $connectorName;
    private $nodes = [];                 // name => ConnectorNode
    private $connections = [];           // [ [fromNode, fromPort, toNode, toPort], ... ]
    private $height = 480;
    private $endpoint = null;
    private $grid = 0;
    private $readonly = false;
    private $theme = "auto";
    private $cssClass = "";
    private $unstyled = false;
    private $designOnly = false;
    private $tools = true;
    private $state = null;

    /**
     * Constructor: creates an empty graph and remembers it under its name.
     *
     * @param string $connectorName Name of the connector. JavaScript finds it as $dotapp().connector(name)
     */
    function __construct($connectorName) {
        $this->connectorName = self::checkName($connectorName, "connector");
        self::$instances[$this->connectorName] = $this;
    }

    /**
     * Creates or retrieves a connector with the specified name.
     *
     * @param string $connectorName
     * @return Connector
     */
    public static function use($connectorName) {
        if (isset(self::$instances[$connectorName])) {
            return self::$instances[$connectorName];
        }
        return new self($connectorName);
    }

    public static function exists($connectorName) {
        return isset(self::$instances[$connectorName]);
    }

    public function name() {
        return $this->connectorName;
    }

    /**
     * Creates or retrieves a node of this connector.
     *
     * @param string $name Name of the node, unique in the connector.
     * @return ConnectorNode
     */
    public function node($name) {
        $name = self::checkName($name, "node");
        if (!isset($this->nodes[$name])) {
            $this->nodes[$name] = new ConnectorNode($this, $name);
        }
        return $this->nodes[$name];
    }

    public function hasNode($name) {
        return isset($this->nodes[$name]);
    }

    /**
     * Places a copy of a template into the graph and returns it, so it can be set up:
     * $graph->copy('keyword', 'keyword-1')->at(280, 70)->data(['text' => 'casino']);
     *
     * @param string $template Name of a node marked with template()
     * @param string $name Name of the copy
     * @return ConnectorNode
     */
    public function copy($template, $name) {
        $name = self::checkName($name, "node");
        if (!isset($this->nodes[$template]) || !$this->nodes[$template]->isTemplate()) {
            throw new \InvalidArgumentException("Connector '" . $this->connectorName . "': '" . $template . "' is not a template. Mark the node with template() first.");
        }
        if (isset($this->nodes[$name])) {
            if ($this->nodes[$name]->of() === $template) return $this->nodes[$name];
            throw new \InvalidArgumentException("Connector '" . $this->connectorName . "': node '" . $name . "' already exists.");
        }
        $this->nodes[$name] = $this->nodes[$template]->copyAs($name);
        return $this->nodes[$name];
    }

    /**
     * Joins an output with an input. Both are written as "node.port".
     * The ports are checked when the connector is rendered, so a wire can be
     * declared before its nodes.
     *
     * @param string $from "node.output"
     * @param string $to "node.input"
     * @return Connector
     */
    public function connect($from, $to) {
        $from = self::splitPort($from);
        $to = self::splitPort($to);
        $wire = [$from[0], $from[1], $to[0], $to[1]];
        if (!in_array($wire, $this->connections, true)) $this->connections[] = $wire;      // the same wire twice is one wire
        return $this;
    }

    /**
     * Height of the canvas in pixels.
     */
    public function height($pixels) {
        $this->height = max(120, (int) $pixels);
        return $this;
    }

    /**
     * URL which runs the server nodes. It must be registered with Connector::listen()
     * ( or handled with Connector::handle() ) and it must wake the module ( initializeRoutes ).
     */
    public function endpoint($url) {
        $this->endpoint = (string) $url;
        return $this;
    }

    /**
     * Nodes snap to a grid of this size while they are moved. 0 turns the grid off.
     */
    public function grid($pixels) {
        $this->grid = max(0, (int) $pixels);
        return $this;
    }

    /**
     * A readonly connector still computes, but the user can not move nodes or change wires.
     */
    public function readonly($setting = true) {
        $this->readonly = ($setting === true);
        return $this;
    }

    /**
     * The graph is only designed in the browser: the user moves nodes, joins them and sets them up,
     * but no executor runs there. The saved state is run on the server with execute().
     */
    public function designOnly($setting = true) {
        $this->designOnly = ($setting === true);
        return $this;
    }

    public function isDesignOnly() {
        return $this->designOnly;
    }

    /**
     * The two buttons in the corner of the canvas ( over the whole page, over the whole screen ).
     * tools(false) hides them; JavaScript can still call graph.maximize() and graph.fullscreen().
     */
    public function tools($setting = true) {
        $this->tools = ($setting === true);
        return $this;
    }

    /**
     * Colors of the built-in look: "auto" ( follows the system ), "light" or "dark".
     */
    public function theme($theme) {
        $this->theme = in_array($theme, ["auto", "light", "dark"], true) ? $theme : "auto";
        return $this;
    }

    /**
     * Switches the built-in look off. The connector keeps only what it needs to work
     * ( positions, wires ) and the module styles the canvas and the nodes with its own CSS.
     * The color and the width of the wires are set with --dc-wire, --dc-accent and --dc-wire-width.
     */
    public function unstyled($setting = true) {
        $this->unstyled = ($setting === true);
        return $this;
    }

    /**
     * Extra CSS class of the canvas.
     */
    public function cssClass($class) {
        $this->cssClass = preg_replace('/[^a-zA-Z0-9_\- ]/', '', (string) $class);
        return $this;
    }

    /**
     * Restores what the user changed: positions of nodes, parked nodes, wires and the data of nodes.
     * The state is what save() returns in JavaScript: an array from unpack(), the packed text of
     * save(true), or JSON text. When the state has wires, they replace the wires declared with connect().
     */
    public function restore($state) {
        if (is_string($state)) $state = self::unpack($state);
        $this->state = (is_array($state) && !empty($state)) ? $state : null;
        return $this;
    }

    /**
     * Reads a state sent by JavaScript: save(true) ( packed text ) or JSON text.
     * Returns an array which holds only what a state may hold, or null when the text is not a state.
     * Store the result ( DSM, database ) and give it to restore() on the next render.
     */
    public static function unpack($text) {
        if (!is_string($text) || $text === "") return null;
        $state = json_decode($text, true);
        if (!is_array($state)) {
            $state = json_decode((string) base64_decode(strtr($text, '-_', '+/')), true);
        }
        if (!is_array($state)) return null;

        $name = '/^[a-zA-Z][a-zA-Z0-9_\-]*$/';
        $port = '/^[a-zA-Z][a-zA-Z0-9_\-]*\.[a-zA-Z][a-zA-Z0-9_\-]*$/';
        $clean = ['nodes' => [], 'connections' => [], 'data' => []];
        foreach ((array) ($state['nodes'] ?? []) as $node => $position) {
            if (!is_string($node) || preg_match($name, $node) !== 1 || !is_array($position)) continue;
            if (!empty($position['parked'])) {
                $clean['nodes'][$node] = ['parked' => true];
            } else {
                $clean['nodes'][$node] = ['x' => max(0, (int) ($position['x'] ?? 0)), 'y' => max(0, (int) ($position['y'] ?? 0))];
            }
            // A copy of a template: the name of the template travels with it
            if (isset($position['of']) && is_string($position['of']) && preg_match($name, $position['of']) === 1) {
                $clean['nodes'][$node]['of'] = $position['of'];
            }
        }
        foreach ((array) ($state['connections'] ?? []) as $wire) {
            $from = is_array($wire) ? ($wire['from'] ?? null) : null;
            $to = is_array($wire) ? ($wire['to'] ?? null) : null;
            if (is_string($from) && is_string($to) && preg_match($port, $from) === 1 && preg_match($port, $to) === 1) {
                $clean['connections'][] = ['from' => $from, 'to' => $to];
            }
        }
        foreach ((array) ($state['data'] ?? []) as $node => $data) {
            if (is_string($node) && is_array($data) && !empty($data) && preg_match($name, $node) === 1) $clean['data'][$node] = $data;
        }
        return $clean;
    }

    /**
     * Description of the whole graph for dotapp.connector.js. Checks the wires.
     */
    public function toArray() {
        $this->checkConnections();
        $nodes = [];
        $needsEndpoint = false;
        foreach ($this->nodes as $node) {
            if (is_string($node->getController()) && !$this->designOnly) $needsEndpoint = true;
            $nodes[] = $node->toArray();
        }
        if ($needsEndpoint && ($this->endpoint === null || $this->endpoint === "")) {
            throw new \RuntimeException("Connector '" . $this->connectorName . "': a node has a controller, but the connector has no endpoint. Set endpoint('/url') and register it with Connector::listen('/url').");
        }
        $connections = [];
        foreach ($this->connections as $c) {
            $connections[] = ['from' => $c[0] . "." . $c[1], 'to' => $c[2] . "." . $c[3]];
        }
        $config = [
            'name' => $this->connectorName,
            'nodes' => $nodes,
            'connections' => $connections,
            'grid' => $this->grid,
            'readonly' => $this->readonly,
            'theme' => $this->theme
        ];
        if ($this->unstyled) $config['unstyled'] = true;
        if ($this->designOnly) $config['designOnly'] = true;
        if (!$this->tools) $config['tools'] = false;
        if ($this->endpoint !== null) $config['endpoint'] = $this->endpoint;
        if ($this->state !== null) $config['state'] = $this->state;
        return $config;
    }

    /**
     * Renders the connector: the canvas, the HTML of the nodes, the description
     * of the graph and the JavaScript functions of the nodes.
     *
     * @return string HTML
     */
    public function render() {
        $config = $this->toArray();
        $class = "dotapp-connector" . ($this->unstyled ? " dc-unstyled" : "") . ($this->cssClass !== "" ? " " . $this->cssClass : "");

        $html = '<div class="' . $class . '" id="dotapp-connector-' . $this->connectorName . '" data-connector="' . $this->connectorName . '" style="height:' . $this->height . 'px">';
        foreach ($this->nodes as $name => $node) {
            if ($node->getContent() !== "") {
                $html .= '<div class="dotapp-connector-content" data-node="' . $name . '" hidden>' . $node->getContent() . '</div>';
            }
        }
        $html .= '<script type="application/json" class="dotapp-connector-config">' . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . '</script>';
        $html .= '</div>';

        // JavaScript of the nodes. Real functions in a script, nothing is evaluated from text.
        $nodes = [];
        foreach ($this->nodes as $name => $node) {
            $functions = [];
            foreach ($node->getScripts() as $kind => $body) {
                $body = str_ireplace("</script", "<\\/script", $body);
                $functions[] = $kind . ":function(" . ($kind === "fn" ? "inputs,node" : "node") . "){\n" . $body . "\n}";
            }
            if (!empty($functions)) $nodes[] = json_encode($name) . ":{" . implode(",", $functions) . "}";
        }
        if (!empty($nodes)) {
            $html .= '<script>(function(w){var f=w.dotappConnectorFn=w.dotappConnectorFn||{};f[' . json_encode($this->connectorName) . ']={' . implode(",", $nodes) . '};})(window);</script>';
        }

        if (self::$autoScript && !self::$scriptPrinted) {
            self::$scriptPrinted = true;
            $html .= '<script src="/assets/dotapp/dotapp.connector.js"></script>';
        }
        return $html;
    }

    public function __toString() {
        return $this->render();
    }

    /**
     * render() adds <script src="/assets/dotapp/dotapp.connector.js"> after the first connector of the page.
     * Turn it off when the page loads the library itself.
     */
    public static function autoScript($setting) {
        self::$autoScript = ($setting === true);
    }

    /* ---------------------------------------------------------------- templates */

    /**
     * Replaces <connector> tags in the code of a template with rendered connectors.
     * Called by the Renderer for every view, layout and rendered code.
     *
     * @param string $code Code of the template
     * @return string
     */
    public static function compile($code) {
        if (!is_string($code) || stripos($code, "<connector") === false) return $code;

        // <connector use="name"> - a connector built in PHP
        $code = preg_replace_callback('/<connector\b(?=[^>]*\buse\s*=)([^>]*?)\/?>(?:\s*<\/connector\s*>)?/i', function ($match) {
            $attributes = self::attributes($match[1]);
            $name = (string) ($attributes['use'] ?? "");
            if (!isset(self::$instances[$name])) {
                throw new \RuntimeException("Connector: <connector use=\"" . $name . "\"> - no connector with this name was created in PHP.");
            }
            return self::$instances[$name]->render();
        }, $code);

        // <connector name="..."> ... </connector> - a connector written with tags
        $code = preg_replace_callback('/<connector\b([^>]*)>(.*?)<\/connector\s*>/is', function ($match) {
            return self::fromTags(self::attributes($match[1]), $match[2])->render();
        }, $code);

        return $code;
    }

    // Builds a connector from the tags between <connector> and </connector>
    private static function fromTags($attributes, $inner) {
        if (!isset($attributes['name']) || !is_string($attributes['name'])) {
            throw new \RuntimeException("Connector: <connector> needs the attribute name.");
        }
        $connector = new self($attributes['name']);
        if (isset($attributes['height'])) $connector->height($attributes['height']);
        if (isset($attributes['endpoint'])) $connector->endpoint($attributes['endpoint']);
        if (isset($attributes['grid'])) $connector->grid($attributes['grid'] === true ? 20 : $attributes['grid']);
        if (isset($attributes['readonly'])) $connector->readonly(true);
        if (isset($attributes['unstyled'])) $connector->unstyled(true);
        if (isset($attributes['design-only'])) $connector->designOnly(true);
        if (isset($attributes['tools']) && in_array($attributes['tools'], ['off', 'false', '0', 'no'], true)) $connector->tools(false);
        if (isset($attributes['theme'])) $connector->theme($attributes['theme']);
        if (isset($attributes['class']) && is_string($attributes['class'])) $connector->cssClass($attributes['class']);

        // Nodes are taken out first. What is left are the wires.
        $inner = preg_replace_callback('/<node\b([^>]*)>(.*?)<\/node\s*>/is', function ($match) use ($connector) {
            self::nodeFromTags($connector, self::attributes($match[1]), $match[2]);
            return "";
        }, $inner);

        if (preg_match_all('/<connect\b([^>]*?)\/?>/i', $inner, $wires)) {
            foreach ($wires[1] as $wire) {
                $wire = self::attributes($wire);
                if (!isset($wire['from']) || !isset($wire['to'])) {
                    throw new \RuntimeException("Connector '" . $connector->name() . "': <connect> needs the attributes from and to.");
                }
                $connector->connect($wire['from'], $wire['to']);
            }
        }
        return $connector;
    }

    private static function nodeFromTags($connector, $attributes, $inner) {
        if (!isset($attributes['name']) || !is_string($attributes['name'])) {
            throw new \RuntimeException("Connector '" . $connector->name() . "': <node> needs the attribute name.");
        }
        $node = $connector->node($attributes['name']);
        if (isset($attributes['title']) && is_string($attributes['title'])) $node->title($attributes['title']);
        if (isset($attributes['x']) || isset($attributes['y'])) $node->at($attributes['x'] ?? 0, $attributes['y'] ?? 0);
        if (isset($attributes['width'])) $node->width($attributes['width']);
        if (isset($attributes['controller'])) $node->controller($attributes['controller']);
        if (isset($attributes['manual'])) $node->manual(true);
        if (isset($attributes['parked'])) $node->parked(true);
        if (isset($attributes['template'])) $node->template(true);
        if (isset($attributes['debounce'])) $node->debounce($attributes['debounce']);
        if (isset($attributes['class']) && is_string($attributes['class'])) $node->cssClass($attributes['class']);

        // JavaScript and HTML of the node are taken out before the ports, so an <input> field inside of them is not read as a port.
        $scripts = ['function' => 'js', 'init' => 'init', 'save' => 'onSave', 'load' => 'onLoad'];
        foreach ($scripts as $tag => $method) {
            $inner = preg_replace_callback('/<' . $tag . '\b[^>]*>(.*?)<\/' . $tag . '\s*>/is', function ($match) use ($node, $method) {
                $node->$method(trim($match[1]));
                return "";
            }, $inner);
        }
        $inner = preg_replace_callback('/<layout\b[^>]*>(.*?)<\/layout\s*>/is', function ($match) use ($node) {
            $node->layout(trim($match[1]));
            return "";
        }, $inner);
        $inner = preg_replace_callback('/<content\b[^>]*>(.*?)<\/content\s*>/is', function ($match) use ($node) {
            $node->content(trim($match[1]));
            return "";
        }, $inner);

        if (preg_match_all('/<(input|output)\b([^>]*?)\/?>/i', $inner, $ports, PREG_SET_ORDER)) {
            foreach ($ports as $port) {
                $options = self::attributes($port[2]);
                if (!isset($options['name']) || !is_string($options['name'])) {
                    throw new \RuntimeException("Connector '" . $connector->name() . "': a port of node '" . $node->name() . "' has no name.");
                }
                if (isset($options['value']) && is_string($options['value']) && is_numeric($options['value'])) {
                    $options['value'] = $options['value'] + 0;     // value="6" is the number 6
                }
                if (strtolower($port[1]) === "input") {
                    $node->input($options['name'], $options);
                } else {
                    $node->output($options['name'], $options);
                }
            }
        }
    }

    /**
     * Reads the attributes of a tag. An attribute without a value is true.
     */
    public static function attributes($text) {
        $attributes = [];
        if (preg_match_all('/([a-zA-Z_][a-zA-Z0-9_\-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>\/]+)))?/', (string) $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = strtolower($match[1]);
                if (isset($match[4]) && $match[4] !== "") {
                    $value = $match[4];
                } elseif (isset($match[3]) && $match[3] !== "") {
                    $value = $match[3];
                } elseif (isset($match[2])) {
                    $value = $match[2];
                } else {
                    $value = true;
                }
                if (is_string($value)) $value = html_entity_decode($value, ENT_QUOTES, "UTF-8");
                $attributes[$name] = $value;
            }
        }
        return $attributes;
    }

    /* ---------------------------------------------------------------- running on the server */

    /**
     * Runs the graph on the server, without a browser. The wires are the ones of the restored
     * state ( what the user joined ), or the declared ones when no state was restored.
     *
     * Every node runs once, from the sources to the sinks, by the same rules as in the browser:
     * the controller of the node receives the values of its inputs and returns the value of its
     * output ( more outputs: an array keyed by their names ). An input marked "required" which
     * is empty stops the node; Connector::halt() stops it as well. A node without a controller
     * holds the values declared on its outputs. Nodes in the palette do not take part.
     *
     * @param array $values Values put on outputs before the run - usually on the sources:
     *                      ['incoming' => $email]              node with one output
     *                      ['incoming.email' => $email]        one output of a node
     *                      A node which was given values is not called.
     * @return ConnectorRun What happened: output(), outputs(), ran(), halted(), failed(), ok()
     */
    public function execute($values = []) {
        $this->checkConnections();
        $state = is_array($this->state) ? $this->state : [];

        // Nodes which take part: not parked ( the state decides, then the declaration ). A template never runs,
        // only its copies do - and with a state, the state says which copies exist.
        $active = [];
        foreach ($this->nodes as $name => $node) {
            if ($node->isTemplate()) continue;
            $position = $state['nodes'][$name] ?? null;
            if ($node->of() !== null && !empty($state) && !is_array($position)) continue;      // the user removed this copy
            $parked = is_array($position) ? !empty($position['parked']) : $node->isParked();
            if (!$parked) $active[$name] = $node;
        }
        foreach ((array) ($state['nodes'] ?? []) as $name => $position) {
            // A copy the user made in the browser
            $of = is_array($position) ? ($position['of'] ?? null) : null;
            if (!is_string($of) || isset($this->nodes[$name]) || !empty($position['parked'])) continue;
            if (isset($this->nodes[$of]) && $this->nodes[$of]->isTemplate()) $active[$name] = $this->nodes[$of]->copyAs($name);
        }

        // Wires: the saved ones are checked again, a wire which does not fit the nodes any more is left out
        $wires = [];        // "node.input" => [ [fromNode, fromPort], ... ]
        if (!empty($state)) {
            foreach ((array) ($state['connections'] ?? []) as $wire) {
                $from = explode(".", (string) ($wire['from'] ?? ''));
                $to = explode(".", (string) ($wire['to'] ?? ''));
                if (count($from) !== 2 || count($to) !== 2) continue;
                if (!isset($active[$from[0]]) || !isset($active[$to[0]])) continue;
                if (!$active[$from[0]]->hasOutput($from[1]) || !$active[$to[0]]->hasInput($to[1])) continue;
                $fromType = $active[$from[0]]->outputType($from[1]);
                $toType = $active[$to[0]]->inputType($to[1]);
                if ($fromType !== null && $toType !== null && $fromType !== $toType) continue;
                $key = $to[0] . "." . $to[1];
                if (isset($wires[$key]) && (!$active[$to[0]]->isMultiple($to[1]) || in_array([$from[0], $from[1]], $wires[$key], true))) continue;
                $wires[$key][] = [$from[0], $from[1]];
            }
        } else {
            foreach ($this->connections as $c) {
                if (isset($active[$c[0]]) && isset($active[$c[2]])) $wires[$c[2] . "." . $c[3]][] = [$c[0], $c[1]];
            }
        }

        // Order: a node runs after every node which feeds it
        $waiting = [];
        $feeds = [];
        foreach ($active as $name => $node) $waiting[$name] = 0;
        foreach ($wires as $to => $sources) {
            $toNode = explode(".", $to)[0];
            foreach ($sources as $from) {
                $waiting[$toNode]++;
                $feeds[$from[0]][] = $toNode;
            }
        }
        $order = [];
        $ready = array_keys(array_filter($waiting, function ($count) { return $count === 0; }));
        while (!empty($ready)) {
            $name = array_shift($ready);
            $order[] = $name;
            foreach ($feeds[$name] ?? [] as $next) {
                if (--$waiting[$next] === 0) $ready[] = $next;
            }
        }
        if (count($order) !== count($active)) {
            throw new \RuntimeException("Connector '" . $this->connectorName . "': the saved wires make a loop, the graph can not run.");
        }

        // Values given from outside
        $given = [];
        foreach ((array) $values as $key => $value) {
            $parts = explode(".", (string) $key);
            if (!isset($this->nodes[$parts[0]])) {
                throw new \InvalidArgumentException("Connector '" . $this->connectorName . "': execute() got a value for '" . $key . "', but there is no such node.");
            }
            $names = array_keys($this->nodes[$parts[0]]->getOutputs());
            if (!isset($active[$parts[0]])) continue;      // the node is in the palette or was removed
            if (count($parts) === 2) {
                $given[$parts[0]][$parts[1]] = $value;
            } elseif (count($names) === 1) {
                $given[$parts[0]][$names[0]] = $value;
            } else {
                foreach ($names as $output) {
                    if (is_array($value) && array_key_exists($output, $value)) $given[$parts[0]][$output] = $value[$output];
                }
            }
        }

        $outputs = [];
        $ran = [];
        $halted = [];
        $failed = [];
        foreach ($order as $name) {
            $node = $active[$name];
            $declared = $node->getOutputs();
            $outputs[$name] = [];
            foreach ($declared as $output => $options) {
                $outputs[$name][$output] = array_key_exists('value', $options) ? $options['value'] : null;
            }

            if (isset($given[$name])) {
                foreach ($given[$name] as $output => $value) {
                    if (array_key_exists($output, $outputs[$name])) $outputs[$name][$output] = $value;
                }
                $ran[] = $name;
                continue;
            }

            $inputs = [];
            $missing = null;
            foreach ($node->getInputs() as $input => $options) {
                $arrived = [];
                foreach ($wires[$name . "." . $input] ?? [] as $wire) {
                    $arrived[] = $outputs[$wire[0]][$wire[1]] ?? null;
                }
                if (!empty($options['multiple'])) {
                    // More wires may end here: the controller gets a list of the values which arrived
                    $value = array_values(array_filter($arrived, function ($item) { return $item !== null && $item !== ""; }));
                    $empty = empty($value);
                } else {
                    $value = $arrived[0] ?? null;
                    $empty = ($value === null || $value === "");
                }
                $inputs[$input] = $value;
                if (!empty($options['required']) && $empty && $missing === null) $missing = $input;
            }
            if ($missing !== null) {
                foreach ($outputs[$name] as $output => $value) $outputs[$name][$output] = null;
                $halted[$name] = "";
                continue;
            }

            $controller = $node->getController();
            if ($controller === null) {
                continue;       // the node only holds the values declared on its outputs
            }

            $info = [
                'connector' => $this->connectorName,
                'node' => $name,
                'data' => (isset($state['data'][$name]) && is_array($state['data'][$name])) ? $state['data'][$name] : $node->getData(),
                'headless' => true
            ];
            try {
                $result = is_string($controller) ? DotApp::call($controller, $inputs, $info) : $controller($inputs, $info);
                if (!($result instanceof ConnectorHalt) && $result !== null && count($declared) > 1 && !is_array($result)) {
                    throw new \RuntimeException("Node '" . $name . "' has more outputs: its controller must return an array keyed by their names.");
                }
            } catch (\Throwable $error) {
                foreach ($outputs[$name] as $output => $value) $outputs[$name][$output] = null;
                $failed[$name] = $error->getMessage();
                continue;
            }

            if ($result instanceof ConnectorHalt) {
                foreach ($outputs[$name] as $output => $value) $outputs[$name][$output] = null;
                $halted[$name] = $result->reason;
                continue;
            }
            $ran[] = $name;
            if ($result === null) continue;
            if (count($declared) === 1) {
                $outputs[$name][array_keys($declared)[0]] = $result;
            } elseif (count($declared) > 1) {
                foreach ($declared as $output => $options) {
                    $outputs[$name][$output] = array_key_exists($output, $result) ? $result[$output] : null;
                }
            }
        }

        return new ConnectorRun($outputs, $ran, $halted, $failed);
    }

    /* ---------------------------------------------------------------- server nodes */

    /**
     * Registers the route which runs the server nodes. Returns the route, so a
     * middleware can be added: Connector::listen('/shop/pipeline')->before('#Shop:Rights@check!')
     *
     * @param string $url The same URL as endpoint of the connector.
     * @param bool $crcCheck false when a middleware in front of the route already called crcCheck()
     */
    public static function listen($url, $crcCheck = true) {
        return Router::post($url, function ($request) use ($crcCheck) {
            return self::handle($request, $crcCheck);
        });
    }

    /**
     * Runs one server node for the current request and returns the reply for dotapp.connector.js.
     * Always HTTP 200: status 1 with the output, or status 0 with a message shown on the node.
     *
     * @param object $request Request of the route
     * @param bool $crcCheck false when crcCheck() was already called for this request
     * @return string
     */
    public static function handle($request, $crcCheck = true) {
        $dotApp = DotApp::DotApp();
        $isUpload = !empty($_FILES);

        if ($isUpload) {
            // A file is sent by $dotapp().uploadFile - there is no { data, crc }. The token of the node is in the URL.
            if (($_SERVER['HTTP_DOTAPP'] ?? '') !== 'load') {
                return $dotApp->ajaxReply(['status' => 0, 'message' => 'Bad request.'], 200);
            }
            $token = (string) ($_GET['connector-node'] ?? '');
            $rawInputs = (string) ($_GET['connector-inputs'] ?? '');
            $rawData = (string) ($_GET['connector-data'] ?? '');
        } else {
            if ($crcCheck && !$request->crcCheck()) {
                return $dotApp->ajaxReply(['status' => 0, 'message' => 'The request could not be verified. Reload the page and try again.'], 200);
            }
            $posted = $request->data(true);
            $data = (is_array($posted) && isset($posted['data']) && is_array($posted['data'])) ? $posted['data'] : [];
            $token = (string) ($data['connector-node'] ?? '');
            $rawInputs = (string) ($data['connector-inputs'] ?? '');
            $rawData = (string) ($data['connector-data'] ?? '');
        }

        $target = self::verify($token);
        if ($target === false) {
            return $dotApp->ajaxReply(['status' => 0, 'message' => 'This node is not valid any more. Reload the page and try again.'], 200);
        }

        $inputs = json_decode((string) base64_decode(strtr($rawInputs, '-_', '+/')), true);
        if (!is_array($inputs)) $inputs = [];

        $nodeData = json_decode((string) base64_decode(strtr($rawData, '-_', '+/')), true);
        if (!is_array($nodeData)) $nodeData = [];

        $node = ['connector' => $target[0], 'node' => $target[1], 'data' => $nodeData, 'headless' => false, 'upload' => $isUpload];
        $result = DotApp::call($target[2], $inputs, $node);

        if ($result instanceof ConnectorHalt) {
            return $dotApp->ajaxReply(['status' => 0, 'halt' => 1, 'message' => $result->reason], 200);
        }
        return $dotApp->ajaxReply(['status' => 1, 'output' => $result], 200);
    }

    /**
     * A server node returns this when it has nothing to send further.
     *
     * @param string $reason Text shown on the node.
     * @return ConnectorHalt
     */
    public static function halt($reason = "") {
        return new ConnectorHalt($reason);
    }

    /**
     * Encrypts and signs the controller of a server node for the current session.
     * Only hexadecimal characters and a dot, so nothing on the way can change it.
     */
    public static function sign($connectorName, $nodeName, $callable) {
        $payload = json_encode([$connectorName, $nodeName, $callable]);
        $encrypted = bin2hex(DotApp::DotApp()->encrypt($payload, "dotapp.connector.node"));
        return $encrypted . "." . hash_hmac('sha256', $encrypted, self::signKey());
    }

    // Returns [connectorName, nodeName, callable] or false when the token was not created by sign() in this session.
    private static function verify($token) {
        if (!is_string($token) || preg_match('/^([a-f0-9]+)\.([a-f0-9]{64})$/', $token, $parts) !== 1) return false;
        if (!hash_equals(hash_hmac('sha256', $parts[1], self::signKey()), $parts[2])) return false;
        $payload = DotApp::DotApp()->decrypt((string) hex2bin($parts[1]), "dotapp.connector.node");
        if ($payload === false) return false;
        $target = json_decode($payload, true);
        if (!is_array($target) || count($target) !== 3 || !is_string($target[2])) return false;
        return $target;
    }

    private static function signKey() {
        return hash('sha256', DotApp::DotApp()->encKey() . "|dotapp.connector|" . DSM::use()->session_id());
    }

    /* ---------------------------------------------------------------- internals */

    /**
     * Names of connectors, nodes and ports: a letter first, then letters, numbers, _ and -
     */
    public static function checkName($name, $what) {
        if (!is_string($name) || preg_match('/^[a-zA-Z][a-zA-Z0-9_\-]*$/', $name) !== 1) {
            throw new \InvalidArgumentException("Connector: '" . (is_scalar($name) ? $name : gettype($name)) . "' is not a valid name of a " . $what . ". Use letters, numbers, _ and -, starting with a letter.");
        }
        return $name;
    }

    private static function splitPort($port) {
        $parts = explode(".", (string) $port);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("Connector: '" . $port . "' is not a port. Write it as \"node.port\".");
        }
        return [self::checkName($parts[0], "node"), self::checkName($parts[1], "port")];
    }

    // Every wire must go from an existing output to an existing input of a matching type, one wire per input, no loops.
    private function checkConnections() {
        $name = $this->connectorName;
        $used = [];
        $next = [];     // node => nodes fed by it
        foreach ($this->connections as $c) {
            list($fromNode, $fromPort, $toNode, $toPort) = $c;
            if (!isset($this->nodes[$fromNode]) || !$this->nodes[$fromNode]->hasOutput($fromPort)) {
                throw new \RuntimeException("Connector '" . $name . "': wire from '" . $fromNode . "." . $fromPort . "' - there is no such output.");
            }
            if (!isset($this->nodes[$toNode]) || !$this->nodes[$toNode]->hasInput($toPort)) {
                throw new \RuntimeException("Connector '" . $name . "': wire to '" . $toNode . "." . $toPort . "' - there is no such input.");
            }
            $fromType = $this->nodes[$fromNode]->outputType($fromPort);
            $toType = $this->nodes[$toNode]->inputType($toPort);
            if ($fromType !== null && $toType !== null && $fromType !== $toType) {
                throw new \RuntimeException("Connector '" . $name . "': '" . $fromNode . "." . $fromPort . "' ( " . $fromType . " ) can not be joined with '" . $toNode . "." . $toPort . "' ( " . $toType . " ).");
            }
            if (isset($used[$toNode . "." . $toPort]) && !$this->nodes[$toNode]->isMultiple($toPort)) {
                throw new \RuntimeException("Connector '" . $name . "': input '" . $toNode . "." . $toPort . "' has more than one wire.");
            }
            $used[$toNode . "." . $toPort] = true;
            $next[$fromNode][$toNode] = true;
        }
        foreach (array_keys($next) as $start) {
            $stack = array_keys($next[$start]);
            $seen = [];
            while (!empty($stack)) {
                $current = array_pop($stack);
                if ($current === $start) {
                    throw new \RuntimeException("Connector '" . $name . "': the wires make a loop through node '" . $start . "'.");
                }
                if (isset($seen[$current])) continue;
                $seen[$current] = true;
                foreach (array_keys($next[$current] ?? []) as $following) $stack[] = $following;
            }
        }
    }
}

?>
