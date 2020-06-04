<?php declare(strict_types=1);
/**
 * Copyright © 2019 MultiSafepay, Inc. All rights reserved.
 * See DISCLAIMER.md for disclaimer details.
 */

namespace MultiSafepay\Client;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use MultiSafepay\Api\Base\RequestBodyInterface;
use MultiSafepay\Api\Base\Response as ApiResponse;
use MultiSafepay\Exception\ApiException;
use MultiSafepay\Exception\InvalidApiKeyException;
use MultiSafepay\Exception\StrictModeException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class Client
 * @package MultiSafepay
 */
class Client
{
    const LIVE_URL = 'https://api.multisafepay.com/v1/json/';

    const TEST_URL = 'https://testapi.multisafepay.com/v1/json/';

    const METHOD_POST = 'POST';

    const METHOD_GET = 'GET';

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $url;

    /**
     * @var ClientInterface
     */
    protected $httpClient;

    /**
     * @var RequestFactoryInterface|null
     */
    private $requestFactory;

    /**
     * @var StreamFactoryInterface|null
     */
    private $streamFactory;

    /**
     * @var string
     */
    private $locale = 'en_US';
    /**
     * @var bool
     */
    private $strictMode;

    /**
     * Client constructor.
     * @param string $apiKey
     * @param bool $isProduction
     * @param ClientInterface|null $httpClient
     * @param RequestFactoryInterface|null $requestFactory
     * @param StreamFactoryInterface|null $streamFactory
     * @param string $locale
     * @param bool $strictMode
     */
    public function __construct(
        string $apiKey,
        bool $isProduction,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        string $locale = 'en_US',
        bool $strictMode = false
    ) {
        $this->initApiKey($apiKey);
        $this->url = $isProduction ? self::LIVE_URL : self::TEST_URL;
        $this->httpClient = $httpClient ?: Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory ?: Psr17FactoryDiscovery::findStreamFactory();
        $this->locale = $locale;
        $this->strictMode = $strictMode;
    }

    /**
     * @param string $endpoint
     * @param RequestBodyInterface|null $requestBody
     * @param array $context
     * @return ApiResponse
     * @throws ClientExceptionInterface
     */
    public function createPostRequest(
        string $endpoint,
        RequestBodyInterface $requestBody = null,
        array $context = []
    ): ApiResponse {
        $client = $this->httpClient;
        $requestFactory = $this->getRequestFactory();
        $url = $this->getRequestUrl($endpoint);
        $request = $requestFactory->createRequest(self::METHOD_POST, $url)
            ->withBody($this->createBody($this->getRequestBody($requestBody)))
            ->withHeader('api_key', $this->apiKey)
            ->withHeader('accept-encoding', 'application/json')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Length', strlen($this->getRequestBody($requestBody)));

        $context['headers'] = $request->getHeaders();
        $context['request_body'] = $this->getRequestBody($requestBody);
        $httpResponse = $client->sendRequest($request);
        return ApiResponse::withJson($httpResponse->getBody()->getContents(), $context);
    }

    /**
     * @param RequestBodyInterface $requestBody
     * @return string
     * @throws StrictModeException
     */
    private function getRequestBody(RequestBodyInterface $requestBody): string
    {
        $requestBody->setStrictMode($this->strictMode);
        return json_encode($requestBody->getData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param string $endpoint
     * @param array $parameters
     * @param array $context
     * @return ApiResponse
     * @throws ClientExceptionInterface
     */
    public function createGetRequest(string $endpoint, array $parameters = [], array $context = []): ApiResponse
    {
        $url = $this->getRequestUrl($endpoint, $parameters);

        $client = $this->httpClient;
        $requestFactory = $this->getRequestFactory();
        $request = $requestFactory->createRequest(self::METHOD_GET, $url)
            ->withHeader('api_key', $this->apiKey)
            ->withHeader('accept-encoding', 'application/json');

        $httpResponse = $client->sendRequest($request);
        $context['headers'] = $request->getHeaders();
        $context['request_params'] = $parameters;
        return ApiResponse::withJson($httpResponse->getBody()->getContents(), $context);
    }

    /**
     * Get the request factory
     *
     * @return RequestFactoryInterface
     */
    public function getRequestFactory(): RequestFactoryInterface
    {
        if (!$this->requestFactory instanceof RequestFactoryInterface) {
            $this->requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        }

        return $this->requestFactory;
    }

    /**
     * @param string $endpoint
     * @param array $parameters
     * @return string
     */
    public function getRequestUrl(string $endpoint, $parameters = []): string
    {
        $parameters['locale'] = $this->locale;
        $endpoint .= '?' . http_build_query($parameters);
        return $this->url . $endpoint;
    }

    /**
     * Create a body used for the Client
     *
     * @param string $body
     * @return StreamInterface
     */
    public function createBody(string $body): StreamInterface
    {
        if (!$this->streamFactory instanceof StreamFactoryInterface) {
            $this->streamFactory = Psr17FactoryDiscovery::findStreamFactory();
        }

        return $this->streamFactory->createStream($body);
    }

    /**
     * @return ClientInterface
     */
    public function getHttpClient(): ClientInterface
    {
        return $this->httpClient;
    }

    /**
     * @param bool $strictMode
     * @return Client
     */
    public function setStrictMode(bool $strictMode): Client
    {
        $this->strictMode = $strictMode;
        return $this;
    }

    /**
     * @param string $apiKey
     */
    private function initApiKey(string $apiKey)
    {
        if (strlen($apiKey) < 5) {
            throw new InvalidApiKeyException('Invalid API key');
        }

        $this->apiKey = $apiKey;
    }
}
