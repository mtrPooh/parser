<?php

namespace App\Feeds\Vendors\CPC;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private array $images = [];
    private array $dims = [];
    private array $shorts = [];
    private ?array $attrs = null;
    private ?float $weight = null;

    public function beforeParse(): void
    {
        // Dimensions, Weight, Attributes
        $this->filter( 'table.woocommerce-product-attributes tr' )->each( function ( ParserCrawler $c ) {
            $name = trim( $c->filter( 'th' )->getNode( 0 )->nodeValue, ' : ' );
            $value = trim( $c->filter( 'td' )->getNode( 0 )->nodeValue, '  ' );
            if ( $name === 'Dimensions' ) {
                $value = explode( '×', $value );
                if ( count( $value ) === 3 ) {
                    $this->dims[ 'z' ] = StringHelper::getFloat( $value[ 0 ] );
                    $this->dims[ 'x' ] = StringHelper::getFloat( $value[ 1 ] );
                    $this->dims[ 'y' ] = StringHelper::getFloat( $value[ 2 ] );
                }
            }
            elseif ( $name === 'Weight' ) {
                $this->weight = StringHelper::getFloat( $value );
            }
            else {
                $this->attrs[ $name ] = $value;
            }
        } );

        // Short Description
        $this->filter( '#tab-description li' )->each( function ( ParserCrawler $c ) {
            $this->shorts[] = trim( $c->text(), '  ' );
        } );
    }

    public function isGroup(): bool
    {
        return $this->exists( 'form.variations_form' );
    }

    public function getMpn(): string
    {
        return $this->getText( 'span.sku' );
    }

    public function getProduct(): string
    {
        return $this->getText( 'h1.product_title' ) ?? '';
    }

    public function getCostToUs(): float
    {
        return $this->getMoney( 'p.price span.amount' );
    }

    public function getImages(): array
    {
        $this->filter( 'div.images a' )->each( function ( ParserCrawler $c ) {
            $image = $c->getAttr( 'a', 'href' );
            $filename = pathinfo( $image );
            $filename = $filename[ 'basename' ];
            if ( str_contains( $filename, '?' ) ) {
                $new_filename = substr( $filename, 0, strpos( $filename, '?' ) );
                $image = str_replace( $filename, $new_filename, $image );
            }
            $this->images[] = $image;
        } );

        return array_values( array_unique( $this->images ) );
    }

    public function getAvail(): ?int
    {
        $avail = trim( $this->getText( 'p.stock' ) );
        return $avail === 'Out of stock' ? 0 : self::DEFAULT_AVAIL_NUMBER;
    }

    public function getCategories(): array
    {
        $categories = $this->getContent( 'nav.woocommerce-breadcrumb a' );
        array_shift( $categories );

        return $categories;
    }

    public function getDescription(): string
    {
        $this->descr = $this->getHtml( 'div.woocommerce-product-details__short-description' );
        $this->filter( 'ul' )->each( function ( ParserCrawler $c ) {
            $this->descr = str_ireplace( $c->outerHtml(), '', $this->descr );
        } );

        return trim( $this->descr );
    }

    public function getShortDescription(): array
    {
        return $this->shorts;
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

    public function getAttributes(): ?array
    {
        return $this->attrs ?? null;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function getChildProducts( FeedItem $parent_fi ): array
    {
        $child = [];

        $json = $this->getAttr( 'form.variations_form', 'data-product_variations' );
        $products_data = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );

        if ( !is_array( $products_data ) ) {
            return [];
        }

        foreach ( $products_data as $product_data ) {

            if ( $product_data[ 'variation_is_visible' ] !== true ) {
                continue;
            }

            $fi = clone $parent_fi;

            $product_name = [];
            foreach ( $product_data[ 'attributes' ] as $key => $value ) {
                $label_id = $this->getAttr( "*[name='$key']", 'id' );
                $label = $this->getText( "label[for='$label_id']" );
                $name = $this->getText( "*[value='$value']" );
                $product_name[] = $label . ': ' . $name;
            }

            $fi->setMpn( $product_data[ 'sku' ] );
            $fi->setProduct( implode( ', ', $product_name ) );
            $fi->setCostToUs( StringHelper::getMoney( $product_data[ 'display_price' ] ) );
            $fi->setRAvail( $product_data[ 'is_in_stock' ] === true ? self::DEFAULT_AVAIL_NUMBER : 0 );
            $fi->setImages( [ $product_data[ 'image' ][ 'src' ] ] );
            if ( !empty( $product_data[ 'weight' ] ) ) {
                $fi->setWeight( (float) $product_data[ 'weight' ] );
            }
            if ( !empty( $product_data[ 'dimensions' ][ 'length' ] ) ) {
                $fi->setDimZ( (float) $product_data[ 'dimensions' ][ 'length' ] );
            }
            if ( !empty( $product_data[ 'dimensions' ][ 'width' ] ) ) {
                $fi->setDimX( (float) $product_data[ 'dimensions' ][ 'width' ] );
            }
            if ( !empty( $product_data[ 'dimensions' ][ 'height' ] ) ) {
                $fi->setDimY( (float) $product_data[ 'dimensions' ][ 'height' ] );
            }
            if ( $product_data[ 'display_price' ] < $product_data[ 'display_regular_price' ] ) {
                $fi->setListPrice( StringHelper::getMoney( $product_data[ 'display_regular_price' ] ) );
            }

            $child[] = $fi;
        }

        return $child;
    }
}
