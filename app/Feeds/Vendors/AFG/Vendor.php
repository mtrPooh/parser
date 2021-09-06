<?php

namespace App\Feeds\Vendors\AFG;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;

class Vendor extends HttpProcessor
{
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'ul.drop a' ];

    protected array $first = [ 'http://www.afgbabyfurniture.com/' ];

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        if ( $fi->isGroup() ) {
            $fi->setChildProducts( array_values(
                array_filter( $fi->getChildProducts(), static fn( FeedItem $item ) => !empty( $item->getMpn() ) && count( $item->getImages() ) )
            ) );

            return count( $fi->getChildProducts() );
        }

        return !empty( $fi->getMpn() ) && count( $fi->getImages() );
    }
}
