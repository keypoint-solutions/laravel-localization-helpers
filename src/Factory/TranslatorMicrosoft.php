<?php namespace Keypoint\LaravelLocalizationHelpers\Factory;

use Keypoint\LaravelLocalizationHelpers\Translator\MicrosoftTranslator\Client;

class TranslatorMicrosoft implements TranslatorInterface
{
    protected $msTranslator;

    /**
     * @param array $config
     *
     * @throws \Keypoint\LaravelLocalizationHelpers\Factory\Exception
     */
    public function __construct($config)
    {
        if ((isset($config['client_key'])) && (!is_null($config['client_key']))) {
            $client_key = $config['client_key'];
        } else {
            $env = (isset($config['env_name_client_key'])) ? $config['env_name_client_key'] : 'LLH_MICROSOFT_TRANSLATOR_CLIENT_KEY';

            if (($client_key = getenv($env)) === false) {
                throw new Exception('Please provide a client_key for Microsoft Translator service');
            }
        }

        $this->msTranslator = new Client([
            'api_client_key' => $client_key,
        ]);
    }

    /**
     * @param string|array $translatable Sentence or word to translate
     * @param string $toLang Target language
     * @param null $fromLang Source language (if set to null, translator will try to guess)
     *
     * @return string|array|null The translated sentence or null if an error occurs
     * @throws \Translator\MicrosoftTranslator\Exception
     */
    public function translate(string|array $translatable, string $toLang, ?string $fromLang = null): string|array|null
    {
        try {
            return $this->msTranslator->translate($translatable, $toLang, $fromLang);
        } catch (\Translator\MicrosoftTranslator\Exception $e) {
            if (!(strpos($e->getMessage(), 'Unable to generate a new access token') === false)) {
                throw $e;
            }
        }

        return null;
    }
}


