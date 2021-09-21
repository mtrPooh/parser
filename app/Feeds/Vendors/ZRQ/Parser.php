<?php

namespace App\Feeds\Vendors\ZRQ;

use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private ?int $avail = null;
    private array $dims = [];
    private array $shorts = [];
    private ?array $attrs = null;
    private ?float $weight = null;
    private ?float $s_weight = null;
    private string $body = '';

    private function parseDims( string $text ): void
    {
        preg_match_all( '%([\d]+\s*[\d.\-/¼½¾"”in]+)%ui', $text, $match );

        if ( !empty( $match[ 1 ][ 0 ] ) ) {
            $this->dims[ 'z' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ][ 0 ] ) );
        }
        if ( !empty( $match[ 1 ][ 1 ] ) ) {
            $this->dims[ 'x' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ][ 1 ] ) );
        }
        if ( !empty( $match[ 1 ][ 2 ] ) ) {
            $this->dims[ 'y' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ][ 2 ] ) );
        }
    }

    public function parseContent( $data, $params = [] ): array
    {
        $d = $data->getData();
        if ( str_contains( $d, '>CONFIGURE YOUR OWN<' ) || str_contains( $d, '>Customize<' )
            || !str_contains( $d, 'id="product-add-to-cart"' ) ) {

            return [];
        }

        return parent::parseContent( $data, $params );
    }

    public function beforeParse(): void
    {
        $this->body = $this->getHtml( 'body' );

        // Available
        $this->avail = str_contains( $this->body, '>[IN STOCK]<' ) ? self::DEFAULT_AVAIL_NUMBER : 0;

        // Short Description, Dimensions
        if ( preg_match( '%SPECS\s*</\w+>(.*?)</div>\s*</div>%uis', $this->body, $match ) ) {

            $data = explode( '</div>', $match[ 1 ] );

            foreach ( $data as $d ) {
                $need_skip = false;
                $d = trim( strip_tags( $d ) );

                if ( empty( $this->dims ) && ( str_contains( $d, 'Dimension' ) || str_contains( $d, 'Total dim' )
                        || str_contains( $d, 'Fully assembled' ) || str_contains( $d, 'Each claws installed' )
                        || str_ends_with( $d, 'deep' ) || ( str_contains( $d, 'L' ) && str_contains( $d, 'W' ) )
                        || ( str_contains( $d, 'H' ) && str_contains( $d, 'W' ) ) ) ) {

                    $text = $d;
                    if ( str_contains( $d, 'Weigh' ) ) {
                        $text = substr( $d, 0, strpos( $d, 'Weigh' ) );
                    }
                    if ( substr_count( $text, 'Dimension' ) > 1 ) {
                        $text = substr( $d, 0, strrpos( $text, 'Dimension' ) );
                    }

                    $this->parseDims( $text );

                    $need_skip = true;
                }

                if ( empty( $this->dims ) && preg_match( '%([\d]+\s*[\d.\-/¼½¾"”inx ]+)%ui', $d, $match )
                    && strlen( strip_tags( $d ) ) === strlen( $match[ 1 ] ) ) {

                    $this->parseDims( $d );

                    $need_skip = true;
                }

                if ( str_contains( $d, 'Diameter' ) && preg_match( '%([\d.\-/¼½¾" ]+)%u', $d, $match ) ) {
                    $this->dims[ 'x' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );
                    $need_skip = true;
                }

                if ( preg_match( '%Shipping Weigh[st:\s]+([\d.\-/¼½¾" ]+)%ui', $d, $match ) ) {
                    $this->s_weight = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );
                    $need_skip = true;
                }

                if ( preg_match( '%Weigh[st:\s]+([\d.\-/¼½¾" ]+)%ui', $d, $match ) ) {
                    $this->weight = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );
                    $need_skip = true;
                }

                if ( $need_skip === false && !empty( $d ) && !str_contains( $d, '$' ) ) {
                    $this->shorts[] = $d;
                }
            }
        }
    }

    public function getMpn(): string
    {
        if ( preg_match( '%Part Number[: ]*(\w+)<%ui', $this->body, $match ) ) {
            return trim( $match[ 1 ] );
        }

        return $this->getAttr( 'a#product-add-to-cart', 'pid' );
    }

    public function getProduct(): string
    {
        return $this->getText( 'h1' );
    }

    public function getCostToUs(): float
    {
        return $this->getMoney( 'span.product_price' );
    }

    public function getListPrice(): ?float
    {
        return $this->getMoney( 'span.product_original_price' );
    }

    public function getImages(): array
    {
        $images = [];
        $this->filter( 'img.product-main-image' )->each( function ( ParserCrawler $c ) use ( &$images ) {
            $image = $c->getAttr( 'img', 'src' );
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
        return $this->avail;
    }

    public function getCategories(): array
    {
        $categories = $this->getContent( 'ul.breadcrumb-2019 a' );
        array_shift( $categories );

        return $categories;
    }

    public function getDescription(): string
    {
        $description = $this->getHtml( 'div.description-section' );

        $this->filter( 'ul' )->each( function ( ParserCrawler $c ) use ( &$description ) {
            $description = str_ireplace( $c->outerHtml(), '', $description );
        } );
        $this->filter( 'div.description-section p' )->each( function ( ParserCrawler $c ) use ( &$description ) {
            $p = $c->getHtml( 'p' );
            if ( str_contains( $p, 'Part Number' ) || str_contains( $p, '$' ) ) {
                $description = str_replace( $p, '', $description );
            }
            if ( str_contains( $p, 'Width' ) ) {
                $this->dims[ 'x' ] = StringHelper::getFloat( $p );
                $description = str_replace( $p, '', $description );
            }
        } );
        $description = preg_replace( '%<h\d+>\s*DESCRIPTION\s*</h\d+>\s*%ui', '', $description );

        return trim( $description );
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

    public function getShippingWeight(): ?float
    {
        return $this->s_weight;
    }

    public function getVideos(): array
    {
        $videos = [];
        $this->filter( 'div.product_video' )->each( function ( ParserCrawler $c ) use ( &$videos ) {
            $src = $c->getAttr( 'ifriend', 'src' );
            if ( !empty( $src ) ) {
                $url_parts = parse_url( $src );
                $domain = $url_parts[ 'host' ];
                if ( str_contains( $domain, '.' ) ) {
                    $domain = explode( '.', $domain );
                    $domain = $domain[ count( $domain ) - 2 ];
                }
                $name = trim( $c->getText( 'h1' ) ) ?: $this->getProduct();
                $videos[] = [ 'name' => $name,
                    'video' => $src,
                    'provider' => $domain ];
            }
        } );

        return $videos;
    }
}
