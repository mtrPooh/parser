<?php

namespace App\Feeds\Vendors\IVR;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private array $json = [];
    private array $categories = [];
    private ?float $list_price = null;
    private const PRODUCT_URL = 'https://www.ivrose.com/v9/product/anon/';
    private const CATEGORY_URL = 'https://www.ivrose.com/productCategory/anon/get-product-categorys-by-product-id?productId=';
    private const IMAGE_URL = 'https://image.geeko.ltd/original/';

    public function beforeParse(): void
    {
        $product_id = pathinfo( $this->getInternalId() );
        $product_id = explode( '.html', $product_id[ 'basename' ] );
        $product_id = $product_id[ 0 ];

        $data = $this->getVendor()->getDownloader()->get( self::PRODUCT_URL . $product_id . '/show' );
        $this->json = json_decode( $data->getData(), true, 512, JSON_THROW_ON_ERROR );
        $this->json = $this->json[ 'result' ][ 'product' ];

        $data = $this->getVendor()->getDownloader()->get( self::CATEGORY_URL . $this->json[ 'id' ] );
        $json = json_decode( $data->getData(), true, 512, JSON_THROW_ON_ERROR );
        $json = $json[ 'result' ];
        foreach ( $json as $cat ) {
            $this->categories[] = $cat[ 'name' ];
        }

        $this->list_price = $this->getMoney( $this->json[ 'usdPrice' ][ 'amount' ] );
    }

    public function isGroup(): bool
    {
        return !empty( $this->json[ 'variants' ] );
    }

    public function getMpn(): string
    {
        return $this->json[ 'parentSku' ] ?: '';
    }

    public function getProduct(): string
    {
        return $this->json[ 'name' ] ?: '';
    }

    public function getCostToUs(): float
    {
        if ( empty( $this->json[ 'promotion' ][ 'promotionPrice' ][ 'amount' ] ) ) {
            $this->list_price = null;
            return $this->getMoney( $this->json[ 'usdPrice' ][ 'amount' ] );
        }

        return $this->getMoney( $this->json[ 'promotion' ][ 'promotionPrice' ][ 'amount' ] );
    }

    public function getListPrice(): ?float
    {
        return $this->list_price ?? null;
    }

    public function getImages(): array
    {
        $images = [];
        if ( !empty( $this->json[ 'mainImage' ] ) ) {
            $images[] = self::IMAGE_URL . $this->json[ 'mainImage' ];
        }
        if ( !empty( $this->json[ 'extraImages' ] ) ) {
            foreach ( $this->json[ 'extraImages' ] as $image ) {
                $images[] = self::IMAGE_URL . $image;
            }
        }

        return array_values( array_unique( $images ) );
    }

    public function getAvail(): ?int
    {
        return $this->json[ 'status' ] === '1' ? self::DEFAULT_AVAIL_NUMBER : 0;
    }

    public function getCategories(): array
    {
        return $this->categories;
    }

    public function getDescription(): string
    {
        return $this->json[ 'description' ];
    }

    public function getAttributes(): ?array
    {
        $attrs = [];

        preg_match_all( '%<tr(.*?)</tr>%uis', $this->json[ 'description2' ], $rows );
        if ( empty( $rows[ 1 ] ) ) {
            return null;
        }

        foreach ( $rows[ 1 ] as $row ) {
            preg_match( '%<td.*?>(.*?)</td>\s*<td.*?>(.*?)</td>%uis', $row, $cols );
            $attrs[ trim( $cols[ 1 ] ) ] = trim( $cols[ 2 ] );
        }

        return $attrs ?? null;
    }

    public function getBrand(): ?string
    {
        return $this->json[ 'brand' ] ?? null;
    }

    public function getChildProducts( FeedItem $parent_fi ): array
    {
        $child = [];

        foreach ( $this->json[ 'variants' ] as $variant ) {

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
            $fi->setRAvail( $variant[ 'status' ] === '1' ? self::DEFAULT_AVAIL_NUMBER : 0 );

            if ( !empty( $variant[ 'image' ] ) ) {
                $fi->setImages( [ self::IMAGE_URL . $variant[ 'image' ] ] );
            }
            if ( !empty( $variant[ 'description' ] ) ) {
                $fi->setFulldescr( ucfirst( $variant[ 'description' ] ) );
            }

            if ( !empty( $variant[ 'descriptionMaps' ][ 0 ] ) ) {
                $attrs = $this->getAttributes();
                foreach ( $variant[ 'descriptionMaps' ][ 0 ] as $key => $value ) {
                    if ( $key === 'unit' ) {
                        continue;
                    }
                    $attrs[ ucfirst( $key ) ] = $value;
                }
            }

            $fi->setAttributes( $attrs );

            $child[] = $fi;
        }

        return $child;
    }
}
