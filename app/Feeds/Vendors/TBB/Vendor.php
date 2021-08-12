<?php

namespace App\Feeds\Vendors\TBB;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;
use App\Feeds\Utils\Link;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'div.contentbox div.columns a', 'div.columns li.text-center a', 'ul.pagination li a' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'div[unbxdattr="product"] a.more-info' ];

    protected array $first = [ 'https://www.1000bulbs.com/pages/all_categories.html' ];

    public function filterProductLinks( Link $link ): bool
    {
        return str_contains( $link->getUrl(), '/product/' );
    }

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        return !empty( $fi->getMpn() ) && count( $fi->getImages() ) && $fi->getRAvail() !== null && $fi->getCostToUs() > 0;
    }
}
