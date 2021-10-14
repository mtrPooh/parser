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
        if ( str_contains( $data->getData(), '>Waitlist Signup<' ) ) {
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

            return StringHelper::calculateUPC( $match[ 1 ] );
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
            $images[ $key ] = !str_contains( $value, 'http' ) ? 'https://www.bbtoystore.com/' . ltrim( $value, '/' ) : $value;
        }

        return array_values( array_unique( $images ) );
    }

    public function getAvail(): ?int
    {
        $stock_status = $this->getAttr( 'meta[itemprop="availability"]', 'content' );

        return $stock_status === 'In Stock' ? self::DEFAULT_AVAIL_NUMBER : 0;
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
                if ( str_contains( $row, '$' ) ) {
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

        return FeedHelper::cleanProductDescription( $description ) ?: $this->getProduct();
    }
}
