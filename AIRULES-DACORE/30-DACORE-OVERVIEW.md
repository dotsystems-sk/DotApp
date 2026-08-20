# 30 — DACore Overview (admin UI layer)

**DACore is a module, not part of the framework core.** It provides a ready-made administration shell: login + 2FA, permissions, a DB-driven sidebar menu, a page shell, error pages, optional AI chat with tools, and install tracking.

Your module **plugs into** DACore through public `DotApp::call()` APIs. By default it never edits DACore files or assets, and it never adds files into DACore. **MUST NOT propose** a DACore edit. Work only in the **current** module. Informed exception: [00](00-AGENT-CONTRACT.md) §1.

Framework rules (docs `00`–`22`) still apply in full. This DACore layer (`30`–`39`) is additive.

---

## 1. Absolute rules for a DACore plug-in module

Treat `app/modules/DACore/` like `app/parts/` **by default**. DACore is shipped and updated as a complete package. **Any edit, patch, or extra file inside it is destroyed on the next DACore update.** You may only consume the APIs it already exposes. All new features, pages, controllers, views, and **assets** go in **the module you are programming** (`app/modules/<YourModule>/`). **MUST NOT propose** editing DACore. If the user **themselves** asks to change DACore in place **and** confirms they accept the wipe: then you may edit DACore for that request ([00](00-AGENT-CONTRACT.md) §1). Otherwise: implement it in your module.

| Rule | Why |
|------|-----|
| **Never edit any file in `app/modules/DACore/`** (default) | DACore updates overwrite the whole module — local patches disappear |
| **Never add files, controllers, views, JS, CSS, or SQL into DACore** (default) | Same reason: the next update wipes them. Extend via your own module only |
| **Never propose a DACore patch** | The user must ask; the agent must not offer it |
| **Never INSERT/UPDATE/DELETE `dacore_menu`, `dacore_ai_tools`, `dacore_installations`, `dacore_modules`, `dacore_plugin_logs`, `dacore_settings`, `dacore_notifications`, `dacore_notifications_inbox`, `dacore_email_senders`, `dacore_email_templates`, `dacore_sms_senders` directly** | Use the registration / push / `Email@send` / `Sms@send` APIs — they handle upsert/fan-out, column compatibility and cache invalidation. Installer tables are DACore-owned. |
| **Never write into `{prefix}users_rights*` directly** | Use `DACore:Rights@*` |
| **Never duplicate the admin HTML shell** | Use `DACore:Page@withMenu!` |
| **Never use `$_SESSION` / `session_start()`** | **MUST** `DSM::use('Shop')` ([20](20-CACHE-LOGGER-SESSION.md)) |
| **Never rely on `#DACore:AuthTest@check!` for permission checks** | It ignores the rights you pass — see [36](36-DACORE-KNOWN-ISSUES.md); create your own `Middleware/Rights.php` |
| Register menu/rights/tools **from `Installation.php`**, not on every request | Otherwise you write to the DB on each page load |
| Push inbox notifications **on the event** | `DACore:Notifications@push` — not installer, not every request ([37](37-DACORE-NOTIFICATIONS.md)) |
| Sending mail from a module | Open [38](38-DACORE-EMAIL.md) — do not invent SMTP |
| Sending SMS from a module | Open [39](39-DACORE-SMS.md) — do not invent a gateway |
| **ASK menu layout** on a new DACore module | Shared full sidebar vs module-own (`withMenu` `$menuId`). Many items: group `type => 2` or header + one entry ([31](31-DACORE-MENU.md)) |
| **Active sidebar on subpages** | Edit/detail **MUST** pass `withMenu` 7th `$currentFile` = registered list URL when the path is not under that leaf (`/users/4` vs `/users-list`). [31](31-DACORE-MENU.md) |
| Pack `dainstall.php` + `init/` **only** for a **DACore-bound** module and only when asked | While coding: **`install.php`**. Zip **MUST** rename it to `dainstall.php` (DACore **rejects** `install.php` / never runs Installation without `dainstall.php`) + **`init/`**. Non-DACore: copy the folder. [00](00-AGENT-CONTRACT.md) §2e, [35](35-DACORE-INSTALL.md) §4–§5. **MUST NOT** pack `app/modules/DACore/`. |
| **Search DACore first** before a new library or page chrome | The base already has many subpages and widgets — grep read-only, then reuse ([33](33-DACORE-PAGES-AND-UI.md)) |
| **Operator 2FA stays on**; dangerous actions re-prompt 2FA in **your** module | [32](32-DACORE-RIGHTS.md) §6 — **PHP** verifies the code before persist (overlay is UX only); never `Auth::confirmTwoFactor` while already logged in |

Editable paths **by default:** `app/config.php` and `app/modules/<YourModule>/` only (that module’s assets included). DACore: [00](00-AGENT-CONTRACT.md) §1.

---

## 2. Complete third-party call map

| Operation | Call string | Returns |
|-----------|-------------|---------|
| Register / update menu item | `DACore:Menu@register` | `bool` |
| Read menu tree | `*DACore:Menu@getItems!` | `array` of nodes |
| Render menu HTML | `DACore:Menu@generate!` | `string` |
| Create rights group | `DACore:Rights@createGroup!` | `int\|null` |
| Create right | `DACore:Rights@createRight!` | `int\|null` |
| Assign right to user | `DACore:Rights@assign!` | `bool` |
| Remove right from user | `DACore:Rights@remove!` | `bool` |
| Delete right(s) by creator | `DACore:Rights@deleteRight!` | `bool` |
| Delete group + its rights | `DACore:Rights@deleteGroup!` | `bool` |
| Render admin page | `DACore:Page@withMenu!` | `string` HTML |
| Render pagination | `DACore:Page@paginate!` | `string` HTML |
| Register / update AI tool | `DACore:AITools@register` | `bool` |
| Delete AI tool | `DACore:AITools@delete` (alias `@unregister`) | `bool` |
| Push inbox notification | `DACore:Notifications@push` | `bool` |
| Send mail via DACore senders | `DACore:Email@…!` ([38](38-DACORE-EMAIL.md)) | `true` / `string[]` / `{ok,…}` |
| Send SMS via DACore drivers | `DACore:Sms@…!` ([39](39-DACORE-SMS.md)) | `{ok, message_id, message, errors}` |
| Add AI system context | `DACore:AI@addSystemContext` | `bool` |
| Migration guard | `DACore:Installations@exist!` | `bool` |
| Record migration | `DACore:Installations@insert!` | `bool` |
| Error page body | `DotApp::call(Config::module("DACore","error403Page"))` | `string` |

Prefix reminder: `#` = Middleware namespace, `*` = Models namespace, trailing `!` = no DI.

---

## 3. Database tables owned by DACore

| Table | Purpose |
|-------|---------|
| `dacore_installations` | Migration audit log: `module`, `installation_id` (version), `installation_date`, `installation_user`, `status`, `status_txt` |
| `dacore_menu` | Sidebar tree: `menuid`, `parent`, `icon`, `name`, `url`, `urlprefix`, `rights`, `type`, `ordering` |
| `dacore_ai_tools` | AI tool registry: `toolid`, `creator`, `description`, `howtouse`, `controller`, `rights`, `helper`, `workflow`, `tool_type`, `risk_level`, `requires_confirmation`, `intent_tags`, `allowed_tools`, `forbidden_tools` |
| `dacore_chat` | AI chat sessions |
| `dacore_chat_messages` | AI chat messages |
| `dacore_modules` | ZIP-installed modules (DACore-owned — **never write**) |
| `dacore_plugin_logs` | Plugin installer audit (DACore-owned — **never write**) |
| `dacore_settings` | DACore-wide settings (DACore-owned — **never write**) |
| `dacore_notifications` | Inbox events (DACore-owned — **never write**; `Notifications@push` only) |
| `dacore_notifications_inbox` | Per-user read state (DACore-owned — **never write**) |
| `dacore_email_senders` | Operator SMTP accounts (DACore-owned — **never write**; `Email@send` only) |
| `dacore_email_templates` | Operator HTML templates (DACore-owned — **never write**) |
| `dacore_sms_senders` | SMS driver registry (DACore-owned — **never write**; `Sms@registerSender` / `Sms@send` only) |

Framework tables it uses (does not own): `{prefix}users`, `{prefix}users_rights`, `{prefix}users_rights_groups`, `{prefix}users_rights_list`.

**Note:** `dacore_ai_tools`, `dacore_chat` and `dacore_chat_messages` are **not** created by DACore's `1.0.0` installer. If you register AI tools, make sure the table exists (import from the DACore SQL dump) — see [36](36-DACORE-KNOWN-ISSUES.md).

---

## 4. Config keys (`Config::module("DACore", ...)`)

Read them; never assume. Defaults come from DACore's `module.init.php`.

### URLs and routing

| Key | Default |
|-----|---------|
| `prefixUrl` | `/dacore` |
| `defaultUrl` | `/` |
| `defaultPageAfterLogin` | `DACore:Login@index!` |
| `overtakeUrl` | `true` (DACore claims `/` when free) |
| `loginUrl` | `/login` |
| `loginUrl2fa` | `/login-2fa` |
| `loginUrl2faEmail` | `/login-2fa-email` |
| `logoutUrl` | `/logout` |
| `registerUrl` | `/register` |
| `allowRegistration` | `true` |
| `allowLogin` | `true` |
| `allowPasswordReset` | `true` |
| `passwordResetUrl` | `/forgot-password` |

**Always build HTML admin routes as `rtrim(Config::module("DACore","prefixUrl"), "/") . "/" . {ModuleName} . "/…"`** (e.g. `/dacore/Shop/items`) + `Gate@login`. **POST** `fo-rm` / `load()`: `/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!`. Canonical: [32](32-DACORE-RIGHTS.md), [03](03-MODULES-AND-ROUTING.md).

### Error pages

| Key | Default |
|-----|---------|
| `error403Page` | `DACore:ErrorPages@error403!` |
| `error404Page` | `DACore:ErrorPages@error404!` |
| `error500Page` | `DACore:ErrorPages@error500!` |

### Auth behaviour

| Key | Default |
|-----|---------|
| `autologin` | `true` |
| `permissionsAutorefresh` | `true` |
| `permissionsAutorefreshTime` | `300` (seconds) |
| `showTfaKeyAsText` | `false` |
| `QRColorBg` / `QRColorFront` | `#ffffff` / `#000000` |

### Localisation

| Key | Default |
|-----|---------|
| `miltilanguage` *(sic)* | `true` |
| `language` | `en_US` |
| `languages` | map of `en_US, sk_SK, uk_UA, cs_CZ, de_DE, ru_RU` |
| `languagebar` | `0` (0 auto, 1 links, 2 select) |

### AI

| Key | Default |
|-----|---------|
| `AI.enabled` | `false` |
| `AI.driver` | `AIDriverOpenAI` |
| `AI.language` | `sk_SK` |
| `AI.sex` | `female` |
| `AI.name` | `DAcore Bot` |
| `AI.start_message` | translated string |
| `AI.options` | `{temperature:1, api_key:"", model:"gpt-4o-mini", max_completion_tokens:10000}` |

Set the API key in `app/config.php`:

```php
Config::module('DACore', 'AI', array_replace(
    Config::module('DACore', 'AI') ?? [],
    ['enabled' => true, 'options' => ['api_key' => 'sk-...', 'model' => 'gpt-4o-mini']]
));
```

### Template / branding

`template` is an array with: `loginlogo`, `favicon`, `menulogo`, `menutitle`, `logintitle`, `logindescription`, `loginkeywords`, `loginsystemname`, `loginsubsystemname`, `error403video`, `error403img`, `error404video`, `error404img`, `error500video`, `error500img`.

### Other

| Key | Default |
|-----|---------|
| `useCache` | `Config::cache("use")` |
| `adminUser` / `adminpassword` | only set in the install stub |

---

## 5. Events

### DACore triggers (you can listen)

| Event | Arguments |
|-------|-----------|
| `DACore.login.before` | `$email, $password, $rememberMe` |
| `DACore.login.after` | `$login` (Auth result array) |
| `DACore.permissions.refresh` | none |
| `DACore.ai.chat.active` | none |
| `Dacore:Page@withMenu.rendering` | `$title, $body, $headerCode, $cssCode, $jsCode, $menuId` |
| `Dacore:Page@withMenu.rendered` | `$viewcode, $title, $body, $headerCode, $cssCode, $jsCode, $menuId` |

Note the lowercase `c` in `Dacore:Page@...` — event names are matched case-insensitively, but copy them exactly as written.

Reminder from [12](12-SERVICES.md): `trigger()` **ignores listener return values**, and a listener exception propagates.

### DACore listens

| Event | Effect |
|-------|--------|
| `dotapp.modules.loaded` | Redirects `/` to the admin when `overtakeUrl` is true and `/` is unclaimed |

---

## 6. Where to go next

| Task | Doc | Example |
|------|-----|---------|
| Menu items | [31](31-DACORE-MENU.md) | [EX-D01](examples/EX-D01-dacore-module-skeleton.md) |
| Permissions + route guards | [32](32-DACORE-RIGHTS.md) | EX-D01 |
| Admin pages, dotgrid, tables | [33](33-DACORE-PAGES-AND-UI.md) | [EX-D02](examples/EX-D02-dacore-admin-page.md) |
| AI tools | [34](34-DACORE-AI-TOOLS.md) | [EX-D03](examples/EX-D03-dacore-ai-tool.md) |
| Installer wiring | [35](35-DACORE-INSTALL.md) | [EX-D04](examples/EX-D04-dacore-installer.md) |
| Inbox notifications | [37](37-DACORE-NOTIFICATIONS.md) | [EX-D05](examples/EX-D05-dacore-notifications.md) |
| Outgoing mail (DACore senders) | [38](38-DACORE-EMAIL.md) | [EX-D06](examples/EX-D06-dacore-email.md) |
| Outgoing SMS (DACore drivers) | [39](39-DACORE-SMS.md) | [EX-D07](examples/EX-D07-dacore-sms.md) |
| DACore quirks | [36](36-DACORE-KNOWN-ISSUES.md) | — |
