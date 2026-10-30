<?php

/**
 * GLPI Assist — appel au modèle (OpenAI, Anthropic, Ollama, ou à blanc).
 * Une seule méthode, une seule forme de réponse : du texte, qu'on attend en JSON.
 * Licence GPLv3+, voir LICENSE.
 */

namespace GlpiPlugin\Glpiassist;

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

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $ms = (int) ((microtime(true) - $t0) * 1000);

        if ($raw === false) {
            throw new \RuntimeException("Connexion impossible ($provider) : $err");
        }
        $data = json_decode($raw, true);
        if ($code < 200 || $code >= 300 || !is_array($data)) {
            $msg = is_array($data) ? ($data['error']['message'] ?? $data['error'] ?? '') : '';
            throw new \RuntimeException("Réponse $code de $provider" . ($msg ? " : " . (is_string($msg) ? $msg : json_encode($msg)) : ''));
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
