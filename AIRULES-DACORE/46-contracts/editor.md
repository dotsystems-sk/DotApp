# 46 — `editor` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This is the **content-sanitize / render** peer (HTML, markdown, or blocks). It is **not** `page-builder` (no page tree / `listBlocks`) and **not** `template` (no theme files). A host and a pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `editor` |
| `extra2` | `v1` |
| `extra3` | `html` \| `markdown` \| `blocks` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'editor',
'extra2' => 'v1',
'extra3' => 'html',
'extra4' => 'cms',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'editor', 'v1');
$html = DotApp::call('DACore:Plugins@listByContract!', 'editor', 'v1', 'html');
```

| extra3 | Meaning |
|--------|---------|
| `html` | Tag whitelist on HTML. `sanitize` / `render` stay HTML → HTML |
| `markdown` | Source is markdown. `sanitize` returns safe HTML. `render` converts stored markdown to safe HTML |
| `blocks` | Serialized block document (JSON object/array only). `sanitize` returns safe HTML or a sanitized document in the same `html` key. **MUST NOT** `unserialize` PHP |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** invent a qualifier (`tinymce`, `ckeditor`) as `extra5` |

**Kind:** peer. **Controller:** `{Module}:EditorContract@…!`

The **`<Host>` module** **MUST NOT** set `extra1=editor` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('editor','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':EditorContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:EditorContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'HtmlEditor',        // exact module name
    'modes' => ['html'],             // extra3 this pack actually implements
    'max_chars' => 200000,           // reject longer $html / $content
    'editor_js' => '/assets/modules/HtmlEditor/js/editor.js', // '' when host types only
    'editor_css' => '/assets/modules/HtmlEditor/css/editor.css',
]
```

**Failure:** `['ok' => false, 'message' => 'The editor is not ready.']` — product copy, no `getMessage()`.

`editor_js` / `editor_css` are optional public asset URLs. Empty string = no pack widget. **MUST NOT** return a license key, CDN token, or API secret.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

On HTTP the host/pack reads HTML or markdown with `$request->data(true)` (original), then calls these helpers. The helpers themselves have **no** CRC.

`$mode` **MUST** be in `capabilities.modes` (`html` \| `markdown` \| `blocks`). Unknown → `ok:false`.

### `sanitize($html, $mode)`

**Call:** `DotApp::call('{Module}:EditorContract@sanitize!', $html, $mode)`

**Input:**

| Argument | Type | Meaning |
|----------|------|---------|
| `$html` | string | Inbound markup or markdown or a JSON block document. Host already took the original. Empty after trim → `ok:false`. Length ≤ `max_chars` |
| `$mode` | string | `html` \| `markdown` \| `blocks` — must match a mode this pack implements |

**Success:**

```php
[
    'ok' => true,
    'html' => '<p>Safe fragment</p>',
]
```

`html` is **safe HTML** (or, in `blocks` mode, a sanitized serialized document stored in that same key — pack documents which). Scripts, event handlers, `javascript:` URLs, and unknown tags/attrs are stripped.

**Failure:** unknown mode, oversize, invalid block JSON → `['ok' => false, 'message' => 'This content could not be cleaned.']`.

**MUST NOT** `eval`, `unserialize`, or `include` the string. Blocks: `json_decode` only, then a type whitelist.

### `render($content, $mode)`

**Call:** `DotApp::call('{Module}:EditorContract@render!', $content, $mode)`

**Input:**

| Argument | Type | Meaning |
|----------|------|---------|
| `$content` | string | Stored body (already sanitized, or raw if the host stored original and relies on `render`). Same length cap |
| `$mode` | string | Same whitelist as `sanitize` |

**Success:**

```php
[
    'ok' => true,
    'html' => '<p>Display fragment</p>',
]
```

`html` is a display fragment. `markdown` converts to HTML. `html` applies the same strip rules (idempotent). `blocks` walks the document and renders through pack templates ([05](../05-VIEWS-TEMPLATES-ASSETS.md) §1c) — **MUST NOT** concatenate a page in the controller.

**Failure:** unknown mode, oversize, broken document → `['ok' => false, 'message' => 'This content could not be rendered.']`.

---

## 5. Sanitize vs render

| Method | When |
|--------|------|
| `sanitize` | Before persist. Host stores the returned `html` (or its own original **and** calls `sanitize` again on display) |
| `render` | When painting a public or admin view from stored `$content` |

Calling `sanitize` then `render` **MUST** stay safe. Skipping `sanitize` on persist **MUST** still fail closed inside `render` (strip again). A disabled JS toolbar is UX only.

v1 has no `preview($html)` method — use `render`.

---

## 6. Host views and `htmlspecialchars` (**MUST**)

`{{ var: }}` does **not** escape.

- Titles, labels, mode names, error strings: host **MUST** `htmlspecialchars` before `{{ var: }}`. JS inserts use `.text()`, not `.html()`.
- The `html` fragment from `sanitize` / `render` is pack-cleaned markup. The host **MAY** insert it as HTML only after this call. That does **not** skip escaping of every other field on the page.
- **MUST NOT** treat `sanitize` as a license to print request data raw.

Sandbox: do not pass callable names as view keys ([05](../05-VIEWS-TEMPLATES-ASSETS.md) §5).

---

## 7. No eval / no secrets

Pack **MUST NOT** `eval`, `create_function`, `unserialize`, `preg_replace` `/e`, or `include` a user path. Block JSON is data, not PHP.

Widget JS (if `editor_js` is set) is a `$dotapp().fn` in **this** pack. **MUST NOT** copy DACore JS. Requests stay `$dotapp().form` / `load`.

License keys and cloud editor tokens stay in pack config — **not** extras, **not** `capabilities`, **not** replies.

---

## 8. Hooks

Fire only after a useful persist — **not** on every `sanitize` / `render` (flood).

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.editor_preset_stored.hook` | Operator saved a reusable preset / snippet | `id`, `mode`, `chars` (int) |

A one-shot clean of article body **MUST NOT** fire a hook. Document in the pack `.hooks` only if a preset persist exists. [41](../41-MODULE-HOOKS.md).

**MUST NOT** put HTML, markdown, or API keys in the payload.

---

## 9. MUST NOT

- Invent `extra1` (`wysiwyg`, `tinymce`, `markdown`, `blocks` as a role)
- Set `extra1=page-builder` on an editor-only pack
- `glob('app/modules')` or `include` the pack to discover it
- `eval` / `unserialize` / `include $x` the inbound string
- Skip host `htmlspecialchars` on titles and other plain fields
- Put secrets in extras, capabilities, or replies
- Leak `getMessage()` or the raw inbound HTML on the hook bus
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `sanitize($html, $mode)` returns `{ok, html}` safe HTML
- `render` returns `{ok, html}`; markup via Renderer, not a PHP factory
- Host still `htmlspecialchars` plain view fields
- No `eval` / `unserialize`
- Hooks named in `.hooks` only if a preset persist fires
- No `crcCheck()` on `capabilities` / `sanitize` / `render`
