<?php namespace Keypoint\LaravelLocalizationHelpers\Factory;

interface TranslatorInterface
{
    public function translate(string|array $translatable, string $toLang, ?string $fromLang = null): string|array|null;
}
