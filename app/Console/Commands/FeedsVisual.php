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

        /**
         * Если json файл с товарами дистрибьютора существует,
         * разбиваем список товаров на части по 60 товаров (такое количество будет выводиться на странице)
         * и сохраняем все в кеше фреймворка
         */
        if ( Storage::disk( 'local' )->exists( $feed_file ) ) {
            $products = json_decode( Storage::disk( 'local' )->get( $feed_file ), true, 512, JSON_THROW_ON_ERROR )[ 'products' ];

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
                Cache::put( "{$dx_code}__{$storefront}_$page", $cache_products, 86400 );
            }

            if ( $port = $this->option( 'port' ) ) {
                $this->info( "Visual created. See http://127.0.0.1:$port/feeds/visual/$dx_code_url" );
            }
            else {
                $this->info( "Visual created. See http://127.0.0.1/feeds/visual/$dx_code_url" );
            }
        }
        else {
            throw new FileNotFoundException( "Feed file for $dx_code '/storage/app/$feed_file' does not exists" );
        }
    }
}
