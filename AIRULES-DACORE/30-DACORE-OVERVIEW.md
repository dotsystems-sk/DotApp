# 30 — DACore Overview (admin UI layer)

**DACore is a module, not part of the framework core.** It provides a ready-made administration shell: login + 2FA, permissions, a DB-driven sidebar menu, a page shell, error pages, optional AI chat with tools, and install tracking.

Your module **plugs into** DACore through public `DotApp::call()` APIs. It never edits DACore files, and it never adds files into DACore.

Framework rules (docs `00`–`22`) still apply in full. This DACore layer (`30`–`36`) is additive.

---

## 1. Absolute rules for a DACore plug-in module

Treat `app/modules/DACore/` like `app/parts/`. DACore is shipped and updated as a complete package. **Any edit, patch, or extra file inside it is destroyed on the next DACore update.** You may only consume the APIs it already exposes. All new features, pages, controllers, views, and assets go in **your own** module (`app/modules/<YourModule>/`). If asked to change DACore in place: refuse and implement it in your module.

| Rule | Why |
|------|-----|
| **Never edit any file in `app/modules/DACore/`** | DACore updates overwrite the whole module — local patches disappear |
| **Never add files, controllers, views, JS, CSS, or SQL into DACore** | Same reason: the next update wipes them. Extend via your own module only |
| **Never INSERT/UPDATE/DELETE `dacore_menu`, `dacore_ai_tools`, `dacore_installations` directly** | Use the registration APIs — they handle upsert, column compatibility and cache invalidation |
| **Never write into `{prefix}users_rights*` directly** | Use `DACore:Rights@*` |
| **Never duplicate the admin HTML shell** | Use `DACore:Page@withMenu!` |
| **Never use `$_SESSION`** | Use `DSM` ([20](20-CACHE-LOGGER-SESSION.md)) |
| **Never rely on `#DACore:AuthTest@check!` for permission checks** | It ignores the rights you pass — see [36](36-DACORE-KNOWN-ISSUES.md); create your own `Middleware/Rights.php` |
| Register menu/rights/tools **from `Installation.php`**, not on every request | Otherwise you write to the DB on each page load |
| **Search DACore first** before a new library or page chrome | The base already has many subpages and widgets — grep read-only, then reuse ([33](33-DACORE-PAGES-AND-UI.md)) |
| **Operator 2FA stays on**; dangerous actions re-prompt 2FA in **your** module | [32](32-DACORE-RIGHTS.md) §6 — never `Auth::confirmTwoFactor` while already logged in |

Editable paths are unchanged: `app/config.php` and `app/modules/<YourModule>/` only ([00](00-AGENT-CONTRACT.md)).

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

**Always build your admin routes as `Config::module("DACore","prefixUrl") . "/your-path"`.**

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
| DACore quirks | [36](36-DACORE-KNOWN-ISSUES.md) | — |
