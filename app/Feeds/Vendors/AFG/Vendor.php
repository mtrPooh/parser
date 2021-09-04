<?php

namespace App\Feeds\Vendors\AFG;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'ul.drop a' ];

    protected array $first = [ 'http://www.afgbabyfurniture.com/' ];

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        return !empty( $fi->getMpn() ) && count( $fi->getImages() );
    }
}
