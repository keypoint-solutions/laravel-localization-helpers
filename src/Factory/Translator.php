<?php namespace Keypoint\LaravelLocalizationHelpers\Factory;

class Translator implements TranslatorInterface
{
    protected TranslatorInterface $translator;

    /**
     * @param  string  $translator  The translation service name
     * @param  array  $config  The configuration array for the translation service
     *
     * @throws Exception
     */
    public function __construct(string $translator, array $config = [])
    {
        $class = 'Keypoint\LaravelLocalizationHelpers\Factory\Translator'.$translator;
        $translator = new $class($config);

        if (!$translator instanceof TranslatorInterface) {
            //@codeCoverageIgnoreStart
            // Cannot test a Fatal Error in PHPUnit by invoking non existing class...
            throw new Exception('Provided translator does not implement TranslatorInterface');
            //@codeCoverageIgnoreEnd
        }

        $this->translator = $translator;
    }

    public function translate(
        string|array $translatable,
        string $toLang,
        string|null $fromLang = null,
        array|null $options = []
    ): string|array|null {
        return $this->translator->translate($translatable, $toLang, $fromLang, $options);
    }

    /**
     * Return the used translator
     *
     * @return TranslatorInterface
     */
    public function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }
}


