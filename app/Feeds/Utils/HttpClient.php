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
     * @var Client Асинхронный http клиент
     */
    private Client $client;
    /**
     * @var Response Объект, который содержит информацию о последнем успешном ответе сервера на http запрос
     */
    private Response $response;
    /**
     * @var CookieJar Объект cookie
     */
    private CookieJar $cookie_jar;
    /**
     * @var string Содержит доменное имя сайта, для которого будут устанавливаться пользовательские cookie
     */
    private string $domain;
    /**
     * @var array Содержит массив пользовательских заголовков
     */
    private array $headers = [];
    /**
     * @var int Определяет время ожидания обработки запроса в секундах
     */
    private int $timeout_s = 60;
    /**
     * @var string|null Адрес прокси сервера
     */
    private ?string $proxy = null;

    public function __construct( $source )
    {
        $this->setDomain( $source );

        $this->client = new Client( [ 'cookies' => true, 'verify' => false ] );
        $this->cookie_jar = new CookieJar();
    }

    /**
     * @param int $timeout Устанавливает время ожидания ответа на http запрос
     */
    public function setRequestTimeOut( int $timeout ): void
    {
        $this->timeout_s = $timeout;
    }

    /**
     * @return Client Возвращает объект класса клиента
     */
    public function getHttpClient(): Client
    {
        return $this->client;
    }

    /**
     * @param Response $response Устанавливает объект с информацией об ответе на последний успешный http запрос
     */
    public function setResponse( Response $response ): void
    {
        $this->response = $response;
    }

    /**
     * @return Response Возвращает объект с информацией об ответе на последний успешный http запрос
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * Устанавливает заголовок http запроса
     * @param string $name Название заголовка
     * @param string $value Значение заголовка
     */
    public function setHeader( string $name, string $value ): void
    {
        $this->headers[ $name ] = $value;
    }

    /**
     * @param array $headers Устанавливает набор заголовков http запроса
     */
    public function setHeaders( array $headers ): void
    {
        $this->headers = array_merge( $this->headers, $headers );
    }

    /**
     * Возвращает значение указанного заголовка
     * @param string $name Название заголовка
     * @return string|null Значение заголовка
     */
    public function getHeader( string $name ): ?string
    {
        return $this->headers[ $name ] ?? null;
    }

    /**
     * @return array Возвращает массив заголовков
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Возвращает значение указанного заголовка из ответа на последний успешный http запрос
     * @param string $name
     * @return string
     */
    public function getResponseHeader( string $name ): string
    {
        $header = $this->getResponse()->getHeader( $name );
        return $header ? array_shift( $header ) : '';
    }

    /**
     * Удаляет заголовок по его названию
     * @param string $name Название заголовка
     */
    public function removeHeader( string $name ): void
    {
        unset( $this->headers[ $name ] );
    }

    /**
     * Удаляет все заголовки
     */
    public function removeHeaders(): void
    {
        $this->headers = [];
    }

    /**
     * Устанавливает новую cookie
     * @param string $name Название cookie
     * @param string $value Значение cookie
     */
    public function setCookie( string $name, string $value ): void
    {
        $this->cookie_jar->setCookie( new SetCookie( [ 'Name' => $name, 'Value' => $value, 'Domain' => $this->domain ] ) );
        $this->cookie_jar->setCookie( new SetCookie( [ 'Name' => $name, 'Value' => $value, 'Domain' => 'www.' . $this->domain ] ) );
    }

    /**
     * Возвращает значение указанной cookie
     * @param string $name Название cookie
     * @return string Значение cookie
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
     * Возвращает массив, содержащий в себе ассоциативный массив с информацией о всех активных куках
     * [
     *     'Name' => Название куки
     *     'Value' => Значение куки
     *     'Domain' => Домен, для которого была установлена куки
     * ]
     */
    public function getCookies(): array
    {
        return $this->cookie_jar->toArray();
    }

    /**
     * Удаляет все куки
     */
    public function removeCookies(): void
    {
        $this->cookie_jar->clear();
    }

    /**
     * @param string|null $proxy Устанавливает адрес прокси сервера
     */
    public function setProxy( ?string $proxy ): void
    {
        $this->proxy = $proxy;
    }

    /**
     * Отправляет http запрос
     * @param string $link Ссылка по которой будет отправлен запрос
     * @param array $params Параметры запроса
     * @param string $method Метод запроса
     * @param string $type_params Тип отправки параметров
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
            elseif ( $type_params === 'raw_data' ) {
                $request_params[ 'body' ] = array_shift( $params );
            }
            else {
                $request_params[ 'form_params' ] = $params;
            }
        }
        return $this->client->requestAsync( $method, $link, $request_params );
    }

    /**
     * @param string $source Устанавливает домен для которого будут создаваться пользовательские cookie
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