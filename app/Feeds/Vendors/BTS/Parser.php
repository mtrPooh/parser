<?php

namespace App\Feeds\Vendors\BTS;

use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\FeedHelper;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    public function parseContent( $data, $params = [] ): array
    {
        if ( str_contains( $data->getData(), '>Waitlist Signup<' ) || str_contains( $data->getData(), 'value="Pre-Order"' ) ) {
            return [];
        }

        if ( preg_match( '%<h1(.*?)</h1>%ui', $data->getData(), $match ) ) {
            if ( str_contains( $match[ 1 ], '$' ) ) {
                return [];
            }
        }

        return parent::parseContent( $data, $params );
    }

    public function getMpn(): string
    {
        return trim( $this->getText( 'div.column.whole small' ) );
    }

    public function getUpc(): ?string
    {
        if ( preg_match( '%<strong>UPC:</strong>(.*?)<%ui', $this->getHtml( 'div.product-information--purchase' ),
            $match ) ) {

            return StringHelper::calculateUPC( trim( $match[ 1 ] ) );
        }

        return null;
    }

    public function getProduct(): string
    {
        return $this->getText( 'h1' );
    }

    public function getCostToUs(): float
    {
        return StringHelper::getFloat( $this->getAttr( 'div#js-price-value', 'data-base-price' ) ) ?? 0;
    }

    public function getImages(): array
    {
        $images = [];
        $this->filter( 'div.product-information--description img' )->each( function ( ParserCrawler $c ) use ( &$images
        ) {
            $images[] = $c->getAttr( 'img', 'src' );
        } );

        if ( empty( $images ) ) {
            $image = $this->getAttr( 'img#js-main-image', 'data-image' );
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
            $images[ $key ] = !str_contains( $value, 'http' ) ? 'https://www.bbtoystore.com/mm5/' . ltrim( $value, '/' )
                : $value;
        }

        return array_values( array_unique( $images ) );
    }

    public function getAvail(): ?int
    {
        return StringHelper::getFloat( $this->getText( 'div#js-inventory-message font' ) ) ?? 0;
    }

    public function getBrand(): ?string
    {
        if ( preg_match( '%<strong>Brand:</strong>(.*?)<%ui', $this->getHtml( 'div.product-information--purchase' ),
            $match ) ) {

            return trim( $match[ 1 ] );
        }

        return null;
    }

    public function getDescription(): string
    {
        $description = $this->getHtml( 'div.product-information--description' );

        if ( str_contains( $description, '<br>' ) ) {
            $rows = explode( '<br>', $description );
            foreach ( $rows as $row ) {
                if ( str_contains( $row, '$' ) || str_contains( $row, 'Item number' ) ) {
                    $description = str_replace( $row, '', $description );
                }
            }
        }
        else {
            $rows = explode( "\n", $description );
            foreach ( $rows as $row ) {
                if ( str_contains( $row, '$' ) ) {
                    $description = str_replace( $row, '', $description );
                }
            }
        }

        preg_match_all( '%<h.*?</h\d+>%', $description, $matches );
        foreach ( $matches[ 0 ] as $match ) {
            if ( str_contains( $match, 'Product Details' ) ) {
                $description = str_replace( $match, '', $description );
            }
        }

        $search = [
            '%<p>\s*Product Details\s*</p>%ui',
            '%<b>\s*Product Details\s*</b>%ui',
            '%<hr.*?>%ui'
        ];
        $description = trim( preg_replace( $search, '', $description ) );

        return FeedHelper::cleanProductDescription( $description ) ?: $this->getProduct();
    }

    public function getVideos(): array
    {
        $videos = [];
        $this->filter( 'div.prod-video-holder iframe' )->each( function ( ParserCrawler $c ) use ( &$videos ) {
            $src = $c->getAttr( 'iframe', 'src' );
            if ( !empty( $src ) ) {
                if ( str_contains( $src, 'http' ) === false ) {
                    $src = 'https://' . ltrim( $src, '/' );
                }
                $url_parts = parse_url( $src );
                $domain = $url_parts[ 'host' ];
                if ( str_contains( $domain, '.' ) ) {
                    $domain = explode( '.', $domain );
                    $domain = $domain[ count( $domain ) - 2 ];
                }
                $videos[] = [ 'name' => $this->getProduct(),
                    'video' => $src,
                    'provider' => $domain ];
            }
        } );

        return $videos;
    }
}
