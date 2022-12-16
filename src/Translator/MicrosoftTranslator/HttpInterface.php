<?php

namespace Keypoint\LaravelLocalizationHelpers\Translator\MicrosoftTranslator;

interface HttpInterface
{
    /**
     * GET API endpoint
     *
     * @param string $url
     * @param ?array $parameters
     *
     * @return array
     */
    public function get(string $url, ?array $parameters = []): array;

    /**
     * POST API endpoint
     *
     * @param string $url
     * @param ?array $parameters
     *
     * @return array
     */
    public function post(string $url, ?array $parameters = []): array;

    /**
     * PUT API endpoint
     *
     * @param string $url
     * @param ?array $parameters
     *
     * @return array
     */
    public function put(string $url, ?array $parameters = []): array;

    /**
     * DELETE API endpoint
     *
     * @param string $url
     * @param ?array $parameters
     *
     * @return array
     */
    public function delete(string $url, ?array $parameters = []): array;
}
