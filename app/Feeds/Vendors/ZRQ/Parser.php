<?php

namespace App\Feeds\Vendors\ZRQ;

use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\FeedHelper;
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

        if ( str_contains( $text, 'Oval' ) ) {
            $value = $this->dims[ 'z' ];
            $this->dims[ 'z' ] = $this->dims[ 'x' ];
            $this->dims[ 'x' ] = $value;
        }

        if ( str_contains( $text, 'Mount' ) ) {
            $this->dims[ 'y' ] = $this->dims[ 'x' ];
            $this->dims[ 'x' ] = $this->dims[ 'z' ];
            $this->dims[ 'z' ] = null;
        }

        if ( str_contains( $text, 'H' ) && str_contains( $text, 'W' ) ) {
            $value = $this->dims[ 'z' ];
            $this->dims[ 'z' ] = $this->dims[ 'y' ];
            $this->dims[ 'y' ] = $value;
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

        // Short Description, Dimensions, Weight
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
                        $short = substr( $d, strrpos( $text, 'Dimension' ) );
                        $short = str_contains( $short, 'Weigh' ) ? substr( $short, 0, strrpos( $short, 'Weigh' ) ) : $short;
                        $short = str_contains( $short, 'Price' ) ? substr( $short, 0, strrpos( $short, 'Price' ) ) : $short;
                        $this->shorts[] = trim( $short );
                        $text = substr( $d, 0, strrpos( $text, 'Dimension' ) );
                    }

                    $this->parseDims( $text );

                    $need_skip = true;
                }

                if ( empty( $this->dims ) && preg_match( '%([\d]+[\d.\-/¼½¾"”inx ]+)%ui', $d, $match )
                    && strlen( strip_tags( $d ) ) === strlen( $match[ 1 ] ) ) {

                    $this->parseDims( $d );

                    $need_skip = true;
                }

                if ( str_contains( $d, 'Diameter' ) && preg_match( '%([\d]+[\d.\-/¼½¾" ]+)%u', $d, $match ) ) {
                    $this->dims[ 'x' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );
                    $need_skip = true;
                }

                if ( str_contains( $d, 'Assembled length' ) && preg_match( '%([\d]+[\d.\-/¼½¾" ]+)%u', $d, $match ) ) {
                    $this->dims[ 'z' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );
                    $need_skip = true;
                }
                if ( str_contains( $d, 'Assembled width' ) && preg_match( '%([\d]+[\d.\-/¼½¾" ]+)%u', $d, $match ) ) {
                    $this->dims[ 'x' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );
                    $need_skip = true;
                }
                if ( str_contains( $d, 'Assembled height' ) && preg_match( '%([\d]+[\d.\-/¼½¾" ]+)%u', $d, $match ) ) {
                    $this->dims[ 'y' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );
                    $need_skip = true;
                }

                if ( preg_match( '%Shipping Weigh[st:\s]+([\d]+[\d.\-/¼½¾" ]+)%ui', $d, $match ) ) {
                    $this->s_weight = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );
                    $need_skip = true;
                }

                if ( preg_match( '%Weigh[st:\s]+([\d]+[\d.\-/¼½¾" ]+)%ui', $d, $match ) ) {
                    $this->weight = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );
                    $need_skip = true;
                }

                if ( preg_match( '%^Color[:\s]+(.*?)$%ui', $d, $match ) ) {
                    $this->attrs[ 'Color' ] = trim( $match[ 1 ] );
                    $need_skip = true;
                }

                if ( $need_skip === false && !empty( $d ) && str_contains( $d, ':' ) ) {
                    $data = explode( ':', $d );
                    $key = trim( $data[ 0 ] );
                    $value = trim( $data[ 1 ] );
                    if ( !empty( $key ) && !empty( $value ) ) {
                        $this->attrs[ $key ] = $value;
                        $need_skip = true;
                    }
                }

                if ( $need_skip === false && !empty( $d ) && !str_contains( $d, '$' ) ) {
                    $this->shorts[] = $d;
                }
            }
        }

        $this->filter( 'div.description-section p' )->each( function ( ParserCrawler $c ) {
            $p = $c->getHtml( 'p' );
            if ( str_contains( $p, 'Weigh' ) && str_contains( $p, 'oz' ) ) {
                $this->weight = FeedHelper::convertLbsFromOz( StringHelper::getFloat( $p ) );
            }
        } );
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
            $images[] = trim( $image );
        } );

        return array_values( array_unique( $images ) );
    }

    public function getAvail(): ?int
    {
        return $this->avail;
    }

    public function getMinAmount(): ?int
    {
        if ( $this->exists( 'select#product-quantity option' ) ) {
            $min_amount = (int) $this->getAttr( 'select#product-quantity option', 'value' );
        }

        return $min_amount ?? null;
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

        $specs_found = false;
        $this->filter( 'div.description-section p' )
            ->each( function ( ParserCrawler $c ) use ( &$description, &$specs_found ) {

                $p = $c->outerHtml();

                preg_match_all( '%br>([\w]+.*?:.*?)<%', $p, $match );
                if ( !empty( $match[ 1 ] ) ) {
                    foreach ( $match[ 1 ] as $data ) {
                        $d = explode( ':', $data );

                        $key = trim( strip_tags( $d[ 0 ] ) );
                        $value = trim( strip_tags( $d[ 1 ] ) );

                        if ( str_contains( $key, 'Description' ) ) {
                            $description = str_replace( $data, '', $description );
                            continue;
                        }
                        if ( str_contains( $key, 'Weigh' ) && str_contains( $p, 'oz' ) ) {
                            $description = str_replace( $data, '', $description );
                            continue;
                        }
                        if ( str_contains( $key, 'Width' ) ) {
                            $this->dims[ 'x' ] = StringHelper::getFloat( $value );
                            $description = str_replace( $data, '', $description );
                            continue;
                        }
                        if ( !empty( $key ) && !empty( $value ) ) {
                            $this->attrs[ $key ] = $value;
                            $description = str_replace( $data, '', $description );
                        }
                    }
                }
                else {
                    if ( str_contains( $p, 'Specification' ) ) {
                        $specs_found = true;
                        $description = str_replace( $p, '', $description );
                        return;
                    }
                    if ( str_contains( $p, '$' ) || str_contains( $p, 'Part Number' ) || str_contains( $p, 'Product Name' )
                        || str_contains( $p, 'Description' ) || str_contains( $p, 'Specification' ) ) {

                        $description = str_replace( $p, '', $description );
                        return;
                    }

                    if ( str_contains( $p, 'Weigh' ) && str_contains( $p, 'oz' ) ) {
                        $description = str_replace( $p, '', $description );
                        return;
                    }
                    if ( str_contains( $p, 'Width' ) ) {
                        $this->dims[ 'x' ] = StringHelper::getFloat( $p );
                        $description = str_replace( $p, '', $description );
                        return;
                    }
                    if ( $specs_found && str_contains( $p, ':' ) ) {
                        $d = explode( ':', $p );

                        $key = trim( strip_tags( $d[ 0 ] ) );
                        $value = trim( strip_tags( $d[ 1 ] ) );

                        if ( !empty( $key ) && !empty( $value ) ) {
                            $this->attrs[ $key ] = $value;
                            $description = str_replace( $p, '', $description );
                        }

                        return;
                    }
                    if ( $specs_found ) {
                        $value = trim( strip_tags( $p ) );
                        if ( !empty( $value ) ) {
                            $this->shorts[] = $value;
                            $description = str_replace( $p, '', $description );
                        }
                    }
                }
            } );

        $chip_table = '';
        if ( preg_match( '%chip\s*=\s*({\s*".*?})\s*;%is', $this->body, $match ) ) {

            preg_match_all( '%"([\$\d.,]+)".*?quantity:\s*([\d]+)%', $match[ 1 ], $chips, PREG_SET_ORDER );

            $row = [];
            $col = 0;
            foreach ( $chips as $chip ) {
                $col++;
                $row[] = '<td>' . $chip[ 1 ] . '<br>' . $chip[ 2 ] . '</td>';
                if ( $col === 3 ) {
                    $chip_table .= '<tr>' . implode( '', $row ) . '</tr>';
                    $row = [];
                    $col = 0;
                }
            }

            if ( !empty( $row ) ) {
                $last_row = '';
                for ( $i = 0; $i < 3; $i++ ) {
                    $last_row .= $row[ $i ] ?? '<td></td>';
                }
                $chip_table .= '<tr>' . $last_row . '</tr>';
            }

            $chip_table = '<table>' . $chip_table . '</table>';
        }

        $description = trim( preg_replace( [ '%<h\d+>\s*DESCRIPTION\s*</h\d+>\s*%ui', '%<div>\s*</div>%ui' ], '',
                $description ) ) . $chip_table;

        return FeedHelper::cleanProductDescription( $description ) ?: $this->getProduct();
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

    public function getOptions(): array
    {
        if ( preg_match( '%Color[:\s]+</h.*?<select.*?>(.*?)</select%uis', $this->body, $match ) ) {
            preg_match_all( '%<option.*?>(.*?)</option>%', $match[ 1 ], $opts );
            foreach ( $opts[ 1 ] as $opt ) {
                $options[ 'Color' ][] = trim( $opt );
            }
        }

        return $options ?? [];
    }

    public function getVideos(): array
    {
        $videos = [];
        preg_match_all( '%<(ifriend|iframe).*?src="(.*?)"%', $this->body, $match, PREG_SET_ORDER );
        foreach ( $match as $m ) {
            $url_parts = parse_url( $m[ 2 ] );
            $domain = $url_parts[ 'host' ];
            if ( str_contains( $domain, '.' ) ) {
                $domain = explode( '.', $domain );
                $domain = $domain[ count( $domain ) - 2 ];
            }
            $name = $this->getProduct();

            $videos[] = [ 'name' => $name,
                'video' => $m[ 2 ],
                'provider' => $domain ];
        }

        return $videos;
    }
}
