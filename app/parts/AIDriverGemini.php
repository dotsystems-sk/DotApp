<?php

/**
 * CLASS AIDriverGemini - Google Generative Language (Gemini) driver
 *
 * **Authentication (options, merged with Config for key `AIDriverGemini`)**
 * - `api_key` (string, **required**): key sent as the `key` query parameter on `generateContent` (or override via a custom `base_url` that still expects `?key=`)
 * - `base_url` (string, optional): service root, default `https://generativelanguage.googleapis.com` (no path, no trailing slash)
 * - `model` (string, **required**): e.g. `gemini-1.5-flash` (embedded in the path `.../models/{model}:generateContent`)
 * - `ca_file`, `ca_fingerprint` (optional): see `HttpHelper`. Optional: `http_timeout`, `http_connect_timeout`,
 *   `http_max_retries`, `http_retry_delay_ms` (same semantics as OpenAI-base drivers).
 *
 * **Generation (full `generationConfig` field meanings; self-contained, Google Gemini API)**  
 * Values are read from merged `options` and placed into `generationConfig` on `generateContent` (this driver; names match Google where noted).
 * - `temperature` — **Sampling randomness** (same *idea* as OpenAI’s `temperature`): `0` or low = **more deterministic**,
 *   more **focused** text; **higher** = more **random** and varied wording. Allowed range is **model-specific** (check Google’s docs for min/max; do not assume `0`–`2` like some OpenAI models).
 * - `max_tokens` — **Not** a Google field name: this driver **maps** it to Gemini’s `maxOutputTokens` in `generationConfig`.
 *   It limits how many **tokens the model may generate in the answer** (output only, not your input size). In `->options()`,
 *   use **`max_tokens`** (same as other library drivers). To pass Google’s `maxOutputTokens` key by name, use `max_tokens` only—
 *   it is the single supported key here for that limit.
 * - `topP` (capital **P** in this API) — **Nucleus sampling**: from the probability distribution, keep only the smallest
 *   set of next tokens whose **cumulative** probability is at most `topP` (`0`–`1`). **Lower** = safer, more focused;
 *   **higher** = more variety. It controls diversity in a different way than `temperature`; you often tune one or the other sensibly, not both to extremes.
 * - `topK` — At each step, only the **K** most likely next tokens are allowed (`1` = greedy; a larger `K` = more exploration).
 * - `candidateCount` — How many full **response candidates** the API may return; this driver still only builds `reply`
 *   from the **first** candidate (inspect `raw` for the full payload if you need the rest).
 *
 * **Role mapping (chat format from the facade)**
 * - Incoming messages use the same `user` / `assistant` shape as the OpenAI-style facade; this driver maps
 *   `assistant` to Gemini `model` and `user` to `user` when building `contents`. The separate system string
 *   is sent as `systemInstruction` on the `generateContent` request.
 *
 *   // $out = \Dotsystems\App\Parts\AI::driver('AIDriverGemini')
 *   //     ->system('You are concise.')
 *   //     ->messages([['role' => 'user', 'content' => 'Hi']])
 *   //     ->options(['api_key' => '...', 'model' => 'gemini-1.5-flash', 'max_tokens' => 256])
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
 * Google Gemini (`generateContent`) driver. All parameters are explained in the file docblock **above** (no cross-file required).
 */
class AIDriverGemini implements AIDriverInterface
{
    /**
     * @inheritdoc
     * @return array{reply: string, raw: array<string, mixed>|null}
     */
    public function complete(array $messages, ?string $system, array $options): array
    {
        if (!isset($options['api_key']) || (string) $options['api_key'] === '') {
            throw new AIException('Gemini requires options[api_key] (merge Config and per-call options).');
        }
        if (!isset($options['model']) || (string) $options['model'] === '') {
            throw new AIException('Gemini requires options[model] (merge Config and per-call options).');
        }

        $root = rtrim($options['base_url'] ?? 'https://generativelanguage.googleapis.com', '/');
        $model = (string) $options['model'];
        $key = (string) $options['api_key'];

        $url = $root
            . '/v1beta/models/' . rawurlencode($model) . ':generateContent'
            . '?' . http_build_query(['key' => $key], '', '&', PHP_QUERY_RFC3986);

        $body = [];
        if ($system !== null && trim($system) !== '') {
            $body['systemInstruction'] = [
                'parts' => [
                    ['text' => (string) $system],
                ],
            ];
        }
        $body['contents'] = $this->mapContents($messages);
        $gen = $this->mapGenerationConfig($options);
        if (count($gen) > 0) {
            $body['generationConfig'] = $gen;
        }

        $auth = [];
        if (isset($options['ca_file'])) {
            $auth['ca_file'] = $options['ca_file'];
        }
        if (isset($options['ca_fingerprint']) && (string) $options['ca_fingerprint'] !== '') {
            $auth['ca_fingerprint'] = (string) $options['ca_fingerprint'];
        }
        if (isset($options['http_timeout'])) {
            $v = (int) $options['http_timeout'];
            if ($v > 0) {
                $auth['timeout'] = $v;
            }
        }
        if (isset($options['http_connect_timeout'])) {
            $v = (int) $options['http_connect_timeout'];
            if ($v > 0) {
                $auth['connect_timeout'] = $v;
            }
        }

        $maxAttempts = isset($options['http_max_retries'])
            ? max(1, min(15, (int) $options['http_max_retries']))
            : 3;
        $retryDelayMs = isset($options['http_retry_delay_ms'])
            ? max(0, (int) $options['http_retry_delay_ms'])
            : 500;

        $result = HttpHelper::requestWithRetries(
            'POST',
            $url,
            $body,
            $auth,
            [],
            [],
            null,
            false,
            $maxAttempts,
            $retryDelayMs
        );

        if ($result['error'] !== null) {
            throw new AIException('Gemini request failed: ' . $result['error']);
        }
        if (!$result['success'] || !is_array($result['response'])) {
            $errMsg = is_array($result['response'] ?? null) && isset($result['response']['error']['message'])
                ? (string) $result['response']['error']['message']
                : (string) ($result['error'] ?? 'HTTP ' . ($result['http_code'] ?? ''));
            throw new AIException('Gemini error: ' . $errMsg);
        }

        $decoded = $result['response'];
        $text = $this->extractText($decoded);
        if ($text === null) {
            throw new AIException('Gemini: could not read candidate text from response.');
        }

        return [
            'reply' => $text,
            'raw' => $decoded,
        ];
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @return list<array{role: string, parts: list<array{text: string}>}>
     */
    private function mapContents(array $messages): array
    {
        if (count($messages) === 0) {
            return [];
        }

        $out = [];
        foreach ($messages as $m) {
            if (!is_array($m) || !isset($m['role'], $m['content'])) {
                throw new AIException('Each message must be an array with role and content keys.');
            }
            $role = (string) $m['role'];
            $gRole = $role === 'assistant' ? 'model' : 'user';
            $text = (string) $m['content'];

            $n = count($out);
            if ($n > 0 && $out[$n - 1]['role'] === $gRole) {
                $out[$n - 1]['parts'][0]['text'] .= "\n" . $text;
            } else {
                $out[] = [
                    'role' => $gRole,
                    'parts' => [
                        ['text' => $text],
                    ],
                ];
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function mapGenerationConfig(array $options): array
    {
        $g = [];
        if (array_key_exists('temperature', $options) && $options['temperature'] !== null) {
            $g['temperature'] = (float) $options['temperature'];
        }
        if (array_key_exists('topP', $options) && $options['topP'] !== null) {
            $g['topP'] = (float) $options['topP'];
        }
        if (array_key_exists('topK', $options) && $options['topK'] !== null) {
            $g['topK'] = (int) $options['topK'];
        }
        if (array_key_exists('candidateCount', $options) && $options['candidateCount'] !== null) {
            $g['candidateCount'] = (int) $options['candidateCount'];
        }
        if (array_key_exists('max_tokens', $options) && $options['max_tokens'] !== null) {
            $g['maxOutputTokens'] = (int) $options['max_tokens'];
        }
        return $g;
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractText($decoded): ?string
    {
        if (!isset($decoded['candidates'][0]['content']['parts']) || !is_array($decoded['candidates'][0]['content']['parts'])) {
            return null;
        }
        $buf = [];
        foreach ($decoded['candidates'][0]['content']['parts'] as $part) {
            if (is_array($part) && isset($part['text'])) {
                $buf[] = (string) $part['text'];
            }
        }
        if (count($buf) === 0) {
            return null;
        }
        return implode('', $buf);
    }
}
