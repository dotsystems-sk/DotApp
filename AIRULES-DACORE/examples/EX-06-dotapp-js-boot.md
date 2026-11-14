# EX-06 — DotApp.js page script: boot, request helper, forms, row actions

Law and reasons: [26](../26-CONTROL-SLICE.md). This file is the complete page script for one DACore admin screen. Copy the whole file, rename the `shop` prefix, then add one handler per control.

## Mandatory script

```html
<script src="/assets/dotapp/dotapp.js"></script>
```

This URL is served by the framework with **injected random keys**. Do not replace it with a raw file under `app/parts/js/`. The DACore shell already loads it, together with Notiflix and `window.dacoreRequestError`.

## Markup the script expects

```html
<div class="dacore-toolbar">
  <input type="search" id="shopSearch" class="form-control" placeholder="{{_ "Search rules" }}" />
</div>

<div class="card mb-6" id="shopRuleCard">
  <h5 class="card-header">{{_ "New rule" }}</h5>
  <fo-rm method="post" action="/api/v1/auth/Shop/rules/save" id="shopRuleForm">
    <div class="card-body">
      <label class="form-label" for="shopRuleTitle">{{_ "Title" }}</label>
      <input type="text" name="title" id="shopRuleTitle" class="form-control" />
      {{ formName(saveRule) }}
    </div>
    <div class="dacore-card-actions">
      <button type="submit" class="btn btn-primary">{{_ "Add rule" }}</button>
    </div>
  </fo-rm>
</div>

<div class="card mb-6" id="shopRulesWrap">
  <div id="shopRulesInner">
    <table class="table">
      <tbody>
        <tr>
          <td>{{ var: $rule.title }}</td>
          <td>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
              <button type="button" class="btn btn-sm btn-outline-secondary shop_rule_toggle" data-rule="{{ enc(Shop.rule.id): $rule.id }}">{{_ "Disable" }}</button>
              <button type="button" class="btn btn-sm btn-outline-danger shop_rule_delete" data-rule="{{ enc(Shop.rule.id): $rule.id }}">{{_ "Delete" }}</button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
    <!-- pager buttons: class shop_rule_page, data-page = encrypted page (AIRULES/40) -->
  </div>
</div>
```

- Buttons sit in an action row (`.dacore-card-actions`, `d-flex gap-2`), never bare. `card-footer` / `pb-4` have no CSS rule in the DACore shell.
- `#shopRulesWrap` is overlaid. `#shopRulesInner` is replaced. The `<fo-rm>` is **outside** the replaced fragment — a form that arrives later in `reply.html` is not taken over by `dotapp.js`.
- The class in the view (`shop_rule_delete`) and the selector in the script (`.shop_rule_delete`) are the same string. So is `data-rule`.

## The page script — `assets/js/admin-rules.js`

```javascript
(function () {
  function runMe($dotapp) {
    // ---------------------------------------------------------------
    // About: request helper — written once per module (AIRULES/26 §5).
    // Every request ends in exactly one toast and the overlay is always removed.
    // ---------------------------------------------------------------

    // Why: parseReply() gives back the raw string when the body is not base64 JSON.
    function shopReply(raw) {
      var reply = $dotapp().parseReply(raw);
      if (!reply || typeof reply !== "object") {
        return { status: 0, message: "The server sent an answer this page cannot read." };
      }
      return reply;
    }

    function shopDone(region, reply, onOk) {
      Notiflix.Block.remove(region);
      if (reply.status == 1) {
        if (reply.message) {
          Notiflix.Notify.success(reply.message);
        }
        if (typeof onOk === "function") {
          onOk(reply);
        }
        return;
      }
      Notiflix.Notify.failure(reply.message || "The action failed and the server gave no reason.");
    }

    // Why: HTTP 4xx/5xx and network faults never reach the success callback.
    function shopFail(region, status, body) {
      Notiflix.Block.remove(region);
      Notiflix.Notify.failure(window.dacoreRequestError.message(status, body, "The request did not reach the server"));
    }

    function shopSend(url, payload, region, onOk) {
      Notiflix.Block.standard(region, "Working…");
      $dotapp().load(url, "POST", payload,
        function (raw) {
          shopDone(region, shopReply(raw), onOk);
        },
        function (status, body) {
          shopFail(region, status, body);
        }
      ).catch(function () {
        // Why: load() also rejects its Promise on failure; shopFail already told the user.
      });
    }

    // Why: .after() does not run on HTTP 4xx/5xx — without .onError() the form stays blocked forever.
    function shopForm(selector, region, onOk) {
      $dotapp().form(selector)
        .before(function (data, form) {
          if ($dotapp(form).attr("blocked") == 1) {
            return $dotapp().halt();
          }
          $dotapp(form).attr("blocked", "1");
          Notiflix.Block.standard(region, "Saving…");
        })
        .after(function (data, raw, form) {
          $dotapp(form).attr("blocked", "0");
          shopDone(region, shopReply(raw), onOk);
        })
        .onError(function (data, status, body, form) {
          $dotapp(form).attr("blocked", "0");
          shopFail(region, status, body);
        });
    }

    // About: the one way this page redraws its list.
    function shopDrawRules(reply) {
      if (reply.html) {
        $dotapp("#shopRulesInner").html(reply.html);
      }
    }

    // ---------------------------------------------------------------
    // Section: Shop — rules
    // ---------------------------------------------------------------
    var shopQuery = "";
    var shopSearchTimer = null;

    // About: add a rule; stay on the page, redraw the list, clear the field.
    shopForm("#shopRuleForm", "#shopRuleCard", function (reply) {
      shopDrawRules(reply);
      $dotapp("#shopRuleTitle").val("");
    });

    // About: enable / disable one rule.
    $dotapp().live("click", ".shop_rule_toggle", function (el, e) {
      shopSend("/api/v1/auth/Shop/rules/toggle", { rule: $dotapp(el).attr("data-rule") }, "#shopRulesWrap", shopDrawRules);
    });

    // About: delete one rule. Why: a delete always asks first in a graphical dialog.
    $dotapp().live("click", ".shop_rule_delete", function (el, e) {
      var rule = $dotapp(el).attr("data-rule");
      Notiflix.Confirm.show("Delete this rule?", "This cannot be undone.", "Delete", "Cancel", function () {
        shopSend("/api/v1/auth/Shop/rules/delete", { rule: rule }, "#shopRulesWrap", shopDrawRules);
      });
    });

    // About: pager. data-page is ciphertext (AIRULES/40). The search text travels with every page.
    $dotapp().live("click", ".shop_rule_page", function (el, e) {
      var page = $dotapp(el).attr("data-page");
      if (!page) {
        return;
      }
      shopSend("/api/v1/auth/Shop/rules/list", { page: page, q: shopQuery }, "#shopRulesWrap", shopDrawRules);
    });

    // About: search. Why: 300 ms debounce and a 3-character minimum keep the server quiet.
    $dotapp().live("input", "#shopSearch", function (el, e) {
      var typed = ($dotapp(el).val() || "").trim();
      if (shopSearchTimer) {
        clearTimeout(shopSearchTimer);
      }
      shopSearchTimer = setTimeout(function () {
        shopQuery = typed.length >= 3 ? typed : "";
        // Why: a new search starts at page 1; the plain number 1 is the only non-encrypted page the action accepts (AIRULES/40).
        shopSend("/api/v1/auth/Shop/rules/list", { page: 1, q: shopQuery }, "#shopRulesWrap", shopDrawRules);
      }, 300);
    });
  }

  if (window.$dotapp) {
    runMe(window.$dotapp);
  } else {
    window.addEventListener(
      "dotapp",
      function () {
        runMe(window.$dotapp);
      },
      { once: true }
    );
  }
})();
```

A library that registers `$dotapp().fn(...)` uses the same last block with **`"dotapp-register"`** instead of `"dotapp"` — [EX-15](EX-15-dotapp-js-library.md), [09](../09-DOTAPP-JS-AND-BRIDGE.md) §4. Never edit `app/parts/js/`.

## PHP side of each URL

Each URL above has a route in `module.init.php` and an action that ends every branch in `Reply::ok()` / `Reply::fail()` — skeleton in [26](../26-CONTROL-SLICE.md) §6–§7.

```php
return Reply::ok('Rule disabled.', ['html' => $rowsAndPagerHtml]);
return Reply::fail('The rule was not deleted: 2 schedules still use it.');
```

Leaving the page (login, wizard step): `Reply::ok('', ['redirectTo' => '/dacore/Shop/rules'])` and `window.location = reply.redirectTo;` inside `onOk`. Never `location.reload()`.

## Never

- A boot block in any other form: one-line `if … else`, `DOMContentLoaded`, `document.addEventListener("dotapp")`, polling
- `document.getElementById`, `querySelector`, `getAttribute`, `innerHTML`, `classList`, `el.addEventListener` in page code
- `$dotapp().load()` / `.form()` called directly in a handler; `.form()` without `.onError()`; `load()` without the error callback
- `$('#x')`, `jQuery`, `$.ajax`, raw `fetch` to a DotApp endpoint
- `location.reload()` after `fo-rm` or `load` while staying on the page
- `alert()` / `window.confirm()` / `prompt()`
- One `<fo-rm>` per table-row action, or drag-and-drop through forms
- A growing list with no pager, or a pager that reloads through `<a href="?page=">`
