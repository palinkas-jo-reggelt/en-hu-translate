<?php

namespace aniNya\I18n\Service\Engine;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;

class OpenAI implements EngineInterface
{
    protected Client $client;
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;

    public function __construct(protected SettingsRepositoryInterface $settings)
    {
        $this->apiKey = $this->settings->get('ani-nya-i18n.openai_key');
        $this->model = $this->settings->get('ani-nya-i18n.openai_model') ?: 'gpt-4o-mini';
        $this->baseUrl = rtrim($this->settings->get('ani-nya-i18n.openai_base_url') ?: 'https://api.openai.com/v1', '/');

        $this->client = new Client([
            'base_uri' => $this->baseUrl.'/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    public function translate(string $text, string $targetLang, string $sourceLang = 'auto'): ?string
    {
        if (empty($this->apiKey)) {

            return null;
        }

        $names = ['hu' => 'Hungarian', 'en' => 'English', 'zh-Hans' => 'Simplified Chinese'];
        $langName = $names[$targetLang] ?? $targetLang;
        $template = $this->settings->get('ani-nya-i18n.openai_prompt');
        $prompt = $template
            ? strtr($template, ['{target_lang}' => $langName, '{text}' => $text])
            : "Translate the following text to {$langName}.\n\nRules:\n1. Preserve all formatting (markdown, links, code blocks, line breaks)\n2. Keep URLs, code, and technical identifiers unchanged\n3. Output ONLY the translation, no explanations\n4. If already in target language, return unchanged\n\nText:\n{$text}";


        try {
            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 4096,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true);

            return $body['choices'][0]['message']['content'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getName(): string
    {
        return 'openai';
    }
}
