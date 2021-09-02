<?php

namespace App\Feeds\Vendors\IVR;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Helpers\StringHelper;
use App\Feeds\Utils\Data;
use Illuminate\Support\Facades\Storage;

class Parser extends HtmlParser
{
    private array $json = [];
    private array $categories = [];
    private ?float $list_price = null;
    private const CATEGORY_URL = 'https://www.ivrose.com/productCategory/anon/get-product-categorys-by-product-id?productId=';
    private const IMAGE_URL = 'https://image.geeko.ltd/original/';

    public function parseContent( $data, $params = [] ): array
    {
        preg_match( '%var productVO = ({.*});%ui', $data->getData(), $matches );
        if ( empty( $matches[ 1 ] ) ) {
            return [];
        }

        try {
            $this->json = json_decode( $matches[ 1 ], true, 512, JSON_THROW_ON_ERROR );
        } catch ( \JsonException $e ) {
            die( "\nJson decode error in: " . $params[ 'url' ] . "\n" );
        }

        // TODO: remove if not need
        if ( empty( $this->json[ 'products' ] ) ) {
            die( "\nJson products is empty in: " . $params[ 'url' ] . "\n" );
        }

        $data = new Data( preg_replace( '%<script.*?</script>%uis', '', $data->getData() ) );

        return parent::parseContent( $data, $params );
    }

    public function beforeParse(): void
    {
        $data = $this->getVendor()->getDownloader()->get( self::CATEGORY_URL . $this->json[ 'products' ][ 0 ][ 'id' ] );
        $json = json_decode( $data->getData(), true, 512, JSON_THROW_ON_ERROR );
        $json = $json[ 'result' ];
        foreach ( $json as $cat ) {
            $this->categories[] = $cat[ 'name' ];
        }

        $this->list_price = StringHelper::getMoney( $this->json[ 'products' ][ 0 ][ 'price' ][ 'amount' ] );
    }

    public function isGroup(): bool
    {
        return !empty( $this->json[ 'products' ][ 0 ][ 'variants' ] );
    }

    public function getMpn(): string
    {
        return $this->json[ 'products' ][ 0 ][ 'parentSku' ] ?: '';
    }

    public function getProduct(): string
    {
        return $this->json[ 'products' ][ 0 ][ 'name' ] ?: '';
    }

    public function getCostToUs(): float
    {
        if ( empty( $this->json[ 'products' ][ 0 ][ 'promotion' ][ 'promotionPrice' ][ 'amount' ] ) ) {
            $this->list_price = null;
            return StringHelper::getFloat( $this->json[ 'products' ][ 0 ][ 'price' ][ 'amount' ] );
        }

        return StringHelper::getFloat( $this->json[ 'products' ][ 0 ][ 'promotion' ][ 'promotionPrice' ][ 'amount' ] );
    }

    public function getListPrice(): ?float
    {
        return $this->list_price ?? null;
    }

    public function getImages(): array
    {
        $images = [];

        foreach ( $this->json[ 'products' ] as $product_data ) {
            if ( !empty( $product_data[ 'mainImage' ] ) ) {
                $images[] = self::IMAGE_URL . $product_data[ 'mainImage' ];
            }
            if ( !empty( $product_data[ 'pcExtraImages' ] ) ) {
                foreach ( $product_data[ 'pcExtraImages' ] as $image ) {
                    $images[] = self::IMAGE_URL . $image;
                }
            }
        }

        return array_values( array_unique( $images ) );
    }

    public function getAvail(): ?int
    {
        return StringHelper::getFloat( $this->json[ 'products' ][ 0 ][ 'stocks' ] );
    }

    public function getCategories(): array
    {
        return $this->categories;
    }

    public function getDescription(): string
    {
        return preg_replace( '%<table.*?</table>\s*%uis', '', $this->json[ 'products' ][ 0 ][ 'description2' ] );
    }

    public function getAttributes(): ?array
    {
        $attrs = [];

        preg_match_all( '%<tr(.*?)</tr>%uis', $this->json[ 'products' ][ 0 ][ 'description2' ], $rows );
        if ( empty( $rows[ 1 ] ) ) {
            return null;
        }

        foreach ( $rows[ 1 ] as $row ) {
            preg_match( '%<td.*?>(.*?)</td>\s*<td.*?>(.*?)</td>%uis', $row, $cols );
            $attrs[ trim( $cols[ 1 ], ': ' ) ] = trim( $cols[ 2 ] );
        }

        return $attrs ?? null;
    }

    public function getBrand(): ?string
    {
        return $this->json[ 'products' ][ 0 ][ 'brand' ] ?? null;
    }

    public function getChildProducts( FeedItem $parent_fi ): array
    {
        $child = [];

        foreach ( $this->json[ 'products' ] as $product_data ) {

            $images = [];
            if ( !empty( $product_data[ 'mainImage' ] ) ) {
                $images[] = self::IMAGE_URL . $product_data[ 'mainImage' ];
            }
            if ( !empty( $product_data[ 'pcExtraImages' ] ) ) {
                foreach ( $product_data[ 'pcExtraImages' ] as $image ) {
                    $images[] = self::IMAGE_URL . $image;
                }
            }
            $images = array_values( array_unique( $images ) );

            foreach ( $product_data[ 'variants' ] as $variant ) {

                $fi = clone $parent_fi;

                $product = [];
                if ( !empty( $variant[ 'color' ] ) ) {
                    $product[] = 'Color: ' . $variant[ 'color' ];
                }
                if ( !empty( $variant[ 'size' ] ) ) {
                    $product[] = 'Size: ' . $variant[ 'size' ];
                }
                $product = implode( ', ', $product );

                $fi->setMpn( $variant[ 'sku' ] );
                $fi->setProduct( $product );

                if ( !empty( $variant[ 'promotionPrice' ][ 'amount' ] ) ) {
                    $fi->setCostToUs( StringHelper::getMoney( $variant[ 'promotionPrice' ][ 'amount' ] ) );
                    $fi->setListPrice( StringHelper::getMoney( $variant[ 'price' ][ 'amount' ] ) );
                }
                else {
                    $fi->setCostToUs( StringHelper::getMoney( $variant[ 'price' ][ 'amount' ] ) );
                }

                if ( !empty( $variant[ 'weight' ] ) ) {
                    $fi->setWeight( StringHelper::getFloat( $variant[ 'weight' ] ) );
                }

                $fi->setRAvail( StringHelper::getFloat( $variant[ 'stocks' ] ) ?: 0 );

                $fi->setImages( $images );

                if ( !empty( $variant[ 'description' ] ) ) {
                    $fi->setFulldescr( ucfirst( $variant[ 'description' ] ) . "\n" . $this->getDescription() );
                }

                $attrs = $this->getAttributes();
                if ( !empty( $variant[ 'descriptions' ][ 0 ] ) ) {
                    foreach ( $variant[ 'descriptions' ][ 0 ] as $key => $value ) {
                        if ( $key === 'unit' || empty( $value ) ) {
                            continue;
                        }
                        if ( $key === 'length' ) {
                            $fi->setDimZ( StringHelper::getFloat( $value ) );
                        }
                        else {
                            $attrs[ trim( $key, ': ' ) ] = $value;
                        }
                    }
                }

                $fi->setAttributes( $attrs );

                $child[] = $fi;
            }
        }

        return $child;
    }
}
