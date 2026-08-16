<?php

/**
 * CLASS AIDriverInterface - Contract for AI chat drivers
 *
 * **Any** class that implements this interface can be used as a driver. The framework does not cap how many
 * drivers you may have: ship your own in `Dotsystems\App\Parts\`, pass a **FQCN** to `AI::driver()`, or bind a short
 * name with `AI::registerDriver()`. The facade merges `Config` with per-call `options`; your `complete()` must
 * read credentials and request parameters from the merged `$options` (document supported keys in your class docblock).
 *
 *   // Fluent app code: `AI::driver('AIDriverOpenAI')->system(...)->messages(...)->options([...])->call()`.
 *   // For tests or custom wiring: `AI::makeDriver('AIDriverOpenAI')` then `$driver->complete($messages, $system, $mergedOptions)`.
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
 * Contract for a chat-capable AI driver. Implement in your own class; the AI facade only depends on this interface.
 */
interface AIDriverInterface
{
    /**
     * Execute one completion request to the provider.
     *
     * The facade passes conversation turns in OpenAI style (user/assistant) in $messages;
     * the system prompt (if any) is passed as $system.
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param string|null $system System prompt, or null/empty to omit
     * @param array<string, mixed> $options Merged Config block + per-call overrides: credentials (`api_key`, …), `model`,
     *        `base_url`, and generation parameters (`temperature`, `max_tokens`, `top_p`, …). Exact keys and meanings are
     *        documented on each concrete driver class (`AIDriverOpenAIBase` for OpenAI/Grok-style, `AIDriverGemini` for Gemini).
     *
     * @return array{reply: string, raw: array<string, mixed>|null}
     *
     * @throws \Dotsystems\App\Parts\AIException
     */
    public function complete(array $messages, ?string $system, array $options): array;
}
