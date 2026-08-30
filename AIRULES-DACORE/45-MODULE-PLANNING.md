# 45 — Module planning depth (**MUST** — law)

When the user asks to **plan** (or “write a plan for”) a **new module**, a **first version** of a major operator surface, or a **rewrite** that throws the old product away, the plan **MUST** be extremely detailed — something a **product designer** and a **senior application-security engineer** would both accept **and** a builder could implement without inventing screens.

A bullet list of endpoints is **not** a plan. A plan that is **very long** is correct. Skipping a page, tab, or control because “the plan would be too long” is a **failed plan**.

Canonical pointers: [00](00-AGENT-CONTRACT.md) §2 item 1, [§2k](00-AGENT-CONTRACT.md#2k-module-planning-depth-must), [05](05-VIEWS-TEMPLATES-ASSETS.md) §8d, [17](17-CHECKLISTS.md) Pre-flight, [31](31-DACORE-MENU.md), [33](33-DACORE-PAGES-AND-UI.md).

**Pack for a named host:** the plan **MUST** follow that host’s `AIRULES/` ([00](00-AGENT-CONTRACT.md) §2n). **MUST NOT** open `app/modules/<Host>/PLAN/` — that is the host’s own roadmap ([00](00-AGENT-CONTRACT.md) §2p). Host-specific pack workflows belong in the host’s own handbook and travel with that host. A new **host** plan **MUST** include creating **both** `AIRULES/` (for future packs) and `PLAN/` (for host development).

**PLAN folder (MUST — law):** the inventory below **MUST** live in **`app/modules/<ThisModule>/PLAN/`**, not only in chat. Split **laws** and **rules**, include **positions**, optional images in `PLAN/assets/`. Then implement from that folder. Canonical: [00](00-AGENT-CONTRACT.md) [§2o](00-AGENT-CONTRACT.md#2o-module-plan-folder-must--law).

---

## 0. PLAN folder (**MUST**)

After `dotapper --create-module`, **MUST** create:

```
app/modules/<ThisModule>/PLAN/
  README.md          — index: how to continue this module
  00-laws.md         — module-local MUST / MUST NOT (cannot weaken project AIRULES)
  01-rules.md        — conventions, naming, file map
  02-menu.md         — every Menu@register row or No menu
  03-screens.md      — every page / tab / control (what it does, default, persist)
  04-positions.md    — desktop + mobile regions, padding, where each control sits
  assets/            — optional rasters / mockups
```

Filenames may vary; the **split** (index + laws + rules + inventories + positions) **MUST** exist. English.

**MUST** read **this** module’s `PLAN/` before further coding when it exists. Stack: project AIRULES (wins) → host `AIRULES/` if this is a pack → **this** `PLAN/` ([00](00-AGENT-CONTRACT.md) §2p).

**MUST NOT** skip `PLAN/` because a chat plan exists. **MUST NOT** dump the plan into one file. **MUST NOT** invent a screen that is not in PLAN. **MUST NOT** read a host’s `PLAN/` when writing a pack.

---

## 1. When this law applies (**MUST**)

Apply this file when **any** of the following is true:

- a **new module**
- a **first version** of a major surface operators will live in (file manager, public site, checkout, editor, dashboard, settings workspace)
- a **rewrite** that discards the old product and starts again

**Short plans are allowed only** for a small change to an **already shipped** screen (copy, one field, a localized bug). If the change adds a new primary workspace, treat it as a first surface.

---

## 2. Bar (**MUST**)

Ship **competition-grade** UX and security. “It posts” / “good enough for an admin” is a **failed plan**.

A working control that looks unfinished is still a bug ([00](00-AGENT-CONTRACT.md) §2f, [05](05-VIEWS-TEMPLATES-ASSETS.md) §8c).

Do **not** defer “we will make it look nice while coding” or “we will list the fields in the diff.” The agent **acts as the UX/UI designer** in the plan. **MUST** write every screen, tab, and control **in the plan**.

---

## 3. Menu inventory (**MUST** if there is any admin/nav)

**DACore-bound modules:** the plan **MUST** list **every** sidebar row `Installation.php` will `DACore:Menu@register`. For each row write:

- `menuid` (stable, your prefix)
- `type` (`0` header / `2` branch / `1` leaf)
- `parent`
- visible `name` (product copy)
- `url` + `urlprefix`
- Remix `icon`
- `rights`
- `ordering`

**MUST NOT** register a leaf per edit/detail URL — one list leaf; subpages keep it active via `withMenu` `$currentFile` ([31](31-DACORE-MENU.md)).

**If the module also has public / member nav** (drawer, header links): list those items too (label, URL, parent, who sees them). That nav is **your** markup — **MUST NOT** invent a `dacore_menu` write for it.

**If there is no menu at all:** write **`No menu`** explicitly. Do not leave it implied.

---

## 4. Screen inventory (**MUST** if there are pages)

Walk **every** HTML page the module will ship. For **each** page write:

- URL / route / controller
- which menu leaf it belongs to (or public nav item)
- who may open it (right / origin / login)
- desktop **and** mobile layout (regions, widths, scroll, what collapses)
- empty / loading / error / forbidden / unconfigured states
- toolbar, primary vs secondary actions, confirmations, toasts
- spacing vs the parent on **all sides**; when Save is last in the block, **almost always** pad **below**

Then walk **inside** the page. Tabs, cards, side panels, and drawers are **not optional footnotes**.

**Example shape (this is the required density — not optional flavour):**

> **Menu section Settings** (`Shop.settings`, type `2`, parent `Shop.main`)
>
> **Leaf Settings** (`Shop.settings.page`, type `1`, url `/Shop/settings`)
>
> **Page Settings — Tab 1: Interface**
> - Show XYZ — checkbox; default on; persist `shop_settings.show_xyz`; hides/shows the XYZ panel on the dashboard
> - Hide side panel — checkbox; default off; persist `shop_settings.hide_side_panel`
>
> **Page Settings — Tab 2: Frontend**
> - Public theme — `<select>` of known themes (not a text guess); persist `shop_settings.public_theme`
> - Show search in the drawer — checkbox; default on; persist `shop_settings.drawer_search`

**MUST** do the same for lists (columns, search, filters, sort, pager, row actions), forms (every field, validation, save/fail), and wizards (every step). **MUST** say what each control **does**, its default, and where it persists.

**Admin composition (MUST):** each form/settings/editor page **MUST** name its **numbered sections** (heading + one-sentence lede), whether a **Why-this-matters** panel is on, and which **GET workspace** (tab/leaf) owns it. A plan that says “Settings page with these fields” and draws **one card** is a **failed plan**. Density: [33](33-DACORE-PAGES-AND-UI.md) §6b, [00](00-AGENT-CONTRACT.md) §2f.

**MUST NOT:** “Settings page with some options”; “tabs for interface and frontend”; “the rest we invent while coding”; one inventory list of twenty inputs with no section boundaries.

---

## 5. UI / UX section (**MUST** if the module has pages)

In addition to the screen inventory, the plan **MUST** specify:

- Hierarchy (tree vs list vs grid), selection model
- Icon system (Remix vs thumbs); what DACore CSS/JS is **reused** after a read-only grep
- Interaction model: click vs double-click, drag-drop, keyboard where relevant
- **Section chrome** in **this** module: numbered headers, ledes, Why-this-matters panels, GET workspaces — **MUST NOT** copy a sibling’s CSS ([33](33-DACORE-PAGES-AND-UI.md) §6b)

---

## 6. Security section (**MUST**)

Name the real controls using existing law — do **not** write “we will be careful.” The agent **acts as the senior developer** in the plan: jail, tokens, CRC, rights, and fail-closed defaults must be explicit.

| Control | Law |
|---------|-----|
| Auth, rights (`#Module:Rights@check!`), CRC **once** XOR upload skip | [08](08-FORMS-AND-SECURITY.md), [03](03-MODULES-AND-ROUTING.md) |
| Encrypted browser IDs | [11](11-AUTH-AND-CRYPTO.md) §8 |
| Bound SQL | [06](06-DATABASE.md) |
| Catch bus | [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9 |
| Uploads / path jail / MIME / execution disable | [24](24-ATTACK-VECTORS.md) §6 |
| Second 2FA prompt after login | **ASK** once (default **no**). If the user names actions: [32](32-DACORE-RIGHTS.md) §6 chrome + [EX-D10](examples/EX-D10-stepup-2fa-modal.md). **MUST NOT** invent it on every settings Save |
| Visible success **and** fail | [00](00-AGENT-CONTRACT.md) §2d |
| HTML via Renderer | [00](00-AGENT-CONTRACT.md) §2j, [05](05-VIEWS-TEMPLATES-ASSETS.md) §1c |
| Threat pass | [24](24-ATTACK-VECTORS.md) §11 |

---

## 7. Architecture (**MUST**)

State, in writing:

- Module name
- Installer version keys as **quoted text** (`'1.0.0' =>`)
- Tables `{lowercase_modulename}_*`
- Menu `0` → `2` → `1` (or module-own **only** if the user chose it) — **and** the inventory in §3
- What is **out of scope**
- What other modules **MUST NOT** be patched (especially DACore)

---

## 8. Forbidden planning

- Guessing module-own menu; dumping ten leaves under a header
- Inventing DACore edits
- Planning a growing list without a pager ([40](40-DACORE-LIST-PAGER.md)) — filesystem listings: bounded page + encrypted page token
- “Clone {reference app} including its security holes” without mapping each hole to a DotApp control
- Browsing a sibling under `app/modules/` for a look to copy. Plan UI from DACore chrome + this module + `AIRULES/examples/`. A sibling is in the plan **only** if the user named it as the module this work extends ([00](00-AGENT-CONTRACT.md) §1b)
- A short endpoint list, or “Settings + list + edit”
- Omitting a tab or control to keep the plan short
- “We will decide the fields while coding”
- Inventing a second 2FA prompt on every settings Save without asking. **ASK** once; no answer → none. When the user says yes, the chrome is [EX-D10](examples/EX-D10-stepup-2fa-modal.md) — not a 6-digit field on the card
- Chat-only plan / one mega-file / no `app/modules/<This>/PLAN/` on a new module or rewrite ([00](00-AGENT-CONTRACT.md) §2o)
- Opening the host’s `PLAN/` while planning a pack for that host ([00](00-AGENT-CONTRACT.md) §2p)

---

## 9. Finish

A plan that omits the **PLAN folder**, the **menu inventory** (when there is nav), the **screen inventory** (when there are pages), the UI section, or the security section **MUST NOT** be treated as approved. Implement only after the user accepts that plan **in `PLAN/`**.
