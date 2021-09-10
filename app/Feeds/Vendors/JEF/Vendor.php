<?php

namespace App\Feeds\Vendors\JEF;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\SitemapHttpProcessor;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;

class Vendor extends SitemapHttpProcessor
{
    protected array $first = [ 'https://www.jefferspet.com/sitemap.xml.gz' ];

    public array $custom_products = [ 'https://www.jefferspet.com/products/no-chew-petflex-2' ];

    public function getProductsLinks( Data $data, string $url ): array
    {
        $sitemap = '';
        $fp = gzopen( $url, "r" );
        while ( !gzeof( $fp ) ) {
            $sitemap .= gzread( $fp, 4096 );
        }
        gzclose( $fp );

        if ( preg_match_all( '/<loc>([^<]*)<\/loc>/m', $sitemap, $matches ) ) {
            $links = array_map( static fn( $url ) => new Link( htmlspecialchars_decode( $url ) ), $matches[ 1 ] );
        }

        return array_values( array_filter( $links ?? [], [ $this, 'filterProductLinks' ] ) );
    }

    public function filterProductLinks( Link $link ): bool
    {
        return str_contains( $link->getUrl(), '/products/' );
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
