<?php

namespace App\Feeds\Vendors\KHC;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;
use App\Feeds\Utils\Link;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'ul.nav.nav-left a', 'div.product-category a', 'a.page-number',
        'a.page-numbers' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'div.product.type-product div.image-fade_in_back a' ];

    protected array $first = [ 'https://www.karmanhealthcare.com/?product_cat=&s=&post_type=product' ];

    public function filterProductLinks( Link $link ): bool
    {
        return str_contains( $link->getUrl(), '/product/' );
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
