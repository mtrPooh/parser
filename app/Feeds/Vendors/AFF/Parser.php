<?php

namespace App\Feeds\Vendors\AFF;

use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\FeedHelper;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private array $dims = [];
    private array $shorts = [];
    private ?array $attrs = null;
    private ?float $weight = null;
    private string $desc = '';

    private function parseVideo( string $src ): array
    {
        $url_parts = parse_url( $src );
        $domain = $url_parts[ 'host' ];
        if ( str_contains( $domain, '.' ) ) {
            $domain = explode( '.', $domain );
            $domain = $domain[ count( $domain ) - 2 ];
        }
        return [ 'name' => $this->getProduct(),
            'video' => str_contains( $src, 'http' ) ? $src : 'https:' . $src,
            'provider' => $domain ];
    }

    public function parseContent( $data, $params = [] ): array
    {
        if ( str_contains( $data->getData(), 'productView-info-value--condition">Refurbished' ) ) {
            return [];
        }

        if ( preg_match( '%<h1(.*?)</h1>%ui', $data->getData(), $match ) ) {
            $h1 = strtolower( $match[ 1 ] );
            if ( str_contains( $h1, 'warranty' ) || str_contains( $h1, 'cloud recording' ) ) {
                return [];
            }
        }

        return parent::parseContent( $data, $params );
    }

    public function beforeParse(): void
    {
        $this->desc = $this->getHtml( 'div[itemprop="description"]' );

        $this->filter( 'div[itemprop="description"] p' )->each( function ( ParserCrawler $c ) {
            $p = trim( $c->getText( 'p' ), '  ' );
            if ( $p === 'Product Features:' || $p === 'Overview:' || $p === 'Includes:' || $p === 'Package Includes'
                || $p === 'Package Includes:' || $p === 'Features' || str_contains( $p, '$' ) ) {

                $this->desc = str_replace( $c->outerHtml(), '', $this->desc );
            }
        } );

        $this->filter( 'div[itemprop="description"] span' )->each( function ( ParserCrawler $c ) {
            $p = trim( $c->getText( 'span' ), '  ' );
            if ( str_contains( $p, '$' ) ) {
                $this->desc = str_replace( $c->outerHtml(), '', $this->desc );
            }
        } );

        $this->filter( 'div[itemprop="description"] div' )->each( function ( ParserCrawler $c ) {
            $p = trim( $c->getText( 'div' ), '  ' );
            if ( str_contains( $p, '$' ) ) {
                $this->desc = str_replace( $c->outerHtml(), '', $this->desc );
            }
        } );

        if ( str_contains( $this->desc, '$' ) ) {
            $this->desc = '';
        }

        if ( str_contains( $this->desc, '<!-- snippet location product_description' ) ) {
            $this->desc = substr( $this->desc, 0, strpos( $this->desc, '<!-- snippet location product_description' ) );
        }

        if ( str_contains( $this->desc, '<div class="tab-content" id="tab-warranty"' ) ) {
            $this->desc = substr( $this->desc, 0, strpos( $this->desc, '<div class="tab-content" id="tab-warranty"' ) );
        }

        if ( str_contains( $this->desc, '<div class="tab-content" id="tab-addition"' ) ) {
            $this->desc = substr( $this->desc, 0, strpos( $this->desc, '<div class="tab-content" id="tab-addition"' ) );
        }

        if ( str_contains( $this->desc, '<div class="productView-productTabs"' ) ) {
            $this->desc = substr( $this->desc, 0, strpos( $this->desc, '<div class="productView-productTabs"' ) );
        }

        if ( preg_match_all( '%<ul(.*?)</ul>%uis', $this->desc, $matches, PREG_SET_ORDER ) ) {

            foreach ( $matches as $ul ) {

                preg_match_all( '%<li.*?>(.*?)</li%ui', $ul[ 1 ], $lis );

                foreach ( $lis[ 1 ] as $li ) {
                    $li = strip_tags( $li );
                    if ( str_contains( $li, 'SKU' ) || str_contains( $li, 'UPC' ) ) {
                        continue;
                    }
                    if ( preg_match( '%\(\s*([\d.]+)[”x\s]+([\d.]+)[”x\s]+([\d.]+)%ui', $li, $match ) ) {
                        $this->dims[ 'x' ] = StringHelper::getFloat( $match[ 1 ] );
                        $this->dims[ 'y' ] = StringHelper::getFloat( $match[ 2 ] );
                        $this->dims[ 'z' ] = StringHelper::getFloat( $match[ 3 ] );
                    }
                    elseif ( preg_match( '%([\d.]+)[”HWDLx\s]+([\d.]+)[”HWDLx\s]+([\d.]+)%ui', $li, $match ) ) {
                        $this->dims[ 'x' ] = StringHelper::getFloat( $match[ 1 ] );
                        $this->dims[ 'y' ] = StringHelper::getFloat( $match[ 2 ] );
                        $this->dims[ 'z' ] = StringHelper::getFloat( $match[ 3 ] );
                    }
                    elseif ( preg_match( '%Weight.*?([\d.]+)\s*oz%ui', $li, $match ) ) {
                        $this->weight = FeedHelper::convertLbsFromOz( $match[ 1 ] );
                    }
                    elseif ( str_contains( $li, ':' ) ) {
                        $li = explode( ':', $li );
                        $key = trim( $li[ 0 ] );
                        $value = trim( $li[ 1 ] );
                        if ( !empty( $key ) && !empty( $value ) ) {
                            $this->attrs[ $key ] = $value;
                        }
                    }
                    else {
                        $this->shorts[] = trim( $li );
                    }

                    $this->desc = str_replace( $ul[ 0 ], '##del##', $this->desc );
                }
            }

            $this->desc = preg_replace( '%[<h\w>]+(Spec|Featur|Dimension|Addition).*?</h\d+>\s*##del##%', '', $this->desc );
            $this->desc = str_replace( '##del##', '', $this->desc );
        }

        preg_match_all( '%(<h\d+.*?</h\d+>)%ui', $this->desc, $matches );

        foreach ( $matches[ 1 ] as $match ) {
            if ( str_contains( $match, $this->getMpn() ) ) {
                $this->desc = str_replace( $match, '', $this->desc );
            }
        }

        $this->desc = preg_replace( '%<h\d+>' . $this->getProduct() . '</h\d+>%ui', '', $this->desc );
        $this->desc = trim( str_replace( '.<', '. <', $this->desc ) );
    }

    public function getMpn(): string
    {
        return $this->getText( 'dd.productView-info-value--sku' );
    }

    public function getUpc(): ?string
    {
        return StringHelper::calculateUPC( $this->getText( 'dd.productView-info-value--upc' ) );
    }

    public function getProduct(): string
    {
        return $this->getText( 'h1.productView-title' ) ?? '';
    }

    public function getCostToUs(): float
    {
        return $this->getMoney( 'span.price--main' );
    }

    public function getListPrice(): ?float
    {
        return $this->getMoney( 'span.price--rrp' );
    }

    public function getImages(): array
    {
        $images = [];
        $this->filter( 'ul.productView-imageCarousel-main li a' )->each( function ( ParserCrawler $c ) use ( &$images ) {
            $image = $c->getAttr( 'a', 'href' );
            $filename = pathinfo( $image );
            $filename = $filename[ 'basename' ];
            if ( str_contains( $filename, '?' ) ) {
                $new_filename = substr( $filename, 0, strpos( $filename, '?' ) );
                $image = str_replace( $filename, $new_filename, $image );
            }
            $images[] = $image;
        } );

        if ( count( $images ) === 1 ) {
            $images[ 0 ] = strtolower( $images[ 0 ] );
            $brand = explode( ' ', $this->getBrand() );
            $brand = strtolower( $brand[ 0 ] );

            if ( str_contains( $images[ 0 ], $brand ) ) {
                return [];
            }
        }

        return array_values( array_unique( $images ) );
    }

    public function getAvail(): ?int
    {
        $avail = $this->getAttr( 'meta[itemprop="availability"]', 'content' );

        return $avail === 'http://schema.org/InStock' ? self::DEFAULT_AVAIL_NUMBER : 0;
    }

    public function getBrand(): ?string
    {
        return $this->getText( '*[itemprop="brand"]' ) ?: null;
    }

    public function getDescription(): string
    {
        return FeedHelper::cleanProductDescription( $this->desc ) ?: $this->getProduct();
    }

    public function getShortDescription(): array
    {
        return $this->shorts;
    }

    public function getProductFiles(): array
    {
        $files = [];
        $this->filter( 'table.productView-addition-table tr' )->each( function ( ParserCrawler $c ) use ( &$files ) {
            $name = trim( $c->filter( 'td' )->getNode( 0 )->textContent, ' : ' );
            $file = $c->getAttr( 'td a', 'href' );
            $filename = pathinfo( $file );
            $filename = $filename[ 'basename' ];
            if ( !str_contains( $file, '.pdf' ) ) {
                return;
            }
            if ( str_contains( $filename, '?' ) ) {
                $new_filename = substr( $filename, 0, strpos( $filename, '?' ) );
                $file = str_replace( $filename, $new_filename, $file );
            }
            if ( !str_contains( $file, 'http' ) ) {
                $file = 'https://www.affinitechstore.com/' . ltrim( $file, '/' );
            }
            $files[] = [ 'name' => $name, 'link' => trim( $file ) ];
        } );

        return $files;
    }

    public function getVideos(): array
    {
        $videos = [];
        $this->filter( 'div[itemprop="description"] iframe' )->each( function ( ParserCrawler $c ) use ( &$videos ) {
            $src = $c->getAttr( 'iframe', 'src' ) ?: $c->getAttr( 'iframe', 'data-src' );
            if ( !empty( $src ) ) {
                $videos[] = $this->parseVideo( $src );
            }
        } );
        $this->filter( 'div#videoGallery-content iframe' )->each( function ( ParserCrawler $c ) use ( &$videos ) {
            $src = $c->getAttr( 'iframe', 'src' ) ?: $c->getAttr( 'iframe', 'data-src' );
            if ( !empty( $src ) ) {
                $videos[] = $this->parseVideo( $src );
            }
        } );

        return $videos;
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
}
