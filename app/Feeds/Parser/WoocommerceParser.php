<?php

namespace App\Feeds\Parser;

use App\Feeds\Feed\FeedItem;
use App\Helpers\FeedHelper;
use App\Helpers\StringHelper;

abstract class WoocommerceParser extends HtmlParser
{
    public function isGroup(): bool
    {
        return $this->getAttr( 'form.variations_form', 'data-product_variations' );
    }

    public function getChildProducts( FeedItem $parent_fi ): array
    {
        $children = [];

        $product_json = $this->getAttr( '[data-product_variations]', 'data-product_variations' );
        $product_variants = json_decode( $product_json, true, 512, JSON_THROW_ON_ERROR );

        foreach ( $product_variants as $product ) {
            $fi = clone( $parent_fi );

            $product_name = '';
            foreach ( $product[ 'attributes' ] as $attribute ) {
                if ( !empty( $attribute ) ) {
                    $product_name .= ' ' . $attribute;
                }
            }

            $fi->setProduct( trim( str_replace( '-', ' ', $product_name ) ) ?: $this->getProduct() );
            $fi->setMpn( $product[ 'sku' ] );
            $fi->setFulldescr( $product[ 'variation_description' ] ?: $parent_fi->getFulldescr() );

            $fi->setCostToUs( StringHelper::getMoney( $product[ 'display_regular_price' ] ) );
            $fi->setRAvail( $product[ 'is_in_stock' ] === true ? self::DEFAULT_AVAIL_NUMBER : 0 );

            $fi->setDimX( isset( $product[ 'dimensions' ][ 'length' ] ) ? StringHelper::getFloat( $product[ 'dimensions' ][ 'length' ] ) : null );
            $fi->setDimY( isset( $product[ 'dimensions' ][ 'height' ] ) ? StringHelper::getFloat( $product[ 'dimensions' ][ 'height' ] ) : null );
            $fi->setDimZ( isset( $product[ 'dimensions' ][ 'width' ] ) ? StringHelper::getFloat( $product[ 'dimensions' ][ 'width' ] ) : null );

            if ( $product[ 'weight_html' ] !== 'N/A' ) {
                if ( stripos( $product[ 'weight_html' ], 'oz' ) ) {
                    $fi->setWeight( FeedHelper::convertLbsFromOz( StringHelper::getFloat( $product[ 'weight' ] ) ) );
                }
                else {
                    $fi->setWeight( StringHelper::getFloat( $product[ 'weight' ] ) );
                }
            }

            $fi->setMinAmount( $product[ 'min_qty' ] );

            if ( isset( $product[ 'variation_gallery_images' ] ) ) {
                $fi->setImages( array_map( static fn( $image ) => $image[ 'full_src' ], $product[ 'variation_gallery_images' ] ) );
            }
            else {
                $fi->setImages( [ $product[ 'image' ][ 'full_src' ] ] );
            }

            $children[] = $fi;
        }

        return $children;
    }
}