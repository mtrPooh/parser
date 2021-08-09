<?php

namespace App\Feeds\Vendors\MHP;

use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;

class Parser extends HtmlParser
{
    private string $mpn = '';
    private ?int $avail = null;
    private array $images = [];
    private array $videos = [];

    public function beforeParse(): void
    {
        $product_info_blocks = $this->getHtml( '.product-info .right .description' );
        $product_info_blocks = explode( 'br>', $product_info_blocks );

        foreach ( $product_info_blocks as $info_block ) {

            // MPN, Available
            if ( preg_match( '%<span>\s*Product Code:\s*</span>\s*(.*?)\s*<%uis', $info_block, $match ) ) {
                $this->mpn = trim( $match[ 1 ] );
            }
            elseif ( preg_match( '%<span>\s*Availability:\s*</span>\s*(.*?)\s*<%uis', $info_block, $match ) ) {
                $match[ 1 ] = trim( $match[ 1 ] );
                $this->avail = $match[ 1 ] === 'In Stock' ? self::DEFAULT_AVAIL_NUMBER : 0;
            }
        }
    }

    public function getMpn(): string
    {
        return $this->mpn;
    }

    public function getProduct(): string
    {
        return $this->getText( 'h1' ) ?? '';
    }

    public function getCostToUs(): float
    {
        return $this->getMoney( 'div.price' );
    }

    public function getImages(): array
    {
        $this->filter( 'div.left div.image a' )->each( function ( ParserCrawler $c ) {
            $this->images[] = $c->getAttr( 'a', 'href' );
        } );

        $this->filter( 'div.left div.image-additional a' )->each( function ( ParserCrawler $c ) {
            $this->images[] = $c->getAttr( 'a', 'href' );
        } );

        foreach ( $this->images as $key => $value ) {
            $filename = pathinfo( $value );
            $filename = $filename[ 'basename' ];
            if ( str_contains( $filename, '?' ) ) {
                $new_filename = substr( $filename, 0, strpos( $filename, '?' ) );
                $this->images[ $key ] = str_replace( $filename, $new_filename, $value );
            }
            if ( !str_contains( $this->images[ $key ], 'http' ) ) {
                $this->images[ $key ] = 'https://motorheadproducts.com' . $this->images[ $key ];
            }
        }

        return array_values( array_unique( $this->images ) );
    }

    public function getAvail(): ?int
    {
        return $this->avail;
    }

    public function getCategories(): array
    {
        $categories = $this->getContent( 'div.breadcrumb a' );
        array_shift( $categories );
        array_pop( $categories );

        return $categories;
    }

    public function getDescription(): string
    {
        return trim( $this->getHtml( 'div.panel-content' ) );
    }

    public function getVideos(): array
    {
        $this->filter( 'div.panel-content iframe' )->each( function ( ParserCrawler $c ) {
            $src = $c->getAttr( 'iframe', 'src' );
            if ( !empty( $src ) ) {
                $url_parts = parse_url( $src );
                $domain = $url_parts[ 'host' ];
                if ( str_contains( $domain, '.' ) ) {
                    $domain = explode( '.', $domain );
                    $domain = $domain[ count( $domain ) - 2 ];
                }
                $this->videos[] = [
                    'name' => $this->getProduct(),
                    'video' => $src,
                    'provider' => $domain ];
            }
        } );

        return $this->videos;
    }
}
