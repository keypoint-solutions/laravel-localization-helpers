<?php namespace Keypoint\LaravelLocalizationHelpers\Translator\MicrosoftTranslator;

class Client
{
    const VERSION = "1.0.0";

    /**
     * @var \Keypoint\LaravelLocalizationHelpers\Translator\MicrosoftTranslator\HttpInterface
     *
     * This holds the HTTP Manager
     *
     * You can use your own HTTP Manager by injecting it in the constructor
     * Your HTTP Manager must implement the \MicrosoftTranslator\HttpInterface interface
     */
    private $http;

    /**
     * @param array $config an array of configuration parameters
     * @param \Keypoint\LaravelLocalizationHelpers\Translator\MicrosoftTranslator\HttpInterface|null $http if null, a new Http manager will be used
     *
     * @throws Exception
     */
    public function __construct($config = [], $http = null)
    {
        // Init HTTP Manager
        if (is_null($http)) {
            $http = new Http($config);
        }

        if (!$http instanceof HttpInterface) {
            throw new Exception('HTTP Manager is not an instance of MicrosoftTranslator\\HttpInterface');
        }

        $this->http = $http;
    }

    /**
     * Translates a text string from one language to another.
     *
     * @param string $text Required. A string representing the text to translate. The size of the text must
     *                                 not exceed 10000 characters.
     * @param string $to Required. A string representing the language code to translate the text into.
     * @param string|null $from Optional. A string representing the language code of the translation text.
     * @param string $category Optional. A string containing the category (domain) of the translation. Defaults
     *                                 to "general".
     *
     * The language codes are available at https://msdn.microsoft.com/en-us/library/hh456380.aspx
     *
     * The API endpoint documentation is available at https://msdn.microsoft.com/en-us/library/ff512421.aspx
     *
     * @return string|array
     * @throws \Keypoint\LaravelLocalizationHelpers\Translator\MicrosoftTranslator\Exception
     */
    public function translate(string|array $text, string $to, ?string $from = null, string $category = 'general'): string|array
    {
        // make :parameter not translatable
        $queryParameters = [
            'text' => is_array($text) ? $text : preg_replace('/(:[a-zA-Z0-9_.-]+)/', '<span class="notranslate">$1</span>', $text),
            'from' => $from ?: self::getDefaultLanguage(),
            'to' => $to,
            'textType' => 'html',
            'category' => $category,
        ];

        $endpoint = '/translate?api-version=' . self::getApiVersion();

        // do not translate same language
        if ($to == $queryParameters['from']) {
            return $text;
        }

        $response = $this->post($endpoint, [], $queryParameters + self::getHttpConfig());

        $translation = array_map(fn($t) => preg_replace('/<span class="notranslate">(.+?)<\/span>/', '$1', $t), $response['http_body']);

        return is_array($text) ? $translation : head($translation);
    }

    public static function getDefaultLanguage()
    {
        return config('laravel-localization-helpers.translators.Microsoft.default_language', config('app.fallback_locale'));
    }

    public static function getApiVersion()
    {
        return config('laravel-localization-helpers.translators.Microsoft.api_version');
    }

    /**
     * @param string $endpoint
     * @param array $urlParameters
     * @param string|array $queryParameters
     *
     * @return array
     * @throws \Keypoint\LaravelLocalizationHelpers\Translator\MicrosoftTranslator\Exception
     */
    private function post(string $endpoint, array $urlParameters = [], array $queryParameters = []): array
    {
        $url = $this->buildUrl($endpoint, $urlParameters);

        $result = $this->http->post($url, $queryParameters);

        return $this->getResponse($result);
    }

    /**
     * Build the URL according to endpoint by replacing URL parameters
     *
     * @param string $endpoint
     * @param array $url_parameters
     * @param string|null $special_url
     *
     * @return string
     */
    private function buildUrl($endpoint, $url_parameters = [], $special_url = null)
    {
        foreach ($url_parameters as $key => $value) {
            //@codeCoverageIgnoreStart
            $endpoint = str_replace('{' . $key . '}', $value, $endpoint);
            //@codeCoverageIgnoreEnd
        }

        if (is_null($special_url)) {
            $url = trim(self::getApiBaseUrl(), "/ \t\n\r\0\x0B");
        } else {
            $url = $special_url;
        }
        $url = $url . '/' . trim($endpoint, "/ \t\n\r\0\x0B");

        return $url;
    }

    public static function getApiBaseUrl()
    {
        return config('laravel-localization-helpers.translators.Microsoft.api_base_url', 'https://api.cognitive.microsofttranslator.com');
    }

    /**
     * @param array $result
     *
     * @return array
     * @throws \Keypoint\LaravelLocalizationHelpers\Translator\MicrosoftTranslator\Exception
     */
    private function getResponse(array $result): array
    {
        if ((isset($result['http_code'])) && (substr(strval($result['http_code']), 0, 1) !== '2')) {
            throw new Exception($result);
        }

        if (isset($result['http_body'])) {
            if (!is_null($json = json_decode($result['http_body'], true))) {
                if (isset($json[0]['translations'])) {
                    $result['http_body'] = data_get($json, '0.translations.*.text');
                } else {
                    $result['http_body'] = $json;
                }
            }
        }

        return $result;
    }

    private static function getHttpConfig()
    {
        return [
            'clientKey' => self::getClientKey(),
            'apiRegion' => self::getApiRegion(),
            'clientVersion' => self::VERSION,
        ];
    }

    public static function getClientKey()
    {
        return config('laravel-localization-helpers.translators.Microsoft.client_key', null);
    }

    public static function getApiRegion()
    {
        return config('laravel-localization-helpers.translators.Microsoft.region', null);
    }

    /**
     * @param string $endpoint
     * @param array $url_parameters
     * @param array $query_parameters
     * @param null $special_url new url instead of api url
     *
     * @return array
     * @throws \Keypoint\LaravelLocalizationHelpers\Translator\MicrosoftTranslator\Exception
     */
    private function get($endpoint, $url_parameters = [], $query_parameters = [], $special_url = null): array
    {
        $url = $this->buildUrl($endpoint, $url_parameters, $special_url);

        $result = $this->http->get($url, $query_parameters);

        return $this->getResponse($result);
    }
}

