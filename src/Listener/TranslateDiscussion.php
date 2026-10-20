<?php

namespace aniNya\I18n\Listener;

use Flarum\Discussion\Event\Created;
use Flarum\Settings\SettingsRepositoryInterface;
use aniNya\I18n\Service\TranslationService;

class TranslateDiscussion
{
    public function __construct(
        protected TranslationService $translator,
        protected SettingsRepositoryInterface $settings,
    ) {}

    public function handle(Created $event): void
    {
        $discussion = $event->discussion;

        if (! $this->settings->get('ani-nya-i18n.auto_translate')) {
            return;
        }

        $targetLang = $discussion->user?->locale ?? $this->settings->get('ani-nya-i18n.target_lang', 'zh-Hans');

        try {
            $this->translator->translate(
                discussionId: $discussion->id,
                field: 'title',
                text: $discussion->title,
                targetLang: $targetLang
            );

            $content = $discussion->content;
            if (! empty($content)) {
                $this->translator->translate(
                    discussionId: $discussion->id,
                    field: 'content',
                    text: $content,
                    targetLang: $targetLang
                );
            }
        } catch (\Throwable $e) {
            // ignored
        }
	}
}
