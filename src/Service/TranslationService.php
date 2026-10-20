<?php

namespace aniNya\I18n\Service;

use Flarum\Settings\SettingsRepositoryInterface;
use aniNya\I18n\Models\I18nTranslation;
use aniNya\I18n\Service\Engine\EngineInterface;
use aniNya\I18n\Service\Engine\OpenAI;
use aniNya\I18n\Service\Engine\GoogleTranslate;
use aniNya\I18n\Service\Engine\DeepL;
use aniNya\I18n\Service\Engine\YandexTranslate;
use aniNya\I18n\Service\Engine\BaiduTranslate;
use aniNya\I18n\Service\Engine\LibreTranslate;
use aniNya\I18n\Service\Engine\FreeGoogleTranslate;

class TranslationService
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
    ) {}

    public function translate(
        int $discussionId,
        string $field,
        string $text,
        string $targetLang,
        string $sourceLang = 'auto'
    ): ?I18nTranslation {
        if (empty(trim($text))) {
            return null;
        }

        $existing = I18nTranslation::where('discussion_id', $discussionId)
            ->where('field', $field)
            ->where('target_lang', $targetLang)
            ->first();

        if ($existing && $existing->source_text === $text) {
            return $existing;
        }

        if ($existing) {
            $existing->delete();
        }

        if ($sourceLang === 'auto') {
            $sourceLang = $this->detectLanguage($text);
        }

        if ($sourceLang === $targetLang) {
            return null;
        }

        $sourceBase = explode('-', $sourceLang)[0];
        $targetBase = explode('-', $targetLang)[0];
        if ($sourceBase === $targetBase) {
            return null;
        }

        $engine = $this->getEngine();
        $translated = $engine->translate($text, $targetLang, $sourceLang);

        if (empty($translated)) {
            return null;
        }

        $result = I18nTranslation::create([
            'discussion_id'   => $discussionId,
            'field'           => $field,
            'source_lang'     => $sourceLang,
            'target_lang'     => $targetLang,
            'source_text'     => $text,
            'translated_text' => $translated,
            'engine'          => $this->settings->get('ani-nya-i18n.engine'),
        ]);

        return $result;
    }

    protected function detectLanguage(string $text): string
    {
        $clean = $this->stripSystemTags($text);

        preg_match_all('/[\x{4e00}-\x{9fff}]/u', $clean, $chinese);
        $chineseCount = count($chinese[0]);

        preg_match_all('/[\x{3040}-\x{309f}\x{30a0}-\x{30ff}]/u', $clean, $japanese);
        $japaneseCount = count($japanese[0]);

        preg_match_all('/[\x{ac00}-\x{d7af}]/u', $clean, $korean);
        $koreanCount = count($korean[0]);

        $cjkTotal = $chineseCount + $japaneseCount + $koreanCount;
        if ($cjkTotal === 0) {
            return $this->detectHuEn($clean);
        }

        if ($chineseCount >= $japaneseCount && $chineseCount >= $koreanCount) {
            return 'zh-Hans';
        }

        if ($japaneseCount >= $chineseCount && $japaneseCount >= $koreanCount) {
            return 'ja';
        }

        return 'ko';
    }

    protected function detectHuEn(string $clean): string
    {
        $clean = mb_strtolower($clean);
        $score = 0;

        // Letters that are strongly Hungarian
        $score += 3 * preg_match_all('/[őű]/u', $clean);
        $score += preg_match_all('/[áéíóöúü]/u', $clean);

        $huWords = ['az','és','hogy','nem','egy','van','meg','már','ez','ha','mint','volt','csak','még','vagy','lehet','nagyon','szia','köszönöm','igen','nincs','vagyok','minden','után','előtt','mert','akkor','ami','amit','ezt','azt','pedig','között','hogyan','mikor','kérem'];
        $enWords = ['the','and','is','are','of','to','in','that','it','for','with','you','this','was','not','have','on','as','at','by','from','or','an','can','will','how','what','my','your','feel','free'];

        foreach (preg_split('/[^\p{L}]+/u', $clean, -1, PREG_SPLIT_NO_EMPTY) as $w) {
            if (in_array($w, $huWords, true)) {
                $score += 2;
            }
            if (in_array($w, $enWords, true)) {
                $score -= 2;
            }
        }

        return $score > 0 ? 'hu' : 'en';
    }


    protected function stripSystemTags(string $text): string
    {
        $text = preg_replace('/@\S+/u', '', $text);
        $text = preg_replace('/\{I\d+\}/', '', $text);
        $text = preg_replace('/\[[^\]]*\]/', '', $text);
        $text = preg_replace('/:[a-z0-9_]+:/i', '', $text);
        $text = preg_replace('/<[^>]+>/', '', $text);
        $text = preg_replace('/https?:\/\/\S+/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    protected function getEngine(): EngineInterface
    {
        $engine = $this->settings->get('ani-nya-i18n.engine');

        return match ($engine) {
            'openai' => new OpenAI($this->settings),
            'google' => new GoogleTranslate($this->settings),
            'deepl' => new DeepL($this->settings),
            'yandex' => new YandexTranslate($this->settings),
            'baidu' => new BaiduTranslate($this->settings),
            'libre' => new LibreTranslate($this->settings),
            'free_google' => new FreeGoogleTranslate($this->settings),
            default => new OpenAI($this->settings),
        };
    }
}
