# 42 — User origin and secure module identity (MUST — law)

Origin is **provenance on one global account**: which module created it. It is not a permission, tenant boundary, PHP sandbox, or separate authentication store. DACore 1.0.10 catalogs tokens in **`dacore_user_origins`** and stores **`origin_id` plus the denormalized token** on `dacore_users_profiles`.

**Read this before** a shop, custom registration/login, member area, user lookup, import, profile page, or any screen/API that touches people.

Canonical APIs: `DACore:UserPolicy@registerOrigin`, `@removeOrigin`, `@stampOrigin`, `@listOriginRows`, `@read`. Tables/call shapes: [30](30-DACORE-OVERVIEW.md). Auth: [11](11-AUTH-AND-CRYPTO.md). Threat law: [24](24-ATTACK-VECTORS.md). Finish gate: [00](00-AGENT-CONTRACT.md) §2c.

---

## 0. Trust model (do not misunderstand this)

1. **All modules run in one trusted PHP application.** A module with executable PHP and DB/API access is not sandboxed by DACore, an object boundary, `private`, encryption, or origin. Any vulnerable or malicious module can endanger the application. The security goal is therefore to make the module itself correct: no injection/RCE, no IDOR, no cross-origin data access, no privilege escalation, strict server-side authorization.
2. **Auth identity is global.** `{prefix}users.email` and `username` are globally unique. Two origins cannot own separate accounts with the same email/username. Origin does not select a tenant-specific account.
3. **The Auth session is global.** A successful `Auth::login()` is not “a Shop-only session”. Every route in your module **MUST** apply its own rights, ownership and—when the product requires it—origin gate. Never rely on which login page created the session.
4. **DACore’s `dacore_login` flag is only the DACore login-form allow-list.** It is not a global session firewall and does not authorize another module’s form/routes. Your module owns and enforces its own origin policy.

---

## 1. Root laws

1. **Register, create, resolve, stamp, verify.** Register your token in the installer. After `Auth::createUser` returns `error === 0`, resolve the newly created positive user id by a bound exact lookup on the globally unique email/username (do not assume `createUser` returned an id), stamp it, then re-read and verify exact token **and** positive `origin_id`. Any failure aborts the workflow visibly.
2. **Fail closed in your module.** A missing profile, `read()` fallback, empty/unknown origin, failed catalog call, failed decrypt, DB exception, or token mismatch means **deny + generic reply**, not “legacy means allowed”.
3. **Scope every surface.** Login, stage-2 2FA, every authenticated route gate, profile/update/delete, lookup, export, background job and list query must enforce the module’s explicit origin allow-list in PHP. UI hiding and encrypted ids are not authorization.
4. **Never adopt a foreign global account.** A duplicate email/username or an account with another origin must not be restamped, reassigned, logged in to your module, or exposed. Return the same generic registration/login message used for other failures.
5. **No escalation or RCE.** The module must not grant `dotapp.root`, DACore `users.*`, or rights the actor lacks; mutate a more privileged target; execute user-selected PHP/callables/commands; deserialize input; or include a path derived from input.

---

## 2. Catalog contract (DACore 1.0.10)

| Piece | Exact contract |
|-------|----------------|
| Token | Lowercase, 2–5 dot-separated segments; regex `^[a-z][a-z0-9]{0,31}(\.[a-z0-9][a-z0-9_-]{0,47}){1,4}$`; max 200. Examples: `shop.checkout`, `daps.registered`; invalid: `Shop.checkout`, `my_shop.customer` |
| `id` | Autoincrement catalog id; profile stores it as `dacore_users_profiles.origin_id` |
| `creator` | `/^[A-Za-z][A-Za-z0-9]{0,63}$/`; module identity such as `Shop` or `DACore`; hyphens/underscores are invalid |
| Unique ownership | Token is globally unique. Reuse succeeds only for the same creator. Another creator must not steal or overwrite it |
| `dacore_login` | DACore login-form allow-list only; `1` by default for `dacore.*`, `0` for other newly registered tokens |
| Remove | Idempotent if absent; fails on creator mismatch, DB failure, or while any profile uses the id/token |

Installer seeds `dacore.legacy`, `dacore.installer`, `dacore.admin`, `dacore.registered` with `creator=DACore`, `dacore_login=1`.

**`dacore.legacy` is never a safe custom-module allow token.** `UserPolicy@read` uses it as a compatibility/default result for a missing profile/schema/read failure, so a module cannot distinguish that fallback from a real legacy row. Migrate/reclassify intended accounts to an explicit token first.

**Order and result checks are mandatory:**

```php
$origin = DotApp::call(
    'DACore:UserPolicy@registerOrigin',
    'shop.checkout',
    'Shop'
);
if (!is_array($origin) || ($origin['ok'] ?? false) !== true || (int) ($origin['origin_id'] ?? 0) < 1) {
    // Abort installation and report through this module's catch/error path.
}

// Only after Auth::createUser succeeded and a bound exact lookup found $userId:
if (DotApp::call('DACore:UserPolicy@stampOrigin', $userId, 'shop.checkout', 'Shop') !== true) {
    // Abort; do not continue with a dacore.legacy account.
}
$policy = DotApp::call('DACore:UserPolicy@read', $userId);
if (
    !is_array($policy)
    || (string) ($policy['origin'] ?? '') !== 'shop.checkout'
    || (int) ($policy['origin_id'] ?? 0) < 1
) {
    // Abort visibly and do not authenticate/expose this account.
}
```

**Do not trust `stampOrigin() === true` alone.** Current compatibility behavior may return true when a non-legacy different origin already exists. The mandatory `read()` equality check prevents account takeover.

**Never call `stampOrigin` first or omit creator.** A new external token can otherwise be cataloged as `unknown`. After upgrading an existing installation, inspect `listOriginRows()`; an `unknown` owner requires an explicit operator decision—do not silently claim or delete it.

---

## 3. Registration (global identity)

`Auth::createUser` does **not** return the new user id. Its duplicate result also cannot tell your module which origin owns that globally unique email.

Registration **MUST**:

1. Use original password input (`$request->data(true)`), validate and throttle the public POST.
2. Treat duplicate email/username, foreign-origin collision, invalid input and other expected refusal with product-appropriate **generic copy**. Do not reveal “this is an administrator/another module’s account”.
3. After `error === 0`, resolve only the exact newly created row with a **bound** query, selecting only `id`, `LIMIT 1`; reject missing/ambiguous result.
4. Stamp and re-read as shown above **before** assigning module state, starting a session, sending success, or firing your registration hook. The installer’s checked registration establishes ownership of the fixed token; runtime requires that exact token and a positive id.
5. On partial failure, do not silently leave a usable `dacore.legacy` account. Report it and perform a safe compensating action only if the product’s design explicitly defines one.

---

## 4. Custom login, 2FA and route gates

**MUST ASK in the plan**:

- Does this module have its own register/login or only DACore operators?
- Exact allowed origin token(s)?
- May these accounts use the DACore login form? Default: **no**.
- Does this module list/manage users? Its origin only, or an explicitly requested DACore-replacement UI?

For a custom login:

1. Use a generic invalid-credentials response for unknown account, wrong password **and foreign origin**.
2. Call `Auth::login`, check malformed/failed outcomes, then immediately get the positive `Auth::userId()`.
3. Before redirecting or rendering 2FA, call `UserPolicy@read`; exact token must be in the module’s fixed server-side allow-list, must not be `dacore.legacy`, and `origin_id` must be positive. On any mismatch/fallback/error: **`Auth::logout()` immediately**, return the same generic failure, and never disclose the foreign origin.
4. Repeat the exact origin check on the 2FA GET, after 2FA success, and in the module’s authenticated route middleware. This is required because a global session may have been created by DACore, remember-me, or another module.
5. Rotate/finish session state only through the normal Auth flow; origin does not replace rights or ownership.

**A module route gate is incomplete if it checks only `Auth::isLogged()`.** For an origin-scoped member area it must require: logged in + exact allowed origin + required rights/ownership.

---

## 5. Lists, lookups and writes

Origin fields live on **`dacore_users_profiles`**, not `{prefix}users`. A user list/query must:

- join `{prefix}users u` to `dacore_users_profiles p` by user id;
- use an **INNER JOIN** (or otherwise explicitly reject missing profiles);
- bind `p.origin_id = :origin_id` and, for defense in depth during migration, verify the exact expected `p.origin` token;
- select only columns the screen needs;
- paginate with COUNT + LIMIT ([40](40-DACORE-LIST-PAGER.md));
- re-check origin and rights again in every view/edit/save/delete/export action.

`UserPolicy@findByExtra` is a global discovery lookup; it does not establish origin ownership. **MUST NOT** use its ids as authorization. Scope the final query by joined origin, or keep module-owned membership in your own indexed table.

If the user explicitly requests a plugin that replaces DACore user administration:

- **MUST** warn that a defect can expose operators, all origins and the whole DB, then obtain explicit confirmation;
- still apply least-privilege rights, elevated-target protection, encrypted ids, bound SQL, one CRC, step-up 2FA for dangerous writes, origin-aware filters and audit hooks;
- never load hashes, TOTP secrets, reset tokens, rights blobs or unrelated PII into list/view JSON.

---

## 6. DACore login allow-list: precise behavior

Settings posts encrypted catalog ids as `origin_login[]` with key `UserPolicy::ENC_ORIGIN_ID`; at least one `dacore.*` row must remain checked. External origins default unchecked.

Current compatibility behavior is **not a fail-closed security boundary**:

- while the 1.0.10 catalog/schema is absent, `dacoreLoginAllowed()` returns true and `saveDacoreLoginFlags()` no-ops successfully;
- a missing profile or `UserPolicy@read` exception falls back to `dacore.legacy`; while that catalog row remains allowed, the DACore form can therefore allow that fallback.

Therefore:

- do not describe `dacore_login` as a global security boundary;
- run the 1.0.10 update before relying on the DACore form allow-list;
- your module must always fail closed on its own exact origin check, regardless of DACore Settings;
- an unknown `dacore.*` token may be accepted by the DACore compatibility path; your module must not copy that fallback.

On the DACore flow, password/2FA completion can show “This account cannot sign in through DACore”; a rejected 2FA GET currently logs out and redirects silently. A custom public module login should use the same generic invalid-credentials copy for wrong and foreign accounts.

---

## 7. Uninstall and hooks

Uninstall calls `removeOrigin($token, $creator)` only after module-owned accounts are gone or deliberately migrated. It **MUST** inspect `{ok, message}`. In-use/mismatch/error means uninstall cleanup is incomplete: report it and ask the operator; never remap to `dacore.legacy` silently.

| Event | Exact scope |
|-------|-------------|
| `module.dacore.origin_registered.hook` | New catalog insert only; not same-creator reuse. Payload: `origin_id`, `origin`, `creator` |
| `module.dacore.origin_removed.hook` | Successful catalog delete. Same payload |
| `module.dacore.user_registered.hook` | DACore public registration path—not every `Auth::createUser`. Includes origin token |
| `module.dacore.user_policy_changed.hook` | Policy API/admin changes; ids + changed keys, no values/secrets |

A Shop registration must fire and document **its own** useful `module.shop.*.hook` after stamp verification; it must not pretend DACore fired one.

---

## 8. Non-escalation finish gate (MUST grep)

For every custom registration/login/user surface, grep and verify:

- installer has `registerOrigin` with result checks; create path has `stampOrigin` **and** `read` equality checks;
- no assumption that `Auth::createUser` returned an id;
- login failure/mismatch calls `Auth::logout()` before reply;
- login, 2FA and authenticated middleware all check exact origin;
- user SQL joins `dacore_users_profiles` and binds `p.origin_id`/token;
- duplicate/foreign account responses do not enumerate;
- no direct write to DACore origin/profile tables;
- no `eval`, request-named callable/class, command execution, unserialize, dynamic include, raw SQL input, rights grant, or more-privileged mutation;
- all normal finish-gate rows in [00](00-AGENT-CONTRACT.md) §2c and [17](17-CHECKLISTS.md) still pass.
