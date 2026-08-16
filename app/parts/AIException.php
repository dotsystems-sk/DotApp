<?php

/**
 * CLASS AIException - Exception thrown for AI client failures
 *
 * Thrown when a **driver** cannot be resolved, HTTP/JSON transport fails, a provider returns an error,
 * a response cannot be parsed, or validation in `AIRequest` / a driver fails (e.g. missing `api_key` in `options` merge).
 * Empty `Config` for `ai` is not an error; missing driver id or bad credentials from merged `options` can be.
 *
 *   // Example (typically caught by the caller; not an expected happy path)
 *   // try {
 *   //     $out = \Dotsystems\App\Parts\AI::driver('AIDriverOpenAI')->system('...')->messages([])->options(['api_key' => 'k', 'model' => 'm'])->call();
 *   // } catch (\Dotsystems\App\Parts\AIException $e) {
 *   //     // log or handle: $e->getMessage();
 *   // }
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
 * Exception for AI module errors (configuration, resolution, transport, response shape).
 */
class AIException extends \Exception
{
}
