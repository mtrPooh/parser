<?php

namespace App\Feeds\Utils;

class Link
{
    /**
     * @var string Адрес ссылки на который будет отправлен запрос
     */
    private string $url;
    /**
     * @var string Метод отправки запроса
     */
    private string $method;
    /**
     * @var array Параметры запроса
     */
    private array $params;
    /**
     * @var string Тип отправки параметров запроса
     * Для GET запроса - это query_string
     * Для POST запроса - это form_data или request_payload (тело RESTAPI запроса)
     * При значении "default" параметры POST запроса будут отправлены как form_data
     * Параметры GET запроса при любом значении будут отправлены как query_string
     */
    private string $type_params;
    /**
     * @var bool Статус посещения ссылки. False если ссылка не была посещена загрузчиком
     */
    private bool $visited = false;

    public function __construct( string $url, string $method = 'GET', array $params = [], string $type_params = 'default' )
    {
        $this->method = strtoupper( $method );
        $this->type_params = $type_params;

        $url_info = parse_url( $url );
        if ( $this->method === 'GET' && !empty( $url_info[ 'query' ] ) ) {
            $url = str_replace( '?' . $url_info[ 'query' ], '', $url );

            $raw_query = explode( '&', $url_info[ 'query' ] );
            foreach ( $raw_query as $query ) {
                $raw_query_value = explode( '=', $query, 2 );

                $params[ $raw_query_value[ 0 ] ?? '' ] = $raw_query_value[ 1 ] ?? '';
            }
        }

        $this->url = trim( $url );
        $this->params = $params;
    }

    /**
     * Устанавливает новый url адрес
     * @param string $url
     * @return Link
     */
    public function setUrl( string $url ): self
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Возвращает текущий url адрес
     * @return string
     */
    public function getUrl(): string
    {
        $url = $this->url;
        if ( $this->method === 'GET' && count( $this->params ) ) {
            $get_params = array_map(
                static fn( $k, $v ) => "$k=$v",
                array_keys( $this->params ),
                array_values( $this->params )
            );
            $url .= '?' . rtrim( implode( '&', $get_params ), '=' );
        }
        return $url;
    }

    /** Устанавливает новый метод отправки запроса
     * @param string $method
     * @return Link
     */
    public function setMethod( string $method ): self
    {
        if ( $this->method === 'GET' ) {
            $this->setUrl( $this->getUrl() );
        }
        $this->method = $method;
        return $this;
    }

    /**
     * @return string Возвращает текущий метод отправки запроса
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Устанавливает новый набор параметров
     * @param array $params
     * @return Link
     */
    public function setParams( array $params ): self
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @return array Возвращает набор параметров
     */
    public function getParams(): array
    {
        if ( !count( $this->params ) ) {
            return [];
        }
        return $this->params;
    }

    /**
     * @param string $type Устанавливает новый тип отправки параметров запроса
     * @return $this
     */
    public function setTypeParams( string $type ): self
    {
        $this->type_params = $type;
        return $this;
    }

    /**
     * @return string Возвращает текущий тип отправки параметров запроса
     */
    public function getTypeParams(): string
    {
        return $this->type_params;
    }

    /**
     * @return bool Возвращает статус посещения ссылки
     */
    public function isVisited(): bool
    {
        return $this->visited;
    }

    /**
     * @param bool $visited Устанавливает статус посещения ссылки
     * @return Link
     */
    public function setVisited( bool $visited = true ): self
    {
        $this->visited = $visited;
        return $this;
    }
}
