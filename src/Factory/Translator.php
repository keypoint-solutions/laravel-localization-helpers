<?php namespace Keypoint\LaravelLocalizationHelpers\Factory;

class Translator implements TranslatorInterface
{
    /** @var TranslatorInterface */
    protected $translator;

    /**
     * @param string $translator The translation service name
     * @param array $config The configuration array for the translation service
     *
     * @throws \Factory\Exception
     */
    public function __construct($translator, $config = [])
    {
        $class = 'Factory\Translator' . $translator;
        $translator = new $class($config);

        if (!$translator instanceof TranslatorInterface) {
            //@codeCoverageIgnoreStart
            // Cannot test a Fatal Error in PHPUnit by invoking non existing class...
            throw new Exception('Provided translator does not implement TranslatorInterface');
            //@codeCoverageIgnoreEnd
        }

        $this->translator = $translator;
    }

    public function translate(string|array $translatable, string $toLang, ?string $fromLang = null): string|array|null
    {
        return $this->translator->translate($translatable, $toLang, $fromLang);
    }

    /**
     * Return the used translator
     *
     * @return \Factory\TranslatorInterface
     */
    public function getTranslator()
    {
        return $this->translator;
    }
}


