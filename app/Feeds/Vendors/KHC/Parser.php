<?php

namespace App\Feeds\Vendors\KHC;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private array $json = [];
    private array $dims = [];
    private array $s_dims = [];
    private array $shorts = [];
    private array $images = [];
    private array $videos = [];
    private array $ch_prods = [];
    private ?array $attrs = null;
    private ?float $weight = null;

    public function beforeParse(): void
    {
        $json = trim( $this->getText( 'script[type="application/ld+json"]' ) );
        $json = preg_replace( '%\\\\*"\s+]%', '\\" ', $json );
        $json = str_replace( '"":', '\\"":', $json );

        try {
            $json = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
        } catch ( \JsonException $e ) {
        }

        if ( is_array( $json ) ) {
            foreach ( $json as $key => $value ) {
                if ( !empty( $value[ '@type' ] ) && $value[ '@type' ] === 'Product' ) {
                    $this->json = $value;
                    break;
                }
            }
        }

        $body = $this->getHtml( 'body' );
        if ( str_contains( $body, 'This product may only be purchased over the phone' )
            || str_contains( $body, 'NO LONGER AVAILABLE' ) ) {

            $this->json[ 'offers' ][ 'price' ] = 0;
        }

        // Dimensions, Weight, Attributes
        $this->filter( 'div#tab-description table' )->each( function ( ParserCrawler $c ) {
            $table = $c->getHtml( 'table' );

            if ( str_contains( $table, 'Product Measurements' ) ) {

                $c->filter( 'tr' )->each( function ( ParserCrawler $c ) {
                    if ( !$c->exists( 'td' ) ) {
                        return;
                    }

                    $name = trim( @$c->filter( 'td' )->getNode( 0 )->nodeValue, ' : ' );
                    $value = trim( @$c->filter( 'td' )->getNode( 1 )->nodeValue, '  ' );

                    if ( empty( $value ) ) {
                        return;
                    }

                    $name = str_replace( ' ', ' ', $name );
                    $value = str_replace( ' ', ' ', $value );

                    if ( $name === 'Overall Height' ) {
                        $this->dims[ 'y' ] = StringHelper::getFloat( $value );
                    }
                    elseif ( $name === 'Overall Width' ) {
                        $this->dims[ 'x' ] = StringHelper::getFloat( $value );
                    }
                    elseif ( $name === 'Overall Length' ) {
                        $this->dims[ 'z' ] = StringHelper::getFloat( $value );
                    }
                    elseif ( $name === 'Overall Weight' || $name === 'Product Weight' ) {
                        $this->weight = StringHelper::getFloat( $value );
                    }
                    elseif ( $name === 'Shipping Dimensions' ) {
                        if ( preg_match( '%([\d\\\.\s]+)[”"″\s]+L\s*x\s*([\d\\\.\s]+)[”"″\s]+H\s*x\s*([\d\\\.\s]+)[”"″\s]+W%ui',
                            $value,
                            $match ) ) {
                            $this->s_dims[ 'z' ] = StringHelper::getFloat( $match[ 1 ] );
                            $this->s_dims[ 'y' ] = StringHelper::getFloat( $match[ 2 ] );
                            $this->s_dims[ 'x' ] = StringHelper::getFloat( $match[ 3 ] );
                        }
                    }
                    else {
                        $this->attrs[ $name ] = $value;
                    }
                } );
            }

            // Short Description 1
            if ( str_contains( $table, 'Product Features' ) ) {

                $this->filter( 'div#tab-description table' )->each( function ( ParserCrawler $c ) {

                    $c->filter( 'li' )->each( function ( ParserCrawler $c ) {
                        $this->shorts[] = trim( $c->getText( 'li' ) );
                    } );
                } );
            }
        } );

        // Short Description 2
        $this->filter( 'div.product-short-description li' )->each( function ( ParserCrawler $c ) {
            $this->shorts[] = trim( $c->getText( 'li' ), '  ' );
        } );
        $this->filter( 'div#tab-description li' )->each( function ( ParserCrawler $c ) {
            $this->shorts[] = trim( $c->getText( 'li' ), '  ' );
        } );

        // Dimension, Weight from another table
        $this->filter( '#tab-additional_information table.woocommerce-product-attributes tr' )
            ->each( function ( ParserCrawler $c ) {

                $name = trim( @$c->filter( 'th' )->getNode( 0 )->nodeValue, ' : ' );
                $value = trim( @$c->filter( 'td' )->getNode( 0 )->nodeValue, '  ' );

                if ( empty( $value ) ) {
                    return;
                }

                $name = str_replace( ' ', ' ', $name );
                $value = str_replace( ' ', ' ', $value );

                if ( $name === 'Dimensions'
                    && preg_match( '%([\d\\\.]+)\s*×\s*([\d\\\.]+)\s*×\s*([\d\\\.]+)\s*%ui', $value, $match ) ) {

                    $this->dims[ 'y' ] = StringHelper::getFloat( $match[ 1 ] );
                    $this->dims[ 'x' ] = StringHelper::getFloat( $match[ 2 ] );
                    $this->dims[ 'z' ] = StringHelper::getFloat( $match[ 3 ] );
                }
                elseif ( $name === 'Weight' ) {
                    $this->weight = StringHelper::getFloat( $value );
                }
            } );

        // Clean attributes
        if ( !empty( $this->attrs ) ) {
            $i = 0;
            foreach ( $this->attrs as $key => $value ) {
                if ( $key === 'HCPCS Code' && $i > 0 ) {
                    $this->attrs = array_slice( $this->attrs, $i );
                    break;
                }
                $i++;
            }
        }
    }

    public function isGroup(): bool
    {
        return !empty( $this->ch_prods );
    }

    public function getMpn(): string
    {
        $mpn = '';
        $this->filter( '#tab-description p' )->each( function ( ParserCrawler $c ) use ( &$mpn ) {
            $text = str_replace( ' ', '', $c->getText( 'p' ) );
            if ( preg_match( '%Model:\s*(.*?)$%ui', $text, $match ) ) {
                $mpn = trim( $match[ 1 ] );
            }
        } );

        if ( !$mpn && !empty( $this->json[ 'sku' ] ) ) {
            return $this->json[ 'sku' ];
        }

        return trim( $this->getText( 'span.sku' ) );
    }

    public function getProduct(): string
    {
        return $this->getText( 'h1' );
    }

    public function getCostToUs(): float
    {
        if ( isset( $this->json[ 'offers' ][ 'price' ] ) ) {
            $price = $this->json[ 'offers' ][ 'price' ] > 0 ? StringHelper::getFloat( $this->json[ 'offers' ][ 'price' ] ) : 0;
        }
        else {
            $price = StringHelper::getFloat( $this->getText( 'div.product-info p.price ins span.amount' ) ) ?: 0;

        }

        return $price;
    }

    public function getListPrice(): ?float
    {
        return StringHelper::getFloat( $this->getText( 'p.price del' ) ) ?? null;
    }

    public function getBrand(): ?string
    {
        return !empty( $this->json[ 'brand' ][ 'name' ] ) ? trim( $this->json[ 'brand' ][ 'name' ] ) : null;
    }

    public function getImages(): array
    {
        $this->parseImages( 'div.product-thumbnails img', 'data-lazy-src' );
        if ( empty( $this->images ) ) {
            $this->parseImages( 'div.woocommerce-product-gallery__image img', 'data-large_image' );
        }
        foreach ( $this->images as $key => $image ) {
            $filename = pathinfo( $image );
            $filename = $filename[ 'basename' ];
            if ( str_contains( $filename, '?' ) ) {
                $new_filename = substr( $filename, 0, strpos( $filename, '?' ) );
                $this->images[ $key ] = str_replace( $filename, $new_filename, $this->images[ $key ] );
            }
        }

        return array_values( array_unique( $this->images ) );
    }

    private function parseImages( string $img_selector, string $attr_selector ): void
    {
        $this->filter( $img_selector )->each( function ( ParserCrawler $c ) use ( $attr_selector ) {
            $image = $c->getAttr( 'img', $attr_selector );
            if ( !$image ) {
                $image = $c->getAttr( 'img', 'src' );
            }
            $this->images[] = $image;
        } );
    }

    public function getAvail(): ?int
    {
        if ( !empty( $this->json[ 'offers' ][ 'availability' ] ) ) {
            return str_contains( $this->json[ 'offers' ][ 'availability' ], 'InStock' ) ? self::DEFAULT_AVAIL_NUMBER : 0;
        }

        return $this->getAttr( 'meta[property="og:availability"]', 'content' ) === 'instock' ?
            self::DEFAULT_AVAIL_NUMBER : 0;
    }

    public function getCategories(): array
    {
        $categories = $this->getContent( 'nav#breadcrumbs a' );
        array_shift( $categories );
        if ( !empty( $categories[ 0 ] ) && $categories[ 0 ] === 'Shop' ) {
            array_shift( $categories );
        }

        return $categories;
    }

    public function getDescription(): string
    {
        $descr = $this->getHtml( 'div.product-short-description' );
        $descr_sec = "\r\n" . $this->getHtml( 'div#tab-description div.col-inner' );
        if ( preg_match( '%(.*?)<table%uis', $descr_sec, $match ) ) {
            $descr_sec = trim( $match[ 1 ] );
        }
        $descr .= "\r\n" . $descr_sec;
        $descr = preg_replace( [ '%<ul.*?</ul>%uis', '%<h\d+.*?</h\d+>%uis', '%<p>\s*</p>%uis' ], '', $descr );

        return trim( $descr );
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

    public function getShippingDimZ(): ?float
    {
        return $this->s_dims[ 'z' ] ?? null;
    }

    public function getShippingDimX(): ?float
    {
        return $this->s_dims[ 'x' ] ?? null;
    }

    public function getShippingDimY(): ?float
    {
        return $this->s_dims[ 'y' ] ?? null;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function getAttributes(): ?array
    {
        return $this->attrs ?? null;
    }

    // Options and Child products
    public function getOptions(): array
    {
        $options = [];
        $option_lists = $this->filter( 'div.wc-pao-addon-container' );

        if ( !$option_lists->count() ) {
            return $options;
        }

        $option_lists->each( function ( ParserCrawler $list ) use ( &$options ) {
            $label = $list->filter( 'h2' );
            if ( $label->count() === 0 ) {
                return;
            }
            $name = trim( $label->text(), ' : ' );

            $data = [];
            $list->filter( 'option' )->each( function ( ParserCrawler $option ) use ( &$options, &$data, $name ) {
                $attr = $option->getAttr( 'option', 'value' );
                $text = trim( $option->text(), '  ' );

                if ( !$attr ) {
                    return;
                }

                // Options
                if ( !str_contains( $name, '*' ) && !str_contains( $text, '$' ) ) {
                    $options[ $name ][] = $text;
                }

                // Child Products
                if ( preg_match( '%\(.*?\$\s*([\d\\\.,]+)%ui', $text, $match ) ) {
                    $price = StringHelper::getFloat( $match[ 1 ] );
                }
                else {
                    $price = 0;
                }
                $d[ 'sku' ] = $attr;
                $d[ 'name' ] = trim( preg_replace( '%\(.*?\)%ui', '', $text ), '  ' );
                $d[ 'price' ] = $price;
                $data[] = $d;
            } );

            if ( !empty( $data ) ) {
                $this->ch_prods[] = [ 'label' => trim( str_replace( '*', '', $name ) ), 'options' => $data ];
            }
        } );
        
        return $options;
    }

    public function getProductFiles(): array
    {
        $files = [];
        $this->filter( 'ul.documentationlist a' )->each( function ( ParserCrawler $c ) use ( &$files ) {
            $file = $c->getAttr( 'a', 'href' );
            $name = $c->getText( 'a' );
            $filename = pathinfo( $file );
            $filename = $filename[ 'basename' ];
            if ( !str_contains( $filename, '.pdf' ) ) {
                return;
            }
            if ( str_contains( $filename, '?' ) ) {
                $new_filename = substr( $filename, 0, strpos( $filename, '?' ) );
                $file = str_replace( $filename, $new_filename, $file );
            }
            $files[] = [ 'name' => $name, 'link' => $file ];
        } );

        return $files;
    }

    public function getVideos(): array
    {
        $this->parseVideos( 'a.product-video-popup' );
        $this->parseVideos( 'a[rel="wp-video-lightbox"]' );

        return $this->videos;
    }

    private function parseVideos( string $selector ): void
    {
        $this->filter( $selector )->each( function ( ParserCrawler $c ) {
            $src = $c->getAttr( 'a', 'href' );
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
    }

    public function getChildProducts( FeedItem $parent_fi ): array
    {
        $child = [];

        foreach ( $this->ch_prods[ 0 ][ 'options' ] as $option0 ) {
            $ch_product0 = $this->ch_prods[ 0 ][ 'label' ] . ': ' . $option0[ 'name' ];
            $ch_sku0 = $this->getMpn() . '-' . $option0[ 'sku' ];
            $ch_price0 = $this->getCostToUs() + $option0[ 'price' ];

            if ( !empty( $this->ch_prods[ 1 ] ) ) {
                foreach ( $this->ch_prods[ 1 ][ 'options' ] as $option1 ) {
                    $ch_product1 = $ch_product0 . ', ' . $this->ch_prods[ 1 ][ 'label' ] . ': ' . $option1[ 'name' ];
                    $ch_sku1 = $ch_sku0 . '-' . $option1[ 'sku' ];
                    $ch_price1 = $ch_price0 + $option1[ 'price' ];

                    if ( count( $this->ch_prods ) === 2 ) {
                        $fi = clone $parent_fi;
                        $fi->setMpn( $ch_sku1 );
                        $fi->setProduct( $ch_product1 );
                        $fi->setCostToUs( $ch_price1 );
                        $fi->setRAvail( $this->getAvail() );
                        $child[] = $fi;
                        continue;
                    }

                    if ( !empty( $this->ch_prods[ 2 ] ) ) {
                        foreach ( $this->ch_prods[ 2 ][ 'options' ] as $option2 ) {
                            $ch_product2 = $ch_product1 . ', ' . $this->ch_prods[ 2 ][ 'label' ] . ': ' . $option2[ 'name' ];
                            $ch_sku2 = $ch_sku1 . '-' . $option2[ 'sku' ];
                            $ch_price2 = $ch_price1 + $option2[ 'price' ];

                            if ( count( $this->ch_prods ) === 3 ) {
                                $fi = clone $parent_fi;
                                $fi->setMpn( $ch_sku2 );
                                $fi->setProduct( $ch_product2 );
                                $fi->setCostToUs( $ch_price2 );
                                $fi->setRAvail( $this->getAvail() );
                                $child[] = $fi;
                                continue;
                            }

                            if ( !empty( $this->ch_prods[ 3 ] ) ) {
                                foreach ( $this->ch_prods[ 3 ][ 'options' ] as $option3 ) {
                                    $ch_product3 = $ch_product2 . ', ' . $this->ch_prods[ 3 ][ 'label' ] . ': ' .
                                        $option3[ 'name' ];
                                    $ch_sku3 = $ch_sku2 . '-' . $option3[ 'sku' ];
                                    $ch_price3 = $ch_price2 + $option3[ 'price' ];

                                    if ( count( $this->ch_prods ) === 4 ) {
                                        $fi = clone $parent_fi;
                                        $fi->setMpn( $ch_sku3 );
                                        $fi->setProduct( $ch_product3 );
                                        $fi->setCostToUs( $ch_price3 );
                                        $fi->setRAvail( $this->getAvail() );
                                        $child[] = $fi;
                                        continue;
                                    }

                                    if ( !empty( $this->ch_prods[ 4 ] ) ) {
                                        foreach ( $this->ch_prods[ 4 ][ 'options' ] as $option4 ) {
                                            $ch_product4 = $ch_product3 . ', ' . $this->ch_prods[ 4 ][ 'label' ] . ': ' .
                                                $option4[ 'name' ];
                                            $ch_sku4 = $ch_sku3 . '-' . $option4[ 'sku' ];
                                            $ch_price4 = $ch_price3 + $option4[ 'price' ];

                                            if ( count( $this->ch_prods ) === 5 ) {
                                                $fi = clone $parent_fi;
                                                $fi->setMpn( $ch_sku4 );
                                                $fi->setProduct( $ch_product4 );
                                                $fi->setCostToUs( $ch_price4 );
                                                $fi->setRAvail( $this->getAvail() );
                                                $child[] = $fi;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            else {
                $fi = clone $parent_fi;
                $fi->setMpn( $ch_sku0 );
                $fi->setProduct( $ch_product0 );
                $fi->setCostToUs( $ch_price0 );
                $fi->setRAvail( $this->getAvail() );
                $child[] = $fi;
            }
        }

        return $child;
    }
}
