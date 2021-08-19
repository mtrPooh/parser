<?php

namespace App\Feeds\Utils;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;

class HttpClient
{
    /**
     * @var Client Asynchronous http client
     */
    private Client $client;
    /**
     * @var Response Object that contains information about the last successful response of the server to an http request
     */
    private Response $response;
    /**
     * @var CookieJar Cookie object
     */
    private CookieJar $cookie_jar;
    /**
     * @var string Contains the domain name of the site for which user cookies will be set
     */
    private string $domain;
    /**
     * @var array Contains an array of custom headers
     */
    private array $headers = [];
    /**
     * @var int Defines the request processing timeout in seconds
     */
    private int $timeout_s = 60;
    /**
     * @var string|null Proxy server address
     */
    private ?string $proxy = null;

    public function __construct( $source )
    {
        $this->setDomain( $source );

        $this->client = new Client( [ 'cookies' => true, 'verify' => false ] );
        $this->cookie_jar = new CookieJar();
    }

    /**
     * @param int $timeout Sets the waiting time for a response to an http request
     */
    public function setRequestTimeOut( int $timeout ): void
    {
        $this->timeout_s = $timeout;
    }

    /**
     * @return Client Returns an object of the client class
     */
    public function getHttpClient(): Client
    {
        return $this->client;
    }

    /**
     * @param Response $response Sets an object with information about the response to the last successful http request
     */
    public function setResponse( Response $response ): void
    {
        $this->response = $response;
    }

    /**
     * @return Response Returns an object with information about the response to the last successful http request
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * Sets the http request header
     * @param string $name Title name
     * @param string $value Header value
     */
    public function setHeader( string $name, string $value ): void
    {
        $this->headers[ $name ] = $value;
    }

    /**
     * @param array $headers Sets a set of http request headers
     */
    public function setHeaders( array $headers ): void
    {
        $this->headers = array_merge( $this->headers, $headers );
    }

    /**
     * Returns the value of the specified header
     * @param string $name Title name
     * @return string|null Header value
     */
    public function getHeader( string $name ): ?string
    {
        return $this->headers[ $name ] ?? null;
    }

    /**
     * @return array Returns an array of headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Returns the value of the specified header from the response to the last successful http request
     * @param string $name
     * @return string
     */
    public function getResponseHeader( string $name ): string
    {
        $header = $this->getResponse()->getHeader( $name );
        return $header ? array_shift( $header ) : '';
    }

    /**
     * Deletes the title by its name
     * @param string $name Title name
     */
    public function removeHeader( string $name ): void
    {
        unset( $this->headers[ $name ] );
    }

    /**
     * Removes all headers
     */
    public function removeHeaders(): void
    {
        $this->headers = [];
    }

    /**
     * Sets a new cookie
     * @param string $name Cookie name
     * @param string $value Cookie value
     */
    public function setCookie( string $name, string $value ): void
    {
        $this->cookie_jar->setCookie( new SetCookie( [ 'Name' => $name, 'Value' => $value, 'Domain' => $this->domain ] ) );
        $this->cookie_jar->setCookie( new SetCookie( [ 'Name' => $name, 'Value' => $value, 'Domain' => 'www.' . $this->domain ] ) );
    }

    /**
     * Returns the value of the specified cookie
     * @param string $name Cookie name
     * @return string Cookie value
     */
    public function getCookie( string $name ): string
    {
        $cookie = $this->cookie_jar->getCookieByName( $name );
        if ( $cookie ) {
            return $cookie->getValue();
        }
        return '';
    }

    /**
     * Returns an array containing an associative array with information about all active cookies
     * [
     * 'Name' => Cookie name
     * 'Value' => Cookie value
     * 'Domain' => The domain for which the cookie was installed
     * ]
     */
    public function getCookies(): array
    {
        return $this->cookie_jar->toArray();
    }

    /**
     * Deletes all cookies
     */
    public function removeCookies(): void
    {
        $this->cookie_jar->clear();
    }

    /**
     * @param string|null $proxy Sets the proxy server address
     */
    public function setProxy( ?string $proxy ): void
    {
        $this->proxy = $proxy;
    }

    /**
     * Sends an http request
     * @param string $link The link where the request will be sent
     * @param array $params Request parameters
     * @param string $method Request method
     * @param string $type_params Type of sending parameters
     */
    public function request( string $link, array $params = [], string $method = 'GET', string $type_params = '' ): PromiseInterface
    {
        $request_params = [
            'headers' => $this->headers,
            'timeout' => $this->timeout_s,
            'proxy' => $this->proxy,
            'cookies' => $this->cookie_jar
        ];

        if ( $method === 'POST' && count( $params ) ) {
            if ( $type_params === 'request_payload' ) {
                $request_params[ 'json' ] = $params;
            }
            else {
                $request_params[ 'form_params' ] = $params;
            }
        }
        return $this->client->requestAsync( $method, $link, $request_params );
    }

    /**
     * @param string $source Sets the domain for which user cookies will be created
     */
    private function setDomain( string $source ): void
    {
        $source_path = explode( '/', $source );
        if ( !isset( $source_path[ 2 ] ) ) {
            $source_path[ 2 ] = $source_path[ 0 ];
        }
        $this->domain = str_replace( 'www.', '', $source_path[ 2 ] );
    }
}