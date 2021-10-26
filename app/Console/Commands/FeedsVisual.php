<?php

namespace App\Console\Commands;

use App\Feeds\Repositories\DxRepository;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class FeedsVisual extends Command
{
    /**
     * dx_code - код дистрибьютора
     * port - порт, на котором работает web-server
     *
     * @var string
     */
    protected $signature = 'feed:visual {dx_code} {--port=}';

    /**
     * @var string
     */
    protected $description = 'Visualization products in feed';

    /**
     * @throws FileNotFoundException
     * @throws Exception
     */
    public function handle(): void
    {
        /**
         * Получаем код дистрибьютора переданный в консоль
         * Если код дистрибьютора содержит в себе код магазина (ABC__35), удаляем его, поскольку в параметрах API запроса
         * необходимо указать только код дистрибьютора (ABC)
         */
        $dx_code = $this->argument( 'dx_code' );
        if ( str_contains( $dx_code, '__' ) ) {
            [ $dx_code, $storefront ] = explode( '__', strtoupper( $dx_code ) );
        }
        else {
            $dx_code = strtoupper( $dx_code );
        }

        /** Для получения информации о дистрибьюторе отправляем запрос на API с полученным кодом **/
        $repo = new DxRepository();
        $response = $repo->getDxInfo( $dx_code );

        /**
         * Если ответ на запрос вернулся пустым, значит дистрибьютора с указанным кодом не существует
         */
        if ( empty( $response ) ) {
            $this->error( 'Invalid dx_code!' );
            exit( -1 );
        }

        /** Определяем идентификатор магазина и название json файла с товарами дистрибьютора **/
        if ( count( $response[ 'feeds' ] ) ) {
            if ( empty( $storefront ) && count( $response[ 'feeds' ] ) > 1 ) {
                $this->error( 'Invalid storefront! Example right command "php artisan ' . $dx_code . '__' . array_key_first( $response[ 'feeds' ] ) . '"' );
                exit( -1 );
            }

            if ( isset( $storefront ) ) {
                $dx_code_url = "{$dx_code}__$storefront";
            }
            else {
                $storefront = array_key_first( $response[ 'feeds' ] );

                $dx_code_url = $dx_code;
            }

            $feed_file = $response[ 'feeds' ][ $storefront ][ 'feed_file_name' ];
        }
        else {
            $storefront = $storefront ?? '';
            $feed_file = "feed{$response['id']}p.json";

            $dx_code_url = $dx_code;
        }

        $products_log = [];
        $feed_error_file = "logs/{$dx_code_url}_error.json";
        if ( Storage::exists( $feed_error_file ) ) {
            $errors = json_decode( Storage::get( $feed_error_file ), true, 512, JSON_THROW_ON_ERROR );
        }

        /**
         * Если json файл с товарами дистрибьютора существует,
         * разбиваем список товаров на части по 60 товаров (такое количество будет выводиться на странице)
         * и сохраняем все в кеше фреймворка
         */
        if ( Storage::disk( 'local' )->exists( $feed_file ) ) {
            $products = json_decode( Storage::disk( 'local' )->get( $feed_file ), true, 512, JSON_THROW_ON_ERROR )[ 'products' ];

            /**
             * Если существует файл с ошибками валидации, сохраняем отдельный кэш для товаров с ошибками,
             * чтобы иметь возможность просматривать их отдельно от валидных товаров
             */
            if ( isset( $errors ) ) {
                foreach ( $products as $product ) {
                    $find_error = false;

                    foreach ( $errors as $general_error => $errors_types ) {
                        foreach ( $errors_types as $error_type => $code_products ) {
                            foreach ( $code_products as $code_product ) {
                                if ( $product[ 'productcode' ] === $code_product || $product[ 'hash_product' ] === $code_product ) {
                                    $products_log[ strtolower( $general_error ) ][ strtolower( $error_type ) ][] = $product;

                                    $find_error = true;
                                }
                            }
                        }
                    }

                    if ( !$find_error ) {
                        $products_log[ 'valid' ][ 'valid' ][] = $product;
                    }
                }

                $filters = [];
                foreach ( $products_log as $general_type => $types_products ) {
                    $type = array_key_first( $types_products );
                    $types_products = array_shift( $types_products );
                    $page_products = array_chunk( $types_products, 60 );
                    $total_pages = count( $page_products );
                    foreach ( $page_products as $page => $page_product ) {
                        ++$page;

                        /**
                         * [
                         *      products => array массив товаров, которые будут отображены на странице
                         *      total_products => int общее количество товаров
                         *      total_pages => int количество страниц для построения пагинации
                         * ]
                         */
                        $cache_products = json_encode( [ 'products' => $page_product, 'total_products' => count( $types_products ), 'total_pages' => $total_pages ], JSON_THROW_ON_ERROR );
                        Cache::put( "{$dx_code}__{$storefront}_{$general_type}_" . trim( preg_replace( '/[^a-z0-9]/', '_', $type ), '_' ) . "_$page", $cache_products, 86400 );
                    }

                    /** Формируем массив фильтров для навигации по типам ошибок валидации товаров **/
                    if ( $general_type === 'valid' ) {
                        $filters[ $general_type ] = [
                            'page_link' => $general_type,
                            'total_products' => count( $types_products )
                        ];
                    }
                    else {
                        $filters[ $general_type ][ $type ] = [
                            'page_link' => "errors/$general_type/" . trim( preg_replace( '/[^a-z0-9]/', '_', $type ), '_' ),
                            'total_products' => count( $types_products )
                        ];
                    }
                }

                Cache::put( "{$dx_code}__{$storefront}_filters", $filters, 86400 );
            }

            $page_products = array_chunk( $products, 60 );
            $total_pages = count( $page_products );
            foreach ( $page_products as $page => $page_product ) {
                ++$page;

                /**
                 * [
                 *      products => array массив товаров, которые будут отображены на странице
                 *      total_products => int общее количество товаров
                 *      total_pages => int количество страниц для построения пагинации
                 * ]
                 */
                $cache_products = json_encode( [ 'products' => $page_product, 'total_products' => count( $products ), 'total_pages' => $total_pages ], JSON_THROW_ON_ERROR );
                Cache::put( "{$dx_code}__{$storefront}_all_$page", $cache_products, 86400 );
            }

            if ( $port = $this->option( 'port' ) ) {
                $this->info( "Visual created. See http://127.0.0.1:$port/feeds/visual/$dx_code_url/products" );
            }
            else {
                $this->info( "Visual created. See http://127.0.0.1/feeds/visual/$dx_code_url/products" );
            }
        }
        else {
            throw new FileNotFoundException( "Feed file for $dx_code '/storage/app/$feed_file' does not exists" );
        }
    }
}
