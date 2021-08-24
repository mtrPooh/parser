<?php

namespace App\Feeds\Vendors\IVR;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'ul#menulist li.firstmenu a', 'ul#menulist li.third-ctgr a' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [];
    private const API_URL = 'https://www.ivrose.com/v9/collection/anon/';
    private const PRODUCT_URL = 'https://www.ivrose.com/product/-/';

    protected array $first = [ 'https://www.ivrose.com/' ];

    public function getProductsLinks( Data $data, string $url ): array
    {
        if ( $url === 'https://www.ivrose.com/' ) {
            return [];
        }

        $links = [];

        $category_id = pathinfo( $url );
        $category_id = explode( '.html', $category_id[ 'basename' ] );
        $category_id = $category_id[ 0 ];

        $p = 1;
        while ( true ) {
            $params = [
                'collectionId' => $category_id,
                'currency' => 'USD',
                'endPrice' => '',
                'filterItems' => [],
                'sorter' => 0,
                'startPrice' => ''
            ];

            $link = new Link( self::API_URL . $p * 20 . '/20/filter', 'POST', $params, 'request_payload' );

            $data = $this->getDownloader()->fetch( [ $link ], true );
            $data = json_decode( $data[ 0 ][ 'data' ], true, 512, JSON_THROW_ON_ERROR );

            if ( empty( $data[ 'result' ] ) ) {
                break;
            }

            foreach ( $data[ 'result' ] as $cat_data ) {
                $links[] = self::PRODUCT_URL . $cat_data[ 'id' ] . '.html';
            }

            $p++;
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

        return !empty( $fi->getMpn() ) && count( $fi->getImages() ) && $fi->getRAvail() !== null && $fi->getCostToUs() > 0;
    }
}
