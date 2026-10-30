<?php

/**
 * Relance — appel au modèle (OpenAI, Anthropic, Ollama, ou à blanc).
 * Passe par Toolbox::callCurl pour respecter le proxy configuré dans GLPI.
 * Licence GPLv3+, voir LICENSE.
 */

namespace GlpiPlugin\Relance;

use Toolbox;

class Llm
{
    /**
     * @return array{text: string, tokens: int, http_code: int, duration_ms: int}
     * @throws \RuntimeException si le fournisseur ne répond pas ou répond mal
     */
    public static function complete(array $config, string $system, string $user, int $maxTokens = 400): array
    {
        $t0 = microtime(true);
        $provider = $config['provider'] ?? 'dry';

        if ($provider === 'dry') {
            return ['text' => self::dry($user), 'tokens' => 0, 'http_code' => 0, 'duration_ms' => 0];
        }

        $model = trim((string) ($config['model'] ?? ''));
        $key   = (string) ($config['api_key'] ?? '');

        switch ($provider) {
            case 'openai':
                $url = 'https://api.openai.com/v1/chat/completions';
                $headers = ['Content-Type: application/json', "Authorization: Bearer $key"];
                $body = [
                    'model' => $model ?: 'gpt-4o-mini',
                    'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
                    'max_tokens' => $maxTokens,
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                ];
                break;
            case 'anthropic':
                $url = 'https://api.anthropic.com/v1/messages';
                $headers = ['Content-Type: application/json', "x-api-key: $key", 'anthropic-version: 2023-06-01'];
                $body = [
                    'model' => $model ?: 'claude-haiku-4-5',
                    'system' => $system,
                    'messages' => [['role' => 'user', 'content' => $user]],
                    'max_tokens' => $maxTokens,
                    'temperature' => 0.2,
                ];
                break;
            case 'ollama':
                $base = rtrim((string) ($config['endpoint'] ?? ''), '/') ?: 'http://127.0.0.1:11434';
                $url = "$base/api/chat";
                $headers = ['Content-Type: application/json'];
                $body = [
                    'model' => $model ?: 'qwen2.5:14b',
                    'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
                    'stream' => false,
                    'format' => 'json',
                    'options' => ['temperature' => 0.2, 'num_predict' => $maxTokens],
                ];
                break;
            default:
                throw new \RuntimeException("Fournisseur inconnu : $provider");
        }

        [$raw, $code, $err] = self::post($url, $headers, $body, 20);
        $ms = (int) ((microtime(true) - $t0) * 1000);

        if ($raw === '') {
            if ($code > 0) {
                throw new \RuntimeException("Réponse $code de $provider, sans corps");
            }
            throw new \RuntimeException("Connexion impossible ($provider) : " . self::sansSecret($err ?: 'réponse vide'));
        }
        $data = json_decode($raw, true);
        if ($code < 200 || $code >= 300 || !is_array($data)) {
            $msg = is_array($data) ? ($data['error']['message'] ?? $data['error'] ?? '') : '';
            $msg = is_string($msg) ? $msg : json_encode($msg);
            throw new \RuntimeException("Réponse $code de $provider" . ($msg ? ' : ' . self::sansSecret($msg) : ''));
        }

        switch ($provider) {
            case 'openai':
                $text = $data['choices'][0]['message']['content'] ?? '';
                $tokens = (int) ($data['usage']['total_tokens'] ?? 0);
                break;
            case 'anthropic':
                $text = $data['content'][0]['text'] ?? '';
                $tokens = (int) (($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0));
                break;
            default:
                $text = $data['message']['content'] ?? '';
                $tokens = (int) (($data['prompt_eval_count'] ?? 0) + ($data['eval_count'] ?? 0));
        }

        return ['text' => (string) $text, 'tokens' => $tokens, 'http_code' => $code, 'duration_ms' => $ms];
    }

    /**
     * POST JSON via Toolbox::callCurl (proxy GLPI respecté).
     * @return array{0: string, 1: int, 2: string} corps, code HTTP, message d'erreur
     */
    public static function post(string $url, array $headers, array $body, int $timeout): array
    {
        $msgerr = null;
        $curlerr = null;
        $info = null;
        $raw = Toolbox::callCurl($url, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => $timeout,
        ], $msgerr, $curlerr, false, $info);
        $code = (int) ($info['http_code'] ?? 0);
        return [(string) $raw, $code, (string) ($msgerr ?? $curlerr ?? '')];
    }

    /** Un message d'erreur de fournisseur peut citer la clé : on l'efface avant de journaliser. */
    public static function sansSecret(string $s): string
    {
        return mb_substr(preg_replace('/\b(sk|key|gsk|xai|AIza)[-_][A-Za-z0-9_\-.*]{6,}/', '[CLÉ]', $s), 0, 300);
    }

    /**
     * Mode à blanc : une réponse déterministe pour tester le circuit sans clé.
     * Déclare manquant ce qui n'apparaît pas dans le texte du ticket.
     */
    private static function dry(string $user): string
    {
        $t = mb_strtolower($user);
        $manque = [];
        if (!preg_match('/\b(poste|pc|ordinateur|portable|laptop|inventaire|n°|numéro)\b/u', $t)) {
            $manque[] = 'poste';
        }
        if (!preg_match('/\b(erreur|error|message|code|capture|écran)\b/u', $t)) {
            $manque[] = 'message_erreur';
        }
        if (!preg_match('/\b(depuis|hier|ce matin|aujourd|semaine|heure|h\d|\d+h)\b/u', $t)) {
            $manque[] = 'depuis_quand';
        }
        return json_encode(['manque' => $manque, 'question' => $manque ? 'Pour avancer, pouvez-vous me préciser : ' . implode(', ', Relance::libelles($manque)) . ' ?' : ''], JSON_UNESCAPED_UNICODE);
    }
}
