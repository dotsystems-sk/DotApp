# EX-17 — Extender (opt-in method replacement)

Canonical API: [12-SERVICES.md](../12-SERVICES.md) §10. Controllers: [04](../04-CONTROLLERS-AND-RESPONSES.md). Boot / `initializeRoutes()`: [03](../03-MODULES-AND-ROUTING.md). Writing law: [00](../00-AGENT-CONTRACT.md) §2h.

`Extender` is **not** Events, `module.{mod}.{name}.hook`, or `triggerWithVeto()`. No `.hooks` row. One replacement **replaces** the target method; listener buses do not.

**Judge first — not every method.** Offer Extender when another module would reasonably **swap this output**: **page/block HTML**, a **cart** drawn differently, an **export** built differently, a checkout quote presentation. Skip ordinary persist, CRC, decrypt, pager internals. When you do opt in, the owner **listens** (`exists()` + immediate `return call()`). **MUST NOT** patch DACore to insert `Extender::call`.

- **Opt-in only** — the owner checks `exists()` and immediately returns `call()`.
- **Request-local** static registry — register again on every request in `initialize()`.
- **One handler** — a duplicate `extend()` throws `\LogicException`.
- **Full replacement** — no original / next.
- **Explicit safe context** — never `$request`, secrets, tokens, CRC, rights, or request bodies.
- **`exists()`** is canonical; `exist()` is the same alias.

---

## 1. Target method (Shop owns the extension point)

Shop judges that `Checkout::quote` is a high-value swap (another module may own the quote). It passes only a cart id and a decimal subtotal.

```php
namespace Dotsystems\App\Modules\Shop\Controllers;

use Dotsystems\App\Parts\Controller;
use Dotsystems\App\Parts\Extender;

class Checkout extends Controller
{
    /**
     * CRCchecking — none
     * Returns the checkout quote for this cart. Opt-in Extender target.
     *
     * @param int $cartId Cart id already loaded and authorized by the caller.
     * @param string $subtotal Decimal subtotal already computed by Shop.
     * @return array{cart_id: int, subtotal: string, total: string}
     */
    public static function quote(int $cartId, string $subtotal): array
    {
        // Why: one replacement may own the quote; Extender has no original/next.
        if (Extender::exists(self::class, 'quote')) {
            return Extender::call(self::class, 'quote', $cartId, $subtotal);
        }

        return [
            'cart_id' => $cartId,
            'subtotal' => $subtotal,
            'total' => $subtotal,
        ];
    }
}
```

The HTTP action still does CRC (prefix **or** `crcCheck()`, never both), decrypts ids, and checks `Auth::can`. It then calls `Checkout::quote($cartId, $subtotal)` — it does **not** pass `$request` into `Extender::call`. Handler exceptions **propagate**; the action’s `catch` still reports the catch bus ([18](../18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9).

**MUST NOT** wrap `call()`, merge with the original, or call `quote()` again from the replacement (recursion throws `\LogicException`).

---

## 2. Extending module (listener phase — before Module initialization)

Loyalty registers the replacement in `module.listeners.php`. Matching listeners run **before any matching Module initializes**, so this also works when Shop calls the extension point during its own `initialize()`.

```php
namespace Dotsystems\App\Modules\Loyalty;

use Dotsystems\App\Parts\Extender;

class Listeners extends \Dotsystems\App\Parts\Listeners
{
    public function register($dotApp)
    {
        // Why: listener registration precedes every matching Module::initialize().
        Extender::extend(
            \Dotsystems\App\Modules\Shop\Controllers\Checkout::class,
            'quote',
            'Loyalty:Pricing@quote!'
        );
    }

    public function initializeRoutes()
    {
        // Why: only requests that can reach Shop Checkout::quote need this replacement.
        return [
            '/Shop', '/Shop/*',
            '/api/v1/auth/Shop', '/api/v1/auth/Shop/*',
            '/api/v1/noauth/Shop', '/api/v1/noauth/Shop/*',
        ];
    }
}

new Listeners($dotApp);
```

Loyalty’s `Module::initializeRoutes()` keeps only Loyalty URLs, or returns `[]` if it has no runtime surface. With Module `[]`, listener routes above **MUST** be explicit — `null` would inherit `[]`.

After either map changes: `php dotapper.php --optimize-modules`. **MUST NOT** return `['*']` just to attach. Use it only for a genuinely global/dynamic dependency after warning that this listener registers on every request.

Direct registration is canonical. Optional lifecycle wrappers may use `dotapp.module.shop.init.start` or `.loading`. **MUST NOT** wait for `.loaded` when Shop may call the point during `initialize()`; `.loaded` fires afterward.

A second module calling `extend()` for `Checkout::quote` throws — that conflict is intentional.

---

## 3. Replacement handler

Controller string → `DotApp::call()` (here `!` = no DI). Native callable → `call_user_func_array`, no DI.

```php
namespace Dotsystems\App\Modules\Loyalty\Controllers;

use Dotsystems\App\Parts\Controller;

class Pricing extends Controller
{
    /**
     * CRCchecking — none
     * Replacement Shop checkout quote. Invoked only via Extender, not as an HTTP route.
     *
     * @param int $cartId Cart id passed explicitly by Shop.
     * @param string $subtotal Decimal subtotal passed explicitly by Shop.
     * @return array{cart_id: int, subtotal: string, total: string}
     */
    public static function quote(int $cartId, string $subtotal): array
    {
        // Why: only the explicit cart id and subtotal exist here — no request, token, or body.
        $total = number_format(((float) $subtotal) * 0.95, 2, '.', '');

        return [
            'cart_id' => $cartId,
            'subtotal' => $subtotal,
            'total' => $total,
        ];
    }
}
```

Native equivalent (same arguments, still no DI):

```php
Extender::extend(
    \Dotsystems\App\Modules\Shop\Controllers\Checkout::class,
    'quote',
    [Pricing::class, 'quote']
);
```

---

## 4. Wrong

| Wrong | Right |
|-------|--------|
| `Extender::exists` on every persist / helper | Judge first: page/block, cart, export — [00](../00-AGENT-CONTRACT.md) §2h |
| `Events::on` / `trigger` / `triggerWithVeto` to replace a method | `Extender::extend` + owner `exists()` / `call()` |
| `exist()` in new code | `exists()` (`exist()` is only the alias) |
| Pass `$request` / tokens / bodies into `call()` | Pass ids, flags, already-safe scalars |
| `extend()` in Loyalty Module `initialize()` | `extend()` in `Listeners::register()` before Module initialization |
| Shop URLs in Loyalty Module map | Shop URLs in Loyalty listener map; Module owns Loyalty URLs or `[]` |
| Listener routes omitted with Module `[]` | Explicit target listener routes; `null` inherits `[]` |
| `.loaded` for an initialize-time point | Direct registration, or earlier `.init.start` / `.loading` |
| `.loaded` callback calls `$dotapp->module('Loyalty')` | Register the controller string directly; it autoloads lazily without full Loyalty initialization |
| Listener `['*']` just to attach | Exact Shop URL surfaces; global only when genuinely dynamic and warned |
| Patch DACore (or Shop, from Loyalty) to insert `Extender::call` | Only the **owner** of the target method opts in |
| Two `extend()` calls for the same target | One replacement; the duplicate must throw |
