<?php

/**
 * CLASS AI - Facade: resolve a driver, merge configuration, and run a fluent request
 *
 * The **only** contract is `AIDriverInterface`. There is **no fixed, limited set** of built-in drivers: shipped classes
 * such as `AIDriverOpenAI` are normal implementations in this namespace. You can add your own by:
 * - placing a class in `app/parts/` (namespace `Dotsystems\App\Parts`) and calling `AI::driver('MyDriverName')` with the
 *   class basename; or
 * - passing a **FQCN** from any module or package: `AI::driver(\MyModule\MyAiDriver::class)`; or
 * - calling `registerDriver('ShortName', \Fully\Qualified\Class::class)` so the short name resolves to a class outside
 *   `Dotsystems\App\Parts\` (e.g. under `Dotsystems\App\Modules\...`).
 *
 * Start every request with `AI::driver($driverId)` (short class name, FQCN, or a name from `registerDriver()`), then call
 * `->system(...)->messages(...)->options([...])` to configure the request, and **`->call()` to execute** (HTTP to the
 * provider). The class `AI` has no static `system` method; `system` is an instance method on the object produced by
 * `driver()` (`AIChain` / `AIRequest`). Use `->options([...])` for API keys, `model`, and generation settings; it does
 * not send the request by itself.
 *
 * If you store a default driver id in app config, pass it only when set, e.g.
 * `$d = \Dotsystems\App\Parts\Config::get('ai', 'driver'); if ($d) { \Dotsystems\App\Parts\AI::driver($d)->system('...'); }`
 * — do not cast a missing key to `string` (that would be empty and fail resolution).
 *
 * **Config vs `->options([...])`**  
 * Merged for the driver at **request time** (`->call()`): 1) `Config::get('ai', <shortClassNameOfDriver>)` (may be empty
 * / absent), 2) keys from `->options([...])` overwrites. You may use no Config and put everything in `->options()`. Example:
 *   //   $r = \Dotsystems\App\Parts\AI::driver('AIDriverOpenAI')
 *   //       ->system('You are helpful.')
 *   //       ->messages([['role' => 'user', 'content' => 'Hi']])
 *   //       ->options(['api_key' => $k, 'model' => 'gpt-4o'])
 *   //       ->call();
 *   // With defaults from Config for that driver (optional):
 *   //   $r = \Dotsystems\App\Parts\AI::driver('AIDriverOpenAI')->system('...')->messages([...])
 *   //       ->options(['api_key' => $perTenantKey])  // overrides Config for this call
 *   //       ->call();
 *   //   // When `ai` / `driver` is set in config (guard null in your app):
 *   //   $r = \Dotsystems\App\Parts\AI::driver(Config::get('ai', 'driver'))->system('...')->messages([...])
 *   //       ->options([...])->call();
 *
 * **Common generation keys in `->options([...])` (where the provider API supports them)**  
 * - `temperature` — Randomness of next-token choice: **lower** = more stable/predictable; **higher** = more variety.
 *   *Typical API range* (OpenAI-style chat): `0`–`2` inclusive; **default** is often `1` when omitted. *Heuristics* (not
 *   rules): factual/classification or strict formatting ≈ `0`–`0.3`; balanced chat ≈ `0.5`–`0.8`; brainstorm/creative
 *   ≈ `0.8`–`1.2+`. A given model’s docs are authoritative if the range differs.  
 * - `max_tokens` — Hard cap on **generated** (assistant) tokens, not input length. *Range*: positive integer; **upper
 *   bound** is the model’s per-request output limit minus what the prompt already consumed (see provider docs for the
 *   model you use). *Heuristics*: short answers `128`–`512`; long explanations `1024`–`4096`+ as needed.  
 * - `top_p` — Nucleus sampling: only the smallest set of next tokens whose **cumulative probability** reaches this mass.
 *   *Typical range*: `0`–`1` (often effectively `(0, 1]`; some stacks reject `0`); **default** is often `1` (no nucleus cut).
 *   *Heuristics*: `0.9`–`1` = broad; `0.5`–`0.9` = tighter; very low = very narrow, sometimes repetitive. Usually avoid
 *   cranking `temperature` and `top_p` to extreme ends together.  
 * Other request fields (e.g. penalties, `stop`, provider-specific names) are merged the same way; the active driver maps
 * them to the provider API you configured.
 *
 *   // FQCN / registerDriver (same as above regarding options & Config)
 *   //   $r = \Dotsystems\App\Parts\AI::driver('AIDriverGrok')->system('...')->messages([...])->options([...])->call();
 *   //   $r = \Dotsystems\App\Parts\AI::driver(\MyMod\MyCustomAi::class)->system('...')->messages([...])
 *   //       ->options([...])->call();
 *   //   \Dotsystems\App\Parts\AI::registerDriver('MyApi', \MyMod\MyCustomAi::class);
 *   //   $r = \Dotsystems\App\Parts\AI::driver('MyApi')->system('...')->messages([...])->options([...])->call();
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <stefan@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8 FREE
 * @date      2014 - 2026
 * @license   MIT License
 */

namespace Dotsystems\App\Parts;

/**
 * Static facade: only `driver()` and `registerDriver()` — fluent `system` / `messages` / `options` are on the objects
 * returned from `AI::driver()` (not static methods on this class).
 */
class AI
{
    /**
     * @var array<string, string> Optional map: arbitrary short name => FQCN (for classes outside `Dotsystems\App\Parts\`
     *                             or for aliases). Empty until `registerDriver` is used. Built-in drivers in
     *                             `app/parts/` are resolved by PSR-4 without being listed here.
     */
    private static $registry = [];

    /**
     * Bind a **short** driver name to a fully qualified class. Use this when the implementation lives outside
     * `Dotsystems\App\Parts\` or when you want a custom alias. `resolveClassName()` for a **short** id tries in order:
     * entry from this map, then `Dotsystems\App\Parts\{id}`, then `class_exists($id)` in the global namespace.
     * (A string that contains `\\` is treated as FQCN and is not looked up in this map first.) No limit to registrations.
     *
     * The `Config` key for merged `options` is the **short** name you pass here (e.g. `Config::get('ai', 'MyApi')`).
     *
     * @param string $shortName Key used in `Config::get('ai', $shortName)` and in `AI::driver($shortName)` when not using FQCN
     * @param class-string $fqcn Class name that **implements** `AIDriverInterface` (not only subclasses)
     * @return void
     *
     * @throws \Dotsystems\App\Parts\AIException
     */
    public static function registerDriver(string $shortName, $fqcn): void
    {
        if ($shortName === '') {
            throw new AIException('registerDriver: short name must not be empty.');
        }
        if (!is_string($fqcn) || $fqcn === '' || !class_exists($fqcn)) {
            throw new AIException('registerDriver: class not found: ' . (is_string($fqcn) ? $fqcn : ''));
        }
        if (!self::classImplementsDriver($fqcn)) {
            throw new AIException('Class ' . $fqcn . ' must implement AIDriverInterface.');
        }
        self::$registry[$shortName] = (string) $fqcn;
    }

    /**
     * Start a fluent chain with a fixed driver. Accepts a **FQCN** (recommended for classes outside
     * `Dotsystems\App\Parts\`) or a short name resolved via `registerDriver`, or `Dotsystems\App\Parts\{name}`.
     *
     * @param string $driverId Short class name or FQCN (e.g. `\My\Driver::class` or `'AIDriverOpenAI'`)
     * @return \Dotsystems\App\Parts\AIChain
     */
    public static function driver($driverId): AIChain
    {
        self::assertResolvableDriverId((string) $driverId);
        return new AIChain((string) $driverId);
    }

    /**
     * @param string $driverId
     * @return \Dotsystems\App\Parts\AIDriverInterface
     *
     * @throws \Dotsystems\App\Parts\AIException
     */
    public static function makeDriver($driverId): AIDriverInterface
    {
        $fqcn = self::resolveClassName((string) $driverId);
        return new $fqcn();
    }

    /**
     * Config section `Config::get('ai', <shortName>)` for a driver id, used as the base of `options` merge.
     *
     * @param string $driverId
     * @return array<string, mixed>
     */
    public static function getConfigForDriverId($driverId): array
    {
        $short = self::shortNameFromId((string) $driverId);
        $block = Config::get('ai', $short);
        return is_array($block) ? $block : [];
    }

    /**
     * @param string $id
     * @return class-string
     *
     * @throws \Dotsystems\App\Parts\AIException
     */
    public static function resolveClassName($id): string
    {
        $id = (string) $id;
        if (strpos($id, '\\') !== false) {
            if (!class_exists($id)) {
                throw new AIException('AI driver class not found: ' . $id);
            }
            if (!self::classImplementsDriver($id)) {
                throw new AIException('AI driver class must implement AIDriverInterface: ' . $id);
            }
            return $id;
        }
        if (isset(self::$registry[$id]) && is_string(self::$registry[$id]) && self::$registry[$id] !== '') {
            $cn = self::$registry[$id];
            if (!class_exists($cn)) {
                throw new AIException('Registered AI driver class not found: ' . $cn);
            }
            if (!self::classImplementsDriver($cn)) {
                throw new AIException('Registered class must implement AIDriverInterface: ' . $cn);
            }
            return $cn;
        }
        $ns = 'Dotsystems\\App\\Parts\\' . $id;
        if (class_exists($ns) && self::classImplementsDriver($ns)) {
            return $ns;
        }
        if (class_exists($id) && self::classImplementsDriver($id)) {
            return $id;
        }
        throw new AIException(
            'Unknown AI driver id: ' . $id
            . '. Use a FQCN, a class in Dotsystems\\App\\Parts, or AI::registerDriver() for a custom mapping.'
        );
    }

    /**
     * @param string $driverId
     * @return void
     *
     * @throws \Dotsystems\App\Parts\AIException
     */
    private static function assertResolvableDriverId($driverId): void
    {
        self::resolveClassName($driverId);
    }

    /**
     * @param string $driverId
     * @return string Short class name (e.g. AIDriverOpenAI)
     */
    public static function shortNameFromId($driverId): string
    {
        if (strpos($driverId, '\\') !== false) {
            if (!class_exists($driverId)) {
                return $driverId;
            }
            $r = new \ReflectionClass($driverId);
            return $r->getShortName();
        }
        return $driverId;
    }

    /**
     * @param class-string $className
     */
    private static function classImplementsDriver($className): bool
    {
        if (!is_string($className) || $className === '' || !class_exists($className)) {
            return false;
        }
        $r = new \ReflectionClass($className);
        return $r->implementsInterface(AIDriverInterface::class);
    }
}

/**
 * CLASS AIChain - Fluent link from `AI::driver()` to `AIRequest`
 *
 * Returned only by `AI::driver()`. `system()` here is an **instance** method (not on class `AI`);
 * it constructs `AIRequest` with the driver id fixed at chain start, then you call `messages()`, `options()`, and
 * `call()` to send the request.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <stefan@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8 FREE
 * @date      2014 - 2026
 * @license   MIT License
 */
class AIChain
{
    /**
     * @var string
     */
    private $driverId = '';

    /**
     * @param string $driverId
     */
    public function __construct($driverId)
    {
        $this->driverId = $driverId;
    }

    /**
     * @param string $system
     * @return \Dotsystems\App\Parts\AIRequest
     */
    public function system($system = ''): AIRequest
    {
        return new AIRequest((string) $system, $this->driverId);
    }
}
