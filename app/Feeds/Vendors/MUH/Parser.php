<?php

namespace App\Feeds\Vendors\MUH;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\Link;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;
use App\Helpers\FeedHelper;

class Parser extends HtmlParser
{
    private const   API_URL = 'https://www.mudhole.com/api/items?include=facets&fieldset=details&language=en&country=US&currency=USD&url=';
    private ?string $brand  = null;
    private ?float  $weight = null;
    private ?array  $attrs  = null;
    private ?string $descr  = null;
    private array   $files  = [];
    private array   $videos = [];
    private array   $dims   = [];

    public function beforeParse(): void
    {
        // Attributes
        $this->filter( 'div.specs-content table tr' )->each( function ( ParserCrawler $c )  {
            $name  = trim( $c->filter( 'td' )->getNode( 0 )->textContent, ' : ' );
            $value = trim( $c->filter( 'td' )->getNode( 1 )->textContent, '  ' );

            if ( $name === 'Brand' ) {
                $this->brand = $value;
            }
            elseif ( $name === 'Weight (oz)' ) {
                $this->weight = FeedHelper::convertLbsFromOz( (float) $value );
            }
            elseif ( $name === 'Length' ) {
                $this->dims[ 'z' ] = (float) ( str_replace("'", '.', trim( $value, '" ') ) );
            }
            else {
                $this->attrs[ $name ] = $value;
            }
        });

        // Files
        $this->filter( 'div#Resources .text-center a[target="_blank"]' )->each( function ( ParserCrawler $c )  {
            $name = trim( $c->text(), ' : ' );
            $link = $c->getAttr( 'a', 'href' );
            if ( !str_contains($link, 'http' ) ) {
                $link = 'https://www.mudhole.com' . $link;
            }
            $this->files[] = [ 'name' => $name, 'link' => $link ];
        });

        // Description
        // html-code on site is incorrect and getHtml() return unnecessary code
        // it need to remove from Description
        $this->descr = $this->getHtml( 'div.text-cont' );
        $videos = $this->getHtml( 'div#videos' );
        $resources = $this->getHtml( 'div#Resources' );
        $spec = $this->getHtml( 'div#spec' );

        $del[] = '<div class="tab-pane" id="videos"></div>';
        $del[] = '<div class="tab-pane" id="Resources"></div>';
        $del[] = '<div class="tab-pane" id="spec"></div>';

        $this->descr = str_ireplace( [ $videos, $resources, $spec ], '', $this->descr );
        $this->descr = str_ireplace( $del, '', $this->descr );
        $this->descr = trim( preg_replace( '%<table.*?</table>%uis', '', $this->descr ) );
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
        return $this->getMoney( 'span[itemprop="price"]' );
    }

    public function getListPrice(): ?float
    {
        return $this->getMoney( 'span[itemprop="offers"] small.muted.crossed' );
    }

    public function getImages(): array
    {
        $images = $this->getSrcImages( '.pinterest-image a#btn-lightbox-image noscript img' ) ?:
                  $this->getSrcImages( '.pinterest-image a#btn-lightbox-image img' );

        foreach ( $images as $key => $value ) {
            $filename = pathinfo( $value );
            $filename = $filename[ 'basename' ];
            if ( str_contains( $filename, '?' ) ) {
                $new_filename = substr( $filename, 0, strpos( $filename, '?' ) );
                $images[ $key ] = str_replace( $filename, $new_filename, $value );
            }
        }

        return array_values( array_unique( $images ) );
    }

    public function getAvail(): ?int
    {
        $stock_status = $this->getAttr( 'meta[name="og:availability"]', 'content' );
        return $stock_status === 'InStock' ? self::DEFAULT_AVAIL_NUMBER : 0;
    }

    public function getCategories(): array
    {
        $categories = $this->getContent( '.breadcrumb li a' );
        array_shift( $categories );
        return $categories;
    }

    public function getDescription(): string
    {
        return $this->descr;
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

    public function getProductFiles(): array
    {
        return $this->files;
    }

    public function getVideos(): array
    {
        $this->filter( 'div.youtubevideo' )->each( function ( ParserCrawler $c )  {
            $yt_src = $c->getAttr( 'div', 'data-yt-src' );
            if ( !empty( $yt_src ) ) {
                $this->videos[] = [ 'name' => $this->getProduct(),
                                    'video' => 'https://www.youtube.com/watch?v=' . $yt_src,
                                    'provider' => 'youtube' ];
            }
        });

        $this->filter( 'div#item-videos iframe' )->each( function ( ParserCrawler $c )  {
            $src = $c->getAttr( 'iframe', 'src' );
            if ( !empty( $src ) ) {
                $url_parts = parse_url( $src );
                $domain = $url_parts[ 'host' ];
                if ( str_contains( $domain, '.' ) ) {
                    $domain = explode( '.', $domain );
                    $domain = $domain[ count( $domain ) - 2 ];
                }
                $this->videos[] = [ 'name' => $this->getProduct(),
                                    'video' => $src,
                                    'provider' => $domain ];
            }
        });

        return $this->videos;
    }

    public function getDimZ(): ?float
    {
        return $this->dims[ 'z' ] ?? null;
    }

    public function getChildProducts( FeedItem $parent_fi ): array
    {
        $child = [];

        $product_uri = explode( '/', trim( $parent_fi->supplier_internal_id, '/ ' ) );
        $product_uri = end( $product_uri );

        $link = new Link( self::API_URL . $product_uri );

        $products = $this->getVendor()->getDownloader()->fetch( [ $link ], true )[ 0 ];
        $products_data = json_decode( $products[ 'data' ], true, 512, JSON_THROW_ON_ERROR );

        if ( empty( $products_data[ 'items' ][ 0 ][ 'matrixchilditems_detail' ] ) ) {
            return [];
        }

        $option_labels = [];
        foreach ( $products_data[ 'items' ][ 0 ][ 'itemoptions_detail' ][ 'fields' ] as $option ) {
            if ( empty( $option[ 'ismandatory' ] ) || empty( $option[ 'sourcefrom' ] ) || empty( $option[ 'label' ] ) ) {
                continue;
            }
            $option_labels[ $option[ 'sourcefrom' ] ] = ucfirst( $option[ 'label' ] );
        }

        foreach ( $products_data[ 'items' ][ 0 ][ 'matrixchilditems_detail' ] as $product_data ) {

            $fi = clone $parent_fi;

            $product_name = [];
            foreach ( $option_labels as $key => $value ) {
                if ( !empty( $product_data[ $key ] ) ) {
                    $product_name[] = $value . ': ' . $product_data[ $key ];
                }
            }

            $fi->setMpn( $product_data[ 'itemid' ] );
            $fi->setProduct( implode(', ', $product_name) );
            $fi->setCostToUs( StringHelper::getMoney( $product_data[ 'onlinecustomerprice' ] ) );
            $fi->setListPrice( StringHelper::getMoney( $product_data[ 'pricelevel9' ] ) );
            $fi->setRAvail( $product_data[ 'isinstock' ] ? self::DEFAULT_AVAIL_NUMBER : 0 );

            $child[] = $fi;
        }

        return $child;
    }
}
