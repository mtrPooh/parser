<?php

namespace App\Feeds\Storage;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\AbstractProcessor;
use DateTime;

abstract class AbstractFeedStorage
{
    protected AbstractProcessor $processor;
    protected DateTime $start;

    private array $inventory_props = [
        'productcode',
        'ASIN',
        'product',
        'descr',
        'fulldescr',
        'brand_name',
        'brand_normalized',
        'forsale',
        'eta_date_mm_dd_yyyy',
        'upc',
        'supplier_categories',
        'supplier_internal_id',
        'hash_product',
        'images',
        'alt_names',
        'dim_x',
        'dim_y',
        'dim_z',
        'shipping_weight',
        'shipping_dim_x',
        'shipping_dim_y',
        'shipping_dim_z',
        'weight',
        'min_amount',
        'mult_order_quantity',
        'is_group',
        'child_products',
        'group_mask',
        'r_avail',
        'mpn',
        'lead_time_message',
        'attributes',
        'product_files',
        'options',
        'videos',
    ];

    public function __construct()
    {
        $this->start = new DateTime();
    }

    /**
     * @param AbstractProcessor $processor
     * @param FeedItem[] $items
     */
    abstract public function saveFeed( AbstractProcessor $processor, array $items ): void;

    /**
     * @param FeedItem[] $items
     * @return array
     */
    protected function getData( array $items ): array
    {
        $result = [];
        $feed_type = $this->processor->getFeedType();
        foreach ( $items as $item ) {
            if ( $item->isGroup() ) {
                $child_attrs = [];
                foreach ( $item->getChildProducts() as $child ) {
                    $child_attrs[] = $feed_type === $this->processor::FEED_TYPE_INVENTORY ? $child->propsToArray( $this->inventory_props ) : $child->propsToArray();
                }
            }
            $attrs = $feed_type === $this->processor::FEED_TYPE_INVENTORY ? $item->propsToArray( $this->inventory_props ) : $item->propsToArray();
            $attrs[ 'child_products' ] = $child_attrs ?? [];

            $result[] = $attrs;
        }

        return $result;
    }

    protected function getProcessTime(): int
    {
        return ( new DateTime() )->getTimestamp() - $this->start->getTimestamp();
    }

    public function shutdown(): void
    {

    }
}