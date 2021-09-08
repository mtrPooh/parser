<?php

namespace App\Feeds\Vendors\JEF;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;
use App\Feeds\Utils\Link;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'li.menu-item li a', 'ul.wl-pagination li a' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'li.product-grid-cell p.name a' ];

    protected array $first = [ 'https://www.jefferspet.com/' ];

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
