<?php

namespace App\Feeds\Vendors\ZRQ;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'div.header-secondary div a', 'div#search_result a' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'a.btn-amazon', 'div.row.text-center a' ];

    protected array $first = [ 'https://www.bbopokertables.com/' ];

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        return !empty( $fi->getMpn() ) && count( $fi->getImages() ) && $fi->getRAvail() !== null && $fi->getCostToUs() > 0;
    }
}
