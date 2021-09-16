<?php

namespace App\Feeds\Vendors\JEF;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private array $json = [];
    private ?array $attrs = null;
    private array $dims = [];
    private string $description = '';

    private function parseImages( string $data ): array
    {
        preg_match_all( '%<li class="alternate-image.*?href="(.*?)"%uis', $data, $match );
        if ( empty( $match[ 1 ] ) ) {
            $images[] = $this->json[ 'image' ];
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
        if ( !$json ) {
            return;
        }

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
            if ( ( $name === 'Size' || $name === 'Dimensions' ) && str_contains( $value, ',' ) === false
                && str_contains( $value, 'ount' ) === false && str_contains( $value, 'uart' ) === false ) {

                preg_match_all( '%([\d.\-/¼½¾"yards ]+)%ui', $value, $match );

                $dims = [];
                foreach ( $match[ 1 ] as $size ) {
                    if ( !empty( trim( $size ) ) ) {
                        $dims[] = $size;
                    }
                }

                if ( str_contains( $value, 'L' ) || str_contains( $value, 'W' ) || str_contains( $value, 'H' ) ) {
                    if ( !empty( $dims[ 0 ] ) ) {
                        $value = StringHelper::getFloat( str_replace( '-', ' ', $dims[ 0 ] ) );
                        $this->dims[ 'x' ] = str_contains( $dims[ 0 ], 'yards' ) ? $value * 36 : $value;
                    }
                    if ( !empty( $dims[ 1 ] ) ) {
                        $value = StringHelper::getFloat( str_replace( '-', ' ', $dims[ 1 ] ) );
                        $this->dims[ 'z' ] = str_contains( $dims[ 1 ], 'yards' ) ? $value * 36 : $value;
                    }
                    if ( !empty( $dims[ 2 ] ) ) {
                        $value = StringHelper::getFloat( str_replace( '-', ' ', $dims[ 2 ] ) );
                        $this->dims[ 'y' ] = str_contains( $dims[ 2 ], 'yards' ) ? $value * 36 : $value;
                    }
                }
                else {
                    if ( !empty( $dims[ 0 ] ) ) {
                        $value = StringHelper::getFloat( str_replace( '-', ' ', $dims[ 0 ] ) );
                        $this->dims[ 'x' ] = str_contains( $match[ 1 ][ 0 ], 'yards' ) ? $value * 36 : $value;
                    }
                    if ( !empty( $dims[ 1 ] ) ) {
                        $value = StringHelper::getFloat( str_replace( '-', ' ', $dims[ 1 ] ) );
                        $this->dims[ 'y' ] = str_contains( $dims[ 1 ], 'yards' ) ? $value * 36 : $value;
                    }
                    if ( !empty( $dims[ 2 ] ) ) {
                        $value = StringHelper::getFloat( str_replace( '-', ' ', $dims[ 2 ] ) );
                        $this->dims[ 'z' ] = str_contains( $dims[ 2 ], 'yards' ) ? $value * 36 : $value;
                    }
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

        if ( preg_match( '%(Measures|Dimensions):[</b> ]*(.*?)(</p>|</li>|<br>)%uis', $this->description, $match ) ) {

            preg_match_all( '%([\d.\-/¼½¾"yardsLWH ]+)%ui', $match[ 2 ], $sizes );

            $dims = [];
            foreach ( $sizes[ 1 ] as $size ) {
                if ( !empty( trim( $size ) ) && preg_match( '%\d+%', $size ) ) {
                    $dims[] = $size;
                }
            }

            if ( count( $dims ) > count( $this->dims ) ) {
                foreach ( $dims as $dim ) {
                    if ( str_contains( $dim, 'W' ) ) {
                        $value = StringHelper::getFloat( str_replace( '-', ' ', $dim ) );
                        $this->dims[ 'x' ] = str_contains( $dim, 'yards' ) ? $value * 36 : $value;
                    }
                    elseif ( str_contains( $dim, 'L' ) ) {
                        $value = StringHelper::getFloat( str_replace( '-', ' ', $dim ) );
                        $this->dims[ 'y' ] = str_contains( $dim, 'yards' ) ? $value * 36 : $value;
                    }
                    else {
                        $value = StringHelper::getFloat( str_replace( '-', ' ', $dim ) );
                        $this->dims[ 'z' ] = str_contains( $dim, 'yards' ) ? $value * 36 : $value;
                    }
                }
                if ( empty( $this->dims[ 'x' ] ) && !empty( $dims[ 0 ] ) ) {
                    $value = StringHelper::getFloat( str_replace( '-', ' ', $dims[ 0 ] ) );
                    $this->dims[ 'x' ] = str_contains( $dims[ 0 ], 'yards' ) ? $value * 36 : $value;
                }
                if ( empty( $this->dims[ 'z' ] ) && !empty( $dims[ 1 ] ) ) {
                    $value = StringHelper::getFloat( str_replace( '-', ' ', $dims[ 1 ] ) );
                    $this->dims[ 'z' ] = str_contains( $dims[ 1 ], 'yards' ) ? $value * 36 : $value;
                }
                if ( empty( $this->dims[ 'y' ] ) && !empty( $dims[ 2 ] ) ) {
                    $value = StringHelper::getFloat( str_replace( '-', ' ', $dims[ 2 ] ) );
                    $this->dims[ 'y' ] = str_contains( $dims[ 2 ], 'yards' ) ? $value * 36 : $value;
                }
            }

            if ( empty( $this->dims[ 'x' ] ) && preg_match( '%Lenght:([\d.\-/¼½¾"yards ]+)%ui',
                    $this->description, $match )
            ) {
                $value = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );
                $this->dims[ 'x' ] = str_contains( $match[ 1 ], 'yards' ) ? $value * 36 : $value;
            }
            if ( empty( $this->dims[ 'z' ] ) && preg_match( '%Width:([\d.\-/¼½¾"yards ]+)%ui',
                    $this->description, $match )
            ) {
                $value = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );
                $this->dims[ 'z' ] = str_contains( $match[ 1 ], 'yards' ) ? $value * 36 : $value;
            }
            if ( empty( $this->dims[ 'y' ] ) && preg_match( '%Height:([\d.\-/¼½¾"yards ]+)%ui',
                    $this->description, $match )
            ) {
                $value = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );
                $this->dims[ 'y' ] = str_contains( $match[ 1 ], 'yards' ) ? $value * 36 : $value;
            }
        }

        if ( preg_match( '%Diameter:\s*([\d.\-/¼½¾"yards ]+)%ui', $this->description, $match ) ) {
            $value = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );
            $this->dims[ 'x' ] = str_contains( $match[ 1 ], 'yards' ) ? $value * 36 : $value;
        }

        if ( preg_match( '%Outside:\s*[</b>]*(.*?)<%uis', $this->description, $match ) ) {

            preg_match_all( '%([\d.\-/¼½¾"yardsimetLWH ]+)%ui', $match[ 1 ], $sizes );

            $dims = [];
            foreach ( $sizes[ 1 ] as $size ) {
                if ( !empty( trim( $size ) ) && preg_match( '%\d+%', $size ) ) {
                    $dims[] = $size;
                }
            }

            foreach ( $dims as $dim ) {
                if ( str_contains( $dim, 'W' ) || str_contains( $dim, 'iameter' ) ) {
                    $value = StringHelper::getFloat( str_replace( '-', ' ', $dim ) );
                    $this->dims[ 'x' ] = str_contains( $dim, 'yards' ) ? $value * 36 : $value;
                }
                elseif ( str_contains( $dim, 'L' ) ) {
                    $value = StringHelper::getFloat( str_replace( '-', ' ', $dim ) );
                    $this->dims[ 'y' ] = str_contains( $dim, 'yards' ) ? $value * 36 : $value;
                }
                else {
                    $value = StringHelper::getFloat( str_replace( '-', ' ', $dim ) );
                    $this->dims[ 'z' ] = str_contains( $dim, 'yards' ) ? $value * 36 : $value;
                }
            }
        }

        if ( preg_match( '%Inside:\s*[</b>]*(.*?)<%uis', $this->description, $match ) ) {
            $this->attrs[ 'Inside' ] = trim( $match[ 1 ], ' : ' );
        }

        if ( preg_match( '%Depth:\s*[</b>]*([\d.\-/¼½¾"yards ]+)%ui', $this->description, $match ) ) {
            $value = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );
            $this->dims[ 'z' ] = str_contains( $match[ 1 ], 'yards' ) ? $value * 36 : $value;
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
            : $this->json[ 'offers' ][ 'sku' ] ?? '';
    }

    public function getProduct(): string
    {
        return $this->json[ 'name' ] ?? '';
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

        if ( empty( $images ) && !empty( $this->json[ 'image' ] ) ) {
            $images[] = $this->json[ 'image' ];
        }

        return $this->cleanImgUrls( $images );
    }

    public function getAvail(): ?int
    {
        if ( str_contains( $this->getHtml( '.main' ), 'This product is currently unavailable for purchase' ) ) {
            return 0;
        }
        if ( !empty( $this->json[ 'offers' ][ 0 ][ 'availability' ] ) ) {
            $avail = $this->json[ 'offers' ][ 0 ][ 'availability' ];
        }
        else {
            $avail = !empty( $this->json[ 'offers' ][ 'availability' ] )
                ? $this->json[ 'offers' ][ 'availability' ]
                : 0;
        }

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
        $search = [
            '%(<h\d+>.*?\$.*?</h\d+>)\s*<%ui',
            '%(<b>Color:\s*</b>.*?)<%ui',
            '%(<b>Dimensions:\s*</b>.*?)<%ui',
            '%(<b>Diameter:\s*</b>.*?)<%ui',
            '%(<b>Outside:\s*</b>.*?)<%ui',
            '%(<b>Inside:\s*</b>.*?)<%ui',
            '%(<b>Depth:\s*</b>.*?)<%ui',
            '%<\w+>\s*Lenght:.*?</\w+>\s*<%uis',
            '%<\w+>\s*Width:.*?</\w+>\s*<%uis',
            '%<\w+>\s*Height:.*?</\w+>\s*<%uis'
        ];
        $description = preg_replace( $search, '<', $this->description );
        $description = preg_replace( '%</h(\d+)>%ui', "</h$1><br>", $description );
        $this->filter( 'div.long-description p' )->each( function ( ParserCrawler $c ) use ( &$description ) {
            $p = $c->outerHtml();
            if ( str_contains( $p, '$' ) || str_contains( $p, 'Dimensions:' ) || str_contains( $p, 'Size:' ) || str_contains( $p, 'Measures:' )
                || str_contains( $p, 'Diameter:' ) || str_contains( $p, 'Outside:' ) || str_contains( $p, 'Inside:' ) || str_contains( $p, 'Depth:' ) ) {

                $description = str_replace( $p, '', $description );
            }
        } );
        $this->filter( 'div.long-description b' )->each( function ( ParserCrawler $c ) use ( &$description ) {
            $b = $c->outerHtml();
            if ( str_contains( $b, '$' ) || str_contains( $b, 'Dimensions:' ) || str_contains( $b, 'Size:' )
                || str_contains( $b, 'Measures:' ) || str_contains( $b, 'Diameter:' ) || str_contains( $b, 'Outside:' )
                || str_contains( $b, 'Inside:' ) || str_contains( $b, 'Depth:' ) ) {

                $description = str_replace( $b, '', $description );
            }
        } );
        preg_match_all( '%(<br>.*?\w+.*?<br>)%ui', $description, $match );
        foreach ( $match[ 1 ] as $m ) {
            if ( str_contains( $m, '$' ) ) {
                $description = str_replace( $m, '', $description );
            }
        }
        preg_match_all( '%([<br>p/\s]{3,}<ul.*?</ul>)%uis', $description, $match );
        foreach ( $match[ 1 ] as $m ) {
            if ( str_contains( $m, '$' ) ) {
                $description = str_replace( $m, '#del#', $description );
            }
        }
        preg_match_all( '%(<li.*?</li>)%uis', $description, $match );
        foreach ( $match[ 1 ] as $m ) {
            if ( str_contains( $m, '$' ) || str_contains( $m, 'Dimensions:' ) || str_contains( $m, 'Size:' )
                || str_contains( $m, 'Color:' ) || str_contains( $m, 'Measures:' ) || str_contains( $m, 'Diameter:' )
                || str_contains( $m, 'Outside:' ) || str_contains( $m, 'Inside:' ) || str_contains( $m, 'Depth:' )
            ) {

                $description = str_replace( $m, '', $description );
            }
        }
        preg_match_all( '%([<br>p/\s]{3,}<table.*?</table>)%uis', $description, $match );
        foreach ( $match[ 1 ] as $m ) {
            if ( str_contains( $m, '$' ) ) {
                $description = str_replace( $m, '#del#', $description );
            }
        }
        $description = preg_replace( [ '%<h\d+.*?</h\d+#del#%', '%<ul>\s*</ul>%ui' ], '', $description );
        $description = str_replace( '#del#', '', $description );
        $description = preg_replace( '%<p>Benefits:</p>\s*<ul.*?</ul>%uis', '', $description );

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

            preg_match( '%(\([\$\d.\s]+\))%', $c->text(), $match );
            if ( !empty( $match[ 1 ] ) ) {
                $price = StringHelper::getFloat( $match[ 1 ] );
                $product = trim( str_replace( $match[ 1 ], '', $c->text() ), '  ' );
            }
            else {
                $price = $this->getCostToUs();
                $product = trim( $c->text(), '  ' );
            }

            if ( empty( $product ) || str_contains( $product, '$' ) ) {
                return;
            }

            $url = $this->getInternalId();
            $url = str_contains( $url, '?' ) ? $url . '&sku=' . $mpn : $url . '?sku=' . $mpn;

            $data = $this->getVendor()->getDownloader()->get( $url );
            $data = $data->getData();

            if ( str_contains( $data, 'This product is currently unavailable for purchase' ) ) {
                return;
            }

            $images = $this->parseImages( $data );

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
            if ( empty( $offer[ 'size' ] ) && empty( $offer[ 'color' ] ) ) {
                continue;
            }

            $product = isset( $offer[ 'size' ] ) ? 'Size: ' . $offer[ 'size' ] : '';
            if ( isset( $offer[ 'color' ] ) ) {
                $product = !empty( $product ) ? $product . ', Color: ' . $offer[ 'color' ] : 'Color: ' . $offer[ 'color' ];
            }

            $mpn = $offer[ 'sku' ];

            $url = $this->getInternalId();
            $url = str_contains( $url, '?' ) ? $url . '&sku=' . $mpn : $url . '?sku=' . $mpn;

            $data = $this->getVendor()->getDownloader()->get( $url );
            $data = $data->getData();

            if ( str_contains( $data, 'This product is currently unavailable for purchase' ) ) {
                continue;
            }

            $images = $this->parseImages( $data );

            $fi = clone $parent_fi;
            $fi->setMpn( $mpn );
            $fi->setProduct( $product );
            $fi->setCostToUs( StringHelper::getMoney( $offer[ 'price' ] ?? 0.00 ) );
            $fi->setRAvail( $offer[ 'availability' ] === 'http://schema.org/InStock' ? self::DEFAULT_AVAIL_NUMBER : 0 );
            $fi->setImages( $images );

            $child[] = $fi;
        }

        return $child;
    }
}
