# 46 — `lms` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6. User ids: [42-DACORE-USER-ORIGIN.md](../42-DACORE-USER-ORIGIN.md).

This page is the v1 peer contract. A `<Host>` and an LMS pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md). This is **not** `kb` (articles) and **not** `forum` (board).

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `lms` |
| `extra2` | `v1` |
| `extra3` | `course` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'lms',
'extra2' => 'v1',
'extra3' => 'course',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'lms', 'v1');
$course = DotApp::call('DACore:Plugins@listByContract!', 'lms', 'v1', 'course');
```

| extra3 | Meaning |
|--------|---------|
| `course` | Catalogue of courses plus enrol / progress for a host-checked user |

| extra4 | Meaning |
|--------|---------|
| `generic` | Any host family |
| `cms` | Tuned for a content-management host family |
| `shop` | Tuned for a shop host |
| `erp` | Tuned for an ERP host |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier in v1. **MUST NOT** invent `scorm` / `moodle` as `extra5` |

**Kind:** peer. **Controller:** `{Module}:LmsContract@…!`

The **`<Host>` module** **MUST NOT** set `extra1=lms` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('lms','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':LmsContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:LmsContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'Learn',             // exact module name
    'modes' => ['course'],           // extra3 this pack actually implements
    'families' => ['generic', 'cms'],
    'courses' => [                   // bounded published catalogue for the picker
        [
            'id' => '…ciphertext…',  // {{ enc(Learn.course.id) }} unique $key2
            'title' => 'Onboarding',
            'open' => true,          // false = closed / archived (enroll → ok:false)
        ],
    ],
    'course_count' => 12,            // total published; omit items beyond a bound
    'max_courses_in_capabilities' => 50,
]
```

**About:** `courses[]` is a **bounded** picker list (native `<select>` / `dotSelect2`). A growing catalogue **MUST NOT** dump every row here — keep `max_courses_in_capabilities` (default 50), set `course_count`, and let the operator pick the encrypted `courseRef` in the pack admin or host settings. v1 does **not** add `listCourses`; enrol and progress take a ref the host already holds.

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Learning is not ready.',
]
```

Product copy only. **MUST NOT** `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}`. Incoming encrypted course ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing lists: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

`$userId` is a **positive int** the **host already authorized** (logged in, exact origin, rights). The pack **MUST NOT** re-stamp origin, create users, or own the Auth session ([42](../42-DACORE-USER-ORIGIN.md)). The pack **MUST** still reject `userId < 1` and treat every miss with the **same generic** message (no user / course enumeration).

### `enroll($courseRef, $userId)`

**Call:** `DotApp::call('{Module}:LmsContract@enroll!', $courseRef, $userId)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$courseRef` | string | Encrypted course id or pack-stable token |
| `$userId` | int | Host-checked `{prefix}users.id` (positive) |

**Success:**

```php
[
    'ok' => true,
    'enrolled' => true,
]
```

Already enrolled → **idempotent** `ok:true` (do not leak a different code). **MUST NOT** return whether the user row exists as a distinct case.

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Could not enrol on this course.',
]
```

Decrypt fail, unknown course, closed course, `$userId < 1`, persist fail. **One** generic product line. **MUST NOT** say “unknown user”, “foreign origin”, or “no such course”. **MUST NOT** persist on a non-idempotent failure.

**About:** the host has already enforced origin and membership. The pack writes only **its** `{lowercase_modulename}_*` enrolment row. **MUST NOT** write `dacore_*` / `users_rights*` / `dacore_users_profiles`.

### `progress($courseRef, $userId)`

**Call:** `DotApp::call('{Module}:LmsContract@progress!', $courseRef, $userId)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$courseRef` | string | Same as `enroll` |
| `$userId` | int | Same as `enroll` |

**Success:**

```php
[
    'ok' => true,
    'percent' => 40,   // int 0–100 inclusive
]
```

**About:** `percent` is an **int**. Pack **MUST** clamp to `0`…`100`.

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Progress is not available.',
]
```

Not enrolled / unknown course / bad decrypt / `$userId < 1`. **MUST NOT** return `percent` on failure. **MUST NOT** distinguish missing user vs missing course vs not enrolled.

No lesson dump in v1. Hosts that need a learner UI use the pack’s own admin/member pages.

---

## 5. Origin, HTML, and replies (**MUST**)

1. Host **MUST** check exact origin + rights **before** `enroll` / `progress` ([42](../42-DACORE-USER-ORIGIN.md) §4–§5). `dacore.legacy` is never a custom allow token.
2. Pack **MUST NOT** call `Auth::login`, `UserPolicy@stampOrigin`, or `Auth::createUser` from this contract.
3. Course ids in host HTML = `{{ enc(Learn.course.id): $id }}` (unique `$key2`). Decrypt `false` → reject.
4. If the host ever prints `$userId` in HTML, that id is encrypted too. In-process calls pass the raw int only.
5. Generic miss replies: no user enumeration, no origin token, no email, no “this is an administrator”.
6. Host **MUST** `htmlspecialchars` any course title from `capabilities()` before `{{ var: }}`.

---

## 6. Catalogue picker and lists

`courses[]` in `capabilities()` is a **bounded** choice. Opening the picker **MUST** show options without requiring an exact remembered name ([09](../09-DOTAPP-JS-AND-BRIDGE.md) §3).

A growing catalogue beyond `max_courses_in_capabilities` is pack admin, paged with `COUNT` + `LIMIT` ([40](../40-DACORE-LIST-PAGER.md)). v1 has **no** `LmsContract@list`.

---

## 7. Enrolment tables and HTTP

Pack tables **MUST** be `{lowercase_modulename}_*` (example: `learn_enrolments`). Never `dacore_*` / `users_rights*`.

Host **MUST** call these helpers in-process after the operator picked the pack. Member POST stays on `/api/v1/auth/{Host|Module}/…` + `#DACore:AuthTest@LoginAndCRC!`. The action **MUST NOT** `crcCheck()` again. Contract helpers have **no CRC**.

**MUST NOT** expose `enroll` on `/api/v1/noauth/…`.

`progress` is a read — **MUST NOT** fire a hook (except the one-time `lms_completed` when a **write** first reaches 100 — that write lives on pack lesson pages, not on this read helper).

---

## 8. Hooks

Fire only after a useful persist — **not** on `progress` reads or `capabilities`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.lms_enrolled.hook` | New enrolment row inserted (not idempotent re-call) | `course_id`, `user_id` |
| `module.{mod}.lms_completed.hook` | Progress first reaches `100` | `course_id`, `user_id`, `percent` |

**MUST NOT** put lesson bodies, emails, origin tokens, or secrets in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`learning`, `courses`, `elearning`, `moodle`)
- `glob('app/modules')` or `include` the pack to discover it
- Enumerate users (“no such user”) or leak origin / email
- Own Auth / stamp origin from `LmsContract`
- Write `dacore_*` / `users_rights*`
- `all()` on a growing enrolment or course table
- Leak `getMessage()`, request bodies, or rights blobs
- PHP 8+ syntax unless the plan named a higher version
- Set `extra1=lms` on the host

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML course id is encrypted
- Host checked origin before `enroll` / `progress`
- Miss replies are generic (no user enum)
- `percent` is int 0–100
- Every method has input table + success/fail PHP arrays
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `enroll` / `progress`
