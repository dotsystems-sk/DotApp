# EX-06 — DotApp.js boot, loaders, form hooks

## Mandatory script

```html
<script src="/assets/dotapp/dotapp.js"></script>
```

This URL is served by the framework with **injected random keys**. Do not replace it with a raw file under `app/parts/js/` on production pages.

## Boot

```javascript
(function () {
  var runMe = function ($dotapp) {
    // page logic
  };
  if (window.$dotapp) runMe(window.$dotapp);
  else window.addEventListener("dotapp", function () {
    runMe(window.$dotapp);
  }, { once: true });
})();
```

Plugins (`$dotapp().fn(...)`) register on **`dotapp-register`**, not `dotapp`.

## Submit button loader (common UX)

```javascript
$dotapp("#saveBtn").attr("loading", "true").attr("loader", "dots");
// on done:
$dotapp("#saveBtn").removeAttr("loading").removeAttr("loader");
```

## Double-submit guard

```javascript
.before(function (data, form) {
  if ($dotapp(form).attr("blocked") == 1) return $dotapp().halt();
  $dotapp(form).attr("blocked", "1");
})
.after(function (data, response, form) {
  $dotapp(form).attr("blocked", "0");
});
```

## Never

- `$('#x')`, `jQuery`, `$.ajax`
- Assume `$dotapp('#x').val()` returns a chainable object (often returns the value)
- Call secure endpoints with raw `fetch` without CRC/CSRF
