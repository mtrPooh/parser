<?php

namespace App\Feeds\Vendors\TBB;

use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private ?string $brand = null;
    private ?float $weight = null;
    private ?array $attrs = null;
    private string $mpn = '';
    private array $images = [];
    private array $files = [];
    private array $dims = [];
    private array $shorts = [];
    private array $videos = [];


    public function beforeParse(): void
    {
        if ( preg_match( '%<strong>\s*SKU:\s*</strong>\s*(.*?)\s*<%uis', $this->getHtml( 'div.product-detail' ), $match ) ) {
            $this->mpn = trim( $match[ 1 ] );
        }

        $this->filter( 'div#Specifications tr' )->each( function ( ParserCrawler $c ) {
            $name = trim( $c->filter( 'td' )->getNode( 0 )->nodeValue );
            $value = trim( $c->filter( 'td' )->getNode( 1 )->nodeValue );

            if ( $name === 'Brand' ) {
                $this->brand = $value;
            }
            elseif ( $name === 'Weight' ) {
                $this->weight = StringHelper::getFloat( $value );
            }
            elseif ( $name === 'Length' ) {
                $this->dims[ 'z' ] = StringHelper::getFloat( $value );
            }
            elseif ( $name === 'Height' ) {
                $this->dims[ 'y' ] = StringHelper::getFloat( $value );
            }
            elseif ( $name === 'Width' ) {
                $this->dims[ 'x' ] = StringHelper::getFloat( $value );
            }
            else {
                $this->attrs[ $name ] = $value;
            }
        } );

        // Short Description
        $this->filter( '#detail div.description li' )->each( function ( ParserCrawler $c ) {
            $this->shorts[] = trim( $c->text(), '  ' );
        } );
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
        return $this->getMoney( 'div.product-price .price' );
    }

    public function getImages(): array
    {
        $this->filter( 'div.thumb-container div.image-thumb' )->each( function ( ParserCrawler $c ) {
            $image = $c->getAttr( 'div.image-thumb', 'data-image' );
            $filename = pathinfo( $image );
            $filename = $filename[ 'basename' ];
            if ( str_contains( $filename, '?' ) ) {
                $new_filename = substr( $filename, 0, strpos( $filename, '?' ) );
                $image = str_replace( $filename, $new_filename, $image );
            }
            if ( !empty( $image ) ) {
                $this->images[] = $image;
            }
        } );

        return array_values( array_unique( $this->images ) );
    }

    public function getAvail(): ?int
    {
        $json = trim( $this->getText( 'script[type="application/ld+json"]' ) );

        if ( empty( $json ) ) {
            return null;
        }

        $json = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );

        return str_contains( $json[ 'offers' ][ 'availability' ], 'InStock' ) ? self::DEFAULT_AVAIL_NUMBER : 0;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function getCategories(): array
    {
        $categories = $this->getContent( 'ul.breadcrumbs a' );
        array_shift( $categories );

        return $categories;
    }

    public function getDescription(): string
    {
        $this->descr = $this->getHtml( '#detail div.description' );
        $this->filter( 'ul' )->each( function ( ParserCrawler $c ) {
            $this->descr = str_ireplace( $c->outerHtml(), '', $this->descr );
        } );
        $this->descr = str_ireplace( '<h3>Description</h3>', '', $this->descr );

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

    public function getVideos(): array
    {
        $this->filter( '#detail iframe' )->each( function ( ParserCrawler $c ) {
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

    public function getProductFiles(): array
    {
        $this->filter( 'div.documents li a' )->each( function ( ParserCrawler $c ) {
            $file = $c->getAttr( 'a', 'href' );
            $name = $c->getText( 'a' );
            $filename = pathinfo( $file );
            $filename = $filename[ 'basename' ];
            if ( str_contains( $filename, '?' ) ) {
                $new_filename = substr( $filename, 0, strpos( $filename, '?' ) );
                $file = str_replace( $filename, $new_filename, $file );
            }
            $this->files[] = [ 'name' => $name, 'link' => $file ];
        } );

        return $this->files;
    }
}
