<?php

/**
 * CLASS AIRequest - Fluent per-request state for a single chat completion
 *
 * Created only from `AI::driver(...)->system(...)` (class `AI` has no static `system()`; the chain is always
 * `driver` → `system` on the returned object). Then `messages()` and `options()` set state only; `call()` runs the request
 * and returns `all_messages`, `reply`, and `raw` (same shape as `AIDriverInterface::complete` plus `all_messages`).
 *
 * **`options` is the primary input for the driver** (api key, `model`, and any other keys the driver documents).
 * The implementation does a **shallow merge**: start from `Config::get('ai', <driverShortName>)` (empty array if missing),
 * then in `->call()` apply every key from accumulated `->options([...])` (your values **win** on duplicate keys over
 * Config). You may keep Config empty and pass everything in `->options`, or mix.
 * **What common `options` mean (short; each driver file has the full list for its API)**  
 * - `temperature` — **Lower** = more **deterministic** output; **higher** = more **random** / varied wording.  
 * - `max_tokens` (and `max_completion_tokens` / `maxOutputTokens` where the API renames) — cap on **generated** output tokens, not your prompt.  
 * - `top_p` or `topP` — **Nucleus sampling** (`0`–`1`): how wide the next-token search is.  
 * - Other keys (`topK`, penalties, `stop`, …) — always read the **driver class** you call (`AIDriverOpenAI`, `AIDriverGrok`, `AIDriverGemini`); each file’s header explains every field in full, duplicated for that provider.
 *
 *   // $out = \Dotsystems\App\Parts\AI::driver('AIDriverOpenAI')
 *   //     ->system('You are a helper.')
 *   //     ->messages([['role' => 'user', 'content' => 'Hi']])
 *   //     ->options(['api_key' => 'sk-...', 'model' => 'gpt-4o'])
 *   //     ->call();
 *   // $reply = $out['reply'];
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <stefan@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   2.0 FREE
 * @date      2014 - 2026
 * @license   MIT License
 */

namespace Dotsystems\App\Parts;

/**
 * Fluent builder: system prompt, user/assistant messages, options; `call()` executes.
 */
class AIRequest
{
    /**
     * @var string
     */
    private $system = '';

    /**
     * @var list<array{role: string, content: string}>
     */
    private $messages = [];

    /**
     * @var array<string, mixed> Per-request options merged (last key wins) then merged with `Config` in `call()`.
     */
    private $options = [];

    /**
     * @var string Driver id (short class name or FQCN) as passed to `AI::driver()`. The merge in `call()` uses
     *     `Config::get('ai', <shortNameOfThatDriver>)` where short name is derived from this id.
     */
    private $driverId = '';

    /**
     * @param string $system Default system instruction (empty string to omit in payload where appropriate)
     * @param string $driverId Driver class short name (e.g. AIDriverOpenAI) or FQCN
     */
    public function __construct(string $system, string $driverId)
    {
        $this->system = $system;
        $this->driverId = $driverId;
    }

    /**
     * Set conversation messages (OpenAI-style roles: `user`, `assistant`; do not use `system` here when using `->system()`).
     *
     * @param list<array{role: string, content: string}> $messages
     * @return $this
     */
    public function messages(array $messages): self
    {
        $this->messages = [];
        foreach ($messages as $m) {
            if (!is_array($m) || !isset($m['role'], $m['content'])) {
                throw new AIException('Each message must be an array with string keys "role" and "content".');
            }
            $this->messages[] = [
                'role' => (string) $m['role'],
                'content' => (string) $m['content'],
            ];
        }
        return $this;
    }

    /**
     * Shallow-merge per-call keys into the request; does **not** call the provider. Call `call()` to send the request.
     *
     * Merged in `call()` on top of `Config::get('ai', <driverShortName>)` (your values win). Use `api_key`, `model`,
     * optional `base_url`, and any generation fields the active driver supports.
     *
     * @param array<string, mixed> $options
     * @return $this
     */
    public function options(array $options = []): self
    {
        foreach ($options as $k => $v) {
            $this->options[$k] = $v;
        }
        return $this;
    }

    /**
     * Build merged options, invoke `AIDriverInterface::complete()` (this is the actual API call), return reply payload.
     *
     * @return array{all_messages: list<array{role: string, content: string}>, reply: string, raw: array<string, mixed>|null}
     *
     * @throws \Dotsystems\App\Parts\AIException
     */
    public function call(): array
    {
        $driver = AI::makeDriver($this->driverId);
        $block = AI::getConfigForDriverId($this->driverId);
        $merged = $block;
        foreach ($this->options as $k => $v) {
            $merged[$k] = $v;
        }

        $allMessages = $this->buildAllMessages();

        $systemForDriver = $this->resolveSystemForDriver();
        $messagesForDriver = $this->filterSystemFromMessages($this->messages);

        $out = $driver->complete($messagesForDriver, $systemForDriver, $merged);

        return [
            'all_messages' => $allMessages,
            'reply' => $out['reply'],
            'raw' => $out['raw'] ?? null,
        ];
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function buildAllMessages(): array
    {
        $all = [];
        if (trim($this->system) !== '') {
            $all[] = [
                'role' => 'system',
                'content' => $this->system,
            ];
        }
        foreach ($this->messages as $m) {
            $all[] = $m;
        }
        return $all;
    }

    /**
     * @return string|null
     */
    private function resolveSystemForDriver()
    {
        if (trim($this->system) !== '') {
            return $this->system;
        }
        $parts = [];
        foreach ($this->messages as $m) {
            if (strtolower($m['role']) === 'system') {
                $parts[] = $m['content'];
            }
        }
        if (count($parts) === 0) {
            return null;
        }
        return implode("\n", $parts);
    }

    /**
     * @param list<array{role: string, content: string}> $messages
     * @return list<array{role: string, content: string}>
     */
    private function filterSystemFromMessages(array $messages)
    {
        $p = [];
        foreach ($messages as $m) {
            if (strtolower($m['role']) === 'system') {
                continue;
            }
            $p[] = $m;
        }
        return $p;
    }
}
