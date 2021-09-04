<?php

namespace App\Feeds\Vendors\AFG;

use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private string $page = '';
    private array $dims = [];
    private array $s_dims = [];

    public function beforeParse(): void
    {
        $this->page = html_entity_decode( $this->node->html() );
        $this->page = str_replace( ' ', ' ', $this->page );

        // Dimensions
        if ( preg_match( '%Dimensions:\s*</span>[<br/>\s]*([\d\s\-./]+)″L[×x\s]*([\d\s\-./]+)″W[×x\s]*([\d\s\-./]+)″H%uis',
            $this->page,
            $match )
        ) {
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

            $this->dims[ 'z' ] = StringHelper::getFloat( $match[ 1 ] );
            $this->dims[ 'x' ] = StringHelper::getFloat( $match[ 2 ] );
            $this->dims[ 'y' ] = StringHelper::getFloat( $match[ 3 ] );
        }

        // Shipping Dimensions
        if ( preg_match( '%Packaging Size:\s*</span>[<br/>\s]*([\d\s\-./]+)″L[×x\s]*([\d\s\-./]+)″W[×x\s]*([\d\s\-./]+)″H%uis',
            $this->page,
            $match )
        ) {
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

            $this->s_dims[ 'z' ] = StringHelper::getFloat( $match[ 1 ] );
            $this->s_dims[ 'x' ] = StringHelper::getFloat( $match[ 2 ] );
            $this->s_dims[ 'y' ] = StringHelper::getFloat( $match[ 3 ] );
        }
    }

    public function getMpn(): string
    {
        $mpn = '';
        if ( preg_match( '%Model Numb.*?<br[/\s]*>(.*?)</p>%uis', $this->page, $match ) ) {
            $mpn = str_replace( '&nbsp;', ' ', $match[ 1 ] );
            $mpn = trim( StringHelper::normalizeSpaceInString( strip_tags( $mpn ) ), '  ' );
        }

        return $mpn;
    }

    public function getProduct(): string
    {
        $title = '';
        $url = parse_url( $this->getInternalId() );
        if ( preg_match( '%<li>\s*<a href="' . $url[ 'path' ] . '">(.*?)</a>%ui', $this->page, $match ) ) {
            $title = trim( $match[ 1 ] );
        }

        return $title;
    }

    public function getCostToUs(): float
    {
        return 0;
    }

    public function getImages(): array
    {
        $images = [];
        $this->filter( 'div.content li' )->each( function ( ParserCrawler $c ) use ( &$images ) {
            $image = $c->getAttr( 'li', 'pic' );
            $filename = pathinfo( $image );
            $filename = $filename[ 'basename' ];
            if ( str_contains( $filename, '?' ) ) {
                $new_filename = substr( $filename, 0, strpos( $filename, '?' ) );
                $image = str_replace( $filename, $new_filename, $image );
            }
            if ( !str_contains( $image, 'http' ) ) {
                $image = 'http://www.afgbabyfurniture.com' . $image;
            }
            $images[] = $image;
        } );

        return array_values( array_unique( $images ) );
    }

    public function getAvail(): ?int
    {
        return self::DEFAULT_AVAIL_NUMBER;
    }

    public function getCategories(): array
    {
        $categories = $this->getContent( 'div.address a' );
        array_shift( $categories );
        if ( $categories[ 0 ] === 'Products' ) {
            array_shift( $categories );
        }

        return $categories;
    }

    public function getDescription(): string
    {
        $description = trim( $this->getHtml( 'div[itemprop="description"]' ) );
        if ( empty( $description ) && preg_match( '%product details\s*</span>(.*?)[Key ]*Features\s*[:<]+%uis',
                $this->page,
                $match )
        ) {
            $description = trim( StringHelper::normalizeSpaceInString( $match[ 1 ] ), '  ' );
        }

        return $description;
    }

    public function getShortDescription(): array
    {
        $short_description = [];
        if ( preg_match( '%product details.*?Features.*?(.*?)</ul>%uis',
            $this->page,
            $features ) ) {

            preg_match_all( '%<li.*?>(.*?)</li>%uis', $features[ 1 ], $match );
            foreach ( $match[ 1 ] as $li ) {
                $short_description[] = trim( strip_tags( $li ), '  ' );
            }
        }

        return $short_description;
    }

    public function getWeight(): ?float
    {
        $weight = null;
        if ( preg_match( '%Product Weight:\s*</span>[<br/>\s]*([\d.]+)\s*lbs%uis', $this->page, $match ) ) {
            $weight = $match[ 1 ];
        }

        return $weight;
    }

    public function getShippingWeight(): ?float
    {
        $weight = null;
        if ( preg_match( '%Shipping Weight:\s*</span>[<br/>\s]*([\d.]+)\s*lbs%uis', $this->page, $match ) ) {
            $weight = $match[ 1 ];
        }

        return $weight;
    }

    public function getOptions(): array
    {
        $options = [];
        if ( preg_match( '%Available[in\s]+[Colr]*[s]*[\s:]+(.*?)</p>%uis', $this->page, $match ) ) {
            $match = str_replace( ' or ', ',', $match[ 1 ] );
            $match = explode( ',', strip_tags( $match ) );
            foreach ( $match as $option ) {
                $option = trim( $option, '  ' );
                if ( !empty( $option ) ) {
                    $options[ 'Available Color' ][] = $option;
                }
            }
        }

        return $options;
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
}
