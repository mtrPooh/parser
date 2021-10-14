<?php

namespace App\Feeds\Vendors\BTS;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;
use App\Feeds\Utils\Link;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'li.level-3 a', 'div.sub-category a', 'div.page-links a' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'div.category-product a' ];

    protected array $first = [ 'https://www.bbtoystore.com/' ];

    public function filterProductLinks( Link $link ): bool
    {
        return str_contains( $link->getUrl(), '/store/' );
    }

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        return !empty( $fi->getMpn() ) && count( $fi->getImages() ) && $fi->getCostToUs() > 0;
    }
}
