/*
    DotApp Connector ( dotapp.connector.js )
    Frontend part of Dotsystems\App\Parts\Connector

    A connector is a canvas of nodes joined by wires.

    A node is universal. It has any number of inputs and outputs and one executor - a function.
    The executor receives the values of the inputs and returns the value of the output. The value
    travels along the wires to the inputs of other nodes and their executors run. That is all the
    library knows about a node. What a node shows, reads or does is written in its executor.

        source      a node without inputs    ( a number, a text field, an upload )
        processor   a node with both         ( add, crop, translate )
        sink        a node without outputs   ( show the result, save it )

    The kind is not chosen, it follows from the ports of the node.

    Author: Štefan Miščík <stefan@dotsystems.sk>
    Company: Dotsystems s.r.o.
    License: MIT License - this header must be retained.

    Usage - a connector rendered by PHP:

        var graph = $dotapp().connector("calc");

    Usage - a connector built in JavaScript:

        var graph = $dotapp("#canvas").connector();

        graph.node("six").title("Number").output("value").at(40, 40)
            .fn(function () { return 6; });

        graph.node("typed").title("Your number").output("value").at(40, 180)
            .content('<input type="number">')
            .init(function (node) { node.on("input", "input", function () { node.run(); }); })
            .fn(function (inputs, node) {
                var text = node.find("input").val();
                return text === null || text === "" ? node.halt("Type a number") : Number(text);
            });

        graph.node("sum").title("Add").input("a", { required: true }).input("b", { required: true }).output("result").at(320, 100)
            .fn(function (inputs) { return inputs.a + inputs.b; });

        graph.node("show").title("Result").input("value").at(600, 100)
            .content("<b>?</b>")
            .fn(function (inputs, node) { node.find("b").text(inputs.value === undefined ? "?" : inputs.value); });

        graph.connect("six.value", "sum.a").connect("typed.value", "sum.b").connect("sum.result", "show.value");

    The same as one object: $dotapp("#canvas").connector({ nodes: [ { name, title, x, y, inputs, outputs, content, fn, init } ], connections: [ ["six.value", "sum.a"] ] })

    Node:
        .title(text)                    text of the header of the built-in frame
        .input(name, options)           adds an input.  options: { label, type, required, multiple }
                                        multiple = more wires may end on the input; the executor gets a list of values
        .output(name, options)          adds an output. options: { label, type, value }   value = the output holds it from the start
        .content(html)                  HTML placed into the built-in frame
        .layout(html)                   own design: HTML of the whole node ( text, element or "#selector" ), see below
        .fn(executor)                   the executor: function (inputs, node)
        .init(fn)                       function (node), called once - listeners, first look
        .onSave(fn)                     function (node), called before the state is saved - put what should be kept into node.data
        .onLoad(fn)                     function (node), called after a state was loaded - node.data holds the saved values
        .manual()                       the node does not run when its inputs change, only by node.run()
        .debounce(milliseconds)         waits after the last change before the node runs
        .at(x, y)                       position on the canvas

    The executor: function (inputs, node)
        inputs.name     value of the input; undefined when nothing is connected or nothing arrived
        return value    value of the output. With more outputs an object keyed by their names.
                        A Promise of it when the work takes time.
                        node.halt("reason") - nothing leaves the node, the reason is shown on it.
                        Nothing ( undefined ) - the outputs stay as they are.
        An input marked "required" which is empty stops the node before the executor is called.

    Inside of the executor ( "node" is the second argument, and "this" ):
        node.find(selector)             $dotapp object with the elements inside of the node
        node.on(event, selector, fn)    listener inside of the node, fn(element, event); the selector can be left out
        node.run()                      runs the executor again ( the user typed, pressed a button ... )
        node.set(output, value)         sets an output at any time ( timers, events )
        node.get(name)                  value of an input, or of an output with this name
        node.link(name)                 { value, connected, from } of one input
        node.halt(reason)               value to return when nothing should leave the node
        node.load(url, data)            calls an API of the application ( $dotapp().load ), returns a Promise of the reply
        node.upload(file, url)          sends a file to an API of the application ( $dotapp().uploadFile ), Promise of the reply
        node.backend(inputs)            runs the controller given to the node by PHP ( ->controller() ), returns a Promise
        node.status(text, kind)         text shown on the node; kind "error" marks the node
        node.store(key, value)          writes into node.data and tells the connector that there is something to save
        node.data                       object saved with the state
        node.name  node.type  node.el   name, "source" | "processor" | "sink", element of the node
        node.of  node.template          name of the template this node is a copy of; true for a template
        node.park() / node.unpark(x, y) moves the node into the palette / onto the canvas

    Own design of a node ( layout instead of content ) - the whole node is the HTML of the module:
        connector-input="name"      this element is the input port "name"
        connector-output="name"     this element is the output port "name"
        connector-type="number"     together with a port: only ports of the same type can be joined
        connector-required          together with connector-input: the node waits for this input
        connector-multiple          together with connector-input: more wires may end on this input
        connector-value="6"         together with connector-output: the output holds this value from the start
        connector-anchor="left"     where the wire touches the port: left | right | top | bottom | center
        connector-drag              the node is moved by this element ( without it by the whole node )
        connector-park              pressing this element moves the node into the palette
        connector-status            the element shows the message of the node ( error, halt )
    Classes for the CSS of the module: .dc-node .dc-source .dc-processor .dc-sink .is-busy .is-error .is-dragging .is-parked
    on the node, .is-wired .can-drop .is-source on a port, .dc-wire .has-value .is-selected on a wire.

    Look:
        "unstyled" ( PHP ->unstyled(), <connector unstyled>, { unstyled: true } ) switches the built-in look off.
        Without it the built-in look stays and one rule of the module changes it:
        .shop_canvas { --dc-accent: #0f9d74; --dc-bg: #fff; --dc-wire: #b9c2cf; --dc-wire-width: 3; }

    A configurator: { designOnly: true } ( PHP ->designOnly(), <connector design-only> ). The user moves, joins and
    sets up the nodes, but no executor runs in the browser. graph.save(true) is sent to the server, which stores
    it and runs the graph itself whenever it needs to: Connector::execute(). What a node keeps in node.data
    ( node.store(key, value) ) reaches its controller there.

    Palette: an element with connector-palette="name of the connector" anywhere in the page ( or { palette: "#selector" } ).
    Parked nodes wait there. The user drags them onto the canvas and sends them back with the cross.

    Templates: a node with template: true ( PHP ->template() ) is a stamp. It stays in the palette and every drag
    onto the canvas makes a new copy of it - a node of its own, with its own wires and its own node.data.
    graph.copy("keyword") makes a copy from code. node.of is the name of the template a copy was made from.
    The cross of a copy removes it. Copies are a part of the saved state. The HTML of a template must be text.

    Big graphs: the buttons in the corner of the canvas ( { tools: false } hides them ) or graph.maximize(true)
    spread the canvas over the whole page, graph.fullscreen(true) over the whole screen. Escape returns.
    The palette stays at hand as a side panel; its width is the CSS variable --dc-palette-width.

    Connector:
        graph.node(name)                the node; it is created when it does not exist yet
        graph.has(name)  graph.nodes()  graph.addNode(config)  graph.removeNode(name)  graph.copy(template, name)
        graph.maximize(true)  graph.fullscreen(true)
        graph.connect("a.out", "b.in")  graph.disconnect("b.in")  graph.connections()
        graph.fn(nodeName, executor)  graph.run()  graph.run(nodeName)  graph.arrange()
        graph.save()  graph.save(true)  graph.load(state)  graph.reset()
        graph.readonly(true)  graph.halt(reason)  graph.isHalt(value)
        graph.on(event, fn): connect, disconnect, move, park, unpark, run, output, error, maximize, change
*/
(function () {
    var isRegistered = false;

    var runMe = function ($dotapp) {
        if (isRegistered) {
            return;
        }
        isRegistered = true;

        var SVG = "http://www.w3.org/2000/svg";
        var instances = {};     // name => DotAppConnector
        var NAME = /^[a-zA-Z][a-zA-Z0-9_\-]*$/;
        var CONTROLS = "input,textarea,select,button,a,label,[contenteditable],[data-dc-park]";

        // BASE: what the connector needs to work. No colors, no borders - it stays when the module brings its own design.
        // SKIN: the built-in look. :where() gives it no weight, so any rule of the module wins over it.
        var S = ":where(.dotapp-connector:not(.dc-unstyled),.dotapp-connector-palette:not(.dc-unstyled))";
        var C = ":where(.dotapp-connector:not(.dc-unstyled))";
        var DARK = "{--dc-bg:#14171d;--dc-dots:#272d38;--dc-node:#1f242d;--dc-head:#262c37;--dc-border:#353d4a;--dc-text:#e7ebf1;--dc-muted:#98a3b4;--dc-accent:#6c9cff;--dc-wire:#5d6980;--dc-error:#ff7b7b;--dc-source:#3ecf8e;--dc-sink:#f5a524;--dc-shadow:0 1px 2px rgba(0,0,0,.5),0 8px 24px rgba(0,0,0,.35)}";
        var CSS = [
            ".dotapp-connector{position:relative;overflow:auto;box-sizing:border-box;outline:none}",
            ".dotapp-connector>.dotapp-connector-content{display:none}",
            ".dc-stage{position:relative;min-width:100%;min-height:100%}",
            ".dc-wires{position:absolute;left:0;top:0;width:100%;height:100%;overflow:visible;pointer-events:none}",
            ".dc-wire{fill:none;stroke:var(--dc-wire,#97a1b2);stroke-width:var(--dc-wire-width,2);stroke-linecap:round;transition:stroke .15s}",
            ".dc-wire.has-value{stroke:var(--dc-accent,#2f6fed)}",
            ".dc-wire.is-selected{stroke:var(--dc-accent,#2f6fed);stroke-width:calc(var(--dc-wire-width,2) * 1.75)}",
            ".dc-wire.is-temp{stroke:var(--dc-accent,#2f6fed);stroke-dasharray:6 5}",
            ".dc-wire-hit{fill:none;stroke:transparent;stroke-width:16;pointer-events:stroke;cursor:pointer}",
            ".dc-readonly .dc-wire-hit{pointer-events:none}",
            ".dc-wire-del{position:absolute;width:22px;height:22px;margin:-11px 0 0 -11px;padding:0;border:0;border-radius:50%;background:var(--dc-error,#cf3a3a);color:#fff;font:700 14px/22px system-ui,sans-serif;text-align:center;cursor:pointer;z-index:5}",
            ".dc-node{position:absolute;z-index:1;box-sizing:border-box}",
            ".dc-node.is-dragging{z-index:1000}",
            ".dc-node.is-parked{position:relative;left:auto !important;top:auto !important;cursor:grab;touch-action:none}",
            ".dc-node.is-parked.is-dragging{position:fixed;pointer-events:none}",
            "[data-dc-port]{cursor:crosshair;touch-action:none;user-select:none;-webkit-user-select:none}",
            ".dc-readonly [data-dc-port],.is-parked [data-dc-port]{cursor:inherit}",
            "[data-dc-drag]{cursor:grab;touch-action:none;user-select:none;-webkit-user-select:none}",
            ".dc-readonly [data-dc-drag]{cursor:default}",
            "[data-dc-park]{cursor:pointer}",
            // The canvas over the whole page; the palette becomes a side panel
            ".dotapp-connector.is-maximized{position:fixed !important;left:0;top:0;right:0;bottom:0;width:auto !important;height:auto !important;max-width:none;max-height:none;margin:0;border-radius:0;z-index:2147483000}",
            ".dotapp-connector.is-maximized.has-palette{left:var(--dc-palette-width,240px)}",
            ".dotapp-connector-palette.is-maximized{position:fixed !important;left:0;top:0;bottom:0;width:var(--dc-palette-width,240px) !important;height:auto !important;margin:0;border-radius:0;overflow:auto;box-sizing:border-box;z-index:2147483000;flex-direction:column;align-items:stretch}",
            "html.dc-page-locked{overflow:hidden}",
            ".dc-tools{position:sticky;top:0;left:0;height:0;z-index:6;display:flex;justify-content:flex-end;pointer-events:none}",
            ".dc-tool{pointer-events:auto;flex:none;display:grid;place-items:center;width:30px;height:30px;margin:8px 8px 0 0;padding:0;border:1px solid rgba(128,128,128,.4);border-radius:8px;background:rgba(255,255,255,.9);color:#444;cursor:pointer}",
            ".dc-tool+.dc-tool{margin-right:8px;margin-left:-2px}",
            ".dc-tool svg{display:block;width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}",
            ".is-parked [data-dc-park],.dc-readonly [data-dc-park]{display:none}",
            // Structure of the built-in frame
            ".dc-built{min-width:160px;max-width:420px}",
            ".dc-built *{box-sizing:border-box}",
            ".dc-head{position:relative;display:flex;align-items:center;gap:8px}",
            ".dc-title{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}",
            ".dc-kind{flex:none;width:8px;height:8px;border-radius:50%}",
            ".dc-close{flex:none;width:18px;height:18px;line-height:17px;text-align:center;border-radius:50%}",
            ".dc-ports{display:flex;justify-content:space-between;gap:14px}",
            ".dc-ports:empty,.dc-body:empty,.dc-status:empty{display:none}",
            ".dc-ins,.dc-outs{display:flex;flex-direction:column;gap:2px;min-width:0}",
            ".dc-outs{margin-left:auto;align-items:flex-end}",
            ".dc-port{display:flex;align-items:center;gap:7px;height:24px}",
            ".dc-dot{flex:none;width:13px;height:13px;border-radius:50%;position:relative}",
            ".dc-dot::after{content:'';position:absolute;left:-9px;top:-9px;right:-9px;bottom:-9px}",
            ".dc-in .dc-dot{margin-left:-7px}",
            ".dc-out .dc-dot{margin-right:-7px}",
            ".dc-label{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px}",
            ".dc-body img{display:block;max-width:100%}",
            "@keyframes dc-busy{0%{left:-40%}100%{left:100%}}",

            S + "{--dc-bg:#f6f7f9;--dc-dots:#d6dbe3;--dc-node:#ffffff;--dc-head:#ffffff;--dc-border:#dfe3ea;--dc-text:#1b2430;--dc-muted:#6b7686;--dc-accent:#2f6fed;--dc-wire:#a3adbd;--dc-error:#d43c3c;--dc-source:#12a36d;--dc-sink:#d98a0b;--dc-shadow:0 1px 2px rgba(16,24,40,.06),0 6px 18px rgba(16,24,40,.08);color:var(--dc-text);font:14px/1.4 system-ui,-apple-system,'Segoe UI',Roboto,sans-serif}",
            ":where(.dotapp-connector[data-theme=dark]:not(.dc-unstyled),.dotapp-connector-palette[data-theme=dark]:not(.dc-unstyled))" + DARK,
            "@media (prefers-color-scheme:dark){:where(.dotapp-connector[data-theme=auto]:not(.dc-unstyled),.dotapp-connector-palette[data-theme=auto]:not(.dc-unstyled))" + DARK + "}",
            C + "{min-height:160px;border:1px solid var(--dc-border);border-radius:12px;background-color:var(--dc-bg);background-image:radial-gradient(var(--dc-dots) 1px,transparent 1px);background-size:22px 22px;background-attachment:local}",
            ":where(.dotapp-connector-palette:not(.dc-unstyled)){display:flex;flex-direction:column;gap:10px;padding:12px;border:1px solid var(--dc-border);border-radius:12px;background:var(--dc-bg);min-height:60px}",
            S + " .dc-wire-del{box-shadow:var(--dc-shadow)}",
            C + " .dc-tool{border-color:var(--dc-border);background:var(--dc-node);color:var(--dc-muted);box-shadow:var(--dc-shadow)}",
            C + " .dc-tool:hover{color:var(--dc-accent);border-color:var(--dc-accent)}",
            ":where(.dotapp-connector-palette.is-maximized:not(.dc-unstyled)){border-width:0 1px 0 0}",
            S + " .dc-built{background:var(--dc-node);border:1px solid var(--dc-border);border-radius:11px;box-shadow:var(--dc-shadow);transition:border-color .15s,box-shadow .15s}",
            S + " .dc-built:hover{border-color:var(--dc-wire)}",
            S + " .dc-built.is-dragging{border-color:var(--dc-accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--dc-accent) 18%,transparent),var(--dc-shadow)}",
            S + " .dc-built.is-error{border-color:var(--dc-error)}",
            S + " .dc-head{padding:9px 12px;border-radius:10px 10px 0 0;background:var(--dc-head);border-bottom:1px solid var(--dc-border);font-weight:600;font-size:13px;letter-spacing:.01em;overflow:hidden}",
            S + " .dc-kind{background:var(--dc-accent)}",
            S + " .dc-source .dc-kind{background:var(--dc-source)}",
            S + " .dc-sink .dc-kind{background:var(--dc-sink)}",
            S + " .dc-close{color:var(--dc-muted);font-weight:400;font-size:15px}",
            S + " .dc-close:hover{background:var(--dc-error);color:#fff}",
            S + " .dc-built.is-busy .dc-head::after{content:'';position:absolute;left:0;bottom:0;height:2px;width:var(--dc-progress,40%);background:var(--dc-accent);animation:dc-busy 1s linear infinite}",
            S + " .dc-built.is-busy.has-progress .dc-head::after{animation:none;left:0}",
            S + " .dc-ports{padding:7px 0 5px}",
            S + " .dc-port{color:var(--dc-muted);font-size:12px}",
            S + " .dc-dot{border:2px solid var(--dc-wire);background:var(--dc-node);transition:transform .12s,border-color .12s,background .12s}",
            S + " .dc-port.is-wired .dc-dot{border-color:var(--dc-accent);background:var(--dc-accent)}",
            C + " .dc-port:hover .dc-dot," + C + " .dc-port.can-drop .dc-dot{border-color:var(--dc-accent);transform:scale(1.3)}",
            ":where(.dotapp-connector.dc-connecting:not(.dc-unstyled)) [data-dc-port]:not(.can-drop):not(.is-source){opacity:.35}",
            S + " .dc-body{padding:8px 12px 11px}",
            S + " .dc-body input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=range])," + S + " .dc-body select," + S + " .dc-body textarea{width:100%;max-width:100%;padding:6px 9px;border:1px solid var(--dc-border);border-radius:7px;background:var(--dc-bg);color:var(--dc-text);font:inherit}",
            S + " .dc-body input:focus," + S + " .dc-body select:focus," + S + " .dc-body textarea:focus{outline:2px solid color-mix(in srgb,var(--dc-accent) 35%,transparent);border-color:var(--dc-accent)}",
            S + " .dc-body input[type=file]," + S + " .dc-body input[type=range]{max-width:100%}",
            S + " .dc-body input{accent-color:var(--dc-accent)}",
            S + " .dc-body img{border-radius:7px}",
            S + " .dc-body button{font:inherit;cursor:pointer}",
            S + " .dc-status{padding:0 12px 9px;font-size:12px;color:var(--dc-muted);overflow-wrap:anywhere}",
            S + " .dc-built.is-error .dc-status{color:var(--dc-error)}"
        ].join("\n");

        function injectCss() {
            if (document.getElementById("dotapp-connector-style")) return;
            var style = document.createElement("style");
            style.id = "dotapp-connector-style";
            style.textContent = CSS;
            document.head.appendChild(style);
        }

        function make(tag, className, text) {
            var el = document.createElement(tag);
            if (className) el.className = className;
            if (text !== undefined && text !== null) el.textContent = text;
            return el;
        }

        function isEmpty(value) {
            return value === undefined || value === null || value === "" || (typeof value === "number" && isNaN(value));
        }

        function isPlainObject(value) {
            return value !== null && typeof value === "object" && Object.getPrototypeOf(value) === Object.prototype;
        }

        function isFile(value) {
            return typeof Blob !== "undefined" && value instanceof Blob;
        }

        // A copy which holds only what JSON can hold. Returns undefined when the value can not be saved.
        function plainCopy(value) {
            try {
                var text = JSON.stringify(value);
                return text === undefined ? undefined : JSON.parse(text);
            } catch (error) {
                return undefined;
            }
        }

        // JSON text packed into characters that survive every filter on the way to PHP
        function pack(value) {
            var json = JSON.stringify(value === undefined ? null : value);
            return btoa(unescape(encodeURIComponent(json))).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
        }

        function splitPort(port) {
            var parts = String(port).split(".");
            if (parts.length !== 2 || !NAME.test(parts[0]) || !NAME.test(parts[1])) {
                throw new Error('Connector: "' + port + '" is not a port. Write it as "node.port".');
            }
            return { node: parts[0], port: parts[1] };
        }

        function normalizePort(port, options) {
            if (typeof port === "string") port = Object.assign({ name: port }, options || {});
            if (!port || !NAME.test(String(port.name))) {
                throw new Error('Connector: "' + (port && port.name) + '" is not a valid name of a port.');
            }
            var out = { name: port.name, label: port.label === undefined || port.label === null ? port.name : port.label, type: port.type || null };
            if (port.required) out.required = true;
            if (port.multiple) out.multiple = true;
            if ("value" in port) out.value = port.value;
            return out;
        }

        class ConnectorHalt {
            constructor(reason) {
                this.reason = reason === undefined || reason === null ? "" : String(reason);
            }
        }

        /*
            One node. Given to the executor as "node" ( and as "this" ).
            The setters return the node, so a node is described in one chain.
        */
        class DotAppConnectorNode {
            constructor(connector, config) {
                if (!config || !NAME.test(String(config.name))) {
                    throw new Error('Connector: "' + (config && config.name) + '" is not a valid name of a node.');
                }
                var self = this;
                this.connector = connector;
                this.name = config.name;
                this.inputPorts = (config.inputs || []).map(function (port) { return normalizePort(port); });
                this.outputPorts = (config.outputs || []).map(function (port) { return normalizePort(port); });
                this.parked = false;
                this.template = config.template === true;                      // a stamp: it stays in the palette, drags make copies
                this.of = typeof config.of === "string" ? config.of : null;    // the template this node is a copy of
                this.data = isPlainObject(config.data) ? (plainCopy(config.data) || {}) : {};
                this.el = null;
                this._config = config;
                this._title = config.title === undefined || config.title === null ? config.name : String(config.title);
                this._manual = config.manual === true;
                this._backend = config.backend || null;
                this._fn = typeof config.fn === "function" ? config.fn : (typeof config.run === "function" ? config.run : null);
                this._init = typeof config.init === "function" ? config.init : null;
                this._save = typeof config.save === "function" ? config.save : null;
                this._load = typeof config.load === "function" ? config.load : null;
                this._inited = false;
                this._debounce = config.debounce === undefined || config.debounce === null ? (this._backend ? 250 : 0) : Math.max(0, Number(config.debounce) || 0);
                this._custom = config.custom === true || config.layout !== undefined;
                this._source = config.layout !== undefined ? config.layout : config.content;
                this._in = {};
                this._out = {};
                this._seq = 0;
                this._timer = null;
                this._inPorts = {};     // input name => element
                this._outPorts = {};    // output name => element
                this._frame = null;     // parts of the built-in frame
                this._status = null;
                this.outputPorts.forEach(function (port) {
                    if ("value" in port) self._out[port.name] = port.value;
                });
            }

            // "source" ( no inputs ), "sink" ( no outputs ) or "processor"
            get type() {
                if (!this.inputPorts.length) return "source";
                if (!this.outputPorts.length) return "sink";
                return "processor";
            }

            /* ---------------------------------------------------- describing the node */

            title(text) {
                if (text === undefined) return this._title;
                this._title = String(text);
                if (this._frame) this._frame.title.textContent = this._title;
                return this;
            }

            // Adds an input. options: { label, type, required }
            input(name, options) {
                this.connector._addPort(this, normalizePort(name, options), true);
                return this;
            }

            // Adds an output. options: { label, type, value }
            output(name, options) {
                this.connector._addPort(this, normalizePort(name, options), false);
                return this;
            }

            addInput(name, options) {
                return this.input(name, options);
            }

            addOutput(name, options) {
                return this.output(name, options);
            }

            // HTML placed into the built-in frame
            content(html) {
                this._custom = false;
                this._source = html;
                this.connector._renderNode(this);
                return this;
            }

            // Own design: the HTML is the whole node. Ports are its elements marked with connector-input / connector-output.
            layout(html) {
                this._custom = true;
                this._source = html;
                this.connector._renderNode(this);
                return this;
            }

            // Sets the executor: function (inputs, node)
            fn(executor) {
                if (typeof executor !== "function") throw new Error("Connector: fn() needs a function.");
                this._fn = executor;
                this.connector._schedule(this, false);
                return this;
            }

            // function (node), called once: listeners, first look
            init(callback) {
                if (typeof callback !== "function") throw new Error("Connector: init() needs a function.");
                this._init = callback;
                this._inited = false;
                if (this.connector._ready) this.connector._initNode(this);
                return this;
            }

            onSave(callback) {
                this._save = callback;
                return this;
            }

            onLoad(callback) {
                this._load = callback;
                return this;
            }

            manual(setting) {
                this._manual = setting !== false;
                return this;
            }

            debounce(milliseconds) {
                this._debounce = Math.max(0, Number(milliseconds) || 0);
                return this;
            }

            at(x, y) {
                return this.position(x, y);
            }

            position(x, y) {
                if (x === undefined) {
                    return { x: parseFloat(this.el.style.left) || 0, y: parseFloat(this.el.style.top) || 0 };
                }
                this.el.style.left = Math.max(0, Math.round(x)) + "px";
                this.el.style.top = Math.max(0, Math.round(y)) + "px";
                this.connector._fitStage();
                this.connector._requestDraw();
                return this;
            }

            /* ---------------------------------------------------- for the executor */

            // Values of the inputs as they were when the node ran last
            inputs() {
                return Object.assign({}, this._in);
            }

            // Value of an input, or of an output with this name
            get(name) {
                return name in this._in ? this._in[name] : this._out[name];
            }

            // { value, connected, from } of one input. from is "node.output" at the other end of the wire ( a list for a "multiple" input ).
            link(name) {
                var wires = this.connector._wiresTo(this.name + "." + name);
                var port = this.connector._port(this, name, "in");
                var from = wires.map(function (wire) { return wire.from; });
                return { value: this._in[name], connected: wires.length > 0, from: port && port.multiple ? from : (from[0] || null) };
            }

            // Sets an output and sends the value along its wires
            set(output, value) {
                this.connector._setOutput(this, output, value);
                return this;
            }

            // Runs the executor now, also when the node is manual
            run() {
                this.connector._runNode(this, true);
                return this;
            }

            halt(reason) {
                return new ConnectorHalt(reason);
            }

            // Runs the controller of the node on the server. Promise of the value of the output(s).
            backend(inputs) {
                return this.connector._callBackend(this, inputs === undefined ? this._in : inputs);
            }

            // Calls an API of the application through $dotapp().load ( CSRF + CRC ). Promise of the parsed reply.
            // The Promise is rejected when the request fails; what the reply means is decided by the executor.
            load(url, data) {
                var dotapp = this.connector.dotapp;
                return new Promise(function (resolve, reject) {
                    var request = dotapp.load(url, "POST", data || {}, function (raw) {
                        var reply = dotapp.parseReply(raw);
                        if (!reply || typeof reply !== "object") {
                            reject(new Error("The server did not send a readable answer."));
                            return;
                        }
                        resolve(reply);
                    }, function (status, body) {
                        var reply = dotapp.parseReply(body);
                        reject(new Error((reply && typeof reply === "object" && reply.message) || "The request failed ( " + status + " )."));
                    });
                    if (request && typeof request.catch === "function") request.catch(function () {});
                });
            }

            // Sends a file to an API of the application through $dotapp().uploadFile. Promise of the parsed reply.
            // The progress is shown on the node.
            upload(file, url) {
                var self = this;
                var dotapp = this.connector.dotapp;
                return dotapp.uploadFile(file, url, function (fileName, percent) {
                    self._busy(true, percent);
                }).then(function (raw) {
                    var reply = dotapp.parseReply(raw);
                    if (!reply || typeof reply !== "object") throw new Error("The server did not send a readable answer.");
                    return reply;
                }, function (error) {
                    var reply = error && error.responseText ? dotapp.parseReply(error.responseText) : null;
                    throw new Error((reply && typeof reply === "object" && reply.message) || "The file could not be sent ( " + ((error && error.status) || 0) + " ).");
                });
            }

            // $dotapp object with the elements inside of the node which match the selector
            find(selector) {
                return $dotapp(Array.prototype.slice.call(this.el.querySelectorAll(selector)));
            }

            // Listener inside of the node: on("input", "input", fn) or on("click", fn). The handler receives (element, event).
            on(event, selector, handler) {
                var self = this;
                if (typeof selector === "function") {
                    handler = selector;
                    selector = null;
                }
                this.el.addEventListener(event, function (e) {
                    var target = selector ? (e.target.closest ? e.target.closest(selector) : null) : self.el;
                    if (!target || !self.el.contains(target)) return;
                    try {
                        handler.call(self, target, e);
                    } catch (error) {
                        self.connector._fail(self, error);
                    }
                });
                return this;
            }

            // Writes into node.data and tells the connector that there is something to save
            store(key, value) {
                this.connector._seal();
                this.data[key] = value;
                this.connector._emit("change", { action: "data", node: this.name, key: key });
                return this;
            }

            // Text shown on the node. kind "error" marks the node. Empty text clears it.
            status(text, kind) {
                if (!this.el) return this;
                text = text === undefined || text === null ? "" : String(text);
                if (this._status) this._status.textContent = text;
                // Own layouts can also show the text with CSS: content: attr(data-status)
                if (text === "") this.el.removeAttribute("data-status"); else this.el.setAttribute("data-status", text);
                this.el.classList.toggle("is-error", kind === "error" && text !== "");
                this.connector._requestDraw();
                return this;
            }

            // Moves the node into the palette. Its wires are removed.
            park() {
                this.connector._park(this, false);
                return this;
            }

            // Moves the node from the palette onto the canvas
            unpark(x, y) {
                this.connector._unpark(this, x, y, false);
                return this;
            }

            _busy(on, percent) {
                if (!this.el) return;
                this.el.classList.toggle("is-busy", on);
                if (on && typeof percent === "number") {
                    this.el.classList.add("has-progress");
                    this.el.style.setProperty("--dc-progress", Math.max(2, Math.min(100, percent)) + "%");
                } else {
                    this.el.classList.remove("has-progress");
                    this.el.style.removeProperty("--dc-progress");
                }
            }
        }

        class DotAppConnector {
            constructor(dotapp, element, config) {
                config = config || {};
                this.dotapp = dotapp;
                this.element = element;
                this.name = config.name || element.getAttribute("data-connector") || element.id || "connector";
                this._nodes = {};           // name => DotAppConnectorNode
                this._order = [];           // names in the order they were added
                this._wires = {};           // "node.output>node.input" => { id, from, to, fromNode, fromPort, toNode, toPort, path, hit }
                this._handlers = {};
                this._pending = [];
                this._flushing = false;
                this._flushQueued = false;
                this._drawQueued = false;
                this._selected = null;
                this._deleteButton = null;
                this._palette = null;
                this._endpoint = config.endpoint || null;
                this._grid = Math.max(0, Number(config.grid) || 0);
                this._readonly = config.readonly === true;
                this._designOnly = config.designOnly === true;      // the graph is only wired here, it runs on the server
                this._maximized = false;
                this._fullscreen = false;
                this._depths = null;
                this._declared = null;
                this._ready = false;

                injectCss();
                this._build(config);
            }

            /* ------------------------------------------------------------ building */

            _build(config) {
                var self = this;
                var el = this.element;
                var holders = {};
                Array.prototype.forEach.call(el.children, function (child) {
                    if (child.classList && child.classList.contains("dotapp-connector-content")) {
                        holders[child.getAttribute("data-node")] = child;
                    }
                });

                el.classList.add("dotapp-connector");
                el.setAttribute("data-connector", this.name);
                el.setAttribute("data-theme", config.theme || el.getAttribute("data-theme") || "auto");
                if (!el.hasAttribute("tabindex")) el.setAttribute("tabindex", "0");
                el.classList.toggle("dc-readonly", this._readonly);
                if (config.unstyled === true) el.classList.add("dc-unstyled");

                if (config.tools !== false) {
                    // Two buttons in the corner: over the whole page, over the whole screen
                    var tools = make("div", "dc-tools");
                    [["maximize", "Over the whole page", "<path d='M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5'/>"],
                     ["fullscreen", "Over the whole screen", "<path d='M3 5h18v14H3zM8 21h8'/>"]].forEach(function (tool) {
                        var button = make("button", "dc-tool");
                        button.type = "button";
                        button.setAttribute("data-dc-tool", tool[0]);
                        button.setAttribute("title", tool[1]);
                        button.setAttribute("aria-label", tool[1]);
                        button.innerHTML = "<svg viewBox='0 0 24 24' aria-hidden='true'>" + tool[2] + "</svg>";
                        tools.appendChild(button);
                    });
                    el.appendChild(tools);
                }

                this._stage = make("div", "dc-stage");
                this._svg = document.createElementNS(SVG, "svg");
                this._svg.setAttribute("class", "dc-wires");
                this._stage.appendChild(this._svg);
                el.appendChild(this._stage);

                this._setupPalette(config.palette);

                // Functions of the nodes rendered by PHP: window.dotappConnectorFn[connector][node] = { fn, init, save, load }
                var functions = (window.dotappConnectorFn && window.dotappConnectorFn[this.name]) || {};
                var auto = [];
                var parked = [];
                (config.nodes || []).forEach(function (nodeConfig) {
                    var copy = Object.assign({}, nodeConfig);
                    var given = functions[copy.name] || {};
                    ["fn", "init", "save", "load"].forEach(function (key) {
                        if (typeof copy[key] !== "function" && typeof given[key] === "function") copy[key] = given[key];
                    });
                    if (copy.content === undefined && copy.layout === undefined && holders[copy.name]) {
                        var source = holders[copy.name];
                        if (copy.template === true) {
                            // A template is stamped many times: its HTML is kept as text
                            source = holders[copy.name].innerHTML;
                            holders[copy.name].remove();
                        }
                        if (copy.custom === true) copy.layout = source; else copy.content = source;
                    }
                    var node = self._createNode(copy);
                    if (copy.parked === true || copy.template === true) parked.push(node);
                    else if (copy.x === undefined || copy.x === null) auto.push(node);
                });

                (config.connections || []).forEach(function (wire) {
                    var from = Array.isArray(wire) ? wire[0] : wire.from;
                    var to = Array.isArray(wire) ? wire[1] : wire.to;
                    self._connect(from, to, true);
                });

                if (auto.length) this._autoLayout(auto);
                parked.forEach(function (node) { self._park(node, true); });
                this._bindEvents();
                // A graph described by PHP or by the settings is complete now: this is what reset() returns to.
                // A graph built with node() / addNode() afterwards is fixed later, see _seal().
                if ((config.nodes || []).length) this._seal();

                if (config.state) this._applyState(config.state);

                // Nodes get ready: listeners, first look. A saved state is already in node.data.
                this._ready = true;
                this._order.forEach(function (name) { self._initNode(self._nodes[name]); });

                this._fitStage();
                this._draw();
                this._order.forEach(function (name) { self._schedule(self._nodes[name], false); });

                if (typeof ResizeObserver !== "undefined") {
                    this._observer = new ResizeObserver(function () { self._fitStage(); self._requestDraw(); });
                    this._observer.observe(el);
                    this._order.forEach(function (name) { self._observer.observe(self._nodes[name].el); });
                }
            }

            _createNode(config) {
                var node = new DotAppConnectorNode(this, config);
                if (this._nodes[node.name]) {
                    throw new Error('Connector "' + this.name + '": node "' + node.name + '" already exists.');
                }
                var el = make("div", "dc-node" + (config.cssClass ? " " + config.cssClass : ""));
                el.setAttribute("data-node", node.name);
                if (config.width) el.style.width = Number(config.width) + "px";
                el.style.left = (Number(config.x) || 0) + "px";
                el.style.top = (Number(config.y) || 0) + "px";
                node.el = el;
                this._renderNode(node);

                this._stage.appendChild(el);
                this._nodes[node.name] = node;
                this._order.push(node.name);
                this._depths = null;
                if (this._observer) this._observer.observe(el);
                return node;
            }

            // Builds the inside of a node from its description: the built-in frame, or the own layout of the module
            _renderNode(node) {
                var self = this;
                var el = node.el;
                while (el.firstChild) el.removeChild(el.firstChild);
                el.removeAttribute("data-dc-drag");
                el.classList.toggle("dc-custom", node._custom);
                el.classList.toggle("dc-built", !node._custom);
                node._inPorts = {};
                node._outPorts = {};
                node._frame = null;
                node._status = null;

                var source = node._source;
                if (node._custom && typeof source === "string" && source.trim().charAt(0) !== "<") {
                    source = document.querySelector(source);     // a selector of an element which already is in the page
                }

                if (node._custom) {
                    // Own design: the whole node is the HTML of the module. Ports are the elements marked with attributes.
                    this._fill(el, source);
                    Array.prototype.forEach.call(el.querySelectorAll("[connector-input],[connector-output]"), function (portEl) {
                        var isInput = portEl.hasAttribute("connector-input");
                        var name = portEl.getAttribute(isInput ? "connector-input" : "connector-output");
                        var list = isInput ? node.inputPorts : node.outputPorts;
                        if (!list.some(function (port) { return port.name === name; })) {
                            var found = { name: name, type: portEl.getAttribute("connector-type") };
                            if (isInput && portEl.hasAttribute("connector-required")) found.required = true;
                            if (isInput && portEl.hasAttribute("connector-multiple")) found.multiple = true;
                            if (!isInput && portEl.hasAttribute("connector-value")) {
                                var constant = portEl.getAttribute("connector-value");
                                found.value = constant !== "" && !isNaN(Number(constant)) ? Number(constant) : constant;
                                node._out[name] = found.value;
                            }
                            list.push(normalizePort(found));
                        }
                        self._registerPort(node, portEl, name, isInput, portEl);
                    });
                    node.inputPorts.concat(node.outputPorts).forEach(function (port, index) {
                        var isInput = index < node.inputPorts.length;
                        if (!(isInput ? node._inPorts : node._outPorts)[port.name]) {
                            throw new Error('Connector "' + self.name + '": the layout of node "' + node.name + '" has no element with connector-' + (isInput ? "input" : "output") + '="' + port.name + '".');
                        }
                    });
                    var handles = el.querySelectorAll("[connector-drag]");
                    if (handles.length) {
                        Array.prototype.forEach.call(handles, function (handle) { handle.setAttribute("data-dc-drag", ""); });
                    } else {
                        el.setAttribute("data-dc-drag", "");      // no handle marked: the node is moved by any place which is not a control
                    }
                    Array.prototype.forEach.call(el.querySelectorAll("[connector-park]"), function (button) { button.setAttribute("data-dc-park", ""); });
                    node._status = el.querySelector("[connector-status]");
                } else {
                    // Built-in frame: header, ports, content, status
                    var frame = {};
                    frame.head = make("div", "dc-head");
                    frame.head.setAttribute("data-dc-drag", "");
                    frame.head.appendChild(make("span", "dc-kind"));
                    frame.title = make("span", "dc-title", node._title);
                    frame.head.appendChild(frame.title);
                    if (this._palette) {
                        var close = make("span", "dc-close", "×");
                        close.setAttribute("data-dc-park", "");
                        close.setAttribute("title", "Remove from the canvas");
                        frame.head.appendChild(close);
                    }
                    el.appendChild(frame.head);

                    frame.ports = make("div", "dc-ports");
                    frame.ins = make("div", "dc-ins");
                    frame.outs = make("div", "dc-outs");
                    el.appendChild(frame.ports);

                    frame.body = make("div", "dc-body");
                    this._fill(frame.body, source);
                    el.appendChild(frame.body);

                    node._status = make("div", "dc-status");
                    el.appendChild(node._status);
                    node._frame = frame;

                    node.inputPorts.forEach(function (port) { self._addPortRow(node, port, true); });
                    node.outputPorts.forEach(function (port) { self._addPortRow(node, port, false); });
                }
                this._markKind(node);
                if (this._ready) {
                    this._fitStage();
                    this._requestDraw();
                }
            }

            // One row of the built-in frame: a dot and the label of the port
            _addPortRow(node, port, isInput) {
                var frame = node._frame;
                var column = isInput ? frame.ins : frame.outs;
                if (!column.parentNode) {
                    if (isInput) frame.ports.insertBefore(column, frame.ports.firstChild); else frame.ports.appendChild(column);
                }
                var row = make("div", "dc-port " + (isInput ? "dc-in" : "dc-out"));
                var dot = make("span", "dc-dot");
                var label = make("span", "dc-label", port.label);
                if (isInput) { row.appendChild(dot); row.appendChild(label); } else { row.appendChild(label); row.appendChild(dot); }
                column.appendChild(row);
                this._registerPort(node, row, port.name, isInput, dot);
            }

            // node.input() / node.output(): a port added to a node which already exists
            _addPort(node, port, isInput) {
                var list = isInput ? node.inputPorts : node.outputPorts;
                for (var i = 0; i < list.length; i++) {
                    if (list[i].name === port.name) {
                        list[i] = port;         // described again: the new options replace the old ones
                        if (!isInput && "value" in port) node._out[port.name] = port.value;
                        return;
                    }
                }
                if (node._custom && !node.el.querySelector("[connector-" + (isInput ? "input" : "output") + '="' + port.name + '"]')) {
                    throw new Error('Connector "' + this.name + '": the layout of node "' + node.name + '" has no element with connector-' + (isInput ? "input" : "output") + '="' + port.name + '".');
                }
                list.push(port);
                if (!isInput && "value" in port) node._out[port.name] = port.value;
                if (node._custom) {
                    this._renderNode(node);
                } else {
                    this._addPortRow(node, port, isInput);
                    this._markKind(node);
                }
                this._depths = null;
                this._fitStage();
                this._requestDraw();
                this._schedule(node, false);
            }

            _markKind(node) {
                var type = node.type;
                node.el.classList.toggle("dc-source", type === "source");
                node.el.classList.toggle("dc-processor", type === "processor");
                node.el.classList.toggle("dc-sink", type === "sink");
            }

            // Puts the content of a node into its element: HTML text, the holder rendered by PHP, or an element of the page
            _fill(target, source) {
                if (typeof source === "string") {
                    target.innerHTML = source;
                } else if (source instanceof HTMLElement) {
                    if (source.classList.contains("dotapp-connector-content")) {
                        // Rendered by PHP: the children are moved, so listeners and values stay
                        while (source.firstChild) target.appendChild(source.firstChild);
                        source.remove();
                    } else {
                        source.hidden = false;
                        target.appendChild(source);
                    }
                }
            }

            _registerPort(node, el, name, isInput, anchor) {
                var map = isInput ? node._inPorts : node._outPorts;
                if (map[name]) {
                    throw new Error('Connector "' + this.name + '": node "' + node.name + '" has two ' + (isInput ? "inputs" : "outputs") + ' named "' + name + '".');
                }
                el.setAttribute("data-dc-port", isInput ? "in" : "out");
                el.setAttribute("data-port", name);
                el._dcAnchor = anchor;
                map[name] = el;
            }

            // Remembers the graph as it was declared, for reset(). Called when the building is over:
            // before a state is loaded and before the user touches the graph for the first time.
            _seal() {
                if (!this._declared) this._declared = this._state(false);
            }

            _initNode(node) {
                if (!node._init || node._inited) return;
                node._inited = true;
                try {
                    node._init.call(node, node);
                } catch (error) {
                    this._fail(node, error);
                }
            }

            // Nodes are placed in columns: sources on the left, what they feed to the right
            _autoLayout(nodes) {
                var depths = this._getDepths();
                var rows = {};
                nodes.forEach(function (node) {
                    var column = depths[node.name] || 0;
                    rows[column] = rows[column] || 0;
                    node.el.style.left = (40 + column * 270) + "px";
                    node.el.style.top = (40 + rows[column]) + "px";
                    rows[column] += node.el.offsetHeight + 30;
                });
                this._fitStage();
            }

            /* ------------------------------------------------------------ public API */

            // The node with this name. It is created when it does not exist yet, so a graph is written as chains:
            // graph.node("sum").input("a").input("b").output("result").fn(function (inputs) { return inputs.a + inputs.b; });
            node(name) {
                if (this._nodes[name]) return this._nodes[name];
                var node = this._createNode({ name: name });
                // A free place: new nodes go one under another until they get their own position with at()
                var bottom = 40;
                var self = this;
                this._order.forEach(function (other) {
                    var el = self._nodes[other].el;
                    if (other !== name && !self._nodes[other].parked) bottom = Math.max(bottom, el.offsetTop + el.offsetHeight + 30);
                });
                node.el.style.left = "40px";
                node.el.style.top = bottom + "px";
                this._fitStage();
                this._requestDraw();
                this._emit("change", { action: "addNode", node: node.name });
                return node;
            }

            has(name) {
                return !!this._nodes[name];
            }

            nodes() {
                var self = this;
                return this._order.map(function (name) { return self._nodes[name]; });
            }

            // Adds a node described by one object: { name, title, x, y, width, inputs, outputs, content | layout, fn, init, save, load, manual, debounce, data, parked }
            addNode(config) {
                config = config || {};
                var node = this._createNode(config);
                if (config.parked === true) {
                    this._park(node, true);
                } else if (config.x === undefined || config.x === null) {
                    this._autoLayout([node]);
                }
                this._initNode(node);
                this._fitStage();
                this._requestDraw();
                this._schedule(node, false);
                this._emit("change", { action: "addNode", node: node.name });
                return node;
            }

            // Makes a copy of a template: a new node with its own wires and its own data.
            // Without a name the copy is called "template-1", "template-2" ... Returns the new node.
            copy(template, name) {
                var node = this._copy(template, name, undefined, undefined);
                this._schedule(node, false);
                this._emit("change", { action: "addNode", node: node.name });
                return node;
            }

            _copy(templateName, name, x, y) {
                var template = this._nodes[templateName];
                if (!template || !template.template) {
                    throw new Error('Connector "' + this.name + '": "' + templateName + '" is not a template.');
                }
                if (template._config.content instanceof HTMLElement || template._config.layout instanceof HTMLElement) {
                    throw new Error('Connector "' + this.name + '": the HTML of the template "' + templateName + '" must be text, not an element.');
                }
                if (!name) {
                    var number = 1;
                    do { name = templateName + "-" + number++; } while (this._nodes[name]);
                }
                var placed = x !== undefined && x !== null;
                if (placed && this._grid > 0) {
                    x = Math.round(x / this._grid) * this._grid;
                    y = Math.round(y / this._grid) * this._grid;
                }
                var node = this._createNode(Object.assign({}, template._config, {
                    name: name,
                    template: false,
                    parked: false,
                    of: templateName,
                    x: placed ? Math.max(0, Math.round(x)) : 0,
                    y: placed ? Math.max(0, Math.round(y)) : 0,
                    data: plainCopy(template.data) || {}
                }));
                if (!placed) {
                    var self = this;
                    var bottom = 40;
                    this._order.forEach(function (other) {
                        var el = self._nodes[other].el;
                        if (other !== name && !self._nodes[other].parked) bottom = Math.max(bottom, el.offsetTop + el.offsetHeight + 30);
                    });
                    node.el.style.left = "40px";
                    node.el.style.top = bottom + "px";
                }
                if (this._ready) this._initNode(node);
                this._fitStage();
                this._requestDraw();
                return node;
            }

            // The canvas over the whole page ( true ) or back in its place ( false ). Without an argument: is it spread?
            maximize(setting) {
                if (setting === undefined) return this._maximized;
                var on = setting !== false;
                if (on === this._maximized) return this;
                this._maximized = on;
                this.element.classList.toggle("is-maximized", on);
                this.element.classList.toggle("has-palette", on && !!this._palette);
                if (this._palette) this._palette.classList.toggle("is-maximized", on);
                document.documentElement.classList.toggle("dc-page-locked", on);
                this._fitStage();
                this._requestDraw();
                this._emit("maximize", { maximized: on, fullscreen: this._fullscreen });
                return this;
            }

            // The canvas over the whole screen. The browser allows it only as an answer to a click or a key.
            fullscreen(setting) {
                if (setting === undefined) return this._fullscreen;
                var on = setting !== false;
                var root = document.documentElement;
                if (on) {
                    this._fullscreen = true;
                    this.maximize(true);
                    if (root.requestFullscreen) {
                        var self = this;
                        var asked = root.requestFullscreen();
                        if (asked && typeof asked.catch === "function") asked.catch(function () { self._fullscreen = false; });
                    }
                } else {
                    this._fullscreen = false;
                    if (document.fullscreenElement && document.exitFullscreen) document.exitFullscreen();
                    this.maximize(false);
                }
                return this;
            }

            removeNode(name, silent) {
                var self = this;
                var node = this._nodes[name];
                if (!node) return this;
                Object.keys(this._wires).forEach(function (key) {
                    var wire = self._wires[key];
                    if (wire.fromNode === name || wire.toNode === name) self._removeWire(wire, silent === true);
                });
                if (this._observer) this._observer.unobserve(node.el);
                clearTimeout(node._timer);
                node._seq++;
                node.el.remove();
                delete this._nodes[name];
                this._order = this._order.filter(function (n) { return n !== name; });
                this._pending = this._pending.filter(function (n) { return n !== node; });
                this._depths = null;
                this._fitStage();
                this._requestDraw();
                if (silent !== true) this._emit("change", { action: "removeNode", node: name });
                return this;
            }

            // Joins "node.output" with "node.input". An input holds one wire: a new wire replaces the old one.
            // A wire which can not exist ( a loop, different types ) is refused with a warning; ask with canConnect() first when it matters.
            connect(from, to) {
                this._connect(from, to, false);
                return this;
            }

            // true when a wire between the two ports can exist
            canConnect(from, to) {
                var a = splitPort(from);
                var b = splitPort(to);
                return this._problem(a, b) === "" || this._problem(b, a) === "";
            }

            // Removes the wire of the input "node.port" and every wire of the output "node.port"
            disconnect(port) {
                var self = this;
                var ref = splitPort(port);
                var key = ref.node + "." + ref.port;
                Object.keys(this._wires).forEach(function (id) {
                    var wire = self._wires[id];
                    if (wire.from === key || wire.to === key) self._removeWire(wire, false);
                });
                return this;
            }

            connections() {
                var self = this;
                return Object.keys(this._wires).map(function (key) {
                    return { from: self._wires[key].from, to: self._wires[key].to };
                });
            }

            // Sets the executor of a node
            fn(nodeName, executor) {
                this.node(nodeName).fn(executor);
                return this;
            }

            // Runs one node, or the whole graph again when no name is given
            run(nodeName) {
                var self = this;
                if (nodeName !== undefined) {
                    if (this._nodes[nodeName]) this._runNode(this._nodes[nodeName], true);
                    return this;
                }
                this._order.forEach(function (name) { self._schedule(self._nodes[name], false); });
                return this;
            }

            // Places every node of the canvas in columns: sources on the left, what they feed to the right
            arrange() {
                var self = this;
                this._autoLayout(this._order.map(function (name) { return self._nodes[name]; }).filter(function (node) { return !node.parked; }));
                this._requestDraw();
                this._emit("change", { action: "arrange" });
                return this;
            }

            /**
             * Events: connect, disconnect, move, park, unpark, run, output, error, change
             * "change" fires after everything that should be saved: wires, positions, parked nodes, node.store()
             */
            on(event, handler) {
                (this._handlers[event] = this._handlers[event] || []).push(handler);
                return this;
            }

            off(event, handler) {
                if (!this._handlers[event]) return this;
                this._handlers[event] = handler ? this._handlers[event].filter(function (h) { return h !== handler; }) : [];
                return this;
            }

            // The state: positions of nodes, parked nodes, wires and node.data. Plain data, safe for JSON.
            // save(true) returns it as one text ready for $dotapp().load() - PHP reads it with Connector::unpack() or ->restore()
            save(packed) {
                var state = this._state(true);
                return packed === true ? pack(state) : state;
            }

            // Restores a state returned by save() and runs the graph again
            load(state) {
                var self = this;
                if (typeof state === "string") {
                    try { state = JSON.parse(state); } catch (e) { state = null; }
                }
                if (!state || typeof state !== "object") return this;
                this._seal();
                this._applyState(state);
                this._order.forEach(function (name) {
                    var node = self._nodes[name];
                    if (!node._load) return;
                    try {
                        node._load.call(node, node);
                    } catch (error) {
                        self._fail(node, error);
                    }
                });
                this._fitStage();
                this._draw();
                this.run();
                return this;
            }

            // Back to the graph as it was declared: positions, wires, parked nodes and node.data
            reset() {
                var self = this;
                this._seal();
                this._order.forEach(function (name) {
                    // A template keeps its data: it is the pattern for the copies made later
                    if (!self._nodes[name].template && !(self._declared.data || {})[name]) self._nodes[name].data = {};
                });
                this.load(this._declared);
                this._emit("change", { action: "reset" });
                return this;
            }

            readonly(setting) {
                if (setting === undefined) return this._readonly;
                this._readonly = setting === true;
                this.element.classList.toggle("dc-readonly", this._readonly);
                if (this._palette) this._palette.classList.toggle("dc-readonly", this._readonly);
                this._select(null);
                return this;
            }

            halt(reason) {
                return new ConnectorHalt(reason);
            }

            isHalt(value) {
                return value instanceof ConnectorHalt;
            }

            /* ------------------------------------------------------------ wires */

            _connect(from, to, silent) {
                var a = splitPort(from);
                var b = splitPort(to);
                var fromNode = this._nodes[a.node];
                var toNode = this._nodes[b.node];
                // Written the other way round ( input first ): swap
                if (fromNode && toNode && !this._port(fromNode, a.port, "out") && this._port(fromNode, a.port, "in") && this._port(toNode, b.port, "out")) {
                    var swap = a; a = b; b = swap;
                    fromNode = this._nodes[a.node];
                    toNode = this._nodes[b.node];
                }
                var problem = this._problem(a, b);
                if (problem) {
                    console.warn('Connector "' + this.name + '": ' + problem);
                    return false;
                }
                var self = this;
                var key = b.node + "." + b.port;
                var id = a.node + "." + a.port + ">" + key;
                if (this._wires[id]) return true;
                if (!this._port(toNode, b.port, "in").multiple) {
                    // An input holds one wire: the new wire replaces the old one
                    this._wiresTo(key).forEach(function (existing) { self._removeWire(existing, silent); });
                }
                var wire = { id: id, from: a.node + "." + a.port, to: key, fromNode: a.node, fromPort: a.port, toNode: b.node, toPort: b.port };
                wire.path = document.createElementNS(SVG, "path");
                wire.path.setAttribute("class", "dc-wire");
                wire.hit = document.createElementNS(SVG, "path");
                wire.hit.setAttribute("class", "dc-wire-hit");
                wire.hit.setAttribute("data-wire", id);
                this._svg.appendChild(wire.path);
                this._svg.appendChild(wire.hit);
                this._wires[id] = wire;
                this._depths = null;
                this._requestDraw();
                if (!silent) {
                    this._schedule(toNode, false);
                    this._emit("connect", { from: wire.from, to: wire.to });
                    this._emit("change", { action: "connect", from: wire.from, to: wire.to });
                }
                return true;
            }

            // Wires which end on the input "node.input"
            _wiresTo(key) {
                var self = this;
                return Object.keys(this._wires).filter(function (id) { return self._wires[id].to === key; }).map(function (id) { return self._wires[id]; });
            }

            _removeWire(wire, silent) {
                if (this._selected === wire.id) this._select(null);
                wire.path.remove();
                wire.hit.remove();
                delete this._wires[wire.id];
                this._depths = null;
                this._requestDraw();
                if (!silent) {
                    if (this._nodes[wire.toNode]) this._schedule(this._nodes[wire.toNode], false);
                    this._emit("disconnect", { from: wire.from, to: wire.to });
                    this._emit("change", { action: "disconnect", from: wire.from, to: wire.to });
                }
            }

            _port(node, name, direction) {
                var list = direction === "in" ? node.inputPorts : node.outputPorts;
                for (var i = 0; i < list.length; i++) {
                    if (list[i].name === name) return list[i];
                }
                return null;
            }

            // Returns the reason why output a can not be joined with input b, or "" when it can
            _problem(a, b) {
                var fromNode = this._nodes[a.node];
                var toNode = this._nodes[b.node];
                var out = fromNode ? this._port(fromNode, a.port, "out") : null;
                var input = toNode ? this._port(toNode, b.port, "in") : null;
                if (!out) return '"' + a.node + "." + a.port + '" is not an output.';
                if (!input) return '"' + b.node + "." + b.port + '" is not an input.';
                if (fromNode.parked || toNode.parked) return "a node in the palette can not be joined.";
                if (out.type && input.type && out.type !== input.type) {
                    return '"' + a.node + "." + a.port + '" ( ' + out.type + ' ) can not be joined with "' + b.node + "." + b.port + '" ( ' + input.type + " ).";
                }
                if (a.node === b.node || this._feeds(b.node, a.node)) {
                    return 'a wire from "' + a.node + '" to "' + b.node + '" would make a loop.';
                }
                return "";
            }

            // true when a value leaving node "start" can reach node "target"
            _feeds(start, target) {
                var self = this;
                var stack = [start];
                var seen = {};
                while (stack.length) {
                    var current = stack.pop();
                    if (current === target) return true;
                    if (seen[current]) continue;
                    seen[current] = true;
                    Object.keys(this._wires).forEach(function (key) {
                        if (self._wires[key].fromNode === current) stack.push(self._wires[key].toNode);
                    });
                }
                return false;
            }

            // Column of every node: 0 for nodes that nothing feeds, +1 behind every wire
            _getDepths() {
                if (this._depths) return this._depths;
                var self = this;
                var depths = {};
                var visit = function (name, guard) {
                    if (depths[name] !== undefined) return depths[name];
                    if (guard[name]) return 0;
                    guard[name] = true;
                    var depth = 0;
                    Object.keys(self._wires).forEach(function (key) {
                        var wire = self._wires[key];
                        if (wire.toNode === name && self._nodes[wire.fromNode]) {
                            depth = Math.max(depth, visit(wire.fromNode, guard) + 1);
                        }
                    });
                    depths[name] = depth;
                    return depth;
                };
                this._order.forEach(function (name) { visit(name, {}); });
                this._depths = depths;
                return depths;
            }

            /* ------------------------------------------------------------ running */

            // Values waiting on the inputs of a node: what the wires bring, undefined where nothing is connected or nothing arrived
            _gather(node) {
                var self = this;
                var inputs = {};
                var missing = null;
                node.inputPorts.forEach(function (port) {
                    var values = self._wiresTo(node.name + "." + port.name).map(function (wire) {
                        return self._nodes[wire.fromNode] ? self._nodes[wire.fromNode]._out[wire.fromPort] : undefined;
                    });
                    if (port.multiple) {
                        // More wires may end here: the executor gets a list of the values which arrived
                        values = values.filter(function (value) { return !isEmpty(value); });
                        inputs[port.name] = values;
                        if (port.required && !values.length && missing === null) missing = port.name;
                        return;
                    }
                    inputs[port.name] = values[0];
                    if (port.required && isEmpty(values[0]) && missing === null) missing = port.name;
                });
                return { inputs: inputs, missing: missing };
            }

            _schedule(node, manual) {
                var self = this;
                if (!this._ready || this._designOnly || !node || this._nodes[node.name] !== node || node.parked) return;
                clearTimeout(node._timer);
                if (node._debounce > 0 && !manual) {
                    // The node waits for its timer only: a run asked earlier in this moment is dropped
                    this._pending = this._pending.filter(function (waiting) { return waiting !== node; });
                    node._timer = setTimeout(function () { self._enqueue(node); }, node._debounce);
                    return;
                }
                this._enqueue(node);
            }

            _enqueue(node) {
                var self = this;
                if (this._pending.indexOf(node) === -1) this._pending.push(node);
                if (this._flushQueued || this._flushing) return;
                this._flushQueued = true;
                Promise.resolve().then(function () {
                    self._flushQueued = false;
                    self._flush();
                });
            }

            // Runs the waiting nodes from the left to the right, so a node runs once with all its new inputs
            _flush() {
                this._flushing = true;
                var guard = 0;
                while (this._pending.length && guard++ < 100000) {
                    var depths = this._getDepths();
                    var best = 0;
                    for (var i = 1; i < this._pending.length; i++) {
                        if ((depths[this._pending[i].name] || 0) < (depths[this._pending[best].name] || 0)) best = i;
                    }
                    var node = this._pending.splice(best, 1)[0];
                    if (this._nodes[node.name] === node) this._runNode(node, false);
                }
                this._flushing = false;
            }

            _runNode(node, manual) {
                var self = this;
                if (this._designOnly || node.parked || this._nodes[node.name] !== node) return;
                var gathered = this._gather(node);
                node._in = gathered.inputs;

                if (gathered.missing !== null) {
                    // A required input is empty: the executor is not called and nothing leaves the node
                    node._seq++;
                    node._busy(false);
                    node.status("");
                    this._clearOutputs(node);
                    this._emit("run", { node: node.name, halted: true, missing: gathered.missing });
                    return;
                }
                if (node._manual && !manual) return;

                var executor = node._fn || (node._backend ? function (inputs, n) { return n.backend(inputs); } : null);
                if (!executor) {
                    // No executor: the node only holds the values declared on its outputs
                    node.outputPorts.forEach(function (port) {
                        if ("value" in port) self._setOutput(node, port.name, port.value);
                    });
                    return;
                }

                var seq = ++node._seq;
                var result;
                try {
                    result = executor.call(node, gathered.inputs, node);
                } catch (error) {
                    this._fail(node, error);
                    return;
                }

                if (result && typeof result.then === "function") {
                    node._busy(true);
                    node.status("");
                    result.then(function (value) {
                        if (seq !== node._seq) return;      // a newer run has started, this answer is old
                        node._busy(false);
                        self._apply(node, value);
                    }, function (error) {
                        if (seq !== node._seq) return;
                        node._busy(false);
                        self._fail(node, error);
                    });
                    return;
                }
                this._apply(node, result);
            }

            // What the executor returned goes to the outputs: one output takes the value, more outputs take an object keyed by their names
            _apply(node, result) {
                var self = this;
                if (result instanceof ConnectorHalt) {
                    node.status(result.reason, "info");
                    this._clearOutputs(node);
                    this._emit("run", { node: node.name, halted: true, reason: result.reason });
                    return;
                }
                node.status("");
                if (result !== undefined && node.outputPorts.length === 1) {
                    this._setOutput(node, node.outputPorts[0].name, result);
                } else if (result !== undefined && node.outputPorts.length > 1) {
                    if (!isPlainObject(result)) {
                        this._fail(node, new Error('Node "' + node.name + '" has more outputs: its executor must return an object keyed by their names.'));
                        return;
                    }
                    node.outputPorts.forEach(function (port) {
                        if (port.name in result) self._setOutput(node, port.name, result[port.name]);
                    });
                }
                this._emit("run", { node: node.name, inputs: node.inputs(), outputs: Object.assign({}, node._out) });
            }

            _fail(node, error) {
                var message = error && error.message ? error.message : String(error);
                node.status(message, "error");
                this._clearOutputs(node);
                this._emit("error", { node: node.name, message: message, error: error });
            }

            _clearOutputs(node) {
                var self = this;
                node.outputPorts.forEach(function (port) {
                    if (node._out[port.name] !== undefined) self._setOutput(node, port.name, undefined);
                });
            }

            _setOutput(node, output, value) {
                var self = this;
                if (!this._port(node, output, "out")) {
                    throw new Error('Connector "' + this.name + '": node "' + node.name + '" has no output "' + output + '".');
                }
                var previous = node._out[output];
                if (previous === value && (value === null || typeof value !== "object")) return;
                node._out[output] = value;
                this._requestDraw();
                this._emit("output", { node: node.name, output: output, value: value });
                var from = node.name + "." + output;
                Object.keys(this._wires).forEach(function (key) {
                    if (self._wires[key].from === from) self._schedule(self._nodes[self._wires[key].toNode], false);
                });
            }

            // Sends the inputs to the controller of the node. A file among the inputs travels as an upload.
            _callBackend(node, inputs) {
                var self = this;
                if (!node._backend || !this._endpoint) {
                    return Promise.reject(new Error('Node "' + node.name + '" has no controller.'));
                }
                var plain = {};
                var file = null;
                Object.keys(inputs || {}).forEach(function (name) {
                    var value = inputs[name];
                    if (Array.isArray(value) && value.length && isFile(value[0])) value = value[0];
                    if (isFile(value)) {
                        if (!file) file = value;
                        return;
                    }
                    plain[name] = value;
                });

                var parse = function (raw) {
                    var reply = self.dotapp.parseReply(raw);
                    if (!reply || typeof reply !== "object") {
                        throw new Error("The server did not send a readable answer.");
                    }
                    if (reply.status != 1) {
                        if (reply.halt) return new ConnectorHalt(reply.message);
                        throw new Error(reply.message || "The request failed.");
                    }
                    return reply.output === null ? undefined : reply.output;
                };

                if (file) {
                    // Files can not carry the CRC of load(): the signed token of the node travels in the URL
                    var url = this._endpoint + (this._endpoint.indexOf("?") === -1 ? "?" : "&") + "connector-node=" + encodeURIComponent(node._backend) + "&connector-inputs=" + pack(plain) + "&connector-data=" + pack(plainCopy(node.data) || {});
                    return this.dotapp.uploadFile(file, url, function (fileName, percent) {
                        node._busy(true, percent);
                    }).then(parse, function (error) {
                        var reply = error && error.responseText ? self.dotapp.parseReply(error.responseText) : null;
                        throw new Error((reply && typeof reply === "object" && reply.message) || "The file could not be sent ( " + ((error && error.status) || 0) + " ).");
                    });
                }

                return new Promise(function (resolve, reject) {
                    var request = self.dotapp.load(self._endpoint, "POST", { "connector-node": node._backend, "connector-inputs": pack(plain), "connector-data": pack(plainCopy(node.data) || {}) }, function (raw) {
                        resolve(raw);
                    }, function (status, body) {
                        var reply = self.dotapp.parseReply(body);
                        reject(new Error((reply && typeof reply === "object" && reply.message) || "The request failed ( " + status + " )."));
                    });
                    if (request && typeof request.catch === "function") request.catch(function () {});
                }).then(parse);
            }

            /* ------------------------------------------------------------ state */

            _state(askNodes) {
                var self = this;
                var state = { nodes: {}, connections: this.connections(), data: {} };
                this._order.forEach(function (name) {
                    var node = self._nodes[name];
                    if (askNodes && node._save) {
                        try {
                            node._save.call(node, node);
                        } catch (error) {
                            self._fail(node, error);
                        }
                    }
                    if (node.template) return;         // a stamp is not a part of the graph, only its copies are
                    state.nodes[name] = node.parked ? { parked: true } : node.position();
                    if (node.of) state.nodes[name].of = node.of;
                    var data = plainCopy(node.data);
                    if (isPlainObject(data) && Object.keys(data).length) state.data[name] = data;
                });
                return state;
            }

            _applyState(state) {
                var self = this;
                if (state.nodes && typeof state.nodes === "object") {
                    // Copies of templates: the state says which of them exist
                    this._order.slice().forEach(function (name) {
                        if (self._nodes[name].of && !state.nodes[name]) self.removeNode(name, true);
                    });
                    Object.keys(state.nodes).forEach(function (name) {
                        var entry = state.nodes[name];
                        if (!entry || typeof entry.of !== "string" || self._nodes[name] || !NAME.test(name)) return;
                        if (self._nodes[entry.of] && self._nodes[entry.of].template) self._copy(entry.of, name, 0, 0);
                    });
                    Object.keys(state.nodes).forEach(function (name) {
                        var node = self._nodes[name];
                        var position = state.nodes[name];
                        if (!node || !position || node.template) return;
                        if (position.parked === true) {
                            if (!node.parked && self._palette) self._park(node, true);
                            return;
                        }
                        if (node.parked) self._unpark(node, 0, 0, true);
                        node.el.style.left = Math.max(0, Number(position.x) || 0) + "px";
                        node.el.style.top = Math.max(0, Number(position.y) || 0) + "px";
                    });
                }
                if (Array.isArray(state.connections)) {
                    Object.keys(this._wires).forEach(function (key) { self._removeWire(self._wires[key], true); });
                    state.connections.forEach(function (wire) {
                        try {
                            self._connect(wire.from, wire.to, true);
                        } catch (e) {
                            console.warn('Connector "' + self.name + '": a saved wire was skipped. ' + e.message);
                        }
                    });
                }
                if (state.data && typeof state.data === "object") {
                    Object.keys(state.data).forEach(function (name) {
                        var node = self._nodes[name];
                        if (node && isPlainObject(state.data[name])) node.data = plainCopy(state.data[name]) || {};
                    });
                }
            }

            /* ------------------------------------------------------------ palette */

            // The palette is an element of the page marked with connector-palette="name", or given in the settings
            _setupPalette(setting) {
                var self = this;
                var el = null;
                if (setting instanceof HTMLElement) el = setting;
                else if (typeof setting === "string") el = document.querySelector(setting);
                else el = document.querySelector('[connector-palette="' + this.name + '"]');
                if (!el) return;

                this._palette = el;
                el.classList.add("dotapp-connector-palette");
                el.setAttribute("data-theme", this.element.getAttribute("data-theme"));
                if (this.element.classList.contains("dc-unstyled")) el.classList.add("dc-unstyled");
                el.classList.toggle("dc-readonly", this._readonly);

                el.addEventListener("pointerdown", function (event) {
                    self._seal();
                    if (self._readonly || (event.pointerType === "mouse" && event.button !== 0)) return;
                    var target = event.target;
                    if (!target.closest || target.closest(CONTROLS)) return;
                    var nodeEl = target.closest(".dc-node");
                    var node = nodeEl ? self._nodes[nodeEl.getAttribute("data-node")] : null;
                    if (!node || !node.parked || node.el !== nodeEl) return;
                    event.preventDefault();
                    self._dragFromPalette(node, event);
                });
            }

            _park(node, silent) {
                var self = this;
                if (!this._palette) {
                    throw new Error('Connector "' + this.name + '": there is no palette. Add an element with connector-palette="' + this.name + '" to the page.');
                }
                if (node.parked) return;
                Object.keys(this._wires).forEach(function (key) {
                    var wire = self._wires[key];
                    if (wire.fromNode === node.name || wire.toNode === node.name) self._removeWire(wire, silent);
                });
                clearTimeout(node._timer);
                node._seq++;
                node._busy(false);
                node.status("");
                node.outputPorts.forEach(function (port) { node._out[port.name] = "value" in port ? port.value : undefined; });
                node.parked = true;
                node.el.classList.add("is-parked");
                this._palette.appendChild(node.el);
                this._pending = this._pending.filter(function (n) { return n !== node; });
                this._depths = null;
                this._fitStage();
                this._requestDraw();
                if (!silent) {
                    this._emit("park", { node: node.name });
                    this._emit("change", { action: "park", node: node.name });
                }
            }

            _unpark(node, x, y, silent) {
                if (!node.parked || node.template) return;
                if (this._grid > 0) {
                    x = Math.round(x / this._grid) * this._grid;
                    y = Math.round(y / this._grid) * this._grid;
                }
                node.parked = false;
                node.el.classList.remove("is-parked");
                node.el.style.left = Math.max(0, Math.round(Number(x) || 0)) + "px";
                node.el.style.top = Math.max(0, Math.round(Number(y) || 0)) + "px";
                this._stage.appendChild(node.el);
                this._depths = null;
                this._fitStage();
                this._requestDraw();
                if (!silent) {
                    this._schedule(node, false);
                    this._emit("unpark", { node: node.name });
                    this._emit("change", { action: "unpark", node: node.name });
                }
            }

            // The node follows the pointer. Dropped over the canvas it stays there, anywhere else it returns to the palette.
            _dragFromPalette(node, event) {
                var self = this;
                var el = node.el;
                var rect = el.getBoundingClientRect();
                var offsetX = event.clientX - rect.left;
                var offsetY = event.clientY - rect.top;
                var width = el.style.width;
                var placeholder = make("div");
                placeholder.style.height = rect.height + "px";
                el.parentNode.insertBefore(placeholder, el);

                el.classList.add("is-dragging");
                el.style.width = rect.width + "px";
                var follow = function (e) {
                    el.style.setProperty("left", (e.clientX - offsetX) + "px", "important");
                    el.style.setProperty("top", (e.clientY - offsetY) + "px", "important");
                };
                var finish = function (e, cancelled) {
                    window.removeEventListener("pointermove", follow);
                    window.removeEventListener("pointerup", onUp);
                    window.removeEventListener("pointercancel", onCancel);
                    el.classList.remove("is-dragging");
                    el.style.width = width;
                    el.style.removeProperty("left");
                    el.style.removeProperty("top");
                    placeholder.remove();
                    if (cancelled) return;
                    var canvas = self.element.getBoundingClientRect();
                    if (e.clientX >= canvas.left && e.clientX <= canvas.right && e.clientY >= canvas.top && e.clientY <= canvas.bottom) {
                        var stageRect = self._stage.getBoundingClientRect();
                        var x = e.clientX - offsetX - stageRect.left;
                        var y = e.clientY - offsetY - stageRect.top;
                        if (node.template) {
                            // A stamp stays where it is, a copy of it lands on the canvas
                            var made = self._copy(node.name, null, x, y);
                            self._schedule(made, false);
                            self._emit("change", { action: "addNode", node: made.name });
                        } else {
                            self._unpark(node, x, y, false);
                        }
                    }
                };
                var onUp = function (e) { finish(e, false); };
                var onCancel = function (e) { finish(e, true); };
                window.addEventListener("pointermove", follow);
                window.addEventListener("pointerup", onUp);
                window.addEventListener("pointercancel", onCancel);
                follow(event);
            }

            /* ------------------------------------------------------------ drawing */

            _fitStage() {
                var self = this;
                var width = 0;
                var height = 0;
                this._order.forEach(function (name) {
                    var node = self._nodes[name];
                    if (node.parked) return;
                    width = Math.max(width, node.el.offsetLeft + node.el.offsetWidth + 40);
                    height = Math.max(height, node.el.offsetTop + node.el.offsetHeight + 40);
                });
                this._stage.style.width = width > this.element.clientWidth ? width + "px" : "";
                this._stage.style.height = height > this.element.clientHeight ? height + "px" : "";
            }

            _requestDraw() {
                var self = this;
                if (this._drawQueued) return;
                this._drawQueued = true;
                var later = window.requestAnimationFrame || function (fn) { return setTimeout(fn, 16); };
                later(function () {
                    self._drawQueued = false;
                    self._draw();
                });
            }

            // Where the wire of a port ends, in the coordinates of the stage, and the direction in which it leaves.
            // Small elements ( dots ) are joined in their middle, bigger ones on their edge. connector-anchor="left|right|top|bottom|center" decides otherwise.
            _point(nodeName, portName, isInput, stageRect) {
                var node = this._nodes[nodeName];
                var row = node ? (isInput ? node._inPorts : node._outPorts)[portName] : null;
                if (!row) return null;
                var rect = (row._dcAnchor || row).getBoundingClientRect();
                var side = row.getAttribute("connector-anchor") || (rect.width <= 28 && rect.height <= 28 ? "center" : (isInput ? "left" : "right"));
                var point = { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2, dx: isInput ? -1 : 1, dy: 0 };
                if (side === "left") { point.x = rect.left; point.dx = -1; }
                else if (side === "right") { point.x = rect.right; point.dx = 1; }
                else if (side === "top") { point.y = rect.top; point.dx = 0; point.dy = -1; }
                else if (side === "bottom") { point.y = rect.bottom; point.dx = 0; point.dy = 1; }
                point.x -= stageRect.left;
                point.y -= stageRect.top;
                return point;
            }

            _curve(a, b) {
                var bend = Math.max(40, Math.min(220, Math.sqrt((b.x - a.x) * (b.x - a.x) + (b.y - a.y) * (b.y - a.y)) * 0.45));
                return "M" + a.x + "," + a.y + " C" + (a.x + a.dx * bend) + "," + (a.y + a.dy * bend) + " " + (b.x + b.dx * bend) + "," + (b.y + b.dy * bend) + " " + b.x + "," + b.y;
            }

            _draw() {
                var self = this;
                var stageRect = this._stage.getBoundingClientRect();
                var wired = {};
                Object.keys(this._wires).forEach(function (key) {
                    var wire = self._wires[key];
                    var a = self._point(wire.fromNode, wire.fromPort, false, stageRect);
                    var b = self._point(wire.toNode, wire.toPort, true, stageRect);
                    if (!a || !b) return;
                    var d = self._curve(a, b);
                    wire.path.setAttribute("d", d);
                    wire.hit.setAttribute("d", d);
                    wire.middle = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
                    var source = self._nodes[wire.fromNode];
                    var classes = "dc-wire";
                    if (source && !isEmpty(source._out[wire.fromPort])) classes += " has-value";
                    if (self._selected === key) classes += " is-selected";
                    wire.path.setAttribute("class", classes);
                    wired["out:" + wire.from] = true;
                    wired["in:" + wire.to] = true;
                });
                this._order.forEach(function (name) {
                    var node = self._nodes[name];
                    Object.keys(node._inPorts).forEach(function (port) {
                        node._inPorts[port].classList.toggle("is-wired", wired["in:" + name + "." + port] === true);
                    });
                    Object.keys(node._outPorts).forEach(function (port) {
                        node._outPorts[port].classList.toggle("is-wired", wired["out:" + name + "." + port] === true);
                    });
                });
                if (this._deleteButton) {
                    var selected = this._wires[this._selected];
                    if (selected && selected.middle) {
                        this._deleteButton.style.left = selected.middle.x + "px";
                        this._deleteButton.style.top = selected.middle.y + "px";
                    }
                }
            }

            _select(key) {
                this._selected = key && this._wires[key] ? key : null;
                if (this._deleteButton) {
                    this._deleteButton.remove();
                    this._deleteButton = null;
                }
                if (this._selected && !this._readonly) {
                    this._deleteButton = make("button", "dc-wire-del", "×");
                    this._deleteButton.type = "button";
                    this._deleteButton.setAttribute("aria-label", "Remove wire");
                    this._stage.appendChild(this._deleteButton);
                }
                this._draw();
            }

            /* ------------------------------------------------------------ interaction */

            _bindEvents() {
                var self = this;
                var el = this.element;

                el.addEventListener("click", function (event) {
                    var target = event.target;
                    if (self._deleteButton && target === self._deleteButton) {
                        var wire = self._wires[self._selected];
                        if (wire) self._removeWire(wire, false);
                        return;
                    }
                    var parker = target.closest ? target.closest("[data-dc-park]") : null;
                    if (parker && !self._readonly) {
                        self._seal();
                        var node = self._nodeOf(parker);
                        if (node && node.of) self.removeNode(node.name);            // a copy of a template is removed
                        else if (node && self._palette) self._park(node, false);    // a node goes back to the palette
                        return;
                    }
                    var tool = target.closest ? target.closest("[data-dc-tool]") : null;
                    if (tool) {
                        if (tool.getAttribute("data-dc-tool") === "fullscreen") self.fullscreen(!self._fullscreen);
                        else if (self._fullscreen) self.fullscreen(false);
                        else self.maximize(!self._maximized);
                        return;
                    }
                    var hit = target.getAttribute ? target.getAttribute("data-wire") : null;
                    if (hit && !self._readonly) {
                        self._select(self._selected === hit ? null : hit);
                        return;
                    }
                    if (self._selected) self._select(null);
                });

                el.addEventListener("dblclick", function (event) {
                    var hit = event.target.getAttribute ? event.target.getAttribute("data-wire") : null;
                    if (hit && !self._readonly && self._wires[hit]) self._removeWire(self._wires[hit], false);
                });

                el.addEventListener("keydown", function (event) {
                    if ((event.key === "Delete" || event.key === "Backspace") && self._selected && !self._readonly) {
                        var tag = event.target.tagName;
                        if (tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT" || event.target.isContentEditable) return;
                        event.preventDefault();
                        self._removeWire(self._wires[self._selected], false);
                    } else if (event.key === "Escape" && self._selected) {
                        self._select(null);
                    }
                });

                el.addEventListener("focusin", function () { self._seal(); });

                el.addEventListener("pointerdown", function (event) {
                    self._seal();
                    if (self._readonly || (event.pointerType === "mouse" && event.button !== 0)) return;
                    var target = event.target;
                    if (!target.closest) return;
                    var port = target.closest("[data-dc-port]");
                    if (port && el.contains(port)) {
                        event.preventDefault();
                        self._startWire(port, event);
                        return;
                    }
                    var handle = target.closest("[data-dc-drag]");
                    if (handle && el.contains(handle)) {
                        // Controls inside of a handle keep working: typing, buttons, links
                        if (target.closest(CONTROLS)) return;
                        event.preventDefault();
                        self._startDrag(handle.closest(".dc-node"), event);
                    }
                });

                el.addEventListener("scroll", function () { self._requestDraw(); });

                // Escape returns a spread canvas to its place; leaving the full screen of the browser does the same
                document.addEventListener("keydown", function (event) {
                    if (event.key === "Escape" && self._maximized && !self._fullscreen && !self._selected) self.maximize(false);
                });
                document.addEventListener("fullscreenchange", function () {
                    if (!document.fullscreenElement && self._fullscreen) {
                        self._fullscreen = false;
                        self.maximize(false);
                    }
                });
            }

            _nodeOf(el) {
                var nodeEl = el.closest(".dc-node");
                return nodeEl ? this._nodes[nodeEl.getAttribute("data-node")] || null : null;
            }

            _startDrag(nodeEl, event) {
                var self = this;
                var node = this._nodes[nodeEl.getAttribute("data-node")];
                if (!node) return;
                var startX = event.clientX;
                var startY = event.clientY;
                var origin = node.position();
                var moved = false;
                nodeEl.classList.add("is-dragging");

                var onMove = function (e) {
                    var x = origin.x + (e.clientX - startX);
                    var y = origin.y + (e.clientY - startY);
                    if (self._grid > 0) {
                        x = Math.round(x / self._grid) * self._grid;
                        y = Math.round(y / self._grid) * self._grid;
                    }
                    moved = true;
                    nodeEl.style.left = Math.max(0, x) + "px";
                    nodeEl.style.top = Math.max(0, y) + "px";
                    self._requestDraw();
                };
                var onUp = function () {
                    window.removeEventListener("pointermove", onMove);
                    window.removeEventListener("pointerup", onUp);
                    window.removeEventListener("pointercancel", onUp);
                    nodeEl.classList.remove("is-dragging");
                    self._fitStage();
                    self._requestDraw();
                    if (moved) {
                        var position = node.position();
                        self._emit("move", { node: node.name, x: position.x, y: position.y });
                        self._emit("change", { action: "move", node: node.name, x: position.x, y: position.y });
                    }
                };
                window.addEventListener("pointermove", onMove);
                window.addEventListener("pointerup", onUp);
                window.addEventListener("pointercancel", onUp);
            }

            _startWire(portEl, event) {
                var self = this;
                var node = this._nodeOf(portEl);
                if (!node || node.parked) return;
                var portName = portEl.getAttribute("data-port");
                var isInput = portEl.getAttribute("data-dc-port") === "in";
                var detached = null;

                // Pulling a wired input takes its wire off and continues from the other end
                var anchor = { node: node.name, port: portName, isInput: isInput };
                var ending = isInput ? this._wiresTo(node.name + "." + portName) : [];
                if (ending.length) {
                    detached = ending[ending.length - 1];
                    anchor = { node: detached.fromNode, port: detached.fromPort, isInput: false };
                    this._removeWire(detached, true);
                }
                this._select(null);

                var anchorRow = (anchor.isInput ? this._nodes[anchor.node]._inPorts : this._nodes[anchor.node]._outPorts)[anchor.port];
                anchorRow.classList.add("is-source");
                this.element.classList.add("dc-connecting");

                // Mark the ports the wire may end on
                this._order.forEach(function (name) {
                    var other = self._nodes[name];
                    var list = anchor.isInput ? other.outputPorts : other.inputPorts;
                    list.forEach(function (port) {
                        var a = anchor.isInput ? { node: name, port: port.name } : { node: anchor.node, port: anchor.port };
                        var b = anchor.isInput ? { node: anchor.node, port: anchor.port } : { node: name, port: port.name };
                        if (self._problem(a, b) === "") (anchor.isInput ? other._outPorts : other._inPorts)[port.name].classList.add("can-drop");
                    });
                });

                var temp = document.createElementNS(SVG, "path");
                temp.setAttribute("class", "dc-wire is-temp");
                this._svg.appendChild(temp);

                var onMove = function (e) {
                    var stageRect = self._stage.getBoundingClientRect();
                    var fixed = self._point(anchor.node, anchor.port, anchor.isInput, stageRect);
                    var free = { x: e.clientX - stageRect.left, y: e.clientY - stageRect.top, dx: anchor.isInput ? 1 : -1, dy: 0 };
                    temp.setAttribute("d", anchor.isInput ? self._curve(free, fixed) : self._curve(fixed, free));
                };
                var finish = function (e, cancelled) {
                    window.removeEventListener("pointermove", onMove);
                    window.removeEventListener("pointerup", onUp);
                    window.removeEventListener("pointercancel", onCancel);
                    temp.remove();
                    anchorRow.classList.remove("is-source");
                    self.element.classList.remove("dc-connecting");
                    Array.prototype.forEach.call(self.element.querySelectorAll(".can-drop"), function (p) { p.classList.remove("can-drop"); });

                    if (!cancelled) {
                        var under = document.elementFromPoint(e.clientX, e.clientY);
                        var targetPort = under && under.closest ? under.closest("[data-dc-port]") : null;
                        var targetNode = targetPort && self.element.contains(targetPort) ? self._nodeOf(targetPort) : null;
                        if (targetNode && targetPort !== anchorRow) {
                            var target = targetNode.name + "." + targetPort.getAttribute("data-port");
                            var source = anchor.node + "." + anchor.port;
                            var targetIsInput = targetPort.getAttribute("data-dc-port") === "in";
                            if (targetIsInput !== anchor.isInput) {
                                if (detached && target === detached.to) {
                                    self._connect(source, target, true);      // dropped back where it was: nothing changed
                                    detached = null;
                                } else if (anchor.isInput) {
                                    self._connect(target, source, false);
                                } else {
                                    self._connect(source, target, false);
                                }
                            }
                        }
                    }
                    if (detached) {
                        if (cancelled) {
                            self._connect(detached.from, detached.to, true);
                        } else {
                            // The wire was pulled off its input: tell everybody, and run the node which lost it
                            if (self._nodes[detached.toNode]) self._schedule(self._nodes[detached.toNode], false);
                            self._emit("disconnect", { from: detached.from, to: detached.to });
                            self._emit("change", { action: "disconnect", from: detached.from, to: detached.to });
                        }
                    }
                    self._requestDraw();
                };
                var onUp = function (e) { finish(e, false); };
                var onCancel = function (e) { finish(e, true); };

                window.addEventListener("pointermove", onMove);
                window.addEventListener("pointerup", onUp);
                window.addEventListener("pointercancel", onCancel);
                onMove(event);
            }

            _emit(event, detail) {
                var handlers = (this._handlers[event] || []).slice();
                for (var i = 0; i < handlers.length; i++) {
                    try {
                        handlers[i].call(this, detail);
                    } catch (error) {
                        console.error('Connector "' + this.name + '": handler of "' + event + '" failed.', error);
                    }
                }
                this.element.dispatchEvent(new CustomEvent("connector:" + event, { detail: detail }));
            }
        }

        /* ---------------------------------------------------------------- mounting */

        function mount(element, settings) {
            if (element._dotappConnector) return element._dotappConnector;
            var config = {};
            var script = null;
            Array.prototype.forEach.call(element.children, function (child) {
                if (child.tagName === "SCRIPT" && child.classList.contains("dotapp-connector-config")) script = child;
            });
            if (script) {
                try {
                    config = JSON.parse(script.textContent) || {};
                } catch (error) {
                    throw new Error("Connector: the description of the connector is not valid JSON. " + error.message);
                }
                script.remove();
            }
            if (settings && typeof settings === "object") config = Object.assign(config, settings);
            var instance = new DotAppConnector($dotapp(), element, config);
            element._dotappConnector = instance;
            instances[instance.name] = instance;
            element.dispatchEvent(new CustomEvent("connector:ready", { detail: instance }));
            return instance;
        }

        function byName(name) {
            if (instances[name]) return instances[name];
            var element = NAME.test(name) ? document.querySelector('[data-connector="' + name + '"]') : null;
            return element ? mount(element) : null;
        }

        function mountAll() {
            Array.prototype.forEach.call(document.querySelectorAll("[data-connector]"), function (element) {
                if (element._dotappConnector) return;
                try {
                    mount(element);
                } catch (error) {
                    console.error(error);
                }
            });
        }

        /*
            $dotapp().connector("name")              connector rendered by PHP, found by its name
            $dotapp("#canvas").connector(settings)   connector on one element ( created when it does not exist yet )
        */
        var connector = function (settings) {
            if (typeof settings === "string") return byName(settings);
            var elements = this.getElements();
            if (elements.length !== 1) {
                throw new Error("Connector must be applied to exactly one element, or called with the name of a connector.");
            }
            return mount(elements[0], settings);
        };

        try {
            $dotapp().fn("connector", connector);
        } catch (error) {
            if (String(error && error.message).indexOf("already registered") === -1) throw error;
        }

        // Connectors rendered by PHP start on their own
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", mountAll, { once: true });
        } else {
            mountAll();
        }

        window.dispatchEvent(new Event("dotapp-connector-ready"));
    };

    if (window.$dotapp) {
        runMe(window.$dotapp);
    } else {
        window.addEventListener("dotapp-register", function () {
            runMe(window.$dotapp);
        }, { once: true });
    }
})();
