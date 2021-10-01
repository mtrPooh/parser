<?php

namespace App\Feeds\Vendors\AFF;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'ul.navPages-list--categories a', 'li.pagination-item a' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'li.product .card-title a' ];

    protected array $first = [ 'https://www.affinitechstore.com/' ];

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        return !empty( $fi->getMpn() ) && count( $fi->getImages() ) && $fi->getCostToUs() > 0;
    }
}
