<?php

namespace App\Feeds\Vendors\JEF;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private array $json = [];
    private ?array $attrs = null;
    private array $dims = [];
    private string $description = '';

    private function parseImages( Data $data ): array
    {
        $page = $data->getData();

        preg_match_all( '%<li class="alternate-image.*?href="(.*?)"%uis', $page, $match );
        if ( empty( $match[ 1 ] ) ) {
            preg_match( '%<script type="application/ld\+json">\s*(.*?)\s*</script>%ui', $page, $json );
            $json = json_decode( $json[ 1 ], true, 512, JSON_THROW_ON_ERROR );
            $images[] = $json[ 'image' ];
        }
        else {
            $images = $match[ 1 ];
        }

        return $this->cleanImgUrls( $images );
    }

    private function cleanImgUrls( array $images ): array
    {
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

    public function beforeParse(): void
    {
        $json = trim( $this->getText( 'script[type="application/ld+json"]' ) );
        $this->json = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );

        $this->description = $this->getHtml( 'div.long-description' );

        // Attributes
        if ( !empty( $this->json[ 'offers' ][ 'size' ] ) ) {
            $this->attrs[ 'Size' ] = $this->json[ 'offers' ][ 'size' ];
        }
        if ( !empty( $this->json[ 'offers' ][ 'color' ] ) ) {
            $this->attrs[ 'Color' ] = $this->json[ 'offers' ][ 'color' ];
        }

        $this->filter( 'ul.attributes li' )->each( function ( ParserCrawler $c ) {
            $attr = explode( '</strong>', $c->filter( 'li' )->html() );

            $name = trim( strip_tags( $attr[ 0 ] ), ' : ' );
            $name = StringHelper::normalizeSpaceInString( $name );

            $value = trim( strip_tags( $attr[ 1 ] ), '  ' );
            $value = StringHelper::normalizeSpaceInString( $value );

            if ( $name === 'Brand' ) {
                return;
            }
            if ( ( $name === 'Size' || $name === 'Dimensions' ) && str_contains( $value, '"' ) ) {
                preg_match_all( '%([\d.\-/]+)"%', $value, $match );
                if ( !empty( $match[ 1 ][ 0 ] ) ) {
                    $this->dims[ 'x' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ][ 0 ] ) );
                }
                if ( !empty( $match[ 1 ][ 1 ] ) ) {
                    $this->dims[ 'z' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ][ 1 ] ) );
                }
                if ( !empty( $match[ 1 ][ 2 ] ) ) {
                    $this->dims[ 'y' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ][ 2 ] ) );
                }

                return;
            }
            if ( $name === 'Length' && preg_match( '%([\d.\-/]+)%', $value, $match ) ) {
                if ( str_contains( $value, "'" ) ) {
                    $match[ 1 ] *= 12;
                }
                $this->dims[ 'x' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );

                return;
            }
            if ( $name === 'Width' && preg_match( '%([\d.\-/]+)%', $value, $match ) ) {
                if ( str_contains( $value, "'" ) ) {
                    $match[ 1 ] *= 12;
                }
                $this->dims[ 'z' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );

                return;
            }
            if ( $name === 'Height' && preg_match( '%([\d.\-/]+)%', $value, $match ) ) {
                if ( str_contains( $value, "'" ) ) {
                    $match[ 1 ] *= 12;
                }
                $this->dims[ 'y' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );

                return;
            }

            $this->attrs[ $name ] = $value;
        } );

        if ( preg_match( '%(Measures|Dimensions):(.*?)</p>%uis', $this->description, $match ) ) {
            preg_match_all( '%([\d.\-/]+)"%', $match[ 2 ], $dims );
            if ( count( $dims[ 1 ] ) > count( $this->dims ) ) {
                if ( empty( $this->dims[ 'x' ] ) ) {
                    $this->dims[ 'x' ] = !empty( $dims[ 1 ][ 0 ] )
                        ? StringHelper::getFloat( str_replace( '-', ' ', $dims[ 1 ][ 0 ] ) ) : null;
                }
                if ( empty( $this->dims[ 'z' ] ) ) {
                    $this->dims[ 'z' ] = !empty( $dims[ 1 ][ 1 ] )
                        ? StringHelper::getFloat( str_replace( '-', ' ', $dims[ 1 ][ 1 ] ) ) : null;
                }
                if ( empty( $this->dims[ 'y' ] ) ) {
                    $this->dims[ 'y' ] = !empty( $dims[ 1 ][ 2 ] )
                        ? StringHelper::getFloat( str_replace( '-', ' ', $dims[ 1 ][ 2 ] ) ) : null;
                }
            }
        }
    }

    public function isGroup(): bool
    {
        return isset( $this->json[ 'offers' ][ 1 ] );
    }

    public function getMpn(): string
    {
        return !empty( $this->json[ 'offers' ][ 0 ][ 'sku' ] )
            ? $this->json[ 'offers' ][ 0 ][ 'sku' ]
            : $this->json[ 'offers' ][ 'sku' ];
    }

    public function getProduct(): string
    {
        return $this->json[ 'name' ];
    }

    public function getCostToUs(): float
    {
        return StringHelper::getMoney( !empty( $this->json[ 'offers' ][ 0 ][ 'price' ] )
            ? $this->json[ 'offers' ][ 0 ][ 'price' ]
            : $this->json[ 'offers' ][ 'price' ] ?? 0.0 );
    }

    public function getImages(): array
    {
        $images = [];
        $this->filter( 'li.alternate-image a' )->each( function ( ParserCrawler $c ) use ( &$images ) {
            $image = $c->getAttr( 'a', 'href' );
            $images[] = $image;
        } );

        if ( empty( $images ) ) {
            $images[] = $this->json[ 'image' ];
        }

        return $this->cleanImgUrls( $images );
    }

    public function getAvail(): ?int
    {
        $avail = !empty( $this->json[ 'offers' ][ 0 ][ 'availability' ] )
            ? $this->json[ 'offers' ][ 0 ][ 'availability' ]
            : $this->json[ 'offers' ][ 'availability' ];

        return $avail === 'http://schema.org/InStock' ? self::DEFAULT_AVAIL_NUMBER : 0;
    }

    public function getCategories(): array
    {
        $categories = $this->getContent( 'div.wl-breadcrumbs a' );
        array_shift( $categories );

        return $categories;
    }

    public function getDescription(): string
    {
        $description = preg_replace( '%</h(\d+)>%uis', "</h$1><br>", $this->description );
        $this->filter( 'div.long-description p' )->each( function ( ParserCrawler $c ) use ( &$description ) {
            $p = $c->outerHtml();
            if ( str_contains( $p, '$' ) || str_contains( $p, 'Dimensions:' ) || str_contains( $p, 'Size:' ) || str_contains( $p, 'Measures:' ) ) {
                $description = str_replace( $p, '', $description );
            }
        } );

        return trim( $description );
    }

    public function getAttributes(): ?array
    {
        return $this->attrs ?? null;
    }

    public function getBrand(): string
    {
        return !empty( $this->json[ 'brand' ][ 'name' ] ) ? $this->json[ 'brand' ][ 'name' ] : '';
    }

    public function getVideos(): array
    {
        $videos = [];
        $this->filter( 'div.long-description iframe' )->each( function ( ParserCrawler $c ) use ( &$videos ) {
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

    public function getChildProducts( FeedItem $parent_fi ): array
    {
        $child = [];

        $this->filter( 'select#sku option' )->each( function ( ParserCrawler $c ) use ( &$child, $parent_fi ) {
            $mpn = $c->getAttr( 'option', 'value' );
            if ( empty( $mpn ) ) {
                return;
            }

            $url = $this->getInternalId();
            $url = str_contains( $url, '?' ) ? $url . '&sku=' . $mpn : $url . '?sku=' . $mpn;

            $images = $this->parseImages( $this->getVendor()->getDownloader()->get( $url ) );

            preg_match( '%(\([\$\d.\s]+\))%', $c->text(), $match );
            if ( !empty( $match[ 1 ] ) ) {
                $price = StringHelper::getFloat( $match[ 1 ] );
                $product = trim( str_replace( $match[ 1 ], '', $c->text() ), '  ' );
            }
            else {
                $price = $this->getCostToUs();
                $product = trim( $c->text(), '  ' );
            }


            $fi = clone $parent_fi;
            $fi->setMpn( $mpn );
            $fi->setProduct( $product );
            $fi->setCostToUs( $price );
            $fi->setRAvail( $this->getAvail() );
            $fi->setImages( $images );

            $child[] = $fi;
        } );

        if ( !empty( $child ) ) {
            return $child;
        }

        foreach ( $this->json[ 'offers' ] as $offer ) {
            $product = !empty( $offer[ 'size' ] ) ? 'Size: ' . $offer[ 'size' ] : '';
            $product = !empty( $offer[ 'color' ] ) ? $product . ', Color: ' . $offer[ 'color' ] : $product;

            $mpn = $offer[ 'sku' ];

            $url = $this->getInternalId();
            $url = str_contains( $url, '?' ) ? $url . '&sku=' . $mpn : $url . '?sku=' . $mpn;

            $images = $this->parseImages( $this->getVendor()->getDownloader()->get( $url ) );

            $fi = clone $parent_fi;
            $fi->setMpn( $mpn );
            $fi->setProduct( $product );
            $fi->setCostToUs( $offer[ 'price' ] );
            $fi->setRAvail( $offer[ 'availability' ] === 'http://schema.org/InStock' ? self::DEFAULT_AVAIL_NUMBER : 0 );
            $fi->setImages( $images );

            $child[] = $fi;
        }

        return $child;
    }
}
