<?php

namespace App\Feeds\Parser;

use App\Feeds\Feed\FeedItem;
use App\Helpers\FeedHelper;
use App\Helpers\StringHelper;
use Exception;

abstract class ModalystParser extends HtmlParser
{
    protected bool $dual_parser = false;

    protected array $wrong_categories = [
        'modalyst',
        '$',
        'style'
    ];

    protected array $product_data = [
        'short_description' => [],
        'description' => '',
        'attributes' => []
    ];

    protected function getMeta(): void
    {
        $this->dual_parser = $this->getVendor()::DUAL_PARSER;

        if ( $this->node->count() ) {
            $json = preg_replace( '~.*?({.*}).*~', '$1', $this->node->html() );
            if ( preg_match( '~"description":"(.*?)",".*?":~si', $json, $match ) ) {
                $clean_desc = StringHelper::removeSpaces( StringHelper::cutTagsAttributes( $match[ 1 ] ?? '' ) );
                $clean_desc = preg_replace( '~\$(\d{1,5})~', '$ $1', $clean_desc );
                $json = preg_replace( '~"description":".*?",(".*?":)~si', '"description":"' . $clean_desc . '",$1', $json );
            }

            try {
                $this->meta = json_decode( StringHelper::normalizeJsonString( $json ), true, 512, JSON_THROW_ON_ERROR ) ?? [];
            } catch ( Exception ) {
                echo PHP_EOL . 'Cant parse json:' . PHP_EOL . $this->getUri() . PHP_EOL;
            }
        }
    }

    public function beforeParse(): void
    {
        if ( !empty( $this->meta ) ) {
            $this->product_data = FeedHelper::getShortsAndAttributesInDescription( $this->meta[ 'description' ] );

            if ( str_starts_with( trim( $this->product_data[ 'description' ] ), '</p>' ) ) {
                $this->product_data[ 'description' ] = preg_replace( '~</p>~', '', $this->product_data[ 'description' ], 1 );
            }
        }
    }

    public function isGroup(): bool
    {
        return count( $this->meta[ 'variants' ] ?? [] ) > 1;
    }

    public function getMpn(): string
    {
        if ( $this->dual_parser ) {
            return strtoupper( $this->meta[ 'variants' ][ 0 ][ 'sku' ] ?? '' );
        }
        return $this->meta[ 'sku' ] ?? '';
    }

    public function getProduct(): string
    {
        return str_replace( '&qout;', '"', $this->meta[ 'name' ] ?? '' );
    }

    public function getCostToUs(): float
    {
        return StringHelper::getMoney( $this->meta[ 'min_cost' ] );
    }

    public function getListPrice(): ?float
    {
        return StringHelper::getMoney( $this->meta[ 'min_retail_price' ] );
    }

    public function getBrand(): ?string
    {
        return $this->meta[ 'vendor' ] ?? null;
    }

    public function getAvail(): ?int
    {
        return (int)( $this->meta[ 'quantity' ] ?? 0 );
    }

    public function getShortDescription(): array
    {
        return $this->product_data[ 'short_description' ] ?? [];
    }

    public function getAttributes(): ?array
    {
        if ( empty( $this->meta ) || $this->isGroup() ) {
            return null;
        }

        $attributes = [];
        foreach ( $this->meta[ 'variants' ][ 0 ][ 'options' ] ?? [] as $attribute ) {
            if ( !empty( $attribute[ 'key' ] ) ) {
                $attributes[ $attribute[ 'key' ] ] = $attribute[ 'value' ] ?? '';
            }
        }

        $attributes = array_merge( $this->product_data[ 'attributes' ] ?? [], $attributes );
        foreach ( $attributes as $key => $value ) {
            if ( str_contains( $value, '$' ) ) {
                unset( $attributes[ $key ] );
            }
        }

        $attributes = array_filter( $attributes );
        if ( $this->dual_parser ) {
            $attributes[ 'Modalystsku' ] = strtoupper( $this->meta[ 'sku' ] ?? '' );
        }
        return array_filter( $attributes ) ?: null;
    }

    public function getDescription(): string
    {
        return StringHelper::isNotEmpty( $this->product_data[ 'description' ] ?? '' ) ? $this->product_data[ 'description' ] : parent::getDescription();
    }

    public function getCategories(): array
    {
        $categories = $this->meta[ 'tags' ] ?? [];
        $categories = array_filter( $categories, function ( string $category ) {
            if ( !StringHelper::isNotEmpty( $category ) ) {
                return false;
            }
            foreach ( $this->wrong_categories as $wrong_category ) {
                if ( str_contains( $category, $wrong_category ) ) {
                    return false;
                }
            }
            return true;
        } );

        return array_slice( array_values( $categories ), 0, 5 );
    }

    public function getImages(): array
    {
        $images = [];

        $urls[] = $this->meta[ 'image' ][ 'image' ] ?? '';
        foreach ( $this->meta[ 'images' ] ?? [] as $item ) {
            $urls[] = $item[ 'image' ] ?? '';
        }

        foreach ( $urls as $url ) {
            $final_url = StringHelper::normalizeSrcLink( $url, $this->getUri() );
            if ( !in_array( $final_url, $images, true ) ) {
                $images[] = $final_url;
            }
        }
        return array_values( array_filter( $images ) );
    }

    public function getChildProducts( FeedItem $parent_fi ): array
    {
        $children = [];

        foreach ( $this->meta[ 'variants' ] as $child ) {

            $fi = clone $parent_fi;

            $child_name = '';
            $add_attrs = [];
            foreach ( $child[ 'options' ] as $option ) {
                $child_name .= $option[ 'key' ] . ': ' . $option[ 'value' ] . ', ';
                $add_attrs[ $option[ 'key' ] ] = $option[ 'value' ];
            }
            $child_name = rtrim( $child_name, ', ' );

            if ( !$child_name ) {
                $child_name = $child[ 'sku' ];
            }
            $sku = $this->meta[ 'sku' ] . '-' . str_replace( [ ',', ' ', ':', '/', '--', '---' ], [ '-', '-', '-', '-', '-', '-' ], $child_name );

            $fi->setCostToUs( StringHelper::getMoney( $child[ 'price' ] ) );
            $fi->setProduct( $child_name );
            $fi->setRAvail( (int)$child[ 'quantity' ] );
            $fi->setImages( !is_null( $child[ 'image' ] ) ? [ $child[ 'image' ] ] : $this->getImages() );

            if ( $this->dual_parser ) {
                $fi->setMpn( strtoupper( $child[ 'sku' ] ) );
                $child_attributes = array_merge( $this->product_data[ 'attributes' ] ?? [], [ 'Modalystsku' => strtoupper( $sku ) ], $add_attrs );
            }
            else {
                $fi->setMpn( $sku );
                $child_attributes = array_merge( $this->product_data[ 'attributes' ] ?? [], $add_attrs );
            }

            $child_attributes = array_filter( $child_attributes ) ?: null;
            $fi->setAttributes( $child_attributes );
            $fi->setSupplierInternalId( 'https://modalyst.co/' . trim( $this->meta[ 'url' ], '/' ) );

            $children[] = $fi;
        }
        return $children;
    }

    public function getInternalId(): string
    {
        if ( empty( $this->meta[ 'url' ] ) || $this->isGroup() ) {
            return '';
        }
        return 'https://modalyst.co/' . trim( $this->meta[ 'url' ], '/' );
    }
}
