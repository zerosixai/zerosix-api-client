<?php

/**
 * Class ZeroSix_API_Client
 * @package ZeroSix_API_Client
 * @author ZeroSix.ai
 * @version 1.0
 * @access public
 * @see https://zerosix.ai/api/docs
 */

class ZeroSix_API_Client
{
    const OAUTH_VERSION = '1.0';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_PATCH = 'PATCH';
    const METHOD_GET = 'GET';
    const METHOD_DELETE = 'DELETE';
 
    /**
     * @var string
     */
    private $apiKey;
 
    /**
     * @var string
     */
    private $apiSecret;
 
    /**
     * @var string
     */
    private $apiDomain;
 
    /**
     * @var string
     */
    private $apiProtocol;

    /**
     * ZeroSix_API_Client constructor.
     * @param $apiKey
     * @param $apiSecret
     * @param string $apiDomain
     * @param bool $useHttps
     */
    public function __construct($apiKey, $apiSecret, $apiDomain = 'zerosix.ai/wp-json/api/v1/', $useHttps = true)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->apiDomain = $apiDomain;
        $this->apiProtocol = ($useHttps === true) ? 'https' : 'http';
    }
 

    /** Get the requested URI
     * @param string $uri
     * @param array  $params
     * @return string
     */
    private function getUri($uri, array $params = [])
    {
        return sprintf(
            '%s://%s%s?%s',
            $this->apiProtocol,
            $this->apiDomain,
            $uri,
            http_build_query($params)
        );
    }
 
    /** Handle the request before it sent
     * @param string            $uri
     * @param string            $method
     * @param null|array|string $params
     *
     * @return array
     */
    public function prepareRequest($uri, $method, $params = null)
    {
        $requestParams = $this->getRequestParams($uri, $params);
        $oauth = $this->getOauth($requestParams);
        $requestHeader = $oauth->getRequestHeader($method, $requestParams['uri'], $requestParams['params']);

        return [
            'requestParams' => $requestParams,
            'requestHeader' => $requestHeader
        ];
    }

 
    /** Get all the requested params
     * @param string $uri
     * @param null|array|string $params
     *
     * @return array
     */
    private function getRequestParams($uri, $params = null)
    {
        return [
            'consumerKey' => $this->apiKey,
            'consumerSecret' => $this->apiSecret,
            'nonce' => md5(uniqid(mt_rand(), true)),
            'timestamp' => time(),
            'oauthVersion' => self::OAUTH_VERSION,
            'uri' => $uri,
            'params' => is_array($params) ? $params : []
        ];
    }
 
    /** Creating OAuth1.0 object
     * @param array $requestParams
     * @return OAuth
     */
    private function getOauth(array $requestParams)
    {
        $oauth = new OAuth($requestParams['consumerKey'], $requestParams['consumerSecret']);
        $oauth->setNonce($requestParams['nonce']);
        $oauth->setTimestamp($requestParams['timestamp']);
        $oauth->setVersion($requestParams['oauthVersion']);

        return $oauth;
    }


    /** Getting the signed header from OAuth 1.0
     * @param string $uri
     * @param string $method
     * @return mixed
     */
    private function getSignedHeader($uri, $method) {
        $request = $this->prepareRequest(
            $this->getUri($uri),
            $method
        );

        return $request['requestHeader'];
    }


    /** Make a request to ZeroSix API
     * @param string $uri - Full signed URL
     * @param string $method - HTTP Method
     * @param array $payload - HTTP Request parameters
     * @return array|mixed|object
     */
    private function call($uri, $method, $payload)
    {
        // Parse the params
        $params = $this->parseParams($payload);

        // Building the requested headers
        $requestHeader = [
            'Content-Type: application/json',
            'Content-Length: '.mb_strlen($params),
        ];

        // cURL instance
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $uri);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($curl, CURLOPT_ENCODING, "");
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_1);

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $requestHeader);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);

        if ($method == self::METHOD_POST) {
            curl_setopt($curl, CURLOPT_POST, true);
        }

        $response = curl_exec($curl);

        if ($errorNo = curl_errno($curl)) {
            echo "Curl error ($errorNo): ".curl_strerror($errorNo);
        }

        curl_close($curl);


        return json_decode($response);

    }

    /** Parsing the params of the request
     * @param array $params
     * @return false|mixed|string
     */
    private function parseParams($params)
    {
        return json_encode($params);
    }

    /** Parsing the query string for OAuth 1.0a 'one-legged' authentication
     * @param string $url
     * @return mixed
     */
    private function buildQueryString($url)
    {
        // Removing the 'OAuth' first word
        $buildString = explode(' ', $url);
        array_shift($buildString);
        $finalString = implode(' ', $buildString);

        // Building the URI
        $result = str_replace(',', '&', $finalString);
        $result = str_replace('"', '', $result);

        return $result;
    }

    /** Get all products from ZeroSix.ai
     * @return array|mixed|object
     */
    public function getAllProducts()
    {
        // Products endpoint
        $uri = 'products';

        // GET method
        $method = self::METHOD_GET;

        // Getting the signed headers & building the signed string
        $queryUrl = $this->getSignedHeader($uri, $method);
        $signedString = $this->buildQueryString($queryUrl);

        // For getting all the products, no params needed
        $params = array();

        // Signed url
        $signedUrl = $this->getUri($uri) . $signedString;

        return $this->call($signedUrl, $method, $params);
    }

    /** Filter products by attributes
     * @param array $params
     * @return array|mixed|object
     */
    public function filterProducts($params)
    {
        // Products endpoint
        $uri = 'products';

        // GET method
        $method = self::METHOD_GET;

        // Getting the signed headers & building the signed string
        $queryUrl = $this->getSignedHeader($uri, $method);
        $signedString = $this->buildQueryString($queryUrl);

        // Signed url
        $signedUrl = $this->getUri($uri) . $signedString;

        return $this->call($signedUrl, $method, $params);
    }

    /** Filter products by attribute
     * @param integer $productId
     * @return array|mixed|object
     */
    public function getProductById($productId)
    {
        // Products endpoint
        $uri = 'products/' . $productId;

        // GET method
        $method = self::METHOD_GET;

        // Getting the signed headers & building the signed string
        $queryUrl = $this->getSignedHeader($uri, $method);
        $signedString = $this->buildQueryString($queryUrl);

        $params = array();

        // Signed url
        $signedUrl = $this->getUri($uri) . $signedString;

        return $this->call($signedUrl, $method, $params);
    }


    /** Creating an order in ZeroSix
     * @param integer $productId  - ProductId
     * @param integer $numOfInstances  - Number of instances we want to create
     * @param string $fileUrl Optional. URL of the file to be loaded on the machines.
     * @param string $scriptUrl  Optional. URL of the script file to be executed on the machines.
     * @return array|mixed|object
     */
    public function createOrder($productId, $numOfInstances, $fileUrl = null, $scriptUrl = null)
    {
        // Orders endpoint
        $uri = 'orders';

        // PUT method
        $method = self::METHOD_PUT;

        // Getting the signed headers & building the signed string
        $queryUrl = $this->getSignedHeader($uri, $method);
        $signedString = $this->buildQueryString($queryUrl);

        // Sending the request with the args
        $args = array(
            'product_id' => $productId,
            'instances' => $numOfInstances,
            'file_url' => $fileUrl,
            'script_url' =>$scriptUrl
        );

        // Signed url
        $signedUrl = $this->getUri($uri) . $signedString;

        return $this->call($signedUrl, $method, $args);
    }


    /** Completes an order and all the corresponding instances
     * @param integer $orderId
     * @return array|mixed|object
     */
    public function completeOrder($orderId)
    {
        // Orders endpoint
        $uri = 'orders/' . $orderId;

        // DELETE method
        $method = self::METHOD_DELETE;

        // Getting the signed headers & building the signed string
        $queryUrl = $this->getSignedHeader($uri, $method);
        $signedString = $this->buildQueryString($queryUrl);

        // Sending the request with the args
        $args = array();

        // Signed url
        $signedUrl = $this->getUri($uri) . $signedString;

        return $this->call($signedUrl, $method, $args);
    }

    /** Get all the orders made by you.
     * @return array|mixed|object
     */
    public function getAllOrders()
    {
        // Orders endpoint
        $uri = 'orders';

        // GET method
        $method = self::METHOD_GET;

        // Getting the signed headers & building the signed string
        $queryUrl = $this->getSignedHeader($uri, $method);
        $signedString = $this->buildQueryString($queryUrl);

        // Sending the request with the args
        $args = array();

        // Signed url
        $signedUrl = $this->getUri($uri) . $signedString;

        return $this->call($signedUrl, $method, $args);
    }


    /** Get all the orders made by you.
     * @return array|mixed|object
     */
    public function getOrderById($orderId)
    {
        // Orders endpoint
        $uri = 'orders/' . $orderId;

        // GET method
        $method = self::METHOD_GET;

        // Getting the signed headers & building the signed string
        $queryUrl = $this->getSignedHeader($uri, $method);
        $signedString = $this->buildQueryString($queryUrl);

        // Sending the request with the args
        $args = array();

        // Signed url
        $signedUrl = $this->getUri($uri) . $signedString;

        return $this->call($signedUrl, $method, $args);
    }

    /** Get the credentials of orderId
     *  Notice: In order to get the credentials, we need the order_key and not the order_id.
     * @param string $orderKey
     * @return array|mixed|object
     */
    public function getCredentials($orderKey)
    {
        // Credentials endpoint
        $uri = 'credentials/' . $orderKey;

        // GET method
        $method = self::METHOD_GET;

        // Getting the signed headers & building the signed string
        $queryUrl = $this->getSignedHeader($uri, $method);
        $signedString = $this->buildQueryString($queryUrl);

        // Sending the request with the args
        $args = array();

        // Signed url
        $signedUrl = $this->getUri($uri) . $signedString;

        return $this->call($signedUrl, $method, $args);
    }
}


// Config params
$config = array(
    'consumer_key' => 'CONSUMER KEY',
    'consumer_secret' => 'SECRET KEY',
);

// Attributes to filter products
$params = array(
    "relation" => "greater",
    "attributes" =>  array(
        "gpu" => "nvidia-1080ti",
        "geo" => "europe",
        "ram" => "8gb"
    )
);

// Connect to ZeroSix API
$apiClient = new ZeroSix_API_Client($config['consumer_key'], $config['consumer_secret']);

// Get all listed products in ZeroSix
//$result = $apiClient->getAllProducts();

// Filter products by attributes
//$result = $apiClient->filterProducts($params);

// Get product by ID
//$result = $apiClient->getProductById($productId);

// Create a new order
//$result = $apiClient->createOrder($productId, 3, "file_url", "script_url");

// Complete a order
//$result = $apiClient->completeOrder($orderId);

// Get all your orders
//$result = $apiClient->getAllOrders();

// Get order by ID
//$result = $apiClient->getOrderById(1840);

// Get the credentials by OrderKey
//$result = $apiClient->getCredentials($orderKey);

// Print the results
//print_r($result);




?>

