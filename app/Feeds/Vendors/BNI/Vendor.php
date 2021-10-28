<?php

namespace App\Feeds\Vendors\BNI;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;
use App\Feeds\Utils\Data;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'ul.nav a', 'a.category-link' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'a.product-title' ];
    private const SITE_URL = 'https://www.brunelli.ca/';

    protected array $first = [ 'https://www.brunelli.ca/en' ];

    public function getProductsLinks( Data $data, string $url ): array
    {
        if ( $url === self::SITE_URL . 'en' ) {
            return [];
        }

        $links = [];
        $page_num = 1;
        $links_count = 0;

        while ( true ) {

            $page = $this->getDownloader()->get( $url . '?page=' . $page_num );
            $page = $page->getData();

            preg_match_all( '%<a href="(.*?)" class="product-title"%ui', $page, $matches );

            if ( empty( $matches[ 1 ] ) ) {
                break;
            }

            if ( count( $matches[ 1 ] ) > $links_count ) {
                $links += array_values( $matches[ 1 ] );
                $links_count = count( $links );
            }
            else {
                break;
            }

            $page_num++;
        }

        if ( empty( $links ) ) {
            return $links;
        }

        foreach ( $links as $key => $value ) {
            $links[ $key ] = str_contains( $value, 'http' ) ? $value : self::SITE_URL . ltrim( $value, '/' );
        }

        return array_values( array_unique( $links ) );
    }

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        if ( $fi->isGroup() ) {
            $fi->setChildProducts( array_values(
                array_filter( $fi->getChildProducts(), static fn( FeedItem $item ) => !empty( $item->getMpn() ) && count( $item->getImages() ) && $item->getCostToUs() > 0 )
            ) );

            return count( $fi->getChildProducts() );
        }

        return !empty( $fi->getMpn() ) && count( $fi->getImages() ) && $fi->getCostToUs() > 0;
    }
}
