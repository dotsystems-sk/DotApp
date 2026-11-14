# EX-15 — Custom `$dotapp` library (new + jQuery port)

Rules: [09-DOTAPP-JS-AND-BRIDGE.md](../09-DOTAPP-JS-AND-BRIDGE.md) §4 (playbook = **§4.C**). Human docs (read-only): `app/parts/js/dotapp-docs.html`. Official patterns: `app/parts/js/dotapp.template.js`, `dotapp.reactive.js`.

**Porting a jQuery plugin is writing a new `$dotapp` library.** Do not wrap `$.fn.foo`. Rewrite in vanilla DOM, then `$dotapp().fn(...)`.

**Never** add files under `app/parts/js/` or `app/modules/DACore/`. Put the library in **your** module assets.

If DACore already ships the widget (`dotSelect2`, `dotDataTable`, `modal`, `toast`, `daterangepicker`), **call that** — do not fork those files. Read them **read-only** for shape.

---

## A. New instance library (HTTP via `this.load`)

`app/modules/Shop/assets/js/shop_chart.js` → `/assets/modules/Shop/js/shop_chart.js`

```javascript
(function () {
    var isRegistered = false;

    var runMe = function ($dotapp) {
        if (isRegistered) { return; }
        isRegistered = true;

        function ShopChart(dotApp, settings) {
            this.dotApp = dotApp;
            this.settings = settings || {};
        }

        ShopChart.prototype.refresh = function () {
            var self = this;
            this.dotApp.load(this.settings.url || '/shop/chart', 'GET', {},
                function (raw) {
                    var reply = self.dotApp.parseReply(raw);
                    if (!reply || reply.status != 1) { return; }
                },
                function (code) { /* 400/403/404/429 */ }
            );
        };

        $dotapp().fn('shopChart', function (settings) {
            var key = '_shopChart';
            var els = this.getElements();
            if (els.length !== 1) {
                throw new Error('shopChart requires exactly one element');
            }
            var el = els[0];
            if (!el[key]) {
                el[key] = new ShopChart(this, settings);
            }
            return el[key];
        });

        window.dispatchEvent(new Event('dotapp-shopchart-ready'));
    };

    if (window.$dotapp) runMe(window.$dotapp);
    else window.addEventListener('dotapp-register', function () { runMe(window.$dotapp); }, { once: true });
})();
```

Page script listens to **`dotapp`**, not `dotapp-register`:

```javascript
(function () {
    var runMe = function ($dotapp) {
        var chart = $dotapp('#sales').shopChart({ url: '/shop/chart' });
        chart.refresh();
    };
    if (window.$dotapp) runMe(window.$dotapp);
    else window.addEventListener('dotapp', function () { runMe(window.$dotapp); }, { once: true });
})();
```

---

## B. Ported jQuery widget (Select2 / DataTable shape)

Old: `$('#city').picker({ search: true })` + class `.picker` on the element.  
New: `$dotapp('#city').shopPicker({ search: true })`. Keep the CSS class so existing HTML still matches.

`app/modules/Shop/assets/js/shop_picker.js`

```javascript
(function () {
    "use strict";

    var EVENT_READY = "dotapp-shoppicker-ready";
    var instances = new WeakMap();
    var isRegistered = false;

    function domReady(fn) {
        if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", fn);
        else fn();
    }

    function ShopPicker(el, opts) {
        this.el = el;
        this.opts = opts || {};
        this.open = false;
        el.classList.add("shop_picker");
    }

    ShopPicker.prototype.show = function () { this.open = true; return this; };
    ShopPicker.prototype.hide = function () { this.open = false; return this; };
    ShopPicker.prototype.destroy = function () {
        instances.delete(this.el);
    };

    function bindOutsideClick() {
        if (window.__shopPickerDocBound) return;
        window.__shopPickerDocBound = true;
        document.addEventListener("click", function (e) {
            document.querySelectorAll(".picker").forEach(function (el) {
                var api = instances.get(el);
                if (api && !el.contains(e.target)) api.hide();
            });
        });
    }

    function mount(el, options) {
        if (!el || !el.classList.contains("picker")) return null;
        var prev = instances.get(el);
        if (prev) prev.destroy();
        var api = new ShopPicker(el, options || {});
        instances.set(el, api);
        return api;
    }

    function autoInit() {
        document.querySelectorAll(".picker").forEach(function (el) {
            if (el.getAttribute("data-shop-picker-skip") !== null) return;
            if (instances.get(el)) return;
            mount(el, null);
        });
    }

    function runMe($dotapp) {
        if (isRegistered) return;
        isRegistered = true;
        try {
            $dotapp().fn("shopPicker", function (options) {
                var out = [];
                this.getElements().forEach(function (el) {
                    var api = mount(el, options);
                    if (api) out.push(api);
                });
                return out.length === 1 ? out[0] : this;
            });
        } catch (err) {
            if (!err || !err.message || err.message.indexOf("already registered") === -1) throw err;
        }
        bindOutsideClick();
        domReady(function () {
            autoInit();
            window.dispatchEvent(new Event(EVENT_READY));
        });
    }

    window.ShopPicker = {
        mount: mount,
        get: function (el) { return instances.get(el) || null; }
    };

    if (window.$dotapp) runMe(window.$dotapp);
    else window.addEventListener("dotapp-register", function () { runMe(window.$dotapp); }, { once: true });
})();
```

Usage after `dotapp`: `var p = $dotapp('#city').shopPicker({ search: true }); p.show();`  
Or: `window.ShopPicker.get(document.getElementById('city'))`.

Markup hook (optional): `<select class="picker">` auto-inits. Skip with `data-shop-picker-skip`.

If the original plugin loaded options over AJAX, use `this.load` + `parseReply` inside the constructor (see **A**), not `$.ajax`.

---

## C. Other port shapes (short)

**Markup already in the page** (toast / modal): `fn` uses `this.get(0)`, then `getOrCreate` on that node. Bind **one** document listener for `[data-bs-dismiss="…"]` via `e.target.closest`.

```javascript
$dotapp().fn("shopToast", function (options) {
    var el = this.get(0);
    if (!el || !el.classList.contains("toast")) return this;
    return ShopToast.getOrCreate(el, options || {});
});
```

**Factory on empty `$dotapp()`** (dynamic dialog / notify manager): if `this.get(0)` is missing and `arg1` looks like settings, open a new dialog.

```javascript
$dotapp().fn("shopDialog", function (arg1, arg2) {
    var el = this.get(0);
    if (arg1 && typeof arg1 === "object" && !(el && el.classList.contains("shop_dialog"))) {
        return ShopDialog.open(arg1, arg2 || {});
    }
    if (!el) return this;
    return ShopDialog.getOrCreate(el);
});
```

**Chainable helper:** `$dotapp().fn('shopHighlight', function (color) { …; return this; })` then `$dotapp('#box').shopHighlight('red').addClass('on')`.

---

## Load order

1. `/assets/dotapp/dotapp.js`
2. Optional `/assets/dotapp/dotapp.template.js` / `dotapp.reactive.js`
3. `/assets/modules/Shop/js/shop_picker.js` (or `shop_chart.js`)
4. Page JS

On DACore admin, pass `[libUrl, pageUrl]` as `$js` to `Page@withMenu!` (shell already has `dotapp.js`).

---

## Never

- Edit `app/parts/js/` or `app/modules/DACore/`
- Wrap `$(el).oldPlugin()` inside `fn()` and call it a port
- Register on the `dotapp` event (too late / wrong job)
- Skip `isRegistered` **and** the `already registered` catch (`fn()` throws on a second call)
- `$.ajax` / `fetch` to DotApp endpoints from the library — use `this.load` / `this.form`
- Re-implement a widget DACore already exposes; call `$dotapp('#x').dotSelect2()` (etc.) instead
