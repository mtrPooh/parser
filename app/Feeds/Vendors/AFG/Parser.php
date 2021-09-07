<?php

namespace App\Feeds\Vendors\AFG;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private string $page = '';
    private array $childs = [];
    private array $dims = [];
    private array $s_dims = [];

    public function beforeParse(): void
    {
        $this->page = html_entity_decode( $this->node->html() );
        $this->page = str_replace( ' ', ' ', $this->page );

        // Child products
        $mpn = $this->getMpn();
        preg_match_all( '%(\w+)\s*\((.*?)\)%u', $mpn, $match, PREG_SET_ORDER );

        if ( !empty( $match ) ) {
            $i = 0;
            foreach ( $match as $value ) {
                $this->childs[ $i ][ 'mpn' ] = $value[ 1 ];
                $this->childs[ $i ][ 'color' ] = ucfirst( $value[ 2 ] );
                if ( $value[ 1 ][ strlen( $value[ 1 ] ) - 1 ] !== $value[ 2 ][ 0 ] ) {
                    $this->childs[ $i ][ 'mpn' ] .= $value[ 2 ][ 0 ];
                }
                $i++;
            }
        }
        if ( empty( $this->childs ) && preg_match( '%Available[in\s]+[Colr]*[s]*[\s:]+(.*?)</p>%uis', $this->page, $match ) ) {
            $match = str_replace( [ ' or ', ' and ' ], ',', $match[ 1 ] );
            $match = explode( ',', strip_tags( $match ) );
            $i = 0;
            foreach ( $match as $child ) {
                $child = trim( $child, '  ' );
                if ( !empty( $child ) ) {
                    $this->childs[ $i ][ 'mpn' ] = $mpn . strtoupper( $child[ 0 ] );
                    $this->childs[ $i ][ 'color' ] = $child;
                    $i++;
                }
            }
        }

        // Dimensions
        if ( preg_match( '%Dimensions:\s*</span>[<br/>\s]*([\d\s\-./]+)″L[×x\s]*([\d\s\-./]+)″W[×x\s]*([\d\s\-./]+)″H%ui',
            $this->page,
            $match )
        ) {
            $this->dims[ 'x' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );
            $this->dims[ 'z' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 2 ] ) );
            $this->dims[ 'y' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 3 ] ) );
        }

        // Shipping Dimensions
        if ( preg_match( '%Packaging Size:\s*</span>[<br/>\s]*([\d\s\-./]+)″L[×x\s]*([\d\s\-./]+)″W[×x\s]*([\d\s\-./]+)″H%ui',
            $this->page,
            $match )
        ) {
            $this->s_dims[ 'x' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 1 ] ) );
            $this->s_dims[ 'z' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 2 ] ) );
            $this->s_dims[ 'y' ] = StringHelper::getFloat( str_replace( '-', ' ', $match[ 3 ] ) );
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

    public function isGroup(): bool
    {
        return count( $this->childs ) > 1;
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
        if ( empty( $categories ) ) {
            return [];
        }

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
        if ( preg_match( '%Product Weight:\s*</span>[<br/>\s]*([\d.]+)\s*lbs%ui', $this->page, $match ) ) {
            $weight = $match[ 1 ];
        }

        return $weight;
    }

    public function getShippingWeight(): ?float
    {
        $weight = null;
        if ( preg_match( '%Shipping Weight:\s*</span>[<br/>\s]*([\d.]+)\s*lbs%ui', $this->page, $match ) ) {
            $weight = $match[ 1 ];
        }

        return $weight;
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

    public function getChildProducts( FeedItem $parent_fi ): array
    {
        $child = [];
        $images = $this->getImages();

        foreach ( $this->childs as $child_data ) {

            $fi = clone $parent_fi;

            $child_images = [];
            foreach ( $images as $image ) {
                if ( str_contains( $image, $child_data[ 'color' ] ) ) {
                    $child_images[] = $image;
                }
            }

            $fi->setMpn( $child_data[ 'mpn' ] );
            $fi->setProduct( 'Color: ' . $child_data[ 'color' ] );
            $fi->setImages( $child_images );
            $fi->setRAvail( self::DEFAULT_AVAIL_NUMBER );
            $fi->setCostToUs( 0 );

            $child[] = $fi;
        }

        return $child;
    }
}
