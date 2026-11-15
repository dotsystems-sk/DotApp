# 49 — Connector (visual node graphs)

`dotapp.connector.js` (`app/parts/js/`, served as `/assets/dotapp/dotapp.connector.js`) draws a canvas of **nodes** joined by **wires**. `Dotsystems\App\Parts\Connector` (`app/parts/Connector.php`) is its small PHP facade.

**Open this file whenever a task asks for a node editor, a flow / pipeline / rule builder, "connect blocks with wires", or a graphical configurator of logic.** Do not build one by hand and do not bring another library.

---

## 1. The model (read this first)

A node is **universal**. It has any number of inputs, any number of outputs and one **executor** — a function. The executor receives the values of the inputs and returns the value of the output. The value travels along the wires to the next nodes and their executors run. **That is all the library knows about a node.**

| Kind | Ports | The library decides it from |
|---|---|---|
| `source` | outputs only | the ports — never set by hand |
| `processor` | inputs and outputs | the ports |
| `sink` | inputs only | the ports |

What a node shows, reads from its fields, or asks from the server is **code of its executor** — the library has no built-in "text node", "image node" or "display node".

**The frontend is the main part.** In most modules the graph is created by the module's own JavaScript and the executors call the module's own API. The PHP class only saves typing for simple graphs (§6) and runs a saved graph on the server (§7).

---

## 2. Laws

1. **MUST** use `dotapp.connector.js` for every node graph. **MUST NOT** write own SVG wires / drag code, and **MUST NOT** add a third-party node editor.
2. **MUST NOT** edit `app/parts/js/dotapp.connector.js` or `app/parts/Connector.php` (core). A missing capability is added in **your executor** or your module — tell the user when the core truly lacks something.
3. **MUST** put everything a node does into its executor (`fn`) and its setup into `init`. **MUST NOT** look for attributes that "bind" a field to a port — there are none. Read a field with `node.find(...)`, react with `node.on(...)`, start the node again with `node.run()`.
4. **MUST** return the output from the executor: one output → the value itself; more outputs → an object keyed by their names. Returning a scalar from a node with more outputs is an error shown on the node.
5. **MUST** return a `Promise` from an executor that waits (API call, upload). **MUST NOT** call `node.set()` from inside a `.then()` instead of returning — a returned Promise gives the busy state, the error state and protection against old answers.
6. **MUST** call the server from an executor with `node.load(url, data)` / `node.upload(file, url)` (they wrap `$dotapp().load` / `$dotapp().uploadFile`). **MUST NOT** use `fetch` / `$.ajax`. The PHP action follows [08](08-FORMS-AND-SECURITY.md) (`crcCheck()` once) and [48](48-UPLOADER.md) (uploads), and answers with `ajaxReply`, HTTP 200, `status` 0|1 + `message`.
7. **MUST** report a problem with `return node.halt("reason")` — nothing leaves the node and the reason is shown on it. **MUST NOT** `alert()` and **MUST NOT** return a fake value.
8. **MUST** mark an input `required` when the executor cannot work without it. The executor is then not called while the input is empty. An input that is **not** required arrives as `undefined` when nothing is connected or nothing arrived — a sink that shows a result **MUST** handle `undefined` (show the empty state).
9. **MUST** keep what the user typed into a node in `node.data` with `node.store(key, value)`, and show it again in `init` / `onLoad`. That is what `save()` stores. **MUST NOT** keep node settings only in the DOM.
10. **MUST** style a graph only through the three levels in §5. **MUST NOT** override `.dc-*` rules with `!important`, and **MUST NOT** restyle the library from another module's CSS.
11. Names of connectors, nodes and ports: a letter first, then letters, numbers, `_`, `-`. A port is written `"node.port"`.
12. **MUST** treat a state that comes from the browser as untrusted: read it with `Connector::unpack()` before storing it. **MUST NOT** `json_decode` it by hand into the database.
13. Server-run graphs (§7): **MUST** describe the graph in **one** PHP method used by both the page and the run. **MUST NOT** execute anything from the saved state except through the controllers that this method declares.

---

## 3. JavaScript — building a graph

```html
<script src="/assets/dotapp/dotapp.js"></script>
<script src="/assets/dotapp/dotapp.connector.js"></script>
<script src="/assets/modules/Shop/js/shop_graph.js"></script>
```

```javascript
// inside the mandatory boot block, on the "dotapp" event ( 26 §3 )
var graph = $dotapp("#shopCanvas").connector({ name: "shop", grid: 10, theme: "light" });

graph.node("price").title("Price").at(40, 40)
    .output("value", { type: "number" })
    .content('<input type="number">')
    .init(function (node) {
        node.find("input").val(node.data.value === undefined ? 100 : node.data.value);
        node.on("input", "input", function (field) {
            node.store("value", field.value);
            node.run();
        });
    })
    .fn(function (inputs, node) {
        var number = parseFloat(node.find("input").val());
        return isNaN(number) ? node.halt("Type a number") : number;
    });

graph.node("vat").title("VAT").at(320, 40)
    .input("amount", { type: "number", required: true })
    .output("total", { type: "number" })
    .fn(function (inputs, node) {
        return node.load("/shop/api/vat", { amount: inputs.amount }).then(function (reply) {
            return reply.status == 1 ? reply.total : node.halt(reply.message);
        });
    });

graph.node("show").title("To pay").at(600, 40)
    .input("total", { type: "number" })
    .content('<strong>–</strong>')
    .fn(function (inputs, node) {
        node.find("strong").text(inputs.total === undefined ? "–" : inputs.total);
    });

graph.connect("price.value", "vat.amount").connect("vat.total", "show.total");
```

`.content()` / `.layout()` **MUST** come before `.init()` in the chain — `init` runs at once on a ready graph and needs the HTML.

### Node

| Call | Meaning |
|---|---|
| `graph.node(name)` | the node; **created when it does not exist** |
| `.title(text)` | header of the built-in frame |
| `.input(name, { label, type, required, multiple })` | adds an input. `multiple` = more wires may end on it; the executor gets a **list** of the values that arrived |
| `.output(name, { label, type, value })` | adds an output. `value` = held from the start |
| `.content(html)` | HTML inside the built-in frame |
| `.layout(html \| element \| "#selector")` | own design of the whole node (§5) |
| `.fn(function (inputs, node) {})` | the executor |
| `.init(function (node) {})` | once: listeners, first look |
| `.onSave(fn)` / `.onLoad(fn)` | before the state is saved / after a state was loaded |
| `.manual()` | runs only by `node.run()` |
| `.debounce(ms)` | waits after the last change |
| `.at(x, y)` | position |

`graph.addNode({ name, title, x, y, inputs, outputs, content | layout, fn, init, save, load, manual, debounce, data, parked })` describes a node with one object.

### The executor — what it returns

| Returns | Effect |
|---|---|
| value | the output (more outputs: object keyed by names; a missing key leaves that output unchanged) |
| `Promise` | node is busy; the resolved value is used; an older answer never overwrites a newer one; a rejection is shown as an error |
| `node.halt("reason")` | outputs are emptied, the reason is shown |
| nothing | outputs stay as they are |
| throws | message shown on the node, outputs emptied, `error` event |

### Inside the executor

| | |
|---|---|
| `node.find(selector)` | `$dotapp` object with elements inside the node (`.val()`, `.text()`, `.get(0)` …) |
| `node.on(event, [selector,] fn)` | listener inside the node; `fn(element, event)` |
| `node.run()` | run the executor again |
| `node.set(output, value)` | set an output at any time (timers, sockets) |
| `node.get(name)` / `node.link(name)` | value of an input / `{ value, connected, from }` |
| `node.load(url, data)` | POST through `$dotapp().load`; Promise of the **parsed reply**; rejects on HTTP / network failure |
| `node.upload(file, url)` | `$dotapp().uploadFile`; Promise of the parsed reply; progress shown on the node |
| `node.backend(inputs)` | runs the PHP controller given by `->controller()` (§6) |
| `node.store(key, value)` / `node.data` | values saved with the graph |
| `node.status(text, "error")` | message on the node |
| `node.name`, `node.type`, `node.el` | name, `source`/`processor`/`sink`, element |
| `node.park()` / `node.unpark(x, y)` | to the palette / onto the canvas |

### Graph

| | |
|---|---|
| `connect("a.out", "b.in")` | chainable. An input holds one wire (a new one replaces it) unless it is `multiple`. Different `type`s and loops are refused with a console warning — ask `canConnect(from, to)` first when it matters |
| `disconnect("node.port")`, `connections()` | |
| `has(name)`, `nodes()`, `removeNode(name)`, `copy(template, [name])` | |
| `maximize(bool)`, `fullscreen(bool)` | canvas over the whole page / screen |
| `run()` / `run(name)`, `arrange()` | run again / lay nodes out in columns |
| `readonly(true)` | computes, but the user cannot move or rewire |
| `save()` / `save(true)` | state as an object / as one packed text for `load()` → PHP |
| `load(state)`, `reset()` | restore a state / back to the declared graph |
| `on(event, fn)` | `connect`, `disconnect`, `move`, `park`, `unpark`, `run`, `output`, `error`, `maximize`, `change` |

`change` fires after everything that belongs to the state (wires, positions, palette, `node.store`). Saving = one listener:

```javascript
graph.on("change", function () {
    clearTimeout(shopSaveTimer);
    shopSaveTimer = setTimeout(shopSaveGraph, 400);     // shopSaveGraph() posts { state: graph.save(true) } with the module request helper
});
```

The state is `{ nodes: { name: { x, y } | { parked: true } }, connections: [{ from, to }], data: { name: { … } } }`.

**Palette.** An element with `connector-palette="<connector name>"` anywhere on the page. Nodes with `parked` wait there; the user drags them onto the canvas; the cross of a node sends it back. The built-in frame shows the cross only when a palette exists.

**Templates (stamps).** A node with `template: true` (PHP `->template()`, tag attribute `template`) never leaves the palette. Every drag onto the canvas makes a **copy**: a node of its own named `<template>-1`, `<template>-2`, …, with its own wires and its own `node.data`. Use a template whenever the user may need the same kind of node more than once ("Subject contains" for three different words). `graph.copy("keyword")` makes a copy from code and returns it; `node.of` is the template of a copy; the cross of a copy **removes** it. Copies are part of the state (`nodes: { "keyword-2": { x, y, of: "keyword" } }`). The HTML of a template **MUST** be text (`content` / `layout` as a string), not an element. **MUST NOT** build your own "add node" button that clones DOM.

**Big graphs.** Two buttons in the corner of the canvas spread it over the whole page / the whole screen; `Escape` returns. From code: `graph.maximize(true|false)`, `graph.fullscreen(true|false)` (the browser allows full screen only inside a click or key handler), event `maximize`. The palette becomes a side panel; its width is `--dc-palette-width` (default 240px). Hide the buttons with `{ tools: false }` / `->tools(false)` / `<connector tools="off">` when the module has its own. **MUST NOT** write a second overlay around the canvas.

---

## 4. Traps (verified)

| Trap | Reality |
|---|---|
| `graph.node("x")` to "check" a node | it **creates** the node. Use `graph.has("x")` |
| `.init()` before `.content()` | `init` runs immediately and finds no elements |
| `node.find("input").val()` on an empty field | returns `null` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §2) — test for `null`, not `""` |
| A `debounce` node "runs late" | intended: server nodes given by PHP wait 250 ms by default; set `.debounce(0)` to turn it off |
| Sink does not clear when a wire is pulled off | its input is `required`, so the executor is not called. Leave sink inputs **not** required and handle `undefined` |
| Two outputs, scalar returned | error on the node — return `{ a: …, b: … }` |
| Same name for an input and an output | allowed (`crop.image` in, `crop.image` out); `node.get(name)` returns the **input** |
| `localStorage.clear()` in page code | wipes the keys `dotapp.js` needs for CRC — every later `load()` fails. Remove only your own keys |
| `connect()` returned "false" | it returns the **graph** (chainable). Use `canConnect()` |
| Graph inserted later through `reply.html` | inline `<script>` of a rendered connector does not run from `innerHTML`: build such a graph in JavaScript, or mount it with `$dotapp(el).connector()` and attach executors with `graph.node(name).fn(...)` |

---

## 5. Look — three levels

**1. Tune the built-in look** with CSS variables on your own class (`cssClass` / `class`):

```css
.shop_canvas { --dc-accent: #0f9d74; --dc-bg: #fbfcfd; --dc-dots: #e1e6ed; --dc-wire: #b4bfcc; --dc-wire-width: 2.5; }
```

Variables: `--dc-bg`, `--dc-dots`, `--dc-node`, `--dc-head`, `--dc-border`, `--dc-text`, `--dc-muted`, `--dc-accent`, `--dc-wire`, `--dc-wire-width`, `--dc-error`, `--dc-source`, `--dc-sink`, `--dc-shadow`. `theme`: `auto` | `light` | `dark`. The built-in rules have **no specificity** (`:where`), so one class of the module wins — never `!important`.

**2. Own node** — `layout` instead of `content`. The node **is** your HTML; you only mark what is what:

```html
<div class="shop_card">
  <header connector-drag>Crop</header>
  <span class="shop_port" connector-input="image" connector-type="image" connector-required>image</span>
  <span class="shop_port" connector-output="image" connector-type="image">image</span>
  <p connector-status></p>
</div>
```

| Attribute | Meaning |
|---|---|
| `connector-input="name"` / `connector-output="name"` | the element is a port; ports found in the layout are added to the node automatically |
| `connector-type`, `connector-required`, `connector-multiple`, `connector-value` | options of that port |
| `connector-anchor="left\|right\|top\|bottom\|center"` | where the wire touches (default: small element → centre, bigger → its outer edge) |
| `connector-drag` | handle; without it the whole node is the handle (form controls keep working) |
| `connector-park` | pressing it sends the node to the palette |
| `connector-status` | messages of the node are written here |

**3. `unstyled`** (`{ unstyled: true }`, `->unstyled()`, `<connector unstyled>`) — the built-in look is off for the canvas, the nodes and the palette. Only positions and wires stay.

State classes for your CSS: `.dc-node` + `.dc-source` / `.dc-processor` / `.dc-sink`, `.is-busy`, `.is-error`, `.is-dragging`, `.is-parked`; on a port `.is-wired`, `.can-drop`, `.is-source`; on the canvas `.dc-connecting`, `.dc-readonly`; wires `.dc-wire` + `.has-value`, `.is-selected`. Class names of the module stay `{lowercase_modulename}_*`.

---

## 6. PHP facade — a simple graph without a JavaScript file

`use Dotsystems\App\Parts\Connector;` — every setter is chainable; `->node()` and `->connect()` may be called on a node to continue the chain.

```php
Connector::use('calc')->height(380)->theme('light')

    ->node('six')->title('Number')->at(40, 40)
        ->output('value', ['value' => 6])

    ->node('sum')->title('Add')->at(320, 100)
        ->input('a', ['required' => true])
        ->input('b', ['required' => true])
        ->output('result')
        ->js('return inputs.a + inputs.b;')

    ->connect('six.value', 'sum.a');
```

```html
<connector use="calc">
```

The same with tags (attributes are **literal** — no `{{ }}` inside the opening tags; `{{ }}` inside `<content>` / `<layout>` is fine):

```html
<connector name="calc" height="380" theme="light">
    <node name="sum" title="Add" x="320" y="100">
        <input name="a" required>
        <input name="b" required>
        <output name="result">
        <function>return inputs.a + inputs.b;</function>
    </node>
    <connect from="six.value" to="sum.a">
</connector>
```

| PHP | Tag | |
|---|---|---|
| `Connector::use($name)` / `new Connector($name)` | `<connector name>` | one per name in a request |
| `->height()`, `->grid()`, `->theme()`, `->readonly()`, `->unstyled()`, `->cssClass()`, `->endpoint()`, `->designOnly()` | `height grid theme readonly unstyled class endpoint design-only` | |
| `->node($name)` | `<node name title x y width manual parked debounce controller class>` | |
| `->input($name, $options)` / `->output($name, $options)` | `<input name label type required multiple>` / `<output name label type value>` | `addInput` / `addOutput` are aliases |
| `->content($html)` / `->layout($html)` | `<content>` / `<layout>` | layout from a file: `Renderer::new()->module('Shop')->setLayout('nodes/crop')->renderLayout()` |
| `->js($body)` | `<function>` | body of the executor: `inputs`, `node` |
| `->init($body)`, `->onSave($body)`, `->onLoad($body)` | `<init>`, `<save>`, `<load>` | body: `node` |
| `->data($array)`, `->parked()`, `->manual()`, `->debounce($ms)` | | |
| `->template()` / `$graph->copy($template, $name)` | `<node … template>` | a stamp in the palette / a copy of it placed in the declared graph |
| `->tools(false)` | `tools="off"` | hides the corner buttons |
| `->connect($from, $to)` | `<connect from to>` | checked at render: ports exist, types match, no loop |
| `->restore($state)` | | state from `Connector::unpack()` |
| `->render()` / `<connector use="name">` | | adds the library script once per page |

A connector rendered this way is an ordinary graph in the browser: `$dotapp().connector("calc")` returns it and a module script may add executors with `graph.node("sum").fn(...)`. The page still loads `/assets/dotapp/dotapp.js` itself.

**Controller as executor** (shortcut for "send the inputs, get the output"):

```php
$graph->endpoint('/shop/pipeline');
$graph->node('crop')->input('image', ['required' => true])->output('image')->controller('Shop:Images@crop!');
```

```php
/** CRCchecking — Connector::listen('/shop/pipeline') (crcCheck inside Connector::handle). */
public static function crop($inputs, $node) {
    // $node: ['connector', 'node', 'data' => node.data, 'headless' => false]
    if (!Auth::can('shop.images')) return Connector::halt('You may not do this.');
    return $croppedImage;        // one output: the value. More outputs: ['a' => …, 'b' => …]
}
```

```php
Connector::listen('/shop/pipeline')->before('#Shop:Rights@check!');      // module.init.php; the URL must be in initializeRoutes()
```

The controller name is encrypted and signed for the session at render; the browser cannot call a controller the graph did not declare. **MUST** still check rights in the controller or on the route. **MUST NOT** `crcCheck()` again in such a controller. A file among the inputs arrives as an upload — receive it with `Uploader` ([48](48-UPLOADER.md)).

---

## 7. A graph that runs on the server (configurator)

The user wires a graph once; the **server** runs it later with no browser — rules for incoming e-mail, call routing, automation.

```php
// ONE description: the page draws it, the server runs it
public static function flow() {
    return Connector::use('mailflow')->designOnly()
        ->node('incoming')->title('Incoming e-mail')->output('email')
        ->node('keyword')->title('Subject contains')
            ->input('email', ['required' => true])->output('yes')->output('no')
            ->data(['text' => 'casino'])
            ->content('<input type="text">')
            ->init('node.find("input").val(node.data.text || ""); node.on("input", "input", function (f) { node.store("text", f.value); });')
            ->controller('Shop:Mail@subjectContains!')
        ->node('spam')->title('Move to spam')
            ->input('email', ['required' => true, 'multiple' => true])
            ->controller('Shop:Mail@toSpam!')
        ->connect('incoming.email', 'keyword.email')
        ->connect('keyword.yes', 'spam.email');
}
```

| Step | Code |
|---|---|
| Page | `self::flow()->restore($saved)` then `<connector use="mailflow">` |
| Save (JS) | `graph.on("change", …)` → POST `{ state: graph.save(true) }` |
| Save (PHP) | `crcCheck()`, rights, `$state = Connector::unpack($posted['state'])`; refuse `null`; dry-run `flow()->restore($state)->execute([...])` in `try/catch` (a looped state throws); store `$state` |
| Run | `$run = self::flow()->restore($saved)->execute(['incoming' => $email]);` |

- `designOnly()` — in the browser the user only moves, joins and sets up nodes; **no executor runs there** and no controller ticket is sent.
- `execute($values)` puts `$values` on outputs (`'node'` for a node with one output, `'node.output'` otherwise) and runs every other node once, sources first, by the rules of §3: `required`, `multiple` (a list), `Connector::halt()`, one output → value, more → keyed array. A node with two outputs **branches** by returning only one key.
- The controller receives `($inputs, $node)`; `$node['data']` is what the user set on the node (`node.store`), `$node['headless']` is `true`.
- Result: `$run->ran('spam')`, `$run->ran()`, `$run->output('keyword.yes')`, `$run->outputs()`, `$run->halted()` (node → reason), `$run->failed()` (node → exception message), `$run->ok()`.
- Saved wires are checked again on every run; a wire that no longer fits the declared nodes is skipped. Parked nodes do not take part.
- **Templates** make a configurator useful: declare `->node('keyword')->…->template()`, place the first copy with `->copy('keyword', 'keyword-1')` and wire **copies**, never the template. Copies the user stamped in the browser arrive in the state and `execute()` creates them from the template — same ports, same controller, their own `$node['data']`.
- A closure may be given to `->controller()` for server runs. Only callable **strings** can also be called from the browser.

**MUST** validate every value from `$node['data']` in the controller (it was typed by a user). **MUST NOT** run a state that was not read by `Connector::unpack()`.

---

## 8. Checklist

- [ ] Graph built with `dotapp.connector.js`; no hand-made wires, no other library
- [ ] Every behaviour is in `fn` / `init`; waiting executors return a Promise; failures use `node.halt()`
- [ ] API calls go through `node.load` / `node.upload`; PHP answers HTTP 200 + `status` + `message`
- [ ] `required` on inputs the executor needs; sinks handle `undefined`
- [ ] Settings of nodes live in `node.data` (`node.store`), restored in `init` / `onLoad`
- [ ] Look: variables, `layout`, or `unstyled` — no `!important` against `.dc-*`
- [ ] State from the browser read with `Connector::unpack()`; rights checked on save and in controllers
- [ ] Server-run graph: one `flow()` method, `designOnly()`, dry-run before storing
- [ ] A kind of node the user may need more than once is a `template`; wires go to its copies
- [ ] Works on a phone: ports and handles are reachable by touch (the library handles pointer events; your own layout **MUST** keep ports at least ~24 px tall)
