<?php

namespace App\Feeds\Vendors\CPC;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;
use App\Feeds\Utils\Link;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'ul.sub-menu a', 'li.product-category a', 'a.uabb-new-ib-link', 'a.page-numbers' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'li.product.type-product a' ];

    protected const STATIC_USER_AGENT = true;
    protected array $headers = [ 'Accept: *', 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:90.0) Gecko/20100101 Firefox/90.0' ];

    protected array $first = [ 'https://carolinapetcompany.com/' ];

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
