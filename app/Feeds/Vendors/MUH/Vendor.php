<?php

namespace App\Feeds\Vendors\MUH;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'li.level-2 a', '.category-cell-name a', '.pagination-links li a' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'tr.item-cell-line td a.', 'h2.item-cell-name a' ];

    protected array $first = [ 'https://www.mudhole.com/' ];
    protected ?int $max_products = 1;

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        return !empty( $fi->getMpn() );
    }
}
