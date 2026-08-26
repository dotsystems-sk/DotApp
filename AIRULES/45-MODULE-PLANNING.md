# 45 — Module planning depth (**MUST** — law)

When the user asks to **plan** (or “write a plan for”) a **new module**, a **first version** of a major operator surface, or a **rewrite** that throws the old product away, the plan **MUST** be extremely detailed — something a **product designer** and a **senior application-security engineer** would both accept **and** a builder could implement without inventing screens.

A bullet list of endpoints is **not** a plan. A plan that is **very long** is correct. Skipping a page, tab, or control because “the plan would be too long” is a **failed plan**.

This folder is **framework-only**. There is no DACore sidebar registration. If the module has **any** menu or nav, the plan still **MUST** list every item — just as **your** routes and markup, not `DACore:Menu@register`.

**Pack for a named host:** the plan **MUST** read `app/modules/<Host>/AIRULES/` first and list only routes that host listens to ([00](00-AGENT-CONTRACT.md) §2n). A new **host** plan **MUST** include creating that folder.

Canonical pointers: [00](00-AGENT-CONTRACT.md) [§2k](00-AGENT-CONTRACT.md#2k-module-planning-depth-must), [05](05-VIEWS-TEMPLATES-ASSETS.md) §8d, [17](17-CHECKLISTS.md) Pre-flight.

---

## 1. When this law applies (**MUST**)

Apply this file when **any** of the following is true:

- a **new module**
- a **first version** of a major surface users will live in (public site, checkout, editor, dashboard, settings workspace, member area)
- a **rewrite** that discards the old product and starts again

**Short plans are allowed only** for a small change to an **already shipped** screen (copy, one field, a localized bug). If the change adds a new primary workspace, treat it as a first surface.

---

## 2. Bar (**MUST**)

Ship **competition-grade** UX and security. “It posts” / “good enough” is a **failed plan**.

A working control that looks unfinished is still a bug ([00](00-AGENT-CONTRACT.md) §2f, [05](05-VIEWS-TEMPLATES-ASSETS.md) §8c).

Do **not** defer “we will make it look nice while coding” or “we will list the fields in the diff.” The agent **acts as the UX/UI designer** in the plan. **MUST** write every screen, tab, and control **in the plan**.

---

## 3. Menu / nav inventory (**MUST** if there is any menu)

If the module will have **any** navigation (public header, mobile drawer, logged-in area links, settings section links), the plan **MUST** list **every** item:

- visible label (product copy)
- URL / route
- parent (header vs drawer vs in-page section)
- who sees it (public / logged-in / right)

**MUST NOT** invent DACore `Menu@register` / `dacore_menu` rows here. This folder has no DACore shell.

**If there is no menu at all** (backend-only / library): write **`No menu`** explicitly. Do not leave it implied.

---

## 4. Screen inventory (**MUST** if there are pages)

Walk **every** HTML page the module will ship. For **each** page write:

- URL / route / controller
- which nav item it belongs to (or `No menu`)
- who may open it (login / `Auth::can` / public)
- desktop **and** mobile layout (regions, widths, scroll, what collapses)
- empty / loading / error / forbidden / unconfigured states
- toolbar, primary vs secondary actions, confirmations, toasts
- spacing vs the parent (especially padding **below** Save)

Then walk **inside** the page. Tabs, cards, side panels, and drawers are **not optional footnotes**.

**Example shape (this is the required density — not optional flavour):**

> **Nav section Settings** (logged-in drawer → Settings)
>
> **Page Settings** (`/Shop/settings`)
>
> **Tab 1: Interface**
> - Show XYZ — checkbox; default on; persist `shop_settings.show_xyz`; hides/shows the XYZ panel on the dashboard
> - Hide side panel — checkbox; default off; persist `shop_settings.hide_side_panel`
>
> **Tab 2: Frontend**
> - Public theme — `<select>` of known themes (not a text guess); persist `shop_settings.public_theme`
> - Show search in the drawer — checkbox; default on; persist `shop_settings.drawer_search`

**MUST** do the same for lists (columns, search, filters, sort, pager, row actions), forms (every field, validation, save/fail), and wizards (every step). **MUST** say what each control **does**, its default, and where it persists.

**MUST NOT:** “Settings page with some options”; “tabs for interface and frontend”; “the rest we invent while coding.”

---

## 5. UI / UX section (**MUST** if the module has pages)

In addition to the screen inventory, the plan **MUST** specify:

- Hierarchy (tree vs list vs grid), selection model
- Icon system; what you ship in **this** module’s assets (Notiflix does not exist here)
- Interaction model: click vs double-click, drag-drop, keyboard where relevant
- Public mobile drawer if the module has a public site ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3)

---

## 6. Security section (**MUST**)

Name the real controls using existing law — do **not** write “we will be careful.”

| Control | Law |
|---------|-----|
| Auth, `Gate@login`, CRC **once** XOR upload skip | [08](08-FORMS-AND-SECURITY.md), [03](03-MODULES-AND-ROUTING.md) |
| Encrypted browser IDs | [11](11-AUTH-AND-CRYPTO.md) §8 |
| Bound SQL | [06](06-DATABASE.md) |
| Catch bus | [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9 |
| Uploads / path jail / MIME / execution disable | [24](24-ATTACK-VECTORS.md) §6 |
| Visible success **and** fail | [00](00-AGENT-CONTRACT.md) §2d |
| HTML via Renderer | [00](00-AGENT-CONTRACT.md) §2j, [05](05-VIEWS-TEMPLATES-ASSETS.md) §1c |
| Threat pass | [24](24-ATTACK-VECTORS.md) §11 |

Operator 2FA lock and step-up are **DACore-only**. Do not invent that flow in a framework-only app ([00](00-AGENT-CONTRACT.md) §7).

---

## 7. Architecture (**MUST**)

State, in writing:

- Module name
- Installer version keys as **quoted text** (`'1.0.0' =>`)
- Tables `{lowercase_modulename}_*`
- Nav inventory from §3, or **`No menu`**
- What is **out of scope**
- What other modules **MUST NOT** be patched (kernel is frozen)

---

## 8. Forbidden planning

- Planning a growing list without a pager ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3)
- “Clone {reference app} including its security holes” without mapping each hole to a DotApp control
- Inventing DACore APIs (`Page@withMenu!`, `Menu@register`, Notiflix) in this folder
- A short endpoint list, or “Settings + list + edit”
- Omitting a tab or control to keep the plan short
- “We will decide the fields while coding”

---

## 9. Finish

A plan that omits the **menu/nav inventory** (when there is nav), the **screen inventory** (when there are pages), the UI section, or the security section **MUST NOT** be treated as approved. Implement only after the user accepts that plan.
