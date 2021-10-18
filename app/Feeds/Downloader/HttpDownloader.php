<?php

namespace App\Feeds\Downloader;

use App\Feeds\Utils\Data;
use App\Feeds\Utils\HttpClient;
use App\Feeds\Utils\Link;
use App\Feeds\Utils\ParserCrawler;
use App\Feeds\Utils\ProxyConnector;
use App\Feeds\Interfaces\DownloaderInterface;
use App\Helpers\HttpHelper;
use Exception;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Response;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

class HttpDownloader implements DownloaderInterface
{
    /**
     * @var HttpClient Асинхронный HTTP клиент
     */
    private HttpClient $client;
    /**
     * Использовать статичный user-agent или динамичный (изменять его значение при каждом запросе)
     * По умолчанию user-agent меняется при каждом запросе
     * @var bool
     */
    private bool $static_user_agent;
    /**
     * Использовать прокси или нет
     * По умолчанию прокси не используется
     * @var bool
     */
    private bool $use_proxy;
    /**
     * @var bool Установлено соединение с прокси сервером или нет
     */
    private bool $connect = false;
    /**
     * Отвечает за дополнительную обработку ссылок, при загрузке которых произошли ошибки
     * По умолчанию, ссылки с ошибкой обрабатываются
     * @var bool
     */
    private bool $process_errors_links = true;
    /**
     * @var bool Установлена авторизация в объекте или нет
     */
    private bool $authorise = false;
    /**
     * @var string Текущий user agent
     */
    private string $user_agent;
    /**
     * @var int Время ожидания обработки запроса в секундах
     */
    public int $timeout_s;
    /**
     * @var int Количество попыток подключения к прокси серверу
     */
    private int $connect_limit = 50;
    /**
     * @var float Время ожидания между отправкой запроса
     */
    private float $delay_s;
    /**
     * @var array Параметры для авторизации
     *
     * 'check_login_text' => 'Log Out' - Проверочное слово, которое отображается только авторизованным пользователям (Log Out, My account и прочие).
     * 'auth_url' => 'https://www.authorise_uri.com/login' - Url адрес на который отправляется запрос для авторизации.
     * 'auth_form_url' => 'https://www.authorise_uri.com/login' - Url адрес страницы, на которой находится форма авторизации.
     * 'auth_info' => [] - Массив параметров для авторизации, содержит в себе все поля, которые были отправлены браузером для авторизации
     * 'find_fields_form' => true|false - Определяет искать дополнительные поля формы авторизации перед отправкой запроса или нет
     * Если этот параметр будет опущен, система сочтет его значение как "true"
     * 'api_auth' => true|false - Указывает в каком виде отправлять параметры формы авторизации ("request_payload" или "form_data")
     * Если этот параметр будет опущен, система сочтет его значение как "false".
     * По умолчанию параметры отправляются, как обычные поля формы
     *
     * Пример содержания auth_info:
     * 'auth_info' => [
     *     'login[username]' => 'login',
     *     'login[password]' => 'password',
     * ]
     * Значения 'login' и 'password' автоматически заменяются актуальными логином и паролем для авторизации на сайте
     * Это сделано для автоматического обновления данных при их изменении
     */
    private array $params;

    /**
     * @param array $headers Пользовательский набор заголовков
     * @param array $params Параметры авторизации
     * @param float $timeout_s Время ожидания ответа на запрос
     * @param float $delay_s Время ожидания между запросами
     * @param bool $static_user_agent Использовать статичный user-agent или нет
     * @param bool $use_proxy Использовать прокси или нет
     * @param string $source Ссылка на сайт
     */
    public function __construct( array $headers = [], array $params = [], float $timeout_s = 15, float $delay_s = 0, bool $static_user_agent = false, bool $use_proxy = false, string $source = 'https://google.com' )
    {
        $this->timeout_s = $timeout_s;

        $this->setDelay( $delay_s );
        $this->setParams( $params );
        $this->setUseProxy( $use_proxy );

        $this->setStaticUserAgent( $static_user_agent );
        $this->setUserAgent( HttpHelper::getUserAgent() );

        $this->setClient( new HttpClient( $source ) );
        $this->setHeaders( $headers );
        $this->setTimeOut( $this->timeout_s );

        $this->processAuth();
    }

    /**
     * Устанавливает объект асинхронного http клиента
     * @param HttpClient $client
     * @return HttpDownloader
     */
    public function setClient( HttpClient $client ): HttpDownloader
    {
        $this->client = $client;
        return $this;
    }

    /**
     * Возвращает объект асинхронного http клиента
     * @return HttpClient
     */
    public function getClient(): HttpClient
    {
        return $this->client;
    }

    /**
     * @param bool $static_agent
     * @return HttpDownloader
     */
    public function setStaticUserAgent( bool $static_agent ): HttpDownloader
    {
        $this->static_user_agent = $static_agent;
        return $this;
    }

    /**
     * @return bool
     */
    public function getStaticUserAgent(): bool
    {
        return $this->static_user_agent;
    }

    /**
     * @param bool $use_proxy
     * @return HttpDownloader
     */
    public function setUseProxy( bool $use_proxy ): HttpDownloader
    {
        $this->use_proxy = $use_proxy;
        return $this;
    }

    /**
     * @param string $user_agent
     * @return HttpDownloader
     */
    public function setUserAgent( string $user_agent ): HttpDownloader
    {
        $this->user_agent = $user_agent;
        return $this;
    }

    /**
     * Устанавливает время ожидания отклика на запрос
     * @param float $timeout
     * @return HttpDownloader
     */
    public function setTimeOut( float $timeout ): HttpDownloader
    {
        $this->getClient()->setRequestTimeOut( $timeout * 1000 );
        return $this;
    }

    /**
     * Устанавливает лимит попыток подключения к прокси серверу
     * @param int $limit
     * @return HttpDownloader
     */
    public function setConnectLimit( int $limit ): HttpDownloader
    {
        $this->connect_limit = $limit;
        return $this;
    }

    /**
     * Устанавливает задержку между запросами
     * @param float $delay
     * @return HttpDownloader
     */
    public function setDelay( float $delay ): HttpDownloader
    {
        $this->delay_s = $delay * 1000000;
        return $this;
    }

    /**
     * Устанавливает параметры авторизации
     * @param array $params
     * @return HttpDownloader
     */
    public function setParams( array $params ): HttpDownloader
    {
        $this->params = $params;
        return $this;
    }

    /**
     * Асинхронная отправка нескольких запросов
     * @param array $links Принимается массив ссылок или массив объектов app/Feeds/Utils/Link
     * @return Data[] Массив ответов на запросы, каждый ответ помещен в объект app/Feeds/Utils/Data
     */
    public function fetch( array $links ): array
    {
        $data = [];
        $errors_links = [];

        $requests = function ( $links ) use ( &$data, &$errors_links ) {
            foreach ( $links as $link ) {
                /** Если ссылка не помещена в экземпляр класса app/Feeds/Utils/Link -
                 * создаем новый экземпляр класса и передаем в него ссылку
                 */
                if ( !$link instanceof Link ) {
                    $link = new Link( $link );
                }

                /** Если необходимо использовать прокси для загрузки ссылок и при этом не установлено соединение с прокси сервером - инициируем соединение **/
                if ( $this->use_proxy && !$this->connect ) {
                    print PHP_EOL . 'Check proxies' . PHP_EOL;
                    $this->initProxy( $link );
                }

                /** Используем статичный user-agent **/
                if ( $this->static_user_agent ) {
                    $this->setHeader( 'User-Agent', $this->user_agent );
                }
                /** Используем динамичный user-agent **/
                else {
                    $this->setHeader( 'User-Agent', HttpHelper::getUserAgent() );
                }

                yield function () use ( $link, &$data, &$errors_links ) {
                    return $this->getClient()->request( $link->getUrl(), $link->getParams(), $link->getMethod(), $link->getTypeParams() )->
                    then(
                    /** Анонимная функция для обработки успешного ответа на отправленный Http запрос **/
                        function ( Response $response ) use ( $link, &$data ) {
                            $this->getClient()->setResponse( $response );
                            if ( $body = $response->getBody() ) {
                                $response_body = new Data( $body->getContents() );
                            }
                            $data = $this->prepareRequestData( $response_body ?? null, $link, $data );
                        },
                        /** Анонимная функция для обработки ошибки **/
                        function ( RequestException $exception ) use ( $link, &$data, &$errors_links ) {
                            /**
                             * Если используется прокси, сбрасываем подключение и формируем массив повторной загрузки ссылок
                             * когда код ответа от сервера равен 403 или 430 (код ответа от Shopify), или если произошла ошибка при отправке http запроса
                             *
                             * Если включена обработка ссылок, при загрузке которых произошла ошибка,
                             * формируем массив для повторной загрузки, если код ответа не равен 404 и если при отправке http запроса не произошло ошибок
                             */
                            if ( $response = $exception->getResponse() ) {
                                $status = $response->getStatusCode();
                                if ( $status === 403 || $status === 430 ) {
                                    if ( $this->use_proxy || $this->process_errors_links ) {
                                        $this->connect = false;

                                        $errors_links[] = $this->prepareErrorLinks( $link, 3 );
                                    }
                                }
                                elseif ( $status >= 500 && $this->process_errors_links ) {
                                    $errors_links[] = $this->prepareErrorLinks( $link, 0 );
                                }
                                elseif ( in_array( $status, [ 200, 404 ] ) ) {
                                    $data = $this->prepareRequestData( new Data( $response->getBody()->getContents(), $status ), $link, $data );
                                }
                                else {
                                    $this->printParseError( $link, $exception );
                                    $data = $this->prepareRequestData( new Data( $response->getBody()->getContents(), $status ), $link, $data );
                                }
                            }
                            else if ( $this->use_proxy ) {
                                $this->connect = false;
                                $errors_links[] = $this->prepareErrorLinks( $link, 0 );
                            }
                            else {
                                $this->printParseError( $link, $exception );
                                $data = $this->prepareRequestData( null, $link, $data );
                            }
                        }
                    );
                };
                usleep( $this->delay_s );
            }
        };
        $pool = new Pool( $this->getClient()->getHttpClient(), $requests( $links ) );
        $promise = $pool->promise();
        $promise->wait();

        if ( $errors_links ) {
            $data = array_merge( $data, $this->processErrorLinks( $errors_links ) );
        }
        return $data;
    }

    /**
     * Попытка загрузить ссылки, при загрузке которых произошли 403 или 503 ошибки
     * @param array $errors_links Массив вида ['link' => Link, 'delay' => delay_s]
     * @return Data[] Массив ответов на запросы
     */
    private function processErrorLinks( array $errors_links ): array
    {
        $data = [];
        foreach ( $errors_links as $error_link ) {
            $link = $error_link[ 'link' ];

            /** Используем статичный user-agent **/
            if ( $this->static_user_agent ) {
                $this->setHeader( 'User-Agent', $this->user_agent );
            }
            /** Используем динамичный user-agent **/
            else {
                $this->setHeader( 'User-Agent', HttpHelper::getUserAgent() );
            }

            for ( $i = 1; $i <= 3; $i++ ) {
                if ( $this->use_proxy && !$this->connect ) {
                    print PHP_EOL . 'Check proxies' . PHP_EOL;
                    $this->initProxy( $link );
                }
                else {
                    sleep( $error_link[ 'delay' ] );
                }

                $response = null;
                $exception = null;

                $promise = $this->getClient()->request( $link->getUrl(), $link->getParams(), $link->getMethod(), $link->getTypeParams() )->then(
                    function ( Response $res ) use ( &$response ) {
                        $response = $res;
                    },
                    function ( RequestException $exc ) use ( &$exception ) {
                        $exception = $exc;
                    }
                );
                $promise->wait();
                if ( $response && $body = $response->getBody() ) {
                    $this->getClient()->setResponse( $response );
                    $response_body = new Data( $body->getContents() );
                    break;
                }

                $this->connect = false;
            }

            if ( !isset( $response_body ) && isset( $exception ) && $response = $exception->getResponse() ) {
                $response_body = new Data( $response->getBody()->getContents(), $response->getStatusCode() );
            }

            if ( isset( $exception ) && $exception ) {
                if ( $this->use_proxy ) {
                    $this->connect = false;
                }
                $this->printParseError( $link, $exception );
            }
            $data = $this->prepareRequestData( $response_body ?? null, $link, $data );
        }
        return $data;
    }

    /**
     * Посылает одиночный запрос методом GET
     * @param string|Link $link Принимается ссылка или объект app/Feeds/Utils/Link
     * @param array $params Массив параметров, которые будут преобразованы в query string
     * @return Data Объект app/Feeds/Utils/Data который содержит ответ на запрос
     */
    public function get( string|Link $link, array $params = [] ): Data
    {
        if ( !$link instanceof Link ) {
            $link = new Link( $link, 'GET', $params );
        }
        $data = $this->fetch( [ $link ] );
        return array_shift( $data );
    }

    /**
     * Посылает одиночный запрос методом POST
     * @param string|Link $link Принимается ссылка или объект app/Feeds/Utils/Link
     * @param array $params Массив параметров
     * @param string $type_params Тип отправки параметров запроса - как тело html формы или как json строка (тело API запроса)
     * @return Data Объект app/Feeds/Utils/Data который содержит ответ на запрос
     */
    public function post( string|Link $link, array $params = [], string $type_params = 'form_data' ): Data
    {
        if ( !$link instanceof Link ) {
            $link = new Link( $link, 'POST', $params );
        }
        $link->setTypeParams( $type_params );
        $data = $this->fetch( [ $link ] );
        return array_shift( $data );
    }

    /**
     * Устанавливает заголовок http запроса
     * @param string $name Название заголовка
     * @param string $value Значение заголовка
     */
    public function setHeader( string $name, string $value ): void
    {
        $this->getClient()->setHeader( $name, $value );
    }

    /**
     * @param array $headers Устанавливает набор заголовков http запроса
     */
    public function setHeaders( array $headers ): void
    {
        $this->getClient()->setHeaders( $headers );
    }

    /**
     * Возвращает значение указанного заголовка
     * @param string $name Название заголовка
     * @return string|null Значение заголовка
     */
    public function getHeader( string $name ): ?string
    {
        return $this->getClient()->getHeader( $name );
    }

    /**
     * @return array Возвращает массив заголовков
     */
    #[Pure] public function getHeaders(): array
    {
        return $this->getClient()->getHeaders();
    }

    /**
     * Удаляет заголовок по его названию
     * @param string $name Название заголовка
     */
    public function removeHeader( string $name ): void
    {
        $this->getClient()->removeHeader( $name );
    }

    /**
     * Удаляет все заголовки
     */
    public function removeHeaders(): void
    {
        $this->getClient()->removeHeaders();
    }

    /**
     * Устанавливает новую cookie
     * @param string $name Название cookie
     * @param string $value Значение cookie
     */
    public function setCookie( string $name, string $value ): void
    {
        try {
            $this->getClient()->setCookie( $name, $value );
        } catch ( Exception $e ) {
            print $e->getMessage();
        }
    }

    /**
     * Возвращает значение указанной cookie
     * @param string $name Название cookie
     * @return string Значение cookie
     */
    public function getCookie( string $name ): string
    {
        return $this->getClient()->getCookie( $name );
    }

    /**
     * @return array Возвращает массив, содержащий в себе ассоциативный массив с информацией о всех активных куках
     * [
     *     'Name' => Название куки
     *     'Value' => Значение куки
     *     'Domain' => Домен, для которого была установлена куки
     * ]
     */
    public function getCookies(): array
    {
        return $this->getClient()->getCookies();
    }

    /**
     * Удаляет все куки
     */
    public function removeCookies(): void
    {
        $this->getClient()->removeCookies();
    }

    /**
     * @param bool $authorise Устанавливает статус авторизации
     */
    public function setAuthorise( bool $authorise ): void
    {
        $this->authorise = $authorise;
    }

    /**
     * @return bool Возвращает текущий статус авторизации
     */
    public function getAuthorise(): bool
    {
        return $this->authorise;
    }

    private function printParseError( Link $link, RequestException $exception ): void
    {
        if ( $response = $exception->getResponse() ) {
            if ( $response->getStatusCode() !== 404 ) {
                echo PHP_EOL . "Parser Error: " . $response->getReasonPhrase() .
                    PHP_EOL . "Status code: " . $response->getStatusCode() .
                    PHP_EOL . "URI: " . $link->getUrl() . PHP_EOL;
            }
        }
        else {
            echo PHP_EOL . "Parser Error: " . $exception->getMessage() . PHP_EOL . "URI: " . $link->getUrl() . PHP_EOL;
        }
    }

    #[ArrayShape( [ 'link' => Link::class, 'delay' => "int" ] )]
    private function prepareErrorLinks( Link $link, int $delay ): array
    {
        return [
            'link' => $link,
            'delay' => $delay
        ];
    }

    private function prepareRequestData( ?Data $response_body, Link $link, array $data ): array
    {
        $response_body?->setPageLink( $link );

        $data[] = $response_body ?? new Data( page_link: $link );
        return $data;
    }

    /**
     * @param bool $process
     */
    public function setProcessErrorsLinks( bool $process ): void
    {
        $this->process_errors_links = $process;
    }

    /**
     * @return string|null Возвращает url адрес на который будет отправлен запрос для авторизации
     */
    public function getAuthUrl(): ?string
    {
        return $this->params[ 'auth_url' ] ?? null;
    }

    /**
     * @return string|null Возвращает url адрес страницы, на которой находится форма авторизации
     */
    public function getAuthFormUrl(): ?string
    {
        return $this->params[ 'auth_form_url' ] ?? null;
    }

    /**
     * @return array Возвращает массив параметров для авторизации
     */
    public function getAuthInfo(): array
    {
        return $this->params[ 'auth_info' ] ?? [];
    }

    /**
     * @return bool Возвращает значение, в зависимости от которого, параметры формы авторизации будут отправлены, как "form_data", либо "request_payload"
     */
    public function getApiAuth(): bool
    {
        return isset( $this->params[ 'api_auth' ] ) && $this->params[ 'api_auth' ];
    }

    /**
     * @return string|null Возвращает проверочное слово авторизации
     */
    public function getCheckLoginText(): ?string
    {
        return $this->params[ 'check_login_text' ] ?? null;
    }

    /**
     * Процесс авторизации
     * @param null $callback
     * @return bool
     */
    public function processAuth( $callback = null ): bool
    {
        if ( $this->getAuthUrl() && $this->getAuthInfo() ) {
            if ( !isset( $this->params[ 'find_fields_form' ] ) || $this->params[ 'find_fields_form' ] ) {
                $this->params[ 'auth_info' ] = $this->getFieldsFormOnLink( $this->getAuthFormUrl() ?? $this->getAuthUrl(), array_key_first( $this->getAuthInfo() ), $this->getAuthInfo() );
            }

            $data_n = $this->post( $this->getAuthUrl(), $this->getAuthInfo(), $this->getApiAuth() ? 'request_payload' : 'form_data' );
            $crawler_n = new ParserCrawler( $data_n->getData() );
            if ( $crawler_n->count() && stripos( $crawler_n->html(), 'sucuri_cloudproxy_js' ) !== false ) {
                $cookies = HttpHelper::sucuri( $crawler_n->html() );
                if ( $cookies ) {
                    $this->setCookie( $cookies[ 0 ], $cookies[ 1 ] );

                    $data_n = $this->post( $this->getAuthUrl(), $this->getAuthInfo() );
                    $crawler_n = new ParserCrawler( $data_n->getData() );
                }
            }

            if ( $callback ) {
                $callback( $crawler_n, $this );
            }

            return $this->checkLogin( $crawler_n );
        }
        return false;
    }

    /**
     * Используется для получения полей формы
     * @param string|Link $link Ссылка на страницу, где расположена форма
     * @param string $field_name Имя поля, находящееся внутри нужной формы. По имени этого поля будет произведен поиск формы
     * Для получения всех полей из любой формы необходимо передать пустое значение
     * @param array $params Массив параметров с исходными значениями, если они есть
     * @param bool $only_hidden Параметр позволяет собирать все поля формы или только скрытые. По-умолчанию собираются все поля формы
     * @return array Ассоциативный массив, в котором ключи - имена полей формы, значения - значения полей формы
     */
    public function getFieldsFormOnLink( string|Link $link, string $field_name = '', array $params = [], bool $only_hidden = false ): array
    {
        return $this->findFieldsForm( new ParserCrawler( $this->get( $link )->getData() ), $field_name, $params, $only_hidden );
    }

    /**
     * Используется для получения полей формы
     * @param ParserCrawler $crawler Html содержимое страницы, где расположена форма
     * @param string $field_name Имя поля, находящееся внутри нужной формы. По имени этого поля будет произведен поиск формы
     * Для получения всех полей из любой формы необходимо передать пустое значение
     * @param array $params Массив параметров с исходными значениями, если они есть
     * @param bool $only_hidden Параметр позволяет собирать все поля формы или только скрытые. По-умолчанию собираются все поля формы
     * @return array Ассоциативный массив, в котором ключи - имена полей формы, значения - значения полей формы
     */
    public function getFieldsFormOnCrawler( ParserCrawler $crawler, string $field_name = '', array $params = [], bool $only_hidden = false ): array
    {
        return $this->findFieldsForm( $crawler, $field_name, $params, $only_hidden );
    }

    /**
     * @param ParserCrawler $crawler
     * @param string $field_name
     * @param array $params
     * @param bool $only_hidden
     * @return array
     */
    private function findFieldsForm( ParserCrawler $crawler, string $field_name = '', array $params = [], bool $only_hidden = false ): array
    {
        $selector = 'input';
        if ( $only_hidden ) {
            $selector = 'input[type="hidden"]';
        }

        if ( empty( $field_name ) ) {
            if ( $crawler->filter( 'form' )->count() ) {
                $crawler->filter( "form $selector" )->each( static function ( ParserCrawler $c ) use ( &$params ) {
                    $name = $c->attr( 'name' );
                    $value = $c->attr( 'value' );
                    if ( ( !empty( $params[ $name ] ) ) ) {
                        return;
                    }
                    $params[ $name ] = $value ?? '';
                } );
            }
            return $params;
        }

        if ( $crawler->filter( 'input[name="' . $field_name . '"]' )->count() ) {
            $parents = $crawler->filter( 'input[name="' . $field_name . '"]' )->parents();
            $parents->filter( 'form' )->first()->filter( $selector )->each( function ( ParserCrawler $c ) use ( &$params ) {
                $name = $c->attr( 'name' );
                $value = $c->attr( 'value' );
                if ( ( !empty( $params[ $name ] ) ) ) {
                    return;
                }
                $params[ $name ] = $value ?? '';
            } );
        }
        return $params;
    }

    /**
     * Проверка авторизации на сайте по проверочному слову
     * @param ParserCrawler $crawler
     * @return bool
     */
    public function checkLogin( ParserCrawler $crawler ): bool
    {
        if ( $this->getCheckLoginText() && $crawler->count() ) {
            if ( stripos( $crawler->text(), $this->getCheckLoginText() ) !== false ) {
                print PHP_EOL . 'Authorization successful!' . PHP_EOL;
                $this->setAuthorise( true );
                return true;
            }
        }
        else {
            return true;
        }

        print PHP_EOL . 'Authorization fail!' . PHP_EOL;
        $this->setAuthorise( false );
        return false;
    }

    private function initProxy( Link $link ): void
    {
        ( new ProxyConnector() )->connect( $this, $link, $this->connect_limit );
        $this->connect = true;
    }
}