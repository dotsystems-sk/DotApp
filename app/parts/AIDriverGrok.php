<?php

/**
 * CLASS AIDriverGrok - xAI Grok (OpenAI-compatible) driver
 *
 * **Authentication (options, merged with Config for key `AIDriverGrok`)**
 * - `api_key` (string, **required**): xAI API key, sent as `Authorization: Bearer ...`
 * - `base_url` (string, optional): default `https://api.x.ai/v1` (no trailing slash)
 * - `model` (string, **required**): e.g. `grok-2-latest` (per xAI model list)
 * - `ca_file`, `ca_fingerprint` (optional): see `HttpHelper`
 *
 * **Generation / sampling (duplicated here on purpose; same OpenAI-style chat.completions field meanings)**  
 * xAI’s HTTP API uses the same JSON keys as OpenAI; exact **min/max** for your `model` may differ—use xAI’s docs. Meanings:
 * - `temperature` — **Lower** = more **deterministic** output; **higher** = more **varied** / creative. Typical band `0`–`2` depending on model.
 * - `max_tokens` — **Cap on generated tokens** in the assistant message (not counting your input prompt).
 * - `max_completion_tokens` — Same intent when the endpoint expects this name (check model release notes).
 * - `top_p` — Nucleus sampling: keep only tokens up to this **cumulative probability** (`0`–`1`). **Lower** = tighter sampling.
 * - `frequency_penalty` — Penalize **re-use** of the same tokens to reduce stuttering; typically `-2` to `2`.
 * - `presence_penalty` — Encourage **new** tokens/topics; typically `-2` to `2`.
 * - `stop` — One or more stop **sequences**; output ends before emitting that sequence.
 * - `seed` — Reproducibility where supported.
 * - `user` — Stable id for the provider (billing / abuse), not a display name in the dialog.
 * - `n` — Number of completion variants; this driver uses the **first** for `reply`.
 * - `response_format`, `logprobs`, `top_logprobs`, `logit_bias` — Same idea as OpenAI’s Chat Completions (when supported for Grok).
 *
 *   // $out = \Dotsystems\App\Parts\AI::driver('AIDriverGrok')
 *   //     ->system('You are a helpful assistant.')
 *   //     ->messages([['role' => 'user', 'content' => 'Hi']])
 *   //     ->options(['api_key' => 'xai-...', 'model' => 'grok-2-latest', 'temperature' => 0.3])
 *   //     ->call();
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
 * xAI Grok (OpenAI-compatible) driver. All `options` keys are described in the file docblock **above**.
 */
class AIDriverGrok extends AIDriverOpenAIBase
{
    /**
     * @inheritdoc
     */
    protected function getDefaultBaseUrl(): string
    {
        return 'https://api.x.ai/v1';
    }

    /**
     * @inheritdoc
     */
    protected function getProviderLabel(): string
    {
        return 'Grok';
    }
}
