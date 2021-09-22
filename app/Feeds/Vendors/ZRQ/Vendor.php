<?php

namespace App\Feeds\Vendors\ZRQ;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;
use App\Feeds\Utils\Data;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'div.header-secondary div a', 'div#search_result a' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'a.btn-amazon', 'div.row.text-center a' ];

    protected array $first = [ 'https://www.bbopokertables.com/' ];

    private function getFilteredLinks( array $links ): array
    {
        $filtered_links = [];
        foreach ( $links as $link ) {
            if ( str_contains( $link->getUrl(), 'javascript' ) === false ) {
                $filtered_links[] = $link;
            }
        }

        return $filtered_links;
    }

    public function getCategoriesLinks( Data $data, string $url ): array
    {
        $links = parent::getCategoriesLinks( $data, $url );

        return $this->getFilteredLinks( $links );
    }

    public function getProductsLinks( Data $data, string $url ): array
    {
        $links = parent::getProductsLinks( $data, $url );

        return $this->getFilteredLinks( $links );
    }

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        return !empty( $fi->getMpn() ) && count( $fi->getImages() ) && $fi->getRAvail() !== null && $fi->getCostToUs() > 0;
    }
}
