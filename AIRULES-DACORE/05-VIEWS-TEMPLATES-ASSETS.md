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

---

## 4. Complete directive reference

### Output

```html
{{ var: $title }}
{{ var: $user['name'] }}
{{ var:$title }}
```

Compiles to `echo` — **no auto-escaping**. No expressions, `??`, `->`, or function calls inside `var:`.

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

`{{ content }}` is where `setLayout()` output is injected during `renderView()`. Layout tags are pure includes — there is no closing tag.

### Security / crypto

```html
{{ CSRF }}
{{ formName(saveItem) }}
{{ enc: $secret }}
{{ enc(mykey): $secret }}
{{ enc: "literal" }}
{{ enc(mykey): "literal" }}
```

`{{ formName(x) }}` requires an enclosing `<form>` or `<fo-rm>` **with a `method` attribute**; otherwise the tag is left **unchanged** in the output (silent failure — check your HTML).

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

### Sandbox

Dangerous functions (`eval`, `exec`, `system`, `file_*`, `curl_*`, `mail`, `header`, `extract`, `call_user_func*`, …) are **silently stripped** from template PHP. If a template call "does nothing", that is why. Put logic in controllers.

Eval failure prints `ERROR WHILE EVAL: ...` into the output.

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

### CSS pipeline (optional)

`prepareCss($file, $path, $tagAt, $tagAfter)` concatenates `@import`s, minifies, writes to `{cssDir}/cache/{name}_cache_{md5(layout)}.css` and **echoes** the `<link>` tag. With `useCssCache(true)` it reuses an existing cache file. A missing source produces a cache file containing `/* SOURCE CSS FILE ... NOT FOUND */`.

`removeUnusedCss(true)` strips rules whose selectors do not appear as `class="..."` in the rendered HTML. **Risk:** classes added by JS or built dynamically get deleted, and cache CSS files are overwritten. Leave it off unless you verified the output.

There is no built-in cache-busting helper — append `?v=` manually if needed.

---

## 8. Translations

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

## 9. Blade / Twig trap table

| Wrong | Right |
|-------|-------|
| `{{ $title }}` | `{{ var: $title }}` |
| `@if(...) @endif` | `{{ if ... }} {{ /if }}` |
| `{{ endif }}` / `{{ endforeach }}` | `{{ /if }}` / `{{ /foreach }}` |
| `@include('x')` | `{{ layout:x }}` |
| `@extends` / `@section` / `@yield` | `renderView()` + `{{ content }}` |
| `{{ $x ?? 'd' }}` | prepare in the controller |
| `@csrf` | `{{ CSRF }}` / `{{ formName(...) }}` |
| `{{ __('k') }}` | `{{_ "Source text" }}` |
| `{{ include 'x' }}` | JS templates only |
| assuming escaping | escape in PHP before passing |
| assuming an exception on a missing view | you get `""` |
