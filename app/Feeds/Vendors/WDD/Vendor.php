<?php

namespace App\Feeds\Vendors\WDD;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'div.SubCategoryListGrid li a', 'ul.PagingList a' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'ul.ProductList a' ];

    protected array $first = [ 'https://www.wooddesigns.com/products/' ];

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        return !empty( $fi->getMpn() ) && count( $fi->getImages() ) && $fi->getRAvail() !== null && $fi->getCostToUs() > 0;
    }
}
