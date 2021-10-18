<?php

namespace App\Feeds\Processor;

use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;
use Symfony\Component\DomCrawler\Crawler;

abstract class SitemapHttpProcessor extends HttpProcessor
{
    /**
     * Массив css селекторов, выбирающих элементы ссылок (<a>), на категории товаров для их дальнейшего обхода
     */
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'sitemap loc' ];
    /**
     * Массив css селекторов, выбирающих элементы ссылок (<a>), на страницы товаров для сбора информации с них
     */
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'loc' ];

    /**
     * Возврает все ссылки на страницы категорий, которые были найденны по селекторам, указанным в константе "CATEGORY_LINK_CSS_SELECTORS"
     * @param Data $data Html разметка загружаемой страницы
     * @param string $url url адрес загружаемой страницы
     * @return array Массив ссылок, содержащий объекты app/Feeds/Utils/Link
     */
    public function getCategoriesLinks( Data $data, string $url ): array
    {
        $result = [];
        $crawler = new Crawler( $data->getData() );

        foreach ( static::CATEGORY_LINK_CSS_SELECTORS as $css ) {
            if ( $links = $crawler->filter( $css )->each( static function ( Crawler $node ) {
                return new Link( $node->text() );
            } ) ) {
                array_push( $result, ...$links );
            }
        }

        return $result;
    }

    /**
     * Возврает все ссылки на страницы товаров, которые были найденны по селекторам, указанным в константе "PRODUCT_LINK_CSS_SELECTORS"
     * @param Data $data Html разметка загружаемой страницы
     * @param string $url url адрес загружаемой страницы
     * @return array Массив ссылок, содержащий объекты app/Feeds/Utils/Link
     */
    public function getProductsLinks( Data $data, string $url ): array
    {
        if ( preg_match_all( '/<loc>([^<]*)<\/loc>/m', $data->getData(), $matches ) ) {
            $links = array_map( static fn( $url ) => new Link( htmlspecialchars_decode( $url ) ), $matches[ 1 ] );
        }

        return array_values( array_filter( $links ?? [], [ $this, 'filterProductLinks' ] ) );
    }
}
