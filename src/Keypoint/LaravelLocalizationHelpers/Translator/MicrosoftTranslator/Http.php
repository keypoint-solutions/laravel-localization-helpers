<?php namespace Keypoint\LaravelLocalizationHelpers\Translator\MicrosoftTranslator;

use Illuminate\Support\Arr;

class Http implements HttpInterface
{
    /**
     * Array of configuration parameters managed by this class
     *
     * @var array
     */
    private $config_keys = [
        'http_timeout',
        'http_proxy_host',
        'http_proxy_type',
        'http_proxy_auth',
        'http_proxy_port',
        'http_proxy_user',
        'http_proxy_pass',
        'http_user_agent',
    ];

    /**
     * Timeout for API requests
     *
     * @var int
     */
    private $http_timeout = 10;

    /**
     * An IP or hostname to use for the proxy
     * Let null for direct connexion
     *
     * @var string|null
     */
    private $http_proxy_host = null;

    /**
     * One of these constants :
     * - CURLPROXY_HTTP (default)
     * - CURLPROXY_SOCKS4
     * - CURLPROXY_SOCKS5
     *
     * @var int|null
     */
    private $http_proxy_type = null;

    /**
     * One of these constants:
     * - CURLAUTH_BASIC (default)
     * - CURLAUTH_NTLM
     *
     * @var int|null
     */
    private $http_proxy_auth = null;

    /**
     * The proxy port (default is 3128)
     *
     * @var int|null
     */
    private $http_proxy_port = 3128;

    /**
     * The username to connect to proxy
     *
     * @var string|null
     */
    private $http_proxy_user = null;

    /**
     * The password to connect to proxy
     *
     * @var string|null
     */
    private $http_proxy_pass = null;

    /**
     * The user agent used to make requests
     *
     * @var string
     */
    private $http_user_agent = 'Keypoint MicrosoftTranslator v%VERSION%';

    /**
     * @param array $config
     */
    public function __construct($config)
    {
        foreach ($this->config_keys as $key) {
            if (isset($config[$key])) {
                $this->$key = $config[$key];
            }
        }
    }

    /**
     * Check the http_code in the response array and tell whether if is 200 or 201
     *
     * @param array $result
     *
     * @return bool
     */
    public static function isRequestOk($result)
    {
        return in_array(@$result['http_code'], [200, 201]);
    }

    /**
     * GET API endpoint
     *
     * @param string $url
     * @param ?array $parameters
     *
     * @return array
     */
    public function get(string $url, ?array $parameters = []): array
    {
        return $this->doApiCall($url, 'GET', $parameters);
    }

    /**
     * @param string $url
     * @param string $method
     * @param ?array $parameters
     *
     * @return array
     */
    private function doApiCall(string $url, string $method, ?array $parameters = []): array
    {
        $request = [];
        $headers = [];

        $request[CURLOPT_TIMEOUT] = (int)$this->http_timeout;
        $request[CURLOPT_USERAGENT] = str_replace('%VERSION%', $parameters['clientVersion'] ?? '1.0.0', $this->http_user_agent);
        $request[CURLOPT_CUSTOMREQUEST] = $method;

        if ($clientKey = ($parameters['clientKey'] ?? null)) {
            $headers[] = "Ocp-Apim-Subscription-Key: " . $clientKey;
        }

        if ($region = ($parameters['apiRegion'] ?? null)) {
            $headers[] = "Ocp-Apim-Subscription-Region: " . $region;
        }

        if (!empty($queryParameters = Arr::except($parameters, [
            'clientKey', 'apiRegion', 'clientVersion', 'text'
        ]))) {
            $url = $url . '&' . http_build_query($queryParameters);
        }

        if ($method === 'POST' && !empty($parameters['text'])) {
            $body = json_encode(array_map(fn($t) => ['Text' => $t,], (array)$parameters['text']));

            $headers[] = "Content-Length: " . mb_strlen($body);

            $request[CURLOPT_POSTFIELDS] = $body;
        }

        $request[CURLOPT_URL] = $url;

        $headers[] = "Content-Type: application/json";

        if (!empty($this->http_proxy_host)) {
            $request[CURLOPT_PROXY] = $this->http_proxy_host;

            if (!empty($this->http_proxy_port)) {
                $request[CURLOPT_PROXYPORT] = $this->http_proxy_port;
            }

            if (!empty($this->http_proxy_type)) {
                $request[CURLOPT_PROXYTYPE] = $this->http_proxy_type;
            }

            if (!empty($this->http_proxy_auth)) {
                $request[CURLOPT_PROXYAUTH] = $this->http_proxy_auth;
            }

            if (!empty($this->http_proxy_user)) {
                $request[CURLOPT_PROXYUSERPWD] = $this->http_proxy_user . ':' . $this->http_proxy_pass;
            }
        }

        $request[CURLOPT_HTTPHEADER] = $headers;

        $start = microtime(true);

        @list($result, $status_code, $error, $errno) = $this->execCurl($request);

        $end = microtime(true);
        $duration = (int)round(($end - $start) * 1000);

        if ($errno === 0) {
            $return = [
                'http_code' => $status_code,
                'http_body' => $result,
                'duration' => $duration,
            ];
        } else {
            $return = [
                'error_msg' => $error,
                'error_num' => $errno,
                'duration' => $duration,
            ];
        }

        return $return;
    }

    /**
     * Execute the request with cURL
     *
     * Made public for unit tests, you can publicly call it but this method is not really interesting!
     *
     * @param array $config
     *
     * @return array
     */
    public function execCurl(array $config): array
    {
        $config[CURLOPT_VERBOSE] = false;
        $config[CURLOPT_SSL_VERIFYPEER] = false;
        $config[CURLOPT_RETURNTRANSFER] = true;

        if (defined('CURLOPT_IPRESOLVE')) // PHP5.3
        {
            $config[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }

        $ch = curl_init();

        foreach ($config as $key => $value) {
            curl_setopt($ch, $key, $value);
        }

        $result = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $error_code = curl_errno($ch);

        curl_close($ch);

        return [$result, $status_code, $error, $error_code];
    }

    /**
     * POST API endpoint
     *
     * @param string $url
     * @param ?array $parameters
     *
     * @return array
     */
    public function post(string $url, ?array $parameters = []): array
    {
        return $this->doApiCall($url, 'POST', $parameters);
    }

    /**
     * PUT API endpoint
     *
     * @param string $url
     * @param string|array $parameters
     *
     * @return array
     */
    public function put(string $url, ?array $parameters = []): array
    {
        return $this->doApiCall($url, 'PUT', $parameters);
    }

    /**
     * DELETE API endpoint
     *
     * @param string $url
     * @param string|array $parameters
     *
     * @return array
     */
    public function delete(string $url, ?array $parameters = []): array
    {
        return $this->doApiCall($url, 'DELETE', $parameters);
    }
}
