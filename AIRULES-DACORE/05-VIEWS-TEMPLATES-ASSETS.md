# 05 — Views, Templates, Assets (complete Renderer API)

**This is NOT Blade and NOT Twig.** Any doc showing `{{ $var }}` / `{{ endif }}` is wrong.

---

## 1. File conventions

| Type | Path | Selected with |
|------|------|---------------|
| View | `app/modules/{Module}/views/{name}.view.php` | `setView('name')` |
| Layout | `app/modules/{Module}/views/layouts/{path}.layout.php` | `setLayout('path')` |
| Cross-module | `Module:path` | `setView('Shop:home')`, `{{ layout: Shop:partials/x }}` |
| Base layout | `app/parts/views/layouts/` | `{{ baselayout:name }}` only |

---

## 1b. How templates compose (MUST) — framework Renderer, not the DACore admin shell

This section is **DotApp’s Renderer** when **your module owns the HTML document** (public site, emails, widgets, a standalone page).

**Extender (judge):** if this page/block (cart HTML, export HTML, a fragment another module would swap) is a high-value replacement surface, the **PHP method that produces that HTML** may opt in — [00](00-AGENT-CONTRACT.md) §2h / [12](12-SERVICES.md) §10. **MUST NOT** Extender every `renderView()` / `renderLayout()`. **MUST NOT** patch DACore to insert `Extender::call`.

**DACore admin pages are a different procedure.** You do **not** `setView` a full `<html>` shell and you do **not** fill `{{ content }}` yourself. Render **only your fragment** (`renderLayout()`), then pass `$title`, `$body`, `$header`, `$css`, `$js` to `DACore:Page@withMenu!`. The Page controller generates the shell. Canonical: [33-DACORE-PAGES-AND-UI.md](33-DACORE-PAGES-AND-UI.md), sample [EX-D02](examples/EX-D02-dacore-admin-page.md).

---

**The VIEW is the outer file. The LAYOUT is the inner piece.**  
`renderView()` loads the `.view.php`. If you also called `setLayout()`, it **replaces the literal `{{ content }}` in that view with the generated layout HTML**. The layout does **not** wrap the view.

Three valid ways **for pages you own** (all first-class):

### A — Automatic slot (`{{ content }}`)

Your view is the page (doctype, head, body). The layout is the middle.

```php
$html = Renderer::new()->module('Shop')
    ->setView('home')                 // views/home.view.php — MUST contain {{ content }}
    ->setLayout('content/welcome')    // this HTML is inserted at {{ content }}
    ->setViewVar('title', 'Shop')     // renderView() sees view vars only
    ->renderView();
```

**MUST:** the view contains the token `{{ content }}`. Without it, the layout is discarded. Without `setLayout()`, `{{ content }}` stays in the HTML as text.

### B — Layout only (fragment, AJAX row, email block)

No view. You get a HTML chunk. **This is also the fragment you pass as `$body` to `Page@withMenu!` on admin pages** ([33](33-DACORE-PAGES-AND-UI.md)).

```php
$chunk = Renderer::new()->module('Shop')
    ->setLayout('partials/item-row', 'partials/empty')
    ->setLayoutVar('item', $row)
    ->renderLayout();                 // VIEW is ignored; layout vars only
```

### C — Generate to a string, inject yourself

Render a layout (or any HTML) into a variable, then place it: `setViewVar` + `{{ var: $slot }}`, or `str_replace` on **your** marker in the already-rendered string. Do **not** invent DACore’s `<!--additionalcss-->` markers on your public pages — those exist only inside DACore’s Page controller.

```php
$nav = Renderer::new()->module('Shop')
    ->setLayout('partials/nav')
    ->setLayoutVar('active', 'home')
    ->renderLayout();

$page = Renderer::new()->module('Shop')
    ->setView('home')
    ->setViewVar('navbar', $nav)
    ->renderView();
// home.view.php: {{ var: $navbar }}

$page = str_replace('<!--NAV-->', $nav, $page);
```

`{{ layout:partials/header }}` is a **plain include** (no closer). It is not wrapping and it is not `{{ content }}`.

**MUST:** the string you inject in C is itself `renderLayout()` / `renderView()` output. **MUST NOT** build that string by concatenating tags in PHP ([§1c](#1c-html-via-renderer-must--law)).

---

## 1c. HTML via Renderer (MUST — law)

When the markup **can** be a template, it **MUST** be a template. PHP prepares data (escaped scalars, `0`/`1` flags, lists). `Renderer` produces HTML. This is a **law** ([00](00-AGENT-CONTRACT.md) §2j), not a preference.

**MUST live in** `views/*.view.php` / `views/layouts/*.layout.php`:

- pages and admin fragments (`$body` for `Page@withMenu!`)
- tables, icon grids, trees, crumbs
- empty states
- pager **chrome** (summary + nav wrap)
- AJAX row / list patches (`html` / `live`)

**MUST produce them with** `Renderer::new()->module('Shop')->setLayout('…', '…fallback')->setLayoutVar(…)->renderLayout()` (or `setView` + `renderView()`). The same layout is the first paint and the AJAX patch (DACore `fragment('_users_rows')` pattern).

**MUST NOT** concatenate a screen or fragment in Controllers / Libraries:

```php
// WRONG — HTML factory. Fail the finish gate.
$html = '<table class="table">';
foreach ($items as $item) {
    $html .= '<tr><td>' . Html::e($item['name']) . '</td></tr>';
}
return $html;
```

```php
// RIGHT — data in PHP, markup in the layout
$chunk = Renderer::new()->module('Shop')
    ->setLayout('admin/_items_rows', 'admin/empty')
    ->setLayoutVar('items', $items)
    ->setLayoutVar('hasItems', $items !== [] ? 1 : 0)
    ->renderLayout();
```

**Exception — only when a template cannot do that one piece, and only that piece:**

1. `// Why:` names the real problem (Renderer sandbox would drop a callable key — [§5](#5-render-pipeline-order-matters); `DACore:Page@paginate!` item callback returning `<li>`; one tiny escaped chip such as a status badge printed via `{{ var: $statusHtml }}`).
2. That exception is **not** a table, grid, tree, empty state, crumbs bar, or pager wrapper.
3. Convenience, “it is shorter”, an existing `listHtml()` helper, or “the JSON already has html” is **not** an exception.

A missing layout returns `""` silently — always pass the fallback argument and check ([§2](#2-missing-files-fail-silently--this-is-critical)).

---

## 2. Missing files fail SILENTLY — this is critical

| Situation | Result |
|-----------|--------|
| View file missing | warning in log, render returns **`""`** — no exception |
| Layout file missing | warning in log, **`""`** |
| `loadViewStatic($view)` missing | **no existence check** — PHP include error |
| `getViewVar('missing')` | **`""`** |
| `getViewVars()` with no vars | `[]` |

So a blank page means a missing/misnamed template. **Always use the fallback argument** and verify output:

```php
$html = Renderer::new()
    ->module('Shop')
    ->setView('home', 'fallback/empty')      // 2nd arg = fallback view
    ->setLayout('catalog/list', 'catalog/empty')
    ->setViewVar('title', $title)
    ->renderView();

if ($html === '') {
    Logger::use()->error('Shop view render produced empty output');
    return new Response(500, 'Template error');
}
```

---

## 3. Complete Renderer API

| Method | Returns | Notes |
|--------|---------|-------|
| `Renderer::new($name = false)` | `Renderer` | named instances are singletons |
| `module($name)` | `$this` | sets view/layout dirs |
| `setView($view, $fallback = null)` | `$this` | supports `Module:path` |
| `setLayout($layout, $fallback = null)` | `$this` | supports `Module:path` |
| `setViewVar($k, $v)` / `setLayoutVar($k, $v)` | `$this` | separate bags |
| `getViewVar($k)` / `getLayoutVar($k)` | value or **`""`** | |
| `getViewVars()` / `getLayoutVars()` | `array` | |
| `renderView()` | HTML `string` | eval sees **view vars only** |
| `renderLayout()` | HTML `string` | eval sees **layout vars only** |
| `renderViewCode($custom = true)` | `string` | compile without final eval path |
| `renderLayoutCode(callable $renderer = null)` | `string` | |
| `renderCode($code, $vars = [], $render = true)` | `string` | ad-hoc string template |
| `loadView($view)` | `string` | `""` if missing |
| `loadViewStatic($view)` | `string` | **no existence check** |
| `addRenderer($name, $callable)` | void | throws if not callable |
| `getRenderer($name)` | callable or `false` | |
| `renderWith($name, $code)` | `string` | throws if renderer missing |
| `addBlock($name, $fn)` | void | silently ignored if invalid |
| `customRenderers()` | `array` | |
| `useCache($bool)` | `$this` | **broken — do not enable** |
| `useCssCache($bool)` | `$this` | |
| `prepareCss($file, $path, $tagAt, $tagAfter)` | **void, echoes** a `<link>` | |
| `removeUnusedCss($bool)` | `$this` | risky, see below |
| `minimizeCSS($css)` / `minimizeJS($js)` | `string`/`array` | |
| `escapePHP($code)` | `string` | |
| `concatInnerLayouts(...)` | `string` | depth limit 20 |
| `phprender_isolated($vars, $code)` | `string` | sandboxed eval |

There is **no** `setViewVars()` plural, and no public `getView()`/`getLayout()`.

### Variable bag rule (most common bug)

`renderView()` evaluates with **view vars**. `setLayoutVar()` values **do not reach** the final output in that path. When you use `renderView()` with a layout, pass everything through `setViewVar()`.

A second silent miss: the sandbox can **drop a whole var** (heading stays, `foreach` is empty). See §5 **Sandbox**.

---

## 4. Complete directive reference

### Output

```html
{{ var: $title }}
{{ var: $user['name'] }}
{{ var:$title }}
```

Compiles to `echo` — **no auto-escaping**. No expressions, `??`, `->`, or function calls inside `var:`.

**MUST (XSS):** anything a user could have written — name, title, comment, filename, search term, a column read back from the DB — is escaped in **PHP** before you pass it in: `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`. `protect()` on `$request->data()` is a guard, **not** the escape. Raw HTML only for a field the product needs as HTML, sanitised, and only from a user with the mutate right ([24](24-ATTACK-VECTORS.md) §1).

### Translation

```html
{{_ "Login" }}
{{_ var: $message }}
```

Double quotes only. Missing key returns the original text.

### Conditionals

```html
{{ if isset($user) }}
{{ elseif $guest === true }}
{{ else }}
{{ /if }}
```

Space required after `{{` before `if`. Closing tag is `{{ /if }}`.

### Loops

```html
{{ foreach $items as $item }} ... {{ /foreach }}
{{ while $i < 5 }} ... {{ /while }}
```

### Includes and slots

```html
{{ layout:partials/header }}
{{ layout: Shop:partials/header }}
{{ baselayout: something }}
{{ content }}
```

`{{ content }}` works **only** on the `setView` + `setLayout` + `renderView()` path (§1b A). Layout tags are pure includes — there is no closing tag. On `renderLayout()`, use `{{ layout:... }}` to nest — `{{ content }}` is not filled.

### Security / crypto

```html
{{ CSRF }}
{{ formName(saveItem) }}
{{ enc: $secret }}
{{ enc(mykey): $secret }}
{{ enc: "literal" }}
{{ enc(mykey): "literal" }}
```

`{{ formName(x) }}` **MUST** sit **between** `<fo-rm …>` and `</fo-rm>` (or `<form>`), and that tag **MUST** have a `method` attribute. After `</fo-rm>` / before `<fo-rm>` / missing `method` → the tag is left **unchanged** in the output (silent failure — check your HTML).

`{{ enc(key): $x }}` encrypts at runtime; `{{ enc: "literal" }}` encrypts at **compile time**. Decrypt with the same context key via `Crypto::decrypt($v, 'key')` — remember it returns **`false`** on failure.

### Blocks

```html
{{ block:alert(danger) }}Warning{{ /block:alert }}
{{ privateblock:row }}<li>{{ var: $name }}</li>{{ /privateblock }}
```

Block handler signature: `function (string $inner, array $blockArgs, array $viewVars): string`.

PrivateBlock usage in PHP inside the template:

```php
<?php foreach ($items as $it): ?>
    <?php echo $block['row']->set('name', $it['name'])->html(); ?>
<?php endforeach; ?>
```

### Bridge

```html
{{ dotbridge:on(click)="ping(email)" regenerateId oneTimeUse }}
```

See [09-DOTAPP-JS-AND-BRIDGE.md](09-DOTAPP-JS-AND-BRIDGE.md).

### Input group directives (from `Input.php`, not Renderer)

```html
{{ InputKeys('register_form') }}
{{ input:text name="username" rules="required|alpha_num" group="register_form" }}
```

### Not supported

`{{ $x }}`, `@if`, `@foreach`, `@extends`, `@section`, `@yield`, `{{-- comment --}}`, `{!! raw !!}`, `{{ include ... }}` (JS template engine only).

---

## 5. Render pipeline (order matters)

```
renderView / renderLayout / renderCode
  → concatInnerLayouts   ({{ layout: }}, {{ baselayout: }}, depth ≤ 20)
  → privateblock + custom renderers (dotapp.block, reactive, input_form_*)
  → {{ content }} substitution (renderView only)
  → updateLayoutContentData (var/if/foreach/while/enc/translation)
  → {{ CSRF }} + {{ formName() }}
  → Bridge::dotBridge ({{ dotbridge: }})
  → RenderingIsolator (sandbox + eval)
```

### Sandbox (**MUST** — silent empty lists)

`RenderingIsolator` (and `PrivateBlock::set`) is a security sandbox. **MUST NOT** patch `app/parts/Renderer.php` to “fix” it. Work around in the module.

Two separate behaviours:

#### A. Template PHP calls are stripped

Dangerous calls (`eval(`, `exec(`, `system(`, `file_*`, `curl_*`, `mail(`, `header(`, `extract(`, `call_user_func*`, `copy(`, `unlink(`, … — see `RenderingIsolator::phpsandbox_disabled()`) are **silently removed** from compiled template PHP. The call does nothing. Put logic in controllers.

Eval failure prints `ERROR WHILE EVAL: ...` into the output.

#### B. Whole vars are dropped before eval (**MUST** design around this)

Before extract, every **var name** and every **nested value** (arrays and object properties, recursive) must pass `is_callable($x) === false`. One hit → that **entire** `setViewVar` / `setLayoutVar` / `PrivateBlock::set` is skipped. No warning. Sibling vars still extract.

PHP `is_callable('time')` is **true** because `time()` exists. The same trap hits many short names used as feature keys, column aliases, or var names, including:

`time`, `date`, `key`, `count`, `sort`, `reset`, `end`, `current`, `next`, `min`, `max`, `round`, `log`, `copy`, `file`, `dir`, `link`, `header`, `mail`, `system`, `exec`, `glob`, `touch`, `chmod`, `unlink`, `strlen`, `trim`, `explode`, `implode`, `printf`, `print_r`, `extract`, closures, and arrays that look like callables (`['ClassName', 'method']`).

**Symptom:** card title / `{{ if ($hasRows == 1) }}` shows (the integer extracted) but `{{ foreach $rows as $row }}` prints nothing — `$rows` never existed in the sandbox.

**MUST NOT** put PHP function names in the bag:

```php
// WRONG — 'time' is callable → entire $featureRows dropped
->setLayoutVar('featureRows', [['key' => 'time', 'on' => 1]])
->setLayoutVar('time', 1)          // var NAME is also checked
->setLayoutVar('copy', $html)
$row->set('sort', 'name')          // PrivateBlock::set — same drop
```

**MUST** instead:

1. Prefix keys the template never sees as a bare PHP name: `elapsed`, `feat_time`, `show_date`. **This is the default.**
2. **Exception only** ([§1c](#1c-html-via-renderer-must--law)): if prefixing still cannot ship the bag, pass **one short** escaped chip (`nameHtml`), not a table/list factory. `{{ var: $nameHtml }}` is raw echo. **MUST** `// Why:` the sandbox drop. A long `$html .= '<table'` string is still a **bug**.
3. Keep flags as `0`/`1` integers in their own vars (`hasFeatures`), not inside an array that also holds `'time'`.

```php
// RIGHT — no callable strings in the bag
->setLayoutVar('featuresHtml', $escapedHtml)
->setLayoutVar('hasFeatures', $escapedHtml !== '' ? 1 : 0)
```

**MUST NOT** “fix” an empty foreach by editing the Renderer. If a heading without rows appears after a `foreach` of controller data, grep the bag for `time` / `copy` / `count` / `key` / `header` first.

### Debugging templates

```php
define('__RENDER_TO_FILE__', true);
```

Compiled PHP is written to `app/runtime/generator/rendering_*.php`, included, then deleted — giving real line numbers for syntax errors.

---

## 6. Custom renderers and blocks

```php
Renderer::new()->addRenderer('shop.money', function (string $code, array $vars = []): string {
    return str_replace('{{ money }}', number_format($vars['price'] ?? 0, 2), $code);
});

$html = Renderer::new()->module('Shop')->setView('page')->renderView();
```

Built-in renderers registered automatically: `dotapp.block`, `reactive` (by `Reactive`), `input_form_*` (by `Input::registerRenderer()`).

---

## 7. Assets

Store under `app/modules/{Module}/assets/...`; serve via:

```
/assets/modules/{Module}/{path}
```

```html
<link rel="stylesheet" href="/assets/modules/Shop/css/page.css" />
<script src="/assets/dotapp/dotapp.js"></script>
<script src="/assets/modules/Shop/js/page.js"></script>
```

**Framework JS must come from `/assets/dotapp/dotapp.js`** (router-generated with per-session keys). Never link the raw file under `app/parts/js/` on a page.

**Public website chrome (MUST):** overlay mobile drawer (left or right), lock page scroll while open, scrollable nav list, contacts + compact search in the drawer unless large search is its own mobile section. Canonical: [09](09-DOTAPP-JS-AND-BRIDGE.md) §3. DACore **admin** uses `Page@withMenu!` — do not rebuild that shell.

### CSS pipeline (optional)

`prepareCss($file, $path, $tagAt, $tagAfter)` concatenates `@import`s, minifies, writes to `{cssDir}/cache/{name}_cache_{md5(layout)}.css` and **echoes** the `<link>` tag. With `useCssCache(true)` it reuses an existing cache file. A missing source produces a cache file containing `/* SOURCE CSS FILE ... NOT FOUND */`.

`removeUnusedCss(true)` strips rules whose selectors do not appear as `class="..."` in the rendered HTML. **Risk:** classes added by JS or built dynamically get deleted, and cache CSS files are overwritten. Leave it off unless you verified the output.

There is no built-in cache-busting helper — append `?v=` manually if needed.

---

## 8. Product copy (**MUST**)

Every string a person can **see or hear** in the product **MUST** read as if a software company shipped it — not as if an agent answered a developer prompt.

Applies to: button labels, text under buttons, help, placeholders, empty states, toasts, confirm dialogs, page titles, menu names, permission **name** / **description**, translation JSON **values**, and any other UI copy. Code comments **MUST** be English and explain **why** at trap-prone spots — not every line, not prompt-echo ([03](03-MODULES-AND-ROUTING.md)). Strings meant only for a model (`howtouse` / tool routing) may be technical — if the same field is **shown in the UI**, it still follows this rule.

**Test:** would a product team put this sentence in a released admin panel? If it sounds like a reply to “please let the user hide the AI icon”, **rewrite**.

**MUST:**
- Neutral, short, feature-named. Imperative or noun phrase: `Hide assistant`, `AI assistant`, `Access the in-app AI assistant`.
- Describe **the control**, not the ticket. A hide toggle: `Hide the assistant icon.` A right: `Access the in-app AI assistant.`
- Match the rest of the product (same language as surrounding screens). Translate professionally — do not add chatty extras in `sk_sk.json` that were not in the source.

**MUST NOT:**
- Echo the prompt or narrate the spec (`This was added so the user can hide the icon`).
- Third-person about “this user” on a settings/rights row (`This user can use the AI assistant in the corner. They can hide the icon themselves.`).
- Agent voice (`I added…`, `As requested…`, `You asked to…`).
- Implementation gossip in the UI (`corner widget`, `localStorage`, `DSM`, `JSON in the module`).
- Dump every UX detail into a **permission description**. Rights name the capability. Preferences (hide icon, position) belong on the settings screen as their own labelled controls.

| Wrong (prompt-echo) | Right (shipped product) |
|---------------------|-------------------------|
| This user can use the AI assistant in the corner. They can hide the icon themselves. | **Right:** Access the in-app AI assistant. **Toggle:** Hide assistant icon. |
| You can hide the AI icon yourself if you do not want it. | Hide assistant icon |
| Setting so operators may disable the floating helper. | Show assistant |
| Saved. The list should now update without reload as required. | Saved |

---

## 8b. Module identity brief (**ASK when planning visible UI**)

A module with pages is a product surface, not only routes and tables. When planning a new visible module, **ASK once** for its identity if the user did not already provide it:

- public display name and one-sentence purpose;
- existing logo / mark / banner asset, or no image;
- where it should appear (module landing/header and DACore installer preview);
- preferred accent/brand colours and light/dark variants, if branding is wanted;
- accessible alt text (empty only for a purely decorative image).

Offer a sensible **no-custom-branding** option. A backend-only/library/migration module does not need this question. **MUST NOT** block functional work merely because the user has no logo.

**DACore distinction:** the sidebar `icon` is a Remix Icon class and is **not** the module logo ([31](31-DACORE-MENU.md)). Installer branding belongs in your module’s `about.php` HTML with local raster files under `about-assets/` ([35](35-DACORE-INSTALL.md) §3b). Nothing is added under `app/modules/DACore/`.

**MUST NOT:** invent a company name or logo, hotlink a remote image, reuse a third-party mark without permission, put a huge original image on every page, or patch DACore styling to fit branding. Optimise raster assets, include intrinsic dimensions, and load non-critical imagery lazily. If image generation may cost extra, **ASK before using it** ([00](00-AGENT-CONTRACT.md) §2b).

---

## 8c. Layout, padding, and UX/UI (**MUST** — law)

General UX/UI principles **MUST** be followed **at all costs**. A screen that posts correctly but looks cheap (flush buttons, cramped footers, leftover left-stack) is **not done**. Canonical law: [00](00-AGENT-CONTRACT.md) §2f.

**Every new or moved button / action cluster:**

| Check | Fail if |
|-------|---------|
| Padding vs the **parent** (card, footer, modal, drawer) | Control glued to the edge — especially **no space below** a Save |
| Alignment vs siblings | One card’s Save left-stacked, the next centered; leftover `text-start` / missing flex |
| Rhythm | `pt-0` (or `border-0`) with **no** compensating `pb-*` / CSS `padding-bottom` |
| Touch / wrap | Hover-only placement; overflow on a phone; hit target too small |

**MUST:**

- Look at the **parent padding box**, not only the button. Footer, card, and page edge all count.
- Prefer the shell’s existing spacing (`card-footer`, `pb-3`/`pb-4`, centered flex) — **grep DACore first** on admin pages ([33](33-DACORE-PAGES-AND-UI.md)).
- After adding a button, **re-read the rendered structure** (HTML class list + CSS). Do not claim done from the PHP handler alone.

**MUST NOT:** ship a primary Save flush against the bottom of a card; zero the footer padding on one side and forget the other; invent a one-off margin that fights the parent’s flex/`h-100` layout.

## 8d. Plan the UI before code (**MUST** — first surfaces)

A **new module**, a **first** major operator workspace, or a **rewrite** **MUST** specify desktop and mobile layout, hierarchy, empty/error states, toolbar, and spacing in the **plan** — **and** inventory every page, tab, and control (what it does, default, persist). Not “we will polish in the diff.” Canonical: [45](45-MODULE-PLANNING.md), [00](00-AGENT-CONTRACT.md) §2k, [33](33-DACORE-PAGES-AND-UI.md).

---

## 9. Translations

```php
Translator::loadLocaleFile('Shop:sk_sk.json', 'sk_sk');
Translator::setLocale('sk_sk');
echo Translator::trans('Hello, {{ arg0 }}', $name);
```

| Method | Returns |
|--------|---------|
| `trans($text, ...$args)` / `t()` | `string` (original text if key missing) |
| `setLocale($locale)` | `self` |
| `getLocale()` | `string` (default `en_us`) |
| `setDefaultLocale` / `getDefaultLocale` | `self` / `string` |
| `loadFile($file)` / `loadLocaleFile($file,$locale)` | `self` — **missing file is silently skipped** |
| `loadArray` / `loadLocaleArray` | `self` |
| `has($key, $locale = null)` | `bool` |
| `all($locale = null)` | `array` |

Files: `app/modules/{Module}/translations/{locale}.json`. Keys are the source text, lowercased on lookup. Placeholders: `{{ arg0 }}`, `{{ arg1 }}`, …

**Limitations:** no pluralization, and **no locale fallback chain** — a miss returns the source string even when a default locale is set. Use `has()` if you need to detect missing keys.

---

## 10. Blade / Twig trap table

| Wrong | Right |
|-------|-------|
| `{{ $title }}` | `{{ var: $title }}` |
| `@if(...) @endif` | `{{ if ... }} {{ /if }}` |
| `{{ endif }}` / `{{ endforeach }}` | `{{ /if }}` / `{{ /foreach }}` |
| `@include('x')` | `{{ layout:x }}` |
| `@extends` / `@section` / `@yield` / layout-as-outer-shell | VIEW = outer file; LAYOUT fills `{{ content }}` — or `renderLayout()` / inject a string (§1b) |
| `$html .= '<table'` / `listHtml()` in a Library | `Renderer` + layout; PHP markup **only** for a named one-piece exception (§1c) |
| `{{ $x ?? 'd' }}` | prepare in the controller |
| `@csrf` | `{{ CSRF }}` / `{{ formName(...) }}` |
| `{{ __('k') }}` | `{{_ "Source text" }}` |
| `{{ include 'x' }}` | JS templates only |
| assuming escaping | escape in PHP before passing |
| assuming an exception on a missing view | you get `""` |
