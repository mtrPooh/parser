<?php

namespace App\Feeds\Vendors\MUH;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\Link;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private const   API_URL = 'https://www.mudhole.com/api/items?include=facets&fieldset=details&language=en&country=US&currency=USD&url=';
    private ?string $brand  = null;
    private ?float  $weight = null;
    private ?array  $attrs  = null;
    
    public function beforeParse(): void
    {
        $this->filter( 'div.specs-content table tr' )->each( function ( ParserCrawler $c )  {
            $name  = trim($c->filter( 'td' )->getNode( 0 )->textContent, ' : ' );
            $value = trim($c->filter( 'td' )->getNode( 1 )->textContent, '  ' );
            
            if ( $name == 'Brand' ) {
                $this->brand = $value;
            }
            elseif ( $name == 'Weight (oz)' ) {
                $this->weight = $value;
            }
            else {
                $this->attrs[$name] = $value;
            }
        });
    }
    
    public function isGroup(): bool
    {
        return $this->exists( 'a[data-toggle="set-option"]' );
    }
    
    public function getMpn(): string
    {
        return $this->getText( 'span[itemprop="sku"]' ) ?? '';
    }

    public function getProduct(): string
    {
        return $this->getText( 'h1[itemprop="name"]' ) ?? '';
    }

    public function getCostToUs(): float
    {
        if ( $this->isGroup() ) {
            return 0;
        }
        return StringHelper::getMoney( $this->getMoney( 'span[itemprop="price"]' ) );
    }
    
    public function getListPrice(): ?float
    {
        return StringHelper::getMoney( $this->getMoney( 'span[itemprop="offers"] small.muted.crossed' ) ) ?? null;
    }

    public function getImages(): array
    {
        $images = $this->getSrcImages( '.pinterest-image a#btn-lightbox-image noscript img' ) ?:
                  $this->getSrcImages( '.pinterest-image a#btn-lightbox-image img' );
        return array_values( array_unique( $images ) );
    }
    
    public function getAvail(): ?int
    {
        $stock_status = $this->getAttr( 'meta[name="og:availability"]', 'content' );
        return $stock_status == 'InStock' ? self::DEFAULT_AVAIL_NUMBER : 0;
    }
    
    public function getCategories(): array
    {
        $categories = $this->getContent( '.breadcrumb li a' );
        array_shift( $categories );
        return $categories;
    }
    
    public function getDescription(): string
    {
        return trim( $this->getHtml( 'div.text-cont' ) );
    }
    
    public function getBrand(): ?string
    {
        return $this->brand ?? null;
    }
    
    public function getWeight(): ?float
    {
        return $this->weight ?? null;
    }
    
    public function getAttributes(): ?array
    {
        return $this->attrs ?? null;
    }
    
    public function getChildProducts( FeedItem $parent_fi ): array
    {
        $child = [];
    
        $product_uri = explode( '/', trim( $parent_fi->supplier_internal_id, '/ ' ) );
        $product_uri = end( $product_uri );
        
        $link = new Link( self::API_URL . $product_uri );

        $products = $this->getVendor()->getDownloader()->fetch( [ $link ], true )[ 0 ];
        $products_data = json_decode( $products[ 'data' ], true, 512, JSON_THROW_ON_ERROR );
        
        if ( empty( $products_data[ 'items' ][ 0 ][ 'matrixchilditems_detail' ] ) ) return [];

        $product_name = $products_data[ 'items' ][ 0 ][ 'storedisplayname2' ];
        
        foreach ( $products_data[ 'items' ][ 0 ][ 'matrixchilditems_detail' ] as $product_data ) {
            
            $fi = clone $parent_fi;
            
            $fi->setMpn( $product_data[ 'itemid' ] );
            $fi->setProduct( $product_name );
            $fi->setCostToUs( StringHelper::getMoney( $product_data[ 'onlinecustomerprice' ] ) );
            $fi->setListPrice( StringHelper::getMoney( $product_data[ 'pricelevel9' ] ) );
            $fi->setRAvail( $product_data[ 'isinstock' ] ? self::DEFAULT_AVAIL_NUMBER : 0 );
            
            $child[] = $fi;
        }

        return $child;
    }
}
