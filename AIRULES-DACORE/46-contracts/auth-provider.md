# 46 — `auth-provider` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This is the **v1 peer contract** for an SSO / directory pack. A `<Host>` and a pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md).

This role is **not** DACore login, **not** step-up 2FA (`$dotapp().twoFactor`), and **not** a second Auth store. Origin law: [42](../42-DACORE-USER-ORIGIN.md).

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `auth-provider` |
| `extra2` | `v1` |
| `extra3` | `oauth` \| `oidc` \| `saml` \| `ldap` \| `social` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit) |

```php
'extra1' => 'auth-provider',
'extra2' => 'v1',
'extra3' => 'oidc',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'auth-provider', 'v1');
$oidc = DotApp::call('DACore:Plugins@listByContract!', 'auth-provider', 'v1', 'oidc');
```

| extra3 | Meaning |
|--------|---------|
| `oauth` | OAuth 2.0 authorization-code. `start` returns the IdP authorize URL. Pack callback exchanges `code` |
| `oidc` | OpenID Connect (ID token). Same start/complete shape as `oauth` |
| `saml` | SAML 2.0 AuthnRequest / ACS. `start` returns the IdP SSO URL (or pack POST-binding page) |
| `ldap` | Directory bind. `start` returns a **pack-owned** login form URL. Bind happens on the pack HTTP POST; `complete` receives a one-time ticket, **not** the password |
| `social` | Hosted social button (Google / Apple / …) using OAuth/OIDC under the hood. Same I/O as `oauth` |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** put a client id, tenant, IdP URL, or bind DN here |

A pack that implements more than one mode sets `extra3` to the **primary** mode in `about.php` and lists every implemented mode in `capabilities()['modes']`.

**Kind:** peer. **Controller:** `{Module}:AuthProviderContract@…!`

The **`<Host>` module** **MUST NOT** set `extra1=auth-provider` on itself.

Client secrets, SAML certificates, LDAP bind passwords, and IdP URLs **MUST NOT** appear in `extra1`…`extra5`. Those live in the **pack’s** settings.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('auth-provider','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':AuthProviderContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

The host installer **MUST** `DACore:UserPolicy@registerOrigin` for the host’s own origin token (example `shop.members`) and check `{ok, origin_id}` **before** any `complete` path can create a user. The pack **MUST NOT** register the host’s origin.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:AuthProviderContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'SsoPack',           // exact module name
    'modes' => ['oidc'],             // extra3 this pack actually implements
    'hint_kinds' => ['email_hash', 'subject'], // what complete() may return
    'callback_url' => '/api/v1/noauth/SsoPack/callback', // pack HTTP ACS / OAuth return; '' when unused
    'start_needs_return' => true,    // true when start() requires return_to
]
```

**MUST NOT** return client secrets, private keys, LDAP bind passwords, raw IdP metadata XML, or Authorization headers.

**Failure:** `['ok' => false, 'message' => 'Sign-in provider is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. One-time ticket tables that grow: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

The pack **MUST NOT** call `Auth::login`, `Auth::createUser`, `Auth::logout`, `UserPolicy@stampOrigin`, or `session_start()`. The **host** owns the Auth session and origin.

### `start($opts)`

**About:** Begin an IdP / directory challenge. The browser leaves the host for `redirect_url`.

**Call:** `DotApp::call('{Module}:AuthProviderContract@start!', $opts)`

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `return_to` | string | Host-owned path after the IdP returns, e.g. `/shop/account/sso-return`. **MUST** start with `/`. Charset `[A-Za-z0-9._:/-]`. **MUST NOT** `..`, `http://`, `https://`, `//`, or a value copied from `$request` into `header()` |
| `state` | string | Optional host correlation token. Charset `[A-Za-z0-9._-]`, length ≤ 64. Pack **MAY** embed it in its own state |

Unknown keys **MUST** be ignored (no request-spread into the IdP query).

**Success:**

```php
[
    'ok' => true,
    'redirect_url' => 'https://idp.example/authorize?…', // or pack-relative '/SsoPack/ldap-form'
]
```

`redirect_url` is either:

- an `https://` URL on an IdP host the **operator** configured in pack settings, or
- a host-relative path the **pack** registered (`/api/v1/noauth/{Module}/…` or `{prefix}/{Module}/…`).

**MUST NOT** build `redirect_url` from raw request input. **MUST NOT** put the client secret in the query.

**Failure:** pack not configured; illegal `return_to`; IdP down at discovery time → `['ok' => false, 'message' => 'Sign-in is not available.']`.

`ldap`: `redirect_url` **MUST** be the pack login form (not an LDAP `ldap://` URL). The bind password never leaves that form except as a POST body to the pack action.

### `complete($callbackData)`

**About:** Finish the challenge and return a **hint only**. The host then creates or maps the account ([42](../42-DACORE-USER-ORIGIN.md)).

**Call:** `DotApp::call('{Module}:AuthProviderContract@complete!', $callbackData)`

**Input** `$callbackData` array — whitelist only:

| Key | Type | Meaning |
|-----|------|---------|
| `code` | string | OAuth / OIDC authorization code. Empty when unused |
| `state` | string | Pack/host state from `start`. Decrypt / hash_equals fail → `ok:false` |
| `ticket` | string | One-time pack ticket (LDAP bind already done, or SAML ACS already validated). Encrypted id or `random_bytes` token the pack issued |
| `saml_response_id` | string | Optional pack-internal ACS row id (ciphertext). **MUST NOT** be the raw SAML XML |

**MUST NOT** accept `password`, `passwd`, `secret`, `client_secret`, `assertion`, or raw SAML XML. Extra keys **MUST** be ignored.

**Success — `{ok, user_hint}` ONLY:**

```php
[
    'ok' => true,
    'user_hint' => [
        'kind' => 'email_hash',   // email_hash | subject
        'value' => '…',           // 64-char lowercase hex SHA-256 of normalized email, or IdP subject (no email)
    ],
]
```

| `kind` | `value` |
|--------|---------|
| `email_hash` | `hash('sha256', strtolower(trim($email)))` hex. **MUST NOT** return the email |
| `subject` | Stable IdP subject / `sub` / LDAP `entryUUID`. **MUST NOT** be a password, DN with a secret, or access token |

**MUST NOT** add `email`, `name`, `picture`, `access_token`, `refresh_token`, `id_token`, `password`, or profile blobs to this array. Those stay in the pack or are discarded.

**Failure:** unknown / spent ticket; decrypt fail; IdP reject; state mismatch → `['ok' => false, 'message' => 'Sign-in could not be completed.']`.

A successful `complete` **MUST** invalidate the one-time ticket / code so a replay returns `ok:false`.

---

## 5. Host owns account and session (**MUST**)

`complete` **MUST NOT** create a user or open an Auth session. The **host** does this, in this order ([42](../42-DACORE-USER-ORIGIN.md)):

1. Map `user_hint` to an email the host already stored, **or** collect email on a **host-owned** follow-up form (`$request->data(true)`). **MUST NOT** invent an email from the hash.
2. Installer already ran `DACore:UserPolicy@registerOrigin` for the host token. Runtime **MUST NOT** skip that registration.
3. `Auth::createUser` with original password input when the host sets a password (SSO-only hosts **MAY** set a random `random_bytes` password the user never sees). Check `error === 0`. Duplicate / foreign origin → the **same generic** copy as other failures.
4. Bound exact lookup of the new row (`id` only, `LIMIT 1`). **MUST NOT** assume `createUser` returned an id.
5. `DACore:UserPolicy@stampOrigin` with the host token and host creator. `!== true` → abort. **MUST NOT** continue as `dacore.legacy`.
6. `DACore:UserPolicy@read` and require **exact** token **and** positive `origin_id`. Mismatch / fallback → abort visibly.
7. Only then `Auth::login` (if the product signs the user in) and host route gates.

Existing account: look up by a **host** mapping table (`hint kind+value` → user id) joined to `dacore_users_profiles` on the host origin. Foreign origin → generic failure, **MUST NOT** restamp.

The pack **MUST NOT** own the Auth session (`DSM` for Auth, `$_SESSION`, `Auth::login`). Pack DSM is allowed only for its own one-time tickets.

---

## 6. HTTP (pack-owned, not `AuthProviderContract`)

IdP return / ACS / LDAP form POST lives on the pack:

- Public: `/api/v1/noauth/{Module}/…` + `#DACore:AuthTest@CRC!` **XOR** action `crcCheck()` — never both.
- Operator settings: `/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!`. Action **MUST NOT** `crcCheck()` again.

Public POSTs **MUST** `throttle()`. Skipping the IdP in the browser **MUST** still fail in PHP.

After the pack validates the IdP payload it **MUST** store a one-time ticket and redirect to the host `return_to` (host-relative, already allowlisted in `start`). **MUST NOT** put request data into `header()` / `HttpHelper` URL.

The in-process `start!` / `complete!` helpers have **no** CRC.

---

## 7. Custom gate — never `dacore.legacy` (**MUST**)

A host (or pack helper page) that gates “members of this SSO” **MUST**:

- require `Auth::isLogged()` **and** `UserPolicy@read` exact host origin **and** positive `origin_id`;
- **MUST NOT** treat `dacore.legacy` as allowed (`UserPolicy@read` uses it as a missing-profile fallback);
- on mismatch / fallback / error: `Auth::logout()` immediately, same generic failure, no foreign-origin disclosure.

`dacore_login` is the DACore form allow-list only. It is **not** this module’s boundary.

Repeat the exact origin check after login, before/on/after 2FA, and in every authenticated route. [42](../42-DACORE-USER-ORIGIN.md) §4.

---

## 8. Hooks

Fire only after a useful persist — **not** on `start` or `capabilities`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.auth_hint_ready.hook` | One-time ticket stored after IdP success (before or as `complete` consumes it) | `id` (ticket row), `hint_kind` (`email_hash` \| `subject`) |

**MUST NOT** put email, subject plaintext (when hashed), passwords, tokens, SAML XML, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

The **host** fires its own `module.{host}.{registered}.hook` after stamp + re-read — not this pack.

---

## 9. MUST NOT

- Invent `extra1` (`sso`, `oauth`, `oidc`, `saml`, `ldap`, `social-login`)
- `glob('app/modules')` or `include` the pack to discover it
- Own the Auth session or call `Auth::createUser` / `Auth::login` from the pack
- Return email, password, tokens, or profile blobs from `complete`
- Allow `dacore.legacy` in a custom gate
- Put client secrets, certificates, or bind passwords in extras or replies
- Build `redirect_url` / `Location` from raw request input
- Leak `getMessage()`, IdP bodies, or request bodies
- `all()` on a growing ticket table
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `start` is `{ok, redirect_url}` only
- `complete` is `{ok, user_hint}` only — hash or subject, no password
- Host create path: `registerOrigin` → `createUser` → bound id → `stampOrigin` → re-read exact token/id
- Pack never opens Auth; custom gate refuses `dacore.legacy`
- Ticket / hint ids in HTML are encrypted
- Hooks named in `.hooks` if fired; no email in the payload
- No `crcCheck()` on `capabilities` / `start` / `complete`
