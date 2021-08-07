<?php

namespace App\Feeds\Vendors\MUH;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'li.level-2 a', '.category-cell-name a', '.pagination-links li a' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'tr.item-cell-line td a', 'h2.item-cell-name a' ];
    
    protected array $first = [ 'https://www.mudhole.com/' ];
    
    public function isValidFeedItem( FeedItem $fi ): bool
    {
        if ( $fi->isGroup() ) {
            $fi->setChildProducts( array_values(
                 array_filter( $fi->getChildProducts(), static fn( FeedItem $item ) => !empty( $item->getMpn() ) && count( $item->getImages() ) && $item->getCostToUs() > 0 )
            ) );
            return count( $fi->getChildProducts() );
        }
        return !empty( $fi->getMpn()) && count( $fi->getImages() ) && $fi->getCostToUs() > 0;
    }
}
