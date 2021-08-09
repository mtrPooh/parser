<?php

namespace App\Feeds\Vendors\MHP;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ '.box-category li a', '.category-list li a', '.pagination a' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'div.product-list .name a' ];

    protected array $first = [ 'https://motorheadproducts.com/' ];

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        if ( !$fi->getMpn() ) echo 'Mpn: ' . $fi->getSupplierInternalId(), PHP_EOL;
        if ( !$fi->getCostToUs() ) echo 'CostToUs: ' . $fi->getSupplierInternalId(), PHP_EOL;
        if ( !$fi->getImages() ) echo 'Images: ' . $fi->getSupplierInternalId(), PHP_EOL;
        if ( !$fi->fulldescr ) echo 'Descr: ' . $fi->getSupplierInternalId(), PHP_EOL;

        return !empty( $fi->getMpn() ) && count( $fi->getImages() ) && $fi->getCostToUs() > 0;
    }
}
