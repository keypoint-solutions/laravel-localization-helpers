<?php namespace Keypoint\LaravelLocalizationHelpers\Factory;

use Keypoint\LaravelLocalizationHelpers\Translator\MicrosoftTranslator\Client;
use Keypoint\LaravelLocalizationHelpers\Translator\MicrosoftTranslator\Exception;

class TranslatorMicrosoft implements TranslatorInterface
{
    protected Client $msTranslator;

    /**
     * @param  array  $config
     *
     * @throws Exception
     */
    public function __construct(array $config)
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
     * @param  string|array  $translatable  Sentence or word to translate
     * @param  string  $toLang  Target language
     * @param  string|null  $fromLang  Source language (if set to null, translator will try to guess)
     * @param  array|null  $options
     * @return string|array|null The translated sentence or null if an error occurs
     */
    public function translate(
        string|array $translatable,
        string $toLang,
        string|null $fromLang = null,
        array|null $options = []
    ): string|array|null {
        return $this->msTranslator->translate($translatable, $toLang, $fromLang, $options['category'] ?? 'general', $options);
    }
}


