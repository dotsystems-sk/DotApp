# 46 — `captcha` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This is the **public-form antibot** peer. The secret stays in the pack. A host and a pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `captcha` |
| `extra2` | `v1` |
| `extra3` | `image` \| `recaptcha` \| `hcaptcha` \| `turnstile` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'captcha',
'extra2' => 'v1',
'extra3' => 'turnstile',
'extra4' => 'cms',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'captcha', 'v1');
$img = DotApp::call('DACore:Plugins@listByContract!', 'captcha', 'v1', 'image');
```

| extra3 | Meaning |
|--------|---------|
| `image` | Pack draws a challenge. `challenge` returns `html` (img + input names). No third-party sitekey |
| `recaptcha` | Google reCAPTCHA. `challenge` returns `sitekey` and/or `html` |
| `hcaptcha` | hCaptcha. Same shape as recaptcha |
| `turnstile` | Cloudflare Turnstile. Same shape |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | **MUST NOT** put a secret or sitekey here |

**Kind:** peer. **Controller:** `{Module}:CaptchaContract@…!`

The **host** **MUST NOT** set `extra1=captcha` on itself. Sitekey **MAY** appear in `challenge` success. **Secret MUST NOT** appear in extras, replies, or hooks.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('captcha','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':CaptchaContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:CaptchaContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'TurnPack',
    'modes' => ['turnstile'],
    'widget_js' => 'https://challenges.cloudflare.com/turnstile/v0/api.js', // '' for image
]
```

**Failure:** `['ok' => false, 'message' => 'Captcha is not ready.']` — no secret.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC**. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Public `verify` **MUST** be reached only after host `throttle()` on that POST.

### `challenge($opts)`

**Call:** `DotApp::call('{Module}:CaptchaContract@challenge!', $opts)`

| Key | Type | Meaning |
|-----|------|---------|
| `locale` | string | Optional `sk` \| `en` |

**Success (widget):**

```php
[
    'ok' => true,
    'html' => '<div class="…">…</div>',  // optional
    'sitekey' => '0x4AAA…',              // public sitekey only
]
```

**Success (image):** `html` set, `sitekey` empty. Image bytes go through a pack GET route, not this helper.

**Failure:** pack not configured → `ok:false`. **MUST NOT** return the secret.

### `verify($opts)`

**Call:** `DotApp::call('{Module}:CaptchaContract@verify!', $opts)`

| Key | Type | Meaning |
|-----|------|---------|
| `token` | string | Posted token / image answer. Original (`data(true)`) |
| `ip` | string | Optional client IP. Pack may send it to the vendor |

**Success:** `['ok' => true]`.

**Failure:**

```php
['ok' => false, 'message' => 'Verification failed.']
```

Generic for every miss. **MUST NOT** echo the token, vendor body, or `getMessage()`.

---

## 5. Hooks

v1 **MUST NOT** fire a hook on `challenge` / `verify` (no useful side-effect; would flood).

---

## 6. MUST NOT

- Invent `extra1` (`recaptcha`, `antibot`, `turnstile`)
- Put the secret in `extra1`…`extra5` or replies
- Skip `throttle()` on the public POST that calls `verify`
- `glob('app/modules')` to discover the pack
- PHP 8+ syntax unless the plan named a higher version

---

## 7. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Secret only in pack config
- Public verify throttled
- No `crcCheck()` on these helpers
