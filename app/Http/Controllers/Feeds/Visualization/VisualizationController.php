<?php

namespace App\Http\Controllers\Feeds\Visualization;

use App\Feeds\Repositories\DxRepository;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class VisualizationController extends Controller
{
    private function getDxInfo( string $dx_code ): array
    {
        if ( str_contains( $dx_code, '__' ) ) {
            [ $dx_code, $storefront ] = explode( '__', strtoupper( $dx_code ) );
        }
        else {
            $dx_code = strtoupper( $dx_code );
        }

        $repo = new DxRepository();
        $response = $repo->getDxInfo( $dx_code );

        if ( count( $response[ 'feeds' ] ) ) {
            if ( isset( $storefront ) ) {
                $dx_code_url = "{$dx_code}__$storefront";
            }
            else {
                $storefront = array_key_first( $response[ 'feeds' ] );

                $dx_code_url = $dx_code;
            }
        }
        else {
            $storefront = $storefront ?? '';

            $dx_code_url = $dx_code;
        }

        $vendor_name = $response[ 'name' ];

        return [
            $dx_code_url, $dx_code, $storefront, $vendor_name
        ];
    }

    /**
     * @throws Exception
     */
    private function getProducts( string $cache_name ): array
    {
        if ( $products = Cache::get( $cache_name ) ) {
            return json_decode( $products, true, 512, JSON_THROW_ON_ERROR );
        }

        print '<h1 style="text-align: center; margin-top: 20%">Products cache has been expired or cache not exists. <br> Run command "php artisan feed:visual {dx_code}" to see products</h1>';

        exit();
    }

    private function getFilters( string $dx_code, string $storefront ): array
    {
        return Cache::get( "{$dx_code}__{$storefront}_filters" ) ?: [];
    }

    /**
     * Выводит страницу со всеми товарами из фида
     *
     * @param string $dx_code Код дистрибьютора
     * @param Request $request Объект текущего запроса
     * @return Factory|View|Application
     * @throws Exception
     */
    public function index( string $dx_code, Request $request ): Factory|View|Application
    {
        [ $dx_code_url, $dx_code, $storefront, $vendor_name ] = $this->getDxInfo( $dx_code );
        $filters = $this->getFilters( $dx_code, $storefront );

        $page = $request->get( 'page' ) ?? 1;
        $products = $this->getProducts( "{$dx_code}__{$storefront}_all_$page" );

        return view( 'feeds/visual/visual_products_page', compact( 'page', 'products', 'dx_code_url', 'vendor_name', 'filters' ) );
    }

    /**
     * Выводит страницы с ошибками валидации
     *
     * @param string $dx_code Код дистрибьютора
     * @param string $general_type Ошибка валидации
     * @param string $type Тип ошибки
     * @param Request $request Объект текущего запроса
     * @return Factory|View|Application
     * @throws Exception
     */
    public function errors( string $dx_code, string $general_type, string $type, Request $request ): Factory|View|Application
    {
        [ $dx_code_url, $dx_code, $storefront, $vendor_name ] = $this->getDxInfo( $dx_code );
        $filters = $this->getFilters( $dx_code, $storefront );

        $page = $request->get( 'page' ) ?? 1;
        $products = $this->getProducts( "{$dx_code}__{$storefront}_{$general_type}_{$type}_$page" );

        return view( 'feeds/visual/visual_products_page', compact( 'page', 'products', 'dx_code_url', 'vendor_name', 'filters', 'general_type', 'type' ) );
    }

    /**
     * Выводит страницы с валидными товарами
     *
     * @param string $dx_code Код дистрибьютора
     * @param Request $request Объект текущего запроса
     * @return Factory|View|Application
     * @throws Exception
     */
    public function valid( string $dx_code, Request $request ): Factory|View|Application
    {
        [ $dx_code_url, $dx_code, $storefront, $vendor_name ] = $this->getDxInfo( $dx_code );
        $filters = $this->getFilters( $dx_code, $storefront );

        $general_type = 'valid';
        $type = '';

        $page = $request->get( 'page' ) ?? 1;
        $products = $this->getProducts( "{$dx_code}__{$storefront}_valid_valid_$page" );

        return view( 'feeds/visual/visual_products_page', compact( 'page', 'products', 'dx_code_url', 'vendor_name', 'filters', 'general_type', 'type' ) );
    }

    /**
     * Страница поиска товаров. Поиск работает только по "sku" и "productcode" товара
     *
     * @param string $dx_code Код дистрибьютора
     * @param Request $request Объект текущего запроса
     * @return Factory|View|Application
     * @throws Exception
     */
    public function search( string $dx_code, Request $request ): Factory|View|Application
    {
        [ $dx_code_url, $dx_code, $storefront, $vendor_name ] = $this->getDxInfo( $dx_code );

        $search_sku = $request->get( 'sku' );
        $filters = $this->getFilters( $dx_code, $storefront );

        $page = 1;
        $products = [];
        $products[ 'products' ] = [];
        if ( !empty( $search_sku ) ) {
            while ( true ) {
                $cache_key = "{$dx_code}__{$storefront}_all_$page";
                if ( !Cache::has( $cache_key ) ) {
                    break;
                }

                $products_list = $this->getProducts( $cache_key );
                foreach ( $products_list[ 'products' ] as $product ) {
                    if ( $product[ 'productcode' ] === $search_sku || $product[ 'mpn' ] === $search_sku ) {
                        $products[ 'products' ][] = $product;
                    }
                    if ( $product[ 'is_group' ] ) {
                        foreach ( $product[ 'child_products' ] as $child_product ) {
                            if ( $child_product[ 'productcode' ] === $search_sku || $child_product[ 'mpn' ] === $search_sku ) {
                                $products[ 'products' ][] = $child_product;
                            }
                        }
                    }
                }
                $page++;
            }
        }

        $page = 1;
        $products[ 'total_products' ] = count( $products[ 'products' ] );
        $products[ 'total_pages' ] = 1;

        return view( 'feeds/visual/visual_products_page', compact( 'page', 'products', 'dx_code_url', 'vendor_name', 'filters' ) );
    }

    /**
     * Страница с подробной информацией о товаре
     *
     * @param string $dx_code Код дистрибьютора
     * @param string $hash_product Sku товара или его хэш
     * @return Factory|View|Application
     * @throws Exception
     */
    public function product( string $dx_code, string $hash_product ): Factory|View|Application
    {
        [ $dx_code_url, $dx_code, $storefront ] = $this->getDxInfo( $dx_code );

        $page = 1;
        $hash_group_product = '';
        while ( true ) {
            $cache_key = "{$dx_code}__{$storefront}_all_$page";
            if ( !Cache::has( $cache_key ) ) {
                print '<h1 style="text-align: center; margin-top: 20%">Product not found or cache has been expired</h1>';

                exit();
            }

            $products_list = $this->getProducts( "{$dx_code}__{$storefront}_all_$page" );
            foreach ( $products_list[ 'products' ] as $product ) {
                if ( $product[ 'productcode' ] === $hash_product || $product[ 'hash_product' ] === $hash_product ) {
                    $current_product = $product;
                    break 2;
                }
                if ( $product[ 'is_group' ] ) {
                    foreach ( $product[ 'child_products' ] as $child_product ) {
                        if ( $child_product[ 'productcode' ] === $hash_product || $child_product[ 'hash_product' ] === $hash_product ) {
                            $hash_group_product = $product[ 'hash_product' ];
                            $current_product = $child_product;
                            break 3;
                        }
                    }
                }
            }
            $page++;
        }

        $other_products = [];
        if ( !$current_product[ 'is_group' ] ) {
            if ( count( $products_list[ 'products' ] ) > 4 ) {
                $other_products = array_random( $products_list[ 'products' ], 4 );
            }
            else {
                $other_products = array_random( $products_list[ 'products' ], count( $products_list[ 'products' ] ) );
            }
        }

        return view( 'feeds/visual/visual_product_page', compact( 'current_product', 'dx_code_url', 'hash_group_product', 'other_products' ) );
    }
}
