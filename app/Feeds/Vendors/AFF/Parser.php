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
    private string $descr = '';

    public function parseContent( $data, $params = [] ): array
    {
        if ( str_contains( $data->getData(), 'productView-info-value--condition">Refurbished' ) ) {
            return [];
        }

        return parent::parseContent( $data, $params );
    }

    public function beforeParse(): void
    {
        $this->descr = $this->getHtml( 'div[itemprop="description"]' );

        $this->filter( 'div[itemprop="description"] p' )->each( function ( ParserCrawler $c ) {
            $p = trim( $c->getText( 'p' ), '  ' );
            if ( $p === 'Product Features:' || $p === 'Overview:' || $p === 'Includes:' || $p === 'Package Includes'
                || $p === 'Package Includes:' || $p === 'Features' || str_contains( $p, '$' ) ) {

                $this->descr = str_replace( $c->outerHtml(), '', $this->descr );
            }
        } );

        $this->filter( 'div[itemprop="description"] span' )->each( function ( ParserCrawler $c ) {
            $p = trim( $c->getText( 'span' ), '  ' );
            if ( str_contains( $p, '$' ) ) {
                $this->descr = str_replace( $c->outerHtml(), '', $this->descr );
            }
        } );

        $this->filter( 'div[itemprop="description"] div' )->each( function ( ParserCrawler $c ) {
            $p = trim( $c->getText( 'div' ), '  ' );
            if ( str_contains( $p, '$' ) ) {
                $this->descr = str_replace( $c->outerHtml(), '', $this->descr );
            }
        } );

        if ( str_contains( $this->descr, '$' ) ) {
            $this->descr = '';
        }

        if ( str_contains( $this->descr, '<div class="productView-productTabs"' ) ) {
            $this->descr = substr( $this->descr, 0, strpos( $this->descr, '<div class="productView-productTabs"' ) );
        }

        if ( preg_match_all( '%<ul(.*?)</ul>%uis', $this->descr, $matches, PREG_SET_ORDER ) ) {

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

                    $this->descr = str_replace( $ul[ 0 ], '##del##', $this->descr );
                }
            }

            $this->descr = preg_replace( '%<h\d+<\w>]+(Spec|Featur|Dimension).*?</h\d+>\s*##del##%', '', $this->descr );
            $this->descr = str_replace( '##del##', '', $this->descr );
        }
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

        return array_values( array_unique( $images ) );
    }

    public function getAvail(): ?int
    {
        $avail = $this->getAttr( 'meta[itemprop="availability"]', 'content' );

        return $avail === 'http://schema.org/InStock' ? self::DEFAULT_AVAIL_NUMBER : 0;
    }

    public function getCategories(): array
    {
        $categories = $this->getContent( 'ul.breadcrumbs li a' );
        array_shift( $categories );

        return $categories;
    }

    public function getDescription(): string
    {
        return FeedHelper::cleanProductDescription( $this->descr ) ?: $this->getProduct();
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
            $files[] = [ 'name' => $name, 'link' => $file ];
        } );

        return $files;
    }

    public function getVideos(): array
    {
        $videos = [];
        $this->filter( 'div[itemprop="description"] iframe' )->each( function ( ParserCrawler $c ) use ( &$videos ) {
            $src = $c->getAttr( 'iframe', 'src' );
            if ( !empty( $src ) ) {
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
