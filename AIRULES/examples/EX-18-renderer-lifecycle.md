# EX-18 — Renderer lifecycle events

Use these events to observe rendering or substitute a previously cached **complete** render. They are not business hooks and do not get a `.hooks` row.

```php
use Dotsystems\App\Parts\Cache;
use Dotsystems\App\Parts\Events;
use Dotsystems\App\Parts\RendererLifecycleContext;

final class Listeners
{
    public static function initializeRoutes()
    {
        return ['/Shop/catalog', '/Shop/catalog/*'];
    }

    public static function register()
    {
        Events::on('dotapp.renderer.before', function ($ctx) {
            if (!$ctx instanceof RendererLifecycleContext
                || $ctx->contractVersion() !== 1
                || $ctx->operation() !== 'view'
                || $ctx->module() !== 'Shop'
                || $ctx->source() !== 'catalog') {
                return;
            }

            $html = Cache::use('Shop')->load('render.catalog.public');
            if ($html !== null) {
                // Why: useReplacement is the explicit renderer short-circuit; listener returns are ignored.
                $ctx->useReplacement((string) $html);
            }
        });

        Events::on('dotapp.renderer.after', function ($ctx) {
            if (!$ctx instanceof RendererLifecycleContext
                || $ctx->contractVersion() !== 1
                || $ctx->operation() !== 'view'
                || $ctx->module() !== 'Shop'
                || $ctx->source() !== 'catalog') {
                return;
            }

            // Why: this example is public and identical for every visitor.
            Cache::use('Shop')->save('render.catalog.public', $ctx->output(), 300);
        });
    }
}
```

MUST:

- Keep `Listeners::register()` itself cheap; cache I/O above runs only when the event fires.
- Scope by contract, operation, module, source, locale, permissions, and every other value that changes output.
- Cache only output safe for the same audience. Never share user-specific HTML, CSRF/CRC fields, encrypted session-bound IDs, secrets, or forms.
- Wrap real listener bodies in the module's normal `try/catch` + catch-bus reporting. Listener exceptions propagate.
- `custom` renderer events are observe-only. `useReplacement()` works only in `before` for layout/view/rendered code.

Full contract: [05-VIEWS-TEMPLATES-ASSETS.md](../05-VIEWS-TEMPLATES-ASSETS.md) §5.
