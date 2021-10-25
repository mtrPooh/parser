<?php

namespace App\Feeds\Vendors\BNI;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\FeedHelper;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private array $dims = [];
    private array $shorts = [];
    private ?array $attrs = null;

    private const API_URL = 'https://www.brunelli.ca/ecomwgtproductpage/getproductbyselectedattribute';

    public function parseContent( $data, $params = [] ): array
    {
        preg_match( '%title="en" href="(.*?)"%ui', $data->getData(), $match );

        $link_en = str_contains( $match[ 1 ], 'http' ) ? $match[ 1 ] : 'https://www.brunelli.ca/' . ltrim( $match[ 1 ], '/' );

        $data = $this->getVendor()->getDownloader()->get( $link_en );

        return parent::parseContent( new Data( $data ), $params );
    }

    public function beforeParse(): void
    {
        $product_info = FeedHelper::getShortsAndAttributesInList( $this->getHtml( 'div.product-details-desc' ) );

        $this->shorts = $product_info[ 'short_description' ];
        $this->attrs = $product_info[ 'attributes' ];

        foreach ( $this->attrs as $key => $value ) {

            if ( $key === 'Dimensions' || $key === 'Size' ) {

                preg_match_all( '%(\d+.) cm%ui', $value, $matches );

                if ( !empty( $matches[ 1 ][ 0 ] ) ) {
                    $this->dims[ 'x' ] = StringHelper::getFloat( $matches[ 1 ][ 0 ] / 2.54 );
                }
                if ( !empty( $matches[ 1 ][ 1 ] ) ) {
                    $this->dims[ 'y' ] = StringHelper::getFloat( $matches[ 1 ][ 1 ] / 2.54 );
                }
                if ( !empty( $matches[ 1 ][ 2 ] ) ) {
                    $this->dims[ 'z' ] = StringHelper::getFloat( $matches[ 1 ][ 2 ] / 2.54 );
                }

                if ( empty( $this->dims[ 'x' ] ) ) {

                    preg_match_all( '%(\d+.)%ui', $value, $matches );

                    if ( !empty( $matches[ 1 ][ 0 ] ) ) {
                        $this->dims[ 'x' ] = StringHelper::getFloat( $matches[ 1 ][ 0 ] );
                    }
                    if ( !empty( $matches[ 1 ][ 1 ] ) ) {
                        $this->dims[ 'y' ] = StringHelper::getFloat( $matches[ 1 ][ 1 ] );
                    }
                    if ( !empty( $matches[ 1 ][ 2 ] ) ) {
                        $this->dims[ 'z' ] = StringHelper::getFloat( $matches[ 1 ][ 2 ] );
                    }
                }

                unset( $this->attrs[ $key ] );
            }
        }
    }

    public function isGroup(): bool
    {
        return $this->exists( 'ul.configuration-attributes' );
    }

    public function getMpn(): string
    {
        $mpn = $this->getText( 'p.product-details-code' );
        $mpn = explode( ':', $mpn );

        return trim( $mpn[ 1 ] );
    }

    public function getProduct(): string
    {
        return $this->getText( 'h1' );
    }

    public function getCategories(): array
    {
        $categories = $this->getContent( 'ul.breadcrumb li a' );
        foreach ( $categories as $key => $value ) {
            if ( $value === 'Home' || $value === 'Catalog' ) {
                unset( $categories[ $key ] );
            }
        }

        return array_values( $categories );
    }

    public function getCostToUs(): float
    {
        $price = str_replace( ',', '.', $this->getText( '*.price-current' ) );

        return StringHelper::getFloat( $price );
    }

    public function getListPrice(): ?float
    {
        $price = str_replace( ',', '.', $this->getText( '*.price-before-discount' ) );

        return StringHelper::getFloat( $price );
    }

    public function getImages(): array
    {
        $images = [];
        $this->filter( 'ul.slides li a' )->each( function ( ParserCrawler $c ) use ( &$images ) {
            $images[] = $c->getAttr( 'a', 'data-zoom-image' );
        } );

        if ( empty( $images ) ) {
            $image = $this->getAttr( 'img#product-detail-gallery-main-img', 'data-zoom-image' );
            if ( !empty( $image ) ) {
                $images[] = $image;
            }
        }

        foreach ( $images as $key => $value ) {
            $filename = pathinfo( $value );
            $filename = $filename[ 'basename' ];
            if ( str_contains( $filename, '?' ) ) {
                $new_filename = substr( $filename, 0, strpos( $filename, '?' ) );
                $value = str_replace( $filename, $new_filename, $value );
            }
            $images[ $key ] = !str_contains( $value, 'http' ) ? 'https://www.brunelli.ca/' . ltrim( $value, '/' ) : $value;
        }

        return array_values( array_unique( $images ) );
    }

    public function getAvail(): ?int
    {
        return StringHelper::getFloat( $this->getText( 'div.box-qty li' ), 0 );
    }

    public function getBrand(): ?string
    {
        $brand = $this->getAttr( 'div.product-brand img', 'title' );
        if ( !empty( $brand ) ) {
            return $brand;
        }

        return null;
    }

    public function getDescription(): string
    {
        return $this->getProduct();
    }

    public function getShortDescription(): array
    {
        return array_slice( $this->shorts, 0, 10 );
    }

    public function getAttributes(): ?array
    {
        return $this->attrs ?: null;
    }

    public function getDimZ(): ?float
    {
        return $this->dims[ 'z' ] ?? null;
    }

    public function getDimX(): ?float
    {
        return $this->dims[ 'x' ] ?? null;
    }

    public function getDimY(): ?float
    {
        return $this->dims[ 'y' ] ?? null;
    }

    public function getChildProducts( FeedItem $parent_fi ): array
    {
        $child = [];

        $attributes_title = trim( $this->getText( 'ul.configuration-attributes li.attribute-title' ) );
        $attributes_id = $this->getAttr( 'ul.configuration-attributes li.attribute-value select', 'data-idattribute' );
        $attributes_code = $this->getAttr( 'ul.configuration-attributes li.attribute-value select', 'data-codeattribute' );
        $widget_code = $this->getAttr( 'input#WidgetUniqueCode', 'value' );
        $category_id = $this->getAttr( 'input#CategoryId', 'value' );

        $attributes = [];
        $this->filter( 'ul.configuration-attributes select option' )->each( function ( ParserCrawler $c ) use ( &$attributes
        ) {
            $value = trim( $c->getAttr( 'option', 'value' ) );
            $name = trim( $c->getText( 'option' ) );
            $attributes[] = [ $value, $name ];
        } );

        $product_id = $this->getAttr( 'form#ProductPageForm ul.ejs-addtocart-section', 'data-productid' );

        foreach ( $attributes as $attribute ) {

            $post_data = [
                'selectedAttributeId' => $attributes_id,
                'selectedValue' => $attribute[ 0 ],
                'productId' => $product_id,
                'widgetUniqueCode' => $widget_code,
                'categoryId' => $category_id,
                'codeAttributes' => $attributes_code . '~#~' . str_replace( ' ', '+', $attribute[ 0 ] ) . '~|~',
                'promotionFromId' => -1
            ];

            $json = $this->vendor->getDownloader()->post( self::API_URL, $post_data );

            $json = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );

            $html = $json[ 'ProductPageBody' ];

            $fi = clone $parent_fi;

            // Product
            $fi->setProduct( $attributes_title . ': ' . $attribute[ 1 ] );

            // MPN
            preg_match( '%product-details-code.*>(.*?)</p%ui', $html, $match );
            $mpn = explode( ':', $match[ 1 ] );
            $fi->setMpn( trim( $mpn[ 1 ] ) );

            // Available
            preg_match( '%box-qty.*?<li.*?(\d+)\s*</li%uis', $html, $match );
            $fi->setRAvail( $match[ 1 ] ?? 0 );

            // Price
            preg_match( '%price-current">(.*?)</strong>%ui', $html, $match );
            $fi->setCostToUs( StringHelper::getMoney( str_replace( ',', '.', $match[ 1 ] ) ) );

            // Price
            preg_match( '%price-before-discount">(.*?)</%ui', $html, $match );
            if ( !empty( $match[ 1 ] ) ) {
                $fi->setListPrice( StringHelper::getMoney( str_replace( ',', '.', $match[ 1 ] ) ) );
            }

            // Images
            $images = [];
            if ( preg_match( '%class="slides"(.*?)</ul%uis', $html, $match ) ) {

                preg_match_all( '%data-zoom-image="(.*?)"%ui', $match[ 1 ], $matches );
                $images = $matches[ 1 ] ?: [];
            }

            if ( empty( $images ) && preg_match( '%id="product-detail-gallery-main-img".*?data-zoom-image="(.*?)"%ui', $html, $match ) ) {
                $images[] = $match[ 1 ];
            }

            foreach ( $images as $key => $value ) {
                $filename = pathinfo( $value );
                $filename = $filename[ 'basename' ];
                if ( str_contains( $filename, '?' ) ) {
                    $new_filename = substr( $filename, 0, strpos( $filename, '?' ) );
                    $value = str_replace( $filename, $new_filename, $value );
                }
                $images[ $key ] = !str_contains( $value, 'http' ) ? 'https://www.brunelli.ca/' . ltrim( $value, '/' ) : $value;
            }

            $images = array_values( array_unique( $images ) );

            $fi->setImages( $images );

            $child[] = $fi;

            preg_match( '%ProductPageForm.*?ejs-addtocart-section.*?data-productid="(.*?)"%uis', $html, $match );
            $product_id = $match[ 1 ];
        }

        return $child;
    }
}
