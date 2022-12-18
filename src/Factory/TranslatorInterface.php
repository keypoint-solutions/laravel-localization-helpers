<?php namespace Keypoint\LaravelLocalizationHelpers\Factory;

interface TranslatorInterface
{
    public function translate(
        string|array $translatable,
        string $toLang,
        string|null $fromLang = null,
        array|null $options = []
    ): string|array|null;
}
