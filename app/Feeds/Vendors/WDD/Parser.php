<?php

namespace App\Feeds\Vendors\WDD;

use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;
use Illuminate\Support\Facades\Storage;

class Parser extends HtmlParser
{
    private array $images = [];
    private ?string $descr = null;
    private array $dims = [];

    public function beforeParse(): void
    {
        // Description
        $this->descr = $this->getHtml( 'div.ProductDescriptionContainer' );
        $this->filter( 'ul' )->each( function ( ParserCrawler $c ) {
            $this->descr = str_ireplace( $c->outerHtml(), '', $this->descr );
        } );

        $currency_found = false;
        $this->filter( 'div.ProductDescriptionContainer p' )
            ->each( function ( ParserCrawler $c ) use ( &$currency_found ) {
                $p = $c->outerHtml();
                if ( str_contains( $p, '$' ) ) {
                    $this->descr = str_ireplace( $p, '', $this->descr );
                    $currency_found = true;
                }
            } );

        if ( $currency_found ) {
            $this->descr = preg_replace( '%<p><strong>\s*Item #[ \s]*Description[ \s]*Price\s*</strong>\s*</p>%ui', '', $this->descr );
        }
        $this->descr = preg_replace( '%<p>\s*</p>%uis', '', $this->descr );

        // Dimensions
        $temp = str_replace( '½', '1/2', $this->descr );
        if ( preg_match( '%([\d\.\-\s/]+)[”"]+H\s*x\s*([\d\.\-\s/]+)[”"]+W\s*x\s*([\d\.\-\s/]+)[”"]+D%ui', $temp,
            $match ) ) {

            $match[ 1 ] = str_replace( '-', ' ', $match[ 1 ] );
            $match[ 2 ] = str_replace( '-', ' ', $match[ 2 ] );
            $match[ 3 ] = str_replace( '-', ' ', $match[ 3 ] );

            if ( preg_match( '%([\d]+)\s+([\d]+)/([\d]+)%ui', $match[ 1 ], $frac ) ) {
                $match[ 1 ] = $frac[ 1 ] + $frac[ 2 ] / $frac[ 3 ];
            }
            if ( preg_match( '%([\d]+)\s+([\d]+)/([\d]+)%ui', $match[ 2 ], $frac ) ) {
                $match[ 2 ] = $frac[ 1 ] + $frac[ 2 ] / $frac[ 3 ];
            }
            if ( preg_match( '%([\d]+)\s+([\d]+)/([\d]+)%ui', $match[ 3 ], $frac ) ) {
                $match[ 3 ] = $frac[ 1 ] + $frac[ 2 ] / $frac[ 3 ];
            }
            $this->dims[ 'y' ] = StringHelper::getFloat( $match[ 1 ] );
            $this->dims[ 'x' ] = StringHelper::getFloat( $match[ 2 ] );
            $this->dims[ 'z' ] = StringHelper::getFloat( $match[ 3 ] );
        }
    }

    public function getMpn(): string
    {
        return $this->getText( 'span.VariationProductSKU' );
    }

    public function getProduct(): string
    {
        return $this->getText( 'h1' ) ?? '';
    }

    public function getCostToUs(): float
    {
        return $this->getMoney( 'span.ProductPrice' );
    }

    public function getImages(): array
    {
        $this->filter( 'div.ProductTinyImageList li a' )->each( function ( ParserCrawler $c ) {
            $json = $c->getAttr( 'a', 'rel' );
            $json = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
            $this->images[] = !empty( $json[ 'largeimage' ] ) ? $json[ 'largeimage' ] : $json[ 'smallimage' ];
        } );

        foreach ( $this->images as $key => $value ) {
            $filename = pathinfo( $value );
            $filename = $filename[ 'basename' ];
            if ( str_contains( $filename, '?' ) ) {
                $new_filename = substr( $filename, 0, strpos( $filename, '?' ) );
                $this->images[ $key ] = str_replace( $filename, $new_filename, $value );
            }
        }

        return array_values( array_unique( $this->images ) );
    }

    public function getAvail(): ?int
    {
        $stock_status = $this->getAttr( 'meta[property="og:availability"]', 'content' );
        return $stock_status === 'instock' ? self::DEFAULT_AVAIL_NUMBER : 0;
    }

    public function getCategories(): array
    {
        $categories = $this->getContent( '#ProductBreadcrumb li a' );
        array_shift( $categories );
        if ( !empty( $categories[ 0 ] ) && $categories[ 0 ] === 'Products' ) {
            array_shift( $categories );
        }

        return $categories;
    }

    public function getDescription(): string
    {
        return $this->descr;
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

    public function getWeight(): ?float
    {
        $weight = StringHelper::getFloat( $this->getText( 'span.VariationProductWeight' ) );

        return $weight ?? null;
    }

    public function getBrand(): ?string
    {
        $brand = trim( $this->getText( '.BrandName' ) );

        return $brand ?? null;
    }
}
