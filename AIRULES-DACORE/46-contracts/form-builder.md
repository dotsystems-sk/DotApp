# 46 — `form-builder` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This is the **operator-built public form** peer (render + PHP submit). It is **not** `captcha` (separate extra1) and **not** `survey` as a synonym role. A host and a pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `form-builder` |
| `extra2` | `v1` |
| `extra3` | `contact` \| `survey` \| `quiz` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'form-builder',
'extra2' => 'v1',
'extra3' => 'contact',
'extra4' => 'cms',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'form-builder', 'v1');
$contact = DotApp::call('DACore:Plugins@listByContract!', 'form-builder', 'v1', 'contact');
```

| extra3 | Meaning |
|--------|---------|
| `contact` | Name / email / message style. `submit` stores or mails via **DACore Email** ([38](../38-DACORE-EMAIL.md)) — **MUST NOT** invent SMTP |
| `survey` | Multi-question answers. Same `submit` shape; pack scores nothing required |
| `quiz` | Questions with a pack-owned score. Reply **MAY** include `score` (int) — **MUST NOT** include an answer key |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** invent a qualifier (`gravity`, `typeform`) as `extra5` |

**Kind:** peer. **Controller:** `{Module}:FormBuilderContract@…!`

The **host** (CMS, Shop) **MUST NOT** set `extra1=form-builder` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('form-builder','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':FormBuilderContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:FormBuilderContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'FormForge',         // exact module name
    'modes' => ['contact'],          // extra3 this pack actually implements
    'stores_submissions' => true,
    'throttle_hint' => true,         // host MUST throttle public submit
    'max_fields' => 40,
    'submit_url' => '/api/v1/noauth/FormForge/submit', // '' when host POSTs then calls submit()
]
```

**Failure:** `['ok' => false, 'message' => 'Forms are not ready.']` — product copy, no `getMessage()`.

`submit_url` is optional pack HTTP. The in-process `submit()` still has **no** CRC.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}` and, on validation fail, `errors`.

Form ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}` (unique `$key2`). Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing submission lists: `COUNT` + `LIMIT` on pack admin — **MUST NOT** `all()`. v1 peer methods do **not** include `list`.

### `renderForm($formId)`

**Call:** `DotApp::call('{Module}:FormBuilderContract@renderForm!', $formId)`

**Input:**

| Argument | Type | Meaning |
|----------|------|---------|
| `$formId` | string | Encrypted form id or pack-stable token. Empty / decrypt `false` → `ok:false` |

**Success:**

```php
[
    'ok' => true,
    'html' => '<fo-rm>…fields…</fo-rm>',
]
```

`html` is a fragment. Real multi-field submit uses `<fo-rm>` + `{{ formName(handler) }}` **between** the tags ([08](../08-FORMS-AND-SECURITY.md)). Markup via Renderer — **MUST NOT** concatenate the form in the controller.

Hidden form id in the fragment is ciphertext. Field labels are product copy; operator-entered labels are `htmlspecialchars` in the pack before `{{ var: }}`.

**Failure:** unknown form, decrypt fail, unpublished → `['ok' => false, 'message' => 'This form could not be shown.']`.

### `submit($formId, $fields)`

**Call:** `DotApp::call('{Module}:FormBuilderContract@submit!', $formId, $fields)`

**Input:**

| Argument | Type | Meaning |
|----------|------|---------|
| `$formId` | string | Encrypted form id or pack token |
| `$fields` | array | Posted values keyed by pack field names. HTML / long text from `$request->data(true)` on HTTP, then passed here. Unknown keys dropped. Count ≤ `max_fields` |

**Success:**

```php
[
    'ok' => true,
    'submission_id' => '…ciphertext…', // omit when the pack does not store a row
    'score' => 3,                      // quiz only; omit otherwise
]
```

**Failure (validation):**

```php
[
    'ok' => false,
    'message' => 'Please correct the highlighted fields.',
    'errors' => [
        'email' => 'Enter a valid email address.',
    ],
]
```

**Failure (cannot run):** decrypt fail, unknown form, rights → `ok:false` with product `message`, no `errors` dump of the request.

`submit` **MUST** validate in PHP (required, whitelist, length, email shape). A disabled button or JS-only check is UX only — skipping it **MUST** still fail here.

`quiz` **MUST NOT** return the answer key or expected choices.

---

## 5. PHP validation and persist (**MUST**)

Rules live in the pack. Host **MUST NOT** persist `$fields` itself and skip `submit`.

Passwords are not a v1 form-builder field. If a future field is a secret, it still comes from `$request->data(true)` and **MUST NOT** appear in hooks or admin list columns.

Persist sits in `try/catch`. Every `catch` and `execute()` `$err` reports `dotapp.catch` through the pack helper ([18](../18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9). The visitor still sees a toast or field errors — never `getMessage()`.

Mail from `contact` uses `DACore:Email@…!` ([38](../38-DACORE-EMAIL.md)). **MUST NOT** invent SMTP. Inbox for operators uses [37](../37-DACORE-NOTIFICATIONS.md) on the event, not every request.

---

## 6. Public POST needs throttle (**MUST**)

The host or pack public route that collects the form **MUST** `throttle()` that POST. Typical path: `/api/v1/noauth/{Module}/…` + `#DACore:AuthTest@CRC!` **XOR** action `crcCheck()` — never both — then `submit()`.

Unauthenticated forms are bot-bait. **MUST** warn in the pack/host plan. A `captcha` pack is a separate `extra1` (not MUST).

CRC is on that HTTP route only. `FormBuilderContract@submit!` has **no** CRC.

Visible outcome: public pages mark the wrong field from `errors` (red input + message on that field). Admin save uses a DACore toast. Empty `.after()` is forbidden.

---

## 7. Encrypted form ids (**MUST**)

`$formId` in HTML, `data-*`, and pager tokens is ciphertext (`{{ enc(FormForge.form.id): $id }}`, unique `$key2`). Stored `submission_id` that the browser sees is ciphertext too.

Decrypt `=== false` → reject. Still check rights / ownership in PHP.

Growing admin submission lists follow [40](../40-DACORE-LIST-PAGER.md). **MUST NOT** `all()`.

---

## 8. Hooks

Fire only after a useful persist — **not** on `renderForm`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.form_submitted.hook` | Submission stored or handed to mail | `form_id`, `mode`, `field_count` |

**MUST NOT** put field values, email addresses, messages, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

A failed validation **MUST NOT** fire the hook.

---

## 9. MUST NOT

- Invent `extra1` (`forms`, `survey`, `quiz`, `contact-form`)
- `glob('app/modules')` or `include` the pack to discover it
- Skip PHP validation or `throttle()` on the public POST
- Return plaintext numeric form / submission ids in HTML
- Put field values or an answer key in replies / hooks
- Invent SMTP (use DACore Email)
- `all()` a growing submissions table
- Leak `getMessage()` or request bodies
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `renderForm` returns `{ok, html}`; form id encrypted
- `submit` validates in PHP; public POST is throttled
- Field errors mark the input; admin uses a toast
- Hooks named in `.hooks` if a submission persist fires; payload is ids/counts only
- No `crcCheck()` on `capabilities` / `renderForm` / `submit`
