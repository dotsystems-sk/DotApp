# DotApp 2

**A fast, modular PHP framework with a built-in PHP ↔ JavaScript bridge, a secure-by-default request pipeline, and a rulebook that lets AI agents write production code for it.**

Current release: **2.1.0** · License: **MIT** · Documentation: [dotapp.dev](https://dotapp.dev/) · Made in Slovakia 🇸🇰

---

## Contents

- [Why DotApp](#why-dotapp)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Core concepts](#core-concepts)
- [Frontend: `dotapp.js`](#frontend-dotappjs)
- [Uploader — safe file uploads](#uploader--safe-file-uploads)
- [Connector — visual node graphs](#connector--visual-node-graphs)
- [AI READY](#ai-ready)
- [DACore — the admin module](#dacore--the-admin-module)
- [Configuration](#configuration)
- [DotApper CLI](#dotapper-cli)
- [What's new](#whats-new)
- [Documentation, contact, license](#documentation)

---

## Why DotApp

DotApp stays small and still carries very large applications. Its structure is easy to read for people and for AI assistants, and the parts that usually get bolted on later — secure forms, encrypted identifiers, a frontend library, uploads — ship in the core.

| | |
| --- | --- |
| **Fast routing** | Static and dynamic routes, typed parameters, `{not:}` exclusions, middleware chains |
| **Modular architecture** | Modules wake only on their own URLs; listeners can load without the module |
| **Callable strings** | One notation for controllers, middleware and models: `Shop:Home@index!` |
| **Secure by default** | `<fo-rm>` with CRC and CSRF, encrypted IDs in the browser, session through `DSM` |
| **PHP ↔ JS bridge** | Call PHP functions from DOM events with rate limits and one-time keys |
| **`dotapp.js`** | A light frontend library: selectors, secure `load()`, uploads, reactive add-ons |
| **Uploader** | One safe entry point for visitor files — checked by content, images re-encoded |
| **Connector** | Visual node graphs: universal nodes with any inputs and outputs, your own design, built in JS or described in PHP |
| **AI READY** | A shipped rulebook (`AIRULES/`) that agents follow to produce shippable modules |
| **DACore** | The official admin module — login, 2FA, rights, menus — available free |
| **PSR-4** | Works with Composer packages out of the box |

**Support DotApp's development.** The first 100 sponsors get permanent recognition on [dotapp.dev](https://dotapp.dev). [Become a sponsor](https://github.com/sponsors/dotsystems-sk)

---

## Installation

**Composer**

```bash
composer create-project dotsystems/dotapp ./
```

**DotApper CLI** (recommended)

```bash
wget https://install.dotapp.dev/dotapper.php
php dotapper.php --install
```

`dotapper.php` can also be downloaded by hand from [install.dotapp.dev/dotapper.php](https://install.dotapp.dev/dotapper.php).

**Git**

```bash
git clone https://github.com/dotsystems-sk/dotapp.git ./
```

After installation, `composer require` works as usual for additional libraries.

---

## Quick start

```php
// index.php
define('__ROOTDIR__', "/path/to/your/dotapp");
require_once __ROOTDIR__ . '/app/config.php';

Router::get('/', fn($request) => 'Hello World');

DotApp::DotApp()->run();
```

A route callback receives a **locked Request object**. Return a string and it becomes the response body, or work with `$request->response` for full control.

---

## Core concepts

### Callable strings

The same string form is used on routes, in `->before()` and in `DotApp::call()`.

| Form | Target |
| --- | --- |
| `Shop:Home@index` | Controller method, parameters injected by the DI container |
| `Shop:Home@index!` | Controller method, **DI off** — the preferred form on hot routes |
| `#Shop:AuthGate@check!` | Middleware |
| `*Shop:Item@find!` | Model |

### Routing

```php
// Static route (exact URL, no pattern matching) + controller, no DI
Router::get('/', 'Shop:Home@index!', Router::STATIC_ROUTE);

// Dynamic parameter with an integer constraint
Router::get('/item/{id:i}', 'Shop:Home@item!');

// POST behind a middleware gate
Router::post('/save', 'Shop:Home@save!')
    ->before('#Shop:AuthGate@check!');

// Catch-all that stays out of /admin, /api/v1 and /assets
Router::get('/{path*}{not:/admin*|/api/v1*|/assets*}', 'Shop:Public@page!');

// Closures work too
Router::get(['/', '/home'], fn($request) => 'Welcome!');

// Call a model or another controller from PHP
$item = DotApp::call('*Shop:Item@find!', $id);
$html = DotApp::call('Shop:Home@helper!', $request);
```

### Controllers

Controllers are `public static` methods. The DotApp instance is globally accessible and the framework is used through facades.

```php
public static function index($request)
{
    $renderer = \Dotsystems\App\Parts\Renderer::new();
    return $renderer->module('Shop')
        ->setView('home')
        ->setViewVar('title', 'Hello')
        ->renderView();
}
```

Route: `'Shop:Home@index!'`. Leave the `!` out only when the method should receive injected parameters.

### Security defaults

- **Forms:** `<fo-rm>` + `{{ formName(handler) }}` + CRC. `method` and `action` on the opening tag are plain text.
- **Identifiers:** never a raw `data-id="7"`. `{{ enc(Shop.item.id): $id }}` on the way out, decrypt and a rights check on the way in.
- **Session:** `DSM::use('Shop')->set/get/delete` instead of `$_SESSION`.
- **Database:** `DB::module('RAW')->q(function ($qb) { ... })->all()|first()|execute()`. Lists that can grow use `paginate()`.

---

## Frontend: `dotapp.js`

```html
<script src="/assets/dotapp/dotapp.js"></script>
```

The file is generated by the framework for each session — it carries the key material that powers CRC, CSRF, secure forms and the bridge.

```javascript
// Secure request: CSRF + CRC are added for you
$dotapp().load("/shop/items/toggle", "POST", { item: token }, function (raw) {
    var reply = $dotapp().parseReply(raw);
    $dotapp("#items").html(reply.html);
}, function (status, body) {
    // HTTP error or network fault
});
```

```html
<!-- Bridge: call a PHP function from a DOM event -->
<input type="text" {{ dotbridge:input="email" }}>
<button {{ dotbridge:on(click)="subscribe(email)" rateLimit(60,10) }}>Subscribe</button>
```

Optional add-ons live next to it: `dotapp.template.js` (templates in the browser), `dotapp.reactive.js` (reactive endpoints) and `dotapp.connector.js` (node graphs, below). Rules for agents: `AIRULES/49-CONNECTOR.md`. Your own libraries register with `$dotapp().fn(name, callback)`.

---

## Uploader — safe file uploads

`Dotsystems\App\Parts\Uploader` is the single entry point for files that come from a visitor. A module never touches `$_FILES`.

```php
use Dotsystems\App\Parts\Uploader;

$result = Uploader::use('Shop')
    ->images()                      // jpg, jpeg, png, gif, webp
    ->maxSize(5 * 1024 * 1024)
    ->maxFiles(6)
    ->receive();

if ($result['status'] === 1) {
    foreach ($result['files'] as $file) {
        // $file['name'] (random stored name), $file['original'], $file['mime'], $file['size'], $file['width'], $file['height']
    }
} else {
    // $result['errors'][0]['message']
}
```

What it does for every file:

- **Allow-list only.** Nothing is accepted until `images()` or `allow($extension, $mimes)` says so. Script and executable extensions are refused in every part of the name (`photo.php.jpg`, `photo.jpg.php`) and cannot be allowed.
- **Content, not claims.** The MIME type is read from the bytes. The type sent by the browser is never used.
- **Images are re-encoded.** Only the pixels survive, so code hidden in comments, EXIF or appended after the image is gone.
- **Random stored names** in a folder outside the public web, protected by `.htaccess`. The original name is returned as display text only.

The browser side is `$dotapp().uploadFile(file, url, progressCallback)`. Rules for agents: `AIRULES/48-UPLOADER.md`.

---

## Connector — visual node graphs

`dotapp.connector.js` draws a canvas of **nodes** joined by **wires**.

A node is universal. It has any number of inputs and outputs and one **executor** — a function. The executor receives the values of the inputs and returns the value of the output. That value travels along the wires to the inputs of other nodes, and their executors run. This is all the library knows about a node; what a node shows, reads or calls is written in its executor.

| Kind | Ports | Examples |
| --- | --- | --- |
| **source** | outputs only | a number, a text field, an upload |
| **processor** | inputs and outputs | add, crop, translate, ask an API |
| **sink** | inputs only | show the result, save it |

The kind is never chosen — it follows from the ports the node was given.

Typical uses: calculators, image and data pipelines, rule and flow builders — anything where the user should see and rewire how a result is produced. The graph can run in the browser, or be only *designed* there and [run by the server](#a-graph-the-server-runs).

### In JavaScript

```html
<script src="/assets/dotapp/dotapp.js"></script>
<script src="/assets/dotapp/dotapp.connector.js"></script>
```

```javascript
var graph = $dotapp("#canvas").connector();

graph.node("six").title("Number").at(40, 40)
    .output("value")
    .fn(function () { return 6; });

graph.node("typed").title("Your number").at(40, 180)
    .output("value")
    .content('<input type="number">')
    .init(function (node) {
        node.on("input", "input", function () { node.run(); });
    })
    .fn(function (inputs, node) {
        var text = node.find("input").val();
        return text === null ? node.halt("Type a number") : Number(text);
    });

graph.node("sum").title("Add").at(320, 100)
    .input("a", { required: true })
    .input("b", { required: true })
    .output("result")
    .fn(function (inputs) { return inputs.a + inputs.b; });

graph.node("show").title("Result").at(600, 100)
    .input("value")
    .content("<b>?</b>")
    .fn(function (inputs, node) {
        node.find("b").text(inputs.value === undefined ? "?" : inputs.value);
    });

graph.connect("six.value", "sum.a")
     .connect("typed.value", "sum.b")
     .connect("sum.result", "show.value");
```

Type `4` and the result node shows `10`. Pull a wire off and it shows `?` again.

**The executor** — `function (inputs, node)`:

| It returns | What happens |
| --- | --- |
| a value | it becomes the value of the output (with more outputs: an object keyed by their names) |
| a `Promise` | the node shows that it works; the resolved value is used; an older answer never overwrites a newer one |
| `node.halt("reason")` | nothing leaves the node, the reason is shown on it |
| nothing | the outputs stay as they are |

An input marked `required` that is empty stops the node before the executor is called.

**Inside the executor**, `node` gives it everything it needs:

| | |
| --- | --- |
| `node.find(selector)` | `$dotapp` object with the elements inside the node |
| `node.on(event, selector, fn)` | listener inside the node |
| `node.run()` | run the executor again (the user typed, pressed a button) |
| `node.set(output, value)` | set an output at any time (timers, events) |
| `node.load(url, data)` | call your API through `$dotapp().load` — returns a Promise of the reply |
| `node.upload(file, url)` | send a file through `$dotapp().uploadFile`, progress shown on the node |
| `node.store(key, value)` / `node.data` | values kept with the node and saved with the graph |
| `node.status(text, kind)` | message on the node |

A node that asks the server is just an executor that returns a Promise:

```javascript
graph.node("vat").title("VAT").input("amount", { required: true }).output("total")
    .fn(function (inputs, node) {
        return node.load("/shop/api/vat", { amount: inputs.amount }).then(function (reply) {
            return reply.status == 1 ? reply.total : node.halt(reply.message);
        });
    });
```

**The graph**: `graph.node(name)`, `addNode(config)`, `removeNode(name)`, `connect(from, to)`, `disconnect(port)`, `connections()`, `run()`, `arrange()`, `readonly(true)`, `save()` / `load(state)` / `reset()`, and events `connect`, `disconnect`, `move`, `park`, `unpark`, `run`, `output`, `error`, `change`.

Ports with a `type` only accept wires from a port of the same type. A wire that would close a loop is refused.

### Your own design

The built-in look is tuned with CSS variables — one rule of your module is enough:

```css
.shop_canvas { --dc-accent: #0f9d74; --dc-bg: #fff; --dc-wire: #b9c2cf; --dc-wire-width: 3; }
```

Or bring the whole node yourself. With `layout` the node **is** your HTML; you only mark what is what:

```javascript
graph.node("crop").layout(
    '<div class="shop_card">' +
        '<header connector-drag>Crop</header>' +
        '<span class="shop_port" connector-input="image" connector-required>image</span>' +
        '<span class="shop_port" connector-output="image">image</span>' +
        '<p connector-status></p>' +
    '</div>'
);
```

| Attribute | Meaning |
| --- | --- |
| `connector-input="name"` / `connector-output="name"` | this element is a port; wires are drawn to it |
| `connector-type`, `connector-required`, `connector-value` | options of the port |
| `connector-anchor="left\|right\|top\|bottom\|center"` | where the wire touches the port |
| `connector-drag` | the node is moved by this element |
| `connector-park` | pressing it sends the node to the palette |
| `connector-status` | messages of the node appear here |

`unstyled` switches the built-in look off completely, so canvas, nodes, ports and wires are styled only by your CSS. State classes are there for it: `.is-busy`, `.is-error`, `.is-dragging`, `.is-parked` on a node, `.is-wired`, `.can-drop` on a port, `.has-value`, `.is-selected` on a wire.

**Palette.** Put an element with `connector-palette="name"` anywhere on the page. Nodes marked `parked` wait in it; the user drags them onto the canvas and sends them back with the cross.

**Templates.** A node marked `template` is a stamp: it stays in the palette and every drag makes a new copy with its own wires and its own settings — three "Subject contains" nodes for three different words.

**Big graphs.** Two buttons in the corner spread the canvas over the whole page or the whole screen (`graph.maximize()`, `graph.fullscreen()`); the palette stays at hand as a side panel.

### From PHP — the short way

For simple graphs nothing has to be written in a JavaScript file. `Dotsystems\App\Parts\Connector` is a small facade: it describes the graph, renders it into the page, and the frontend picks it up on its own — the same way it picks up `<fo-rm>`.

```php
use Dotsystems\App\Parts\Connector;

Connector::use('calc')->height(380)

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

The same graph can be written directly in a template:

```html
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
```

Tags of a node: `<input>`, `<output>`, `<content>` (HTML inside the built-in frame), `<layout>` (your own node), `<function>` (the executor), `<init>`, `<save>`, `<load>`. A graph rendered this way is a normal connector in the browser: `$dotapp().connector("calc")` returns it, and your script can add executors with `graph.node("sum").fn(...)`.

A node can also be given a controller as its executor — a shortcut for the common "send the inputs, get the output" case:

```php
$graph->endpoint('/shop/pipeline');
$graph->node('crop')->input('image', ['required' => true])->output('image')
    ->controller('Shop:Images@crop!');
```

```php
// Shop/Controllers/Images.php
public static function crop($inputs, $node)
{
    $path = Uploader::use('Shop')->path($inputs['image']['name'] ?? '');
    if ($path === false) {
        return Connector::halt('The image was not found.');   // shown on the node, nothing flows on
    }
    // ... crop the file ...
    return $croppedImage;                                      // the value of the output
}
```

```php
// Shop/module.init.php — one route runs every such node
Connector::listen('/shop/pipeline')->before('#Shop:Rights@check!');
```

The controller name never travels in readable form. It is encrypted and signed for the session when the graph is rendered, so the browser cannot ask for a controller the graph did not declare.

Anything beyond that — and that is most real graphs — belongs in JavaScript, where an executor is free to call whatever API it needs.

### A graph the server runs

A connector can also be a **configurator**: the user wires rules once, and the server follows them later with no browser open — rules for incoming e-mail, call routing, automation.

```php
// One description. The page draws it, the server runs it.
public static function flow()
{
    return Connector::use('mailflow')->designOnly()

        ->node('incoming')->title('Incoming e-mail')
            ->output('email')

        ->node('keyword')->title('Subject contains')
            ->input('email', ['required' => true])
            ->output('yes')
            ->output('no')
            ->controller('Shop:Mail@subjectContains!')

        ->node('spam')->title('Move to spam')
            ->input('email', ['required' => true, 'multiple' => true])
            ->controller('Shop:Mail@toSpam!')

        ->connect('incoming.email', 'keyword.email')
        ->connect('keyword.yes', 'spam.email');
}
```

```php
// The page: the user moves, joins and sets up the nodes. Nothing runs in the browser.
self::flow()->restore($saved);                       // then <connector use="mailflow"> in the view

// Saving: JavaScript posts graph.save(true) after every change
$saved = Connector::unpack($posted['state']);        // store it

// Later — a cron job, a webhook, a listener:
$run = self::flow()->restore($saved)->execute(['incoming' => $email]);

$run->ran('spam');                 // did the e-mail reach this node?
$run->output('keyword.yes');       // value of one output
$run->halted();                    // nodes that stopped, with their reasons
```

A controller receives the inputs of its node and what the user set on the node, and returns the output. With two outputs it **branches** by returning only one of them:

```php
public static function subjectContains($inputs, $node)
{
    $found = stripos($inputs['email']['subject'], $node['data']['text']) !== false;
    return $found ? ['yes' => $inputs['email']] : ['no' => $inputs['email']];
}
```

`multiple` lets more wires end on one input — the node then receives a list of everything that arrived.

Mark a node with `->template()` and the user can stamp as many copies of it as the rules need. The copies travel in the saved state, and `execute()` creates them on the server with the same controller and their own settings.

---

## AI READY

DotApp ships with a rulebook written for AI agents. It tells an agent exactly how to write a module — routing, forms, database, frontend, error handling — and where it must not go.

- **Stress-tested.** The rulebook was driven in **Cursor** with **Grok 4.5** and **Grok 4.6** until generated admin and public UI stayed secure and fast. Inexpensive models follow it and still produce real DotApp code.
- **Hard boundaries.** Agents may edit `app/config.php` and `app/modules/<YourModule>/` — not the kernel (`app/DotApp.php`, `app/parts/`, `dotapper.php`, `index.php`) and not DACore. That is why the output stays maintainable.

### Which rulebook to use

The repository ships **two** rulebooks. An application keeps **one** of them, and that folder must be named `AIRULES/`.

| Folder | When |
| --- | --- |
| `AIRULES/` | Bare DotApp, no DACore admin. Start at `AIRULES/00-AGENT-CONTRACT.md` |
| `AIRULES-DACORE/` | DACore is installed. Copy this folder **over** the project's `AIRULES/`. Do not keep both names side by side — the agent would follow two contracts |

Cursor loads the compact laws from `.cursor/rules/*.mdc`, the root `AGENTS.md`, and `.cursor/skills/`. They mirror the rulebook; they are not a second source. After choosing a pack:

```powershell
New-Item -ItemType Directory -Force -Path ".cursor\rules", ".cursor\skills" | Out-Null
Copy-Item -Force "AIRULES\cursor\rules\*.mdc" ".cursor\rules\"
Copy-Item -Force "AIRULES\cursor\AGENTS.md" "AGENTS.md"
Copy-Item -Recurse -Force "AIRULES\cursor\skills\*" ".cursor\skills\"
```

In this checkout, `.cursor/` already mirrors `AIRULES-DACORE/`. The framework-only compact rules stay in `AIRULES/cursor/` for a project without DACore.

The `.mdc` files are the short laws present on every turn. The numbered pages (`AIRULES/00-AGENT-CONTRACT.md`, `08-FORMS-AND-SECURITY.md`, and `30`–`46` for DACore) are what the agent opens when a task needs the full text.

### `/finalcontrol`

In Cursor, type `/finalcontrol ModuleName` before handing a module over. The skill is `AIRULES/cursor/skills/finalcontrol/SKILL.md`, mirrored to `.cursor/skills/finalcontrol/`.

It walks every button and form of the module: padding, the JS listener, the route, the rights check, and the opening `<fo-rm>` tag (`method` and `action` must be literal — a `{{ }}` inside the opening tag is treated as a bug and rewritten). It also deletes leftover agent scratch files, meaning any file or folder under the module whose name starts with `_`. The DACore packer refuses to build an install zip while any remain.

---

## DACore — the admin module

**DACore** is the official administration module, available **free**: login, 2FA, rights, a database-driven menu, a page shell, and an optional AI chat with tools. Your own modules plug in through `DotApp::call('DACore:…')`. Details are on [dotapp.dev](https://dotapp.dev/).

---

## Configuration

Main settings are in `app/config.php`:

```php
use \Dotsystems\App\DotApp;
use \Dotsystems\App\SessionDriverRedis;
use \Dotsystems\App\Config;

$dotApp = new \Dotsystems\App\DotApp();

// Encryption key
Config::set("app", "c_enc_key", md5('SECURE_KEY'));

// Databases
Config::addDatabase("main", "127.0.0.1", "dotsystems", "dotsystems", "dotsystems", "UTF8", "MYSQL", "mysqli");

// Session driver
Config::session("lifetime", 30 * 24 * 3600);
Config::session("redis_host", "127.0.0.1");
Config::session("redis_port", 6379);
Config::session("redis_prefix", "session:");
Config::sessionDriver("redis", SessionDriverRedis::driver());

$dotApp->load_modules();
```

---

## DotApper CLI

```bash
php dotapper.php --install                 # install DotApp
php dotapper.php --update                  # update the core
php dotapper.php --create-module=Blog      # create a module
php dotapper.php --list-routes             # list all routes
php dotapper.php --optimize-modules        # regenerate the optimized module loader
php dotapper.php --test                    # run the core tests
```

All options:

```
Usage: php dotapper.php [options]

Options:
  --install                         Install a fresh copy of the dotApp PHP framework
  --update                          Update dotApp core to the latest version
  --create-module=<name>            Create a new module
  --modules                         List all modules
  --install-module=<url|name[:ver]> Install a module (Git URL or name, optional version)
  --prepare-database[=<prefix>]     Prepare database structure (optional table prefix)
  --module=<module_name> --create-controller=<ControllerName>  Create a new controller
  --module=<module_name> --create-middleware=<MiddlewareName>  Create a new middleware
  --module=<module_name> --create-model=<ModelName>            Create a new model
  --list-routes                     List all defined routes
  --list-route=<route>              List callbacks for a specific route (e.g., /)
  --create-htaccess                 Create or recreate a new .htaccess file
  --optimize-modules                Optimize module loading
  --test                            Run all core tests
  --test-modules                    Run all module tests (no core tests)
  --module=<module_name> --test     Run tests for a specific module
```

---

## What's new

The exact version of a build is the latest key in `changelog.txt`.

### Reactivity in `dotapp.js` — fixed (NEW)

Variables, `dotbind`, `computed` and `autosave` now behave as documented: a computed value is recalculated when a variable it read changes (also through other computed values), fields update their variable while the user types, plain elements show values as **text** (HTML only with `dotbind-html`), `$dotapp('.x').databind()` and `variable()` always use the main instance, the first `variable(name, value)` gives a value to a variable created by a `dotbind` attribute, and `autosave` sends through `load()` so `crcCheck()` passes. New: `computed(fn, name)`, `update()` / `notify()` for arrays changed in place, `unbind()`, and `$dotapp('#box').dotbind()` for elements added later. `dotapp.reactive.js` asks at once instead of waiting a whole interval and stays quiet while the tab is hidden. `fn(name, function)` no longer overwrites a function that already exists (a built-in one or one registered earlier): it returns `false` and warns in the console instead of throwing. Rules: `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` §7.

### Connector — visual node graphs (NEW)

New frontend library `dotapp.connector.js` with a small PHP facade, `Dotsystems\App\Parts\Connector`. Universal nodes with any number of inputs and outputs and one executor each, wires drawn by the user, a palette, saved state, and full control over the design of every node. Build a graph in JavaScript, describe a simple one with the fluent PHP API or `<connector>` tags, or use it as a configurator whose saved graph the server runs on its own (`execute()`). See [Connector](#connector--visual-node-graphs).

### Uploader — safe file uploads (NEW)

New core class `Dotsystems\App\Parts\Uploader`: allow-list of extensions, MIME read from the bytes, images re-encoded, random stored names outside the public web. See [Uploader](#uploader--safe-file-uploads) and `AIRULES/48-UPLOADER.md`.

### 2.1.0 — major fix release

Fixes across authentication, the query builder, sessions, the validator, templates and the `dotapp.js` library. Among them: `.find()`, `.children()`, `.siblings()`, `.clone()`, `.last()`, `.nth()`, `.length` and `uploadFile` in `dotapp.js` now behave as documented, and `data(true)` returns the original on every call (2.0.5).

### URL `{not:}` — exclude prefixes before the match (2026-08-26)

The router has a **`{not:mask|mask}`** operator. Exclusions run **before** the positive pattern, so a public catch-all can stay one string and still stay out of `/admin`, `/api/v1` and `/assets`.

```php
Router::get('/{path*}{not:/admin*|/api/v1*|/assets*}', 'Shop:Public@page!');
```

- **Order matters:** `/admin/login` against `/{path*}{not:/admin*}` fails on the exclusion, not on `{path*}`.
- **`/admin*` vs `/admin/*`:** `/admin/*` does **not** match exact `/admin`. Use `{not:/admin*}` when the admin index must stay out too.
- The same syntax works in `Module::initializeRoutes()` / `Listeners::initializeRoutes()`. A public `/{path*}` that only skips `/admin` in `initializeCondition` still **wakes** the module — put `{not:}` on the wake string.

Docs: `AIRULES/03-MODULES-AND-ROUTING.md`.

### Extender — opt-in method replacement (2026-08-22)

New core class **`Dotsystems\App\Parts\Extender`**. A module can **replace** another module's method for the current request — one handler either owns the result or explicitly defers to the owner's original logic. This is **not** Events, `module.{mod}.{name}.hook`, or `triggerWithVeto()`.

- Offer Extender on highly replaceable **outputs** (page or block HTML, cart, export) — not on every method.
- The owner opts in: `Extender::exists()` then `Extender::call(...)`. Return an ordinary result, or continue only when `isOriginal()` recognizes the unique `original()` marker. There is no `next()` chain.
- The extending module registers `Extender::extend()` in **`Listeners::register()`** (`module.listeners.php`), before any matching module initializes. Target URLs belong in `Listeners::initializeRoutes()`.
- One replacement per class and method (a duplicate throws). Recursion into the same target throws. Handler exceptions propagate.
- Pass only explicit safe arguments (ids, flags, scalars) — never `$request`, tokens, CRC, or request bodies.

Rules: `AIRULES/12-SERVICES.md` §10. Sample: `AIRULES/examples/EX-17-extender.md`.

### Module loader v2, listener routes, and `Veto` (2026-08-22)

- **Faster module boot.** `php dotapper.php --optimize-modules` writes `app/modules/modulesAutoLoader.php` **v2** with two independent lists: `$modules` (full `module.init.php` / `initialize()`) and `$listeners` (only `module.listeners.php`). An older file that has only `$modules` still loads.
- **Listeners can have their own URLs.** `Listeners::initializeRoutes()` may return a different prefix list than `Module::initializeRoutes()`. Return `null` (or omit the method) to inherit the module map.
- **Listeners register first.** Matching `module.listeners.php` files run before matching modules initialize, so a subscriber can hear an event without booting its whole admin UI.
- **`triggerWithVeto()` + `Dotsystems\App\Parts\Veto`.** Ordinary `Events::trigger()` still ignores listener returns. The opt-in API stops only when a listener returns `new Veto($code, $message, $details)`. Name: `module.{mod}.{action}.veto`. Never send `message` or `details` to the browser.

Rules: `AIRULES/03-MODULES-AND-ROUTING.md`, `AIRULES/12-SERVICES.md` §2, `AIRULES/41-MODULE-HOOKS.md`. Sample: `AIRULES/examples/EX-16-module-hooks.md`.

### 2.0 (2026-08-18)

2.0 is the result of heavy production use plus a rewrite of how agents are allowed to touch the stack.

- **Fully AI READY** — `AIRULES/` (framework) and `AIRULES-DACORE/` (with DACore).
- **`!` skips DI** and **callable prefixes** `#` (middleware) and `*` (model).
- **Secure forms by default** — `<fo-rm>` + `{{ formName(handler) }}` + CRC. Row actions (toggle, delete, paginate) use `$dotapp().load()`, not a `<fo-rm>` per button.
- **Encrypted IDs in the browser**, **`DSM`** instead of `$_SESSION`, lists that paginate with an AJAX pager.
- **`$dotapp()` frontend** — live DOM patch, overlay and toast after save; no `location.reload()`.
- **DACore admin — free.**
- **Production hardening** — ORM, QueryBuilder, routing and the JS stack were cleaned up because they had to survive daily use.

---

## Version note

Older versions may contain duplicate function names (lowercase and PascalCase). They remain from the transition to **PascalCase** and keep **backward compatibility**; the impact on performance is minimal.

## Documentation

Full documentation: [https://dotapp.dev/](https://dotapp.dev/)

## Contact

- Email: [dotapp@dotapp.dev](mailto:dotapp@dotapp.dev)
- Web: [https://dotapp.dev](https://dotapp.dev)
- Company: [https://dotsystems.sk](https://dotsystems.sk)

## License

DotApp 2 is licensed under the **MIT License**. You must **retain the author's name** in all library headers.

Additional permission: the Software may be used for training AI models, provided the copyright notice is retained.
