<?php

namespace App\Feeds\Vendors\IVR;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;
use App\Feeds\Utils\Data;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'ul#menulist li.firstmenu a', 'ul#menulist li.third-ctgr a' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [];
    private const API_URL1 = 'https://www.ivrose.com/v9/collection/anon/';
    private const API_URL2 = 'https://www.ivrose.com/v9/product/anon/';
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

        $p = 0;
        while ( true ) {
            $params = [
                'collectionId' => $category_id,
                'currency' => 'USD',
                'endPrice' => '',
                'filterItems' => [],
                'sorter' => 0,
                'startPrice' => ''
            ];

            $data = $this->getDownloader()->post( self::API_URL1 . $p * 20 . '/20/filter', $params, 'request_payload' );
            $data = json_decode( $data->getData(), true, 512, JSON_THROW_ON_ERROR );

            if ( empty( $data[ 'result' ] ) ) {
                break;
            }

            foreach ( $data[ 'result' ] as $cat_data ) {
                if ( $cat_data[ 'price' ][ 'amount' ] < 0.01 ) {
                    continue;
                }
                $links[] = self::PRODUCT_URL . $cat_data[ 'id' ] . '.html';
            }

            $p++;
        }

        if ( empty( $links ) ) {
            $p = 0;
            while ( true ) {
                $params = [
                    'categoryId' => $category_id,
                    'currency' => 'USD',
                    'endPrice' => '',
                    'filterItems' => [],
                    'sorter' => 0,
                    'startPrice' => ''
                ];

                $data = $this->getDownloader()->post( self::API_URL2 . $p * 20 . '/20/w-filter', $params, 'request_payload' );
                $data = json_decode( $data->getData(), true, 512, JSON_THROW_ON_ERROR );

                if ( empty( $data[ 'result' ] ) ) {
                    break;
                }

                foreach ( $data[ 'result' ] as $cat_data ) {
                    if ( $cat_data[ 'price' ][ 'amount' ] < 0.01 ) {
                        continue;
                    }
                    $links[] = self::PRODUCT_URL . $cat_data[ 'id' ] . '.html';
                }

                $p++;
            }
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
