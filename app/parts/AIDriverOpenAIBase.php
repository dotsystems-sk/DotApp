<?php

/**
 * CLASS AIDriverOpenAIBase - OpenAI-style chat.completions for compatible APIs
 *
 * Used by OpenAI and xAI (Grok) because both use the same JSON shape: POST
 * {base}/chat/completions with Authorization: Bearer and a { model, messages, ... } body.
 *
 *   // Abstract: do not instantiate; use AIDriverOpenAI or AIDriverGrok.
 *   // $driver = new \Dotsystems\App\Parts\AIDriverOpenAI();
 *   // $out = $driver->complete(
 *   //     [['role' => 'user', 'content' => 'Hi']],
 *   //     'You are a concise assistant.',
 *   //     ['api_key' => '...', 'model' => 'gpt-4o', 'temperature' => 0.2, 'max_tokens' => 500]
 *   // );
 *
 * **Options reference (merged `options` / Config; sent to `POST .../chat/completions` when set)**
 *
 * **Connection / auth (not part of JSON body)**
 * - `api_key` — Bearer token for `Authorization: Bearer …` (required).
 * - `base_url` — API root without trailing slash; default comes from the concrete driver.
 * - `organization` — OpenAI org id; sent as `OpenAI-Organization` header (OpenAI and compatible stacks that honour it).
 * - `ca_file`, `ca_fingerprint` — TLS options for `HttpHelper::request()`.
 * - `http_timeout` — Max. seconds for the **whole** cURL transfer (handshake **and** receiving the response). Optional.
 *   When omitted, HttpHelper uses **connect_timeout + 30** (~30 s budget for generation after connect).
 * - `http_connect_timeout` — Only TCP/TLS connect phase (**seconds**); default **2**.
 * - `http_max_retries` — Maximum attempts on transient failures (timeouts, connection errors, HTTP 429 / 502–504); default `3`.
 * - `http_retry_delay_ms` — Initial pause between retries in milliseconds (then scaled ~1.8×, capped at 8000 ms); default `500`.
 *
 * **Request body (model + generation)**
 * - `model` — Provider model id (required), e.g. `gpt-4o`, `grok-2-latest`.
 * - `temperature` — Sampling randomness: lower (e.g. `0`–`0.3`) = more deterministic/focused;
 *   higher (e.g. `0.8`–`1.5`) = more diverse/creative. Typical range depends on provider (often up to `2`).
 * - `max_tokens` — Legacy cap on **generated** tokens in the assistant message (not input length). Prefer for older models.
 * - `max_completion_tokens` — Same role for newer OpenAI chat models; use what the provider documents for your `model`.
 * - `top_p` — Nucleus sampling: model considers tokens with cumulative probability up to this mass (`0`–`1`). Small = narrower sampling; often used **instead of** or **together with** `temperature` (usually do not crank both to extremes).
 * - `frequency_penalty` — Positive values penalise tokens that already appeared (reduces literal repetition), roughly `-2`–`2`.
 * - `presence_penalty` — Positive values encourage new topics/tokens (reduces sticking to one phrasing), roughly `-2`–`2`.
 * - `stop` — String or array of strings: generation stops when one of these sequences is produced (output does not include the stop sequence).
 * - `seed` — If supported, fixes randomness for reproducible runs (not a guarantee on all hardware/providers).
 * - `user` — Stable end-user identifier string (hashed by the provider); for abuse detection, not shown to the model as a “user name”.
 * - `n` — Number of chat completion **choices** to return (default `1`). This client still reads the first choice for `reply`.
 * - `response_format` — e.g. JSON mode objects as in the provider API (`{ "type": "json_object" }` style), when the model supports it.
 * - `logprobs`, `top_logprobs` — Return log probabilities for tokens (debugging/evaluation); shape per provider.
 * - `logit_bias` — Map of token id → bias to encourage or discourage specific tokens.
 *
 * Values are forwarded only if present in `$options` (after merge with Config). Valid ranges and support **depend on the provider and model**—always confirm in the official API documentation.
 *
 * The same **generation** explanations are **also copied in full** into the `AIDriverOpenAI` and `AIDriverGrok` class
 * file headers (so those files are self-contained). You may read this base file, or only the driver you use.
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
 * Base implementation for any provider exposing OpenAI-compatible chat/completions.
 * See the file-level docblock for the meaning of `temperature`, `max_tokens`, `top_p`, and other `options` keys.
 */
abstract class AIDriverOpenAIBase implements AIDriverInterface
{
    /**
     * @var list<string> Body keys copied from merged `$options` into the chat `completions` JSON (see class doc for semantics).
     */
    private static $openAiPassthrough = [
        'temperature',
        'max_tokens',
        'max_completion_tokens',
        'top_p',
        'frequency_penalty',
        'presence_penalty',
        'response_format',
        'seed',
        'user',
        'n',
        'logprobs',
        'top_logprobs',
        'stop',
        'logit_bias',
    ];

    /**
     * @return string Default base URL (no trailing slash) when options['base_url'] is absent.
     */
    abstract protected function getDefaultBaseUrl(): string;

    /**
     * @return string Value used in error messages (e.g. "OpenAI", "Grok")
     */
    abstract protected function getProviderLabel(): string;

    /**
     * @inheritdoc
     * @return array{reply: string, raw: array<string, mixed>|null}
     */
    public function complete(array $messages, ?string $system, array $options): array
    {
        $this->requireString($options, 'api_key', $this->getProviderLabel());
        $this->requireString($options, 'model', $this->getProviderLabel());

        $url = rtrim($options['base_url'] ?? $this->getDefaultBaseUrl(), '/') . '/chat/completions';

        $outMessages = $this->buildProviderMessages($messages, $system);

        $body = [
            'model' => $options['model'],
            'messages' => $outMessages,
        ];
        $body = $this->applyPassthrough($body, $options);

        $auth = $this->buildAuth($options);
        $headers = [];
        if (isset($options['organization']) && (string) $options['organization'] !== '') {
            $headers[] = 'OpenAI-Organization: ' . (string) $options['organization'];
        }

        $method = 'POST';
        $maxAttempts = isset($options['http_max_retries'])
            ? max(1, min(15, (int) $options['http_max_retries']))
            : 3;
        $retryDelayMs = isset($options['http_retry_delay_ms'])
            ? max(0, (int) $options['http_retry_delay_ms'])
            : 500;

        $result = HttpHelper::requestWithRetries(
            $method,
            $url,
            $body,
            $auth,
            $headers,
            [],
            null,
            false,
            $maxAttempts,
            $retryDelayMs
        );

        if ($result['error'] !== null) {
            throw new AIException($this->getProviderLabel() . ' request failed: ' . $result['error']);
        }

        if (!$result['success'] || !is_array($result['response'])) {
            $msg = is_array($result['response'] ?? null) && isset($result['response']['error']['message'])
                ? (string) $result['response']['error']['message']
                : (string) ($result['error'] ?? 'HTTP ' . ($result['http_code'] ?? ''));
            throw new AIException($this->getProviderLabel() . ' error: ' . $msg);
        }

        $decoded = $result['response'];
        $text = $this->parseAssistantText($decoded);
        if ($text === null) {
            throw new AIException($this->getProviderLabel() . ': could not read assistant text from response.');
        }

        return [
            'reply' => $text,
            'raw' => $decoded,
        ];
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @return array<int, array{role: string, content: string}>
     */
    protected function buildProviderMessages(array $messages, ?string $system): array
    {
        $out = [];
        if ($system !== null && trim($system) !== '') {
            $out[] = [
                'role' => 'system',
                'content' => (string) $system,
            ];
        }
        foreach ($messages as $m) {
            if (!is_array($m) || !isset($m['role'], $m['content'])) {
                throw new AIException('Each message must be an array with role and content keys.');
            }
            $out[] = [
                'role' => (string) $m['role'],
                'content' => (string) $m['content'],
            ];
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function applyPassthrough(array $body, array $options): array
    {
        foreach (self::$openAiPassthrough as $k) {
            if (array_key_exists($k, $options) && $options[$k] !== null) {
                $body[$k] = $options[$k];
            }
        }
        return $body;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function buildAuth(array $options): array
    {
        $auth = [
            'api_key' => (string) $options['api_key'],
        ];
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
        return $auth;
    }

    /**
     * @param array<string, mixed> $decoded
     */
    protected function parseAssistantText($decoded): ?string
    {
        if (!isset($decoded['choices'][0]['message']['content'])) {
            return null;
        }
        $c = $decoded['choices'][0]['message']['content'];
        if (is_string($c)) {
            return $c;
        }
        if (is_array($c)) {
            $parts = [];
            foreach ($c as $part) {
                if (is_array($part) && isset($part['type']) && $part['type'] === 'text' && isset($part['text'])) {
                    $parts[] = (string) $part['text'];
                }
            }
            if (count($parts) > 0) {
                return implode('', $parts);
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function requireString(array $options, string $key, string $label): void
    {
        if (!isset($options[$key]) || $options[$key] === null || (is_string($options[$key]) && trim($options[$key]) === '')) {
            throw new AIException($label . ' requires ' . "options[{$key}]" . ' (merge Config and per-call options).');
        }
    }
}
