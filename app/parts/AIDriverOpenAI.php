<?php

/**
 * CLASS AIDriverOpenAI - OpenAI chat/completions driver
 *
 * **Authentication (options, merged with Config for key `AIDriverOpenAI`)**
 * - `api_key` (string, **required**): API key, sent as `Authorization: Bearer ...`
 * - `organization` (string, optional): `OpenAI-Organization` header
 * - `base_url` (string, optional): override API base; default `https://api.openai.com/v1` (no trailing slash)
 * - `model` (string, **required**): model id, e.g. `gpt-4o`
 * - `ca_file`, `ca_fingerprint` (optional): passed to `HttpHelper` for TLS (see `HttpHelper` behaviour)
 *
 * **Generation / sampling (duplicated here on purpose; same HTTP fields as `AIDriverOpenAIBase` / Chat Completions)**  
 * If set in merged `options`, they are added to the JSON body. Ranges and support depend on the **model**—confirm in OpenAI docs.
 * - `temperature` — How random the next token is: **lower** (e.g. `0`–`0.3`) = more **deterministic**, predictable answers;
 *   **higher** (e.g. `0.7`–`1.2`) = more **varied** wording and creativity. Many models allow up to `2`; too high can ramble.
 * - `max_tokens` — **Maximum tokens in the assistant reply** (output only, not your prompt size). Stops the answer early if hit.
 * - `max_completion_tokens` — Same idea for **newer** OpenAI models that prefer this field name; use what your model’s docs say.
 * - `top_p` (nucleus sampling) — Only tokens whose **cumulative probability** stays within this `0`–`1` window are considered.
 *   **Lower** = narrower, safer choices; **higher** = more diversity. Often tune **either** `temperature` **or** `top_p`, not both to extremes.
 * - `frequency_penalty` — **Positive** = penalize repeating the same words/phrases (less copy-paste); roughly `-2` to `2`.
 * - `presence_penalty` — **Positive** = encourage new topics and tokens (less staying on one formulation); roughly `-2` to `2`.
 * - `stop` — String or list of strings: generation **stops** when the model would output that sequence (sequence not included in the reply).
 * - `seed` — If the API supports it, use for **reproducible** runs; not guaranteed on all infrastructure.
 * - `user` — Opaque end-user id string for provider **abuse/metering**; not the chat “username” shown to the model.
 * - `n` — How many **alternative completions** to return; this client still reads the **first** for `reply`.
 * - `response_format` — e.g. JSON mode `{"type":"json_object"}` when the model supports structured output.
 * - `logprobs`, `top_logprobs` — Return token log-probabilities (debugging / evaluation).
 * - `logit_bias` — Per-token id biases to nudge toward or away from specific tokens.
 *
 *   // $out = \Dotsystems\App\Parts\AI::driver('AIDriverOpenAI')
 *   //     ->system('You are a helpful assistant.')
 *   //     ->messages([['role' => 'user', 'content' => 'Hello']])
 *   //     ->options([
 *   //         'api_key' => getenv('OPENAI_API_KEY'),
 *   //         'model'   => 'gpt-4o',
 *   //     ])
 *   //     ->call();
 *   // // $out['reply'], $out['all_messages'], $out['raw']
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
 * OpenAI (chat.completions) driver. All `options` keys are described in the file docblock **above** (no need to open other files).
 */
class AIDriverOpenAI extends AIDriverOpenAIBase
{
    /**
     * @inheritdoc
     */
    protected function getDefaultBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }

    /**
     * @inheritdoc
     */
    protected function getProviderLabel(): string
    {
        return 'OpenAI';
    }
}
