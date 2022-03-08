<?php


namespace Beyond\Supports\Traits;


use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 *
 * @property $timeout
 *
 * Trait HttpRequest
 * @package SmartHttp\Traits
 */
trait HasHttpRequest
{
    /**
     * Http client.
     *
     * @var Client|null
     */
    protected $httpClient = null;

    /**
     * Http client options.
     *
     * @var array
     */
    protected $httpOptions = [];

    /**
     * 设置的中间件集合
     *
     * @var array
     */
    protected $middleware = [];

    /**
     * @var HandlerStack
     */
    protected $handlerStack;

    /**
     * Send a GET request.
     *
     *
     * @param string $endpoint
     * @param array $query
     * @param array $headers
     * @return ResponseInterface
     */
    public function get(string $endpoint, array $query = [], array $headers = [])
    {
        return $this->request('get', $endpoint, compact('headers', 'query'));
    }

    /**
     * Send a POST request.
     *
     * @param string $endpoint
     * @param string|array $data
     *
     * @param array $options
     * @return ResponseInterface
     */
    public function post(string $endpoint, $data, array $options = [])
    {
        if (!is_array($data)) {
            $options['body'] = $data;
        } else {
            $options['form_params'] = $data;
        }

        return $this->request('post', $endpoint, $options);
    }

    /**
     * Send request.
     *
     * @param string $method
     * @param string $endpoint
     * @param array $options
     * @return ResponseInterface
     */
    public function request(string $method, string $endpoint, array $options = [])
    {
        return $this->getHttpClient()->{$method}($endpoint, $options);
    }

    /**
     * Send request.
     *
     * @param string $method
     * @param string $endpoint
     * @param array $options
     * @return array|string
     */
    public function requestUnwrap(string $method, string $endpoint, array $options = [])
    {
        return $this->unwrapResponse($this->getHttpClient()->{$method}($endpoint, $options));
    }

    /**
     * Set http client.
     *
     * @param Client $client
     * @return $this
     */
    public function setHttpClient(Client $client): self
    {
        $this->httpClient = $client;

        return $this;
    }

    /**
     * Return http client.
     */
    public function getHttpClient(): Client
    {
        if (is_null($this->httpClient)) {
            $this->httpClient = $this->getDefaultHttpClient();
        }

        return $this->httpClient;
    }

    /**
     * Get default http client.
     *
     */
    private function getDefaultHttpClient(): Client
    {
        return new Client($this->getOptions());
    }

    /**
     * setBaseUri.
     *
     * @param string $url
     * @return $this
     */
    public function setBaseUri(string $url): self
    {
        if (property_exists($this, 'baseUri')) {
            $parsedUrl = parse_url($url);

            $this->baseUri = ($parsedUrl['scheme'] ?? 'http') . '://'.
                $parsedUrl['host'] . (isset($parsedUrl['port']) ? (':' . $parsedUrl['port']) : '');
        }

        return $this;
    }

    /**
     * getBaseUri.
     */
    public function getBaseUri(): string
    {
        return property_exists($this, 'baseUri') ? $this->baseUri : '';
    }

    /**
     * getTimeout
     *
     * @return float
     */
    public function getTimeout(): float
    {
        return property_exists($this, 'timeout') ? $this->timeout : 5.0;
    }

    /**
     * setTimeout
     *
     * @param float $timeout
     * @return $this
     */
    public function setTimeout(float $timeout): self
    {
        if (property_exists($this, 'timeout')) {
            $this->timeout = $timeout;
        }

        return $this;
    }

    /**
     * getConnectTimeout
     *
     * @return float
     */
    public function getConnectTimeout(): float
    {
        return property_exists($this, 'connectTimeout') ? $this->connectTimeout : 3.0;
    }

    /**
     * setConnectTimeout
     *
     * @param float $connectTimeout
     * @return $this
     */
    public function setConnectTimeout(float $connectTimeout): self
    {
        if (property_exists($this, 'connectTimeout')) {
            $this->connectTimeout = $connectTimeout;
        }

        return $this;
    }

    /**
     * Get default options.
     *
     */
    public function getOptions(): array
    {
        return array_merge([
            'base_uri'        => $this->getBaseUri(),
            'timeout'         => $this->getTimeout(),
            'connect_timeout' => $this->getConnectTimeout(),
            'handler'         => $this->getHandlerStack(),
        ], $this->getHttpOptions());
    }

    /**
     * setOptions.
     *
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options): self
    {
        return $this->setHttpOptions($options);
    }

    /**
     * getHttpOptions
     *
     * @return array
     */
    public function getHttpOptions(): array
    {
        return $this->httpOptions;
    }

    /**
     * setHttpOptions
     *
     * @param array $httpOptions
     * @return $this
     */
    private function setHttpOptions(array $httpOptions): self
    {
        $this->httpOptions = $httpOptions;

        return $this;
    }

    /**
     * Convert response.
     *
     * @param ResponseInterface $response
     * @return array|string
     *
     */
    public function unwrapResponse(ResponseInterface $response)
    {
        $contentType = $response->getHeaderLine('Content-Type');
        $contents = $response->getBody()->getContents();

        if (false !== stripos($contentType, 'json') || stripos($contentType, 'javascript')) {
            return json_decode($contents, true);
        } elseif (false !== stripos($contentType, 'xml')) {
            return json_decode(json_encode(simplexml_load_string($contents, 'SimpleXMLElement', LIBXML_NOCDATA), JSON_UNESCAPED_UNICODE), true);
        }

        return $contents;
    }

    /**
     * 构建 HandlerStack
     *
     * @return HandlerStack
     */
    public function getHandlerStack()
    {
        if ($this->handlerStack) {
            return $this->handlerStack;
        }

        $this->handlerStack = HandlerStack::create();

        foreach ($this->middleware as $name => $middleware) {
            $this->handlerStack->push($middleware, $name);
        }

        return $this->handlerStack;
    }

    /**
     * @param HandlerStack $handlerStack
     *
     * @return $this
     */
    public function setHandlerStack(HandlerStack $handlerStack)
    {
        $this->handlerStack = $handlerStack;

        return $this;
    }

    /**
     * 添加一个中间件
     *
     * @param callable $middleware
     * @param string $name
     *
     * @return $this
     */
    public function pushMiddleware(callable $middleware, $name = null)
    {
        if(!is_null($name) === false) {
            return $this;
        }

        if (is_null($name)) {
            array_push($this->middleware, $middleware);
        } else {
            $this->middleware[$name] = $middleware;
        }

        return $this;
    }

    /**
     * 获取所有的中间件
     *
     * @return array
     */
    public function getMiddleware()
    {
        return $this->middleware;
    }

    /**
     * @param int $maxRetries 重试次数
     * @param int $interval  间隔时间
     * @return $this
     */
    public function retry($maxRetries = 3, $interval = 1000)
    {
        $class = new class($maxRetries, $interval) {

            private $maxRetries = 1;  // 1次
            private $interval = 1000; // 1000毫秒

            /**
             *  constructor.
             * @param $maxRetries
             * @param $interval
             */
            public function __construct($maxRetries, $interval)
            {
                $maxRetries > 0 && $this->maxRetries = $maxRetries;
                $interval > 0 && $this->interval = $interval;
            }

            /**
             * retryDecider
             * 返回一个匿名函数, 匿名函数若返回false 表示不重试，反之则表示继续重试
             * @return Closure
             */
            public function retryDecider()
            {
                return function ($retries, Request $request, Response $response = null, RequestException $exception = null) {
                    // 超过最大重试次数，不再重试
                    if ($retries >= $this->maxRetries) {
                        return false;
                    }

                    // 请求失败，继续重试
                    if ($exception instanceof ConnectException) {
                        return true;
                    }

                    if ($response) {
                        // 如果请求有响应，但是状态码大于等于400，继续重试(这里根据自己的业务而定)
                        if ($response->getStatusCode() >= 400) {
                            return true;
                        }
                    }

                    return false;
                };
            }

            /**
             * 返回一个匿名函数，该匿名函数返回下次重试的时间（毫秒）
             * @return Closure
             */
            public function retryDelay()
            {
                return function ($numberOfRetries) {
                    return $this->interval * $numberOfRetries;
                };
            }
        };

        $this->getHandlerStack()->push(Middleware::retry($class->retryDecider(), $class->retryDelay()));

        return $this;
    }
}
