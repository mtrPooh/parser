<?php

namespace App\Feeds\Feed;

use App\Feeds\Interfaces\ParserInterface;
use App\Feeds\Parser\HtmlParser;
use App\Helpers\FeedHelper;
use App\Helpers\StringHelper;
use DateTime;
use DateTimeZone;
use Exception;
use Throwable;

class FeedItem
{
    /**
     * @var string Уникальный код товара
     */
    public string $productcode = '';
    /**
     * @var string|null Идентификатор товара в Amazon
     */
    public ?string $ASIN = null;
    /**
     * @var string Название товара
     */
    public string $product = '';
    /**
     * @var float|null Цена товара с учетом скидки
     */
    public ?float $cost_to_us = null;
    /**
     * @var float|null Оригинальная цена товара
     */
    public ?float $list_price = null;
    /**
     * @var string|null Ключевые особенности товара, известные как "features" или "bullets"
     */
    public ?string $descr = '';
    /**
     * @var string|null Описание товара
     */
    public ?string $fulldescr = null;
    /**
     * @var string|null Бренд товара
     */
    public ?string $brand_name = null;
    /**
     * @var bool Присутствует ли бренд товара в названии товара
     */
    public bool $brand_normalized = false;
    /**
     * @var string Можно ли продавать товар "Y/N"
     */
    public string $forsale = 'Y';
    /**
     * @var string|null Дата поступления товара на склад
     */
    public ?string $eta_date_mm_dd_yyyy = null;
    /**
     * @var string|null Штрихкод товара
     */
    public ?string $upc = null;
    // категория
    public array $supplier_categories = [];
    /**
     * @var string Ссылка на страницу товара
     */
    public string $supplier_internal_id = '';
    /**
     * @var string Хэш сумма товара
     */
    public string $hash_product = '';
    /**
     * @var array Ссылки на изображения товара
     */
    public array $images = [];
    /**
     * @var array Альтернативные названия изображений товара для атрибута "alt"
     */
    public array $alt_names = [];
    /**
     * @var float|null Размеры товара (длина, высота, ширина)
     */
    public ?float $dim_x = null;
    public ?float $dim_y = null;
    public ?float $dim_z = null;
    /**
     * @var float|null Вес товара для доставки (брутто)
     */
    public ?float $shipping_weight = null;
    /**
     * @var float|null Размеры товара для доставки
     */
    public ?float $shipping_dim_x = null;
    public ?float $shipping_dim_y = null;
    public ?float $shipping_dim_z = null;
    /**
     * @var float|null Вес товара (нетто)
     */
    public ?float $weight = null;
    /**
     * @var int Минимальное количество единиц для покупки товара
     */
    public int $min_amount = 1;
    /**
     * @var string|null Продавать товар упаковкой или поштучно "Y/N"
     */
    public ?string $mult_order_quantity = null;
    /**
     * @var bool Является ли товар групповым
     */
    public bool $is_group = false;
    /**
     * @var FeedItem[] Дочерние товары
     */
    public array $child_products = [];
    /**
     * @var string|null Общая часть названия для групповых товаров
     */
    public ?string $group_mask = null;
    /**
     * @var float|null Минимальная цена продажи товара
     */
    public ?float $new_map_price = null;
    /**
     * @var int|null Количество единиц на складе
     */
    public ?int $r_avail = null;
    /**
     * @var string|null Уникальный идентификатор товара "sku"
     */
    public ?string $mpn = null;
    /**
     * @var string|null Сообщение о требуемом времени для обработки заказа
     */
    public ?string $lead_time_message = null;
    /**
     * @var array|null Характеристики товара
     */
    public ?array $attributes = null;
    /**
     * @var array Файлы к товару: инструкции и пр.
     */
    public array $product_files = [];
    /**
     * @var array Опции товара: размер, цвет и пр.
     */
    public array $options = [];
    /**
     * @var array Видео товара
     */
    public array $videos = [];

    /**
     * FeedItem constructor.
     * @param ParserInterface|null $parser
     */
    public function __construct( ParserInterface $parser = null )
    {
        if ( $parser ) {
            $parser->beforeParse();
            try {
                $this->setItemInfo( $parser );

                if ( $parser->isGroup() ) {
                    $children = $parser->getChildProducts( $this );
                    $children = array_reduce( $children, function ( $c, FeedItem $child ) use ( $parser ) {
                            if ( !isset( $c[ $child->getMpn() ] ) ) {
                                $c[ $child->getMpn() ] = $child;

                                if ( is_null( $child->getGroupMask() ) ) {
                                    $child->setGroupMask( $this->getProduct() );
                                }

                                similar_text( $child->getGroupMask(), $child->getProduct(), $percent );
                                if ( $percent > 50 ) {
                                    $child->setGroupMask( '' );
                                }

                                $child->setMultOrderQuantity( $child->getMinAmount() > 1 ? 'Y' : 'N' );
                                $child->setProductCode( strtoupper( $parser->getVendor()->getPrefix() . $child->getMpn() ) );

                                if ( empty( $child->getSupplierInternalId() ) ) {
                                    $child->setSupplierInternalId( $parser->getInternalId() );
                                }
                                $child->setHashProduct( $parser->getVendor()->getForce() );
                            }

                            return $c;
                        } ) ?? [];

                    if ( count( $children ) > 1 ) {
                        $this->setMpn( '' );
                        $this->setProductCode( '' );
                        $this->setIsGroup( true );
                        $this->setListPrice( null );
                        $this->setCostToUs( 0 );
                        $this->setNewMapPrice();
                        $this->setRAvail( 0 );
                        $this->setForsale( 'Y' );
                        $this->setImages( [] );

                        $this->setChildProducts( array_values( $children ) );
                    }
                    else {
                        $child = array_shift( $children );
                        $this->setItemInfo( item: $child );

                        $this->setIsGroup( false );
                        $this->setChildProducts( [] );
                        $this->setRAvail( $child?->getRAvail() );
                    }
                }
                else {
                    $this->setIsGroup( false );
                    $this->setRAvail( $parser->getAvail() );
                    $this->setForsale( $parser->getForsale() );
                    $this->setChildProducts( [] );
                }

                $this->setMultOrderQuantity( $parser->getMinAmount() > 1 ? 'Y' : 'N' );
                $this->setHashProduct( $parser->getVendor()->getForce() );

                $parser->afterParse( $this );
            } catch ( Throwable $e ) {
                $message = '  ERROR: failed parse product' . PHP_EOL;
                $message .= 'message: ' . $e->getMessage() . PHP_EOL;
                $message .= '     in: ' . $e->getFile() . '(' . $e->getLine() . ')' . PHP_EOL;

                if ( $parser instanceof HtmlParser ) {
                    $message .= '    uri: ' . $parser->getUri() . PHP_EOL;
                }

                $stack = $e->getTraceAsString();

                // reduce stack to Parser class errors
                if ( preg_match( "/.*\\\\Parser->.*?\(\)/s", $stack, $matches ) ) {
                    $stack = array_map( static fn( $elem ) => trim( $elem ), explode( "\n", $matches[ 0 ] ) );

                    foreach ( $stack as $i => $line ) {
                        $prefix = ( $i === 0 ) ? '  stack: ' : '         ';
                        $message .= $prefix . $line . PHP_EOL;
                    }

                    $message .= PHP_EOL;
                }
                echo $message;
            }
        }
    }

    public function setItemInfo( ParserInterface $parser = null, FeedItem $item = null ): void
    {
        if ( $parser || $item ) {
            $this->setMpn( $parser?->getMpn() ?? $item?->getMpn() );
            $this->setASIN( $parser?->getASIN() ?? $item?->getASIN() );
            $this->setProductCode( $parser?->getProductCode() ?? $item?->getProductcode() );

            if ( $parser ) {
                $this->setProduct( $parser->getProduct() );
            }
            else {
                similar_text( $item->getGroupMask(), $item->getProduct(), $percent );
                if ( $percent > 50 ) {
                    $this->setProduct( $item->getProduct() );
                }
                else {
                    $this->setProduct( "{$item->getGroupMask()} {$item->getProduct()}" );
                }
            }

            $this->setFulldescr( $parser?->getDescription() ?? $item?->getFulldescr() );
            $this->setShortdescr( $parser?->getShortDescription() ?? [] );
            $this->setBrandName( $parser?->getBrand() ?? $item?->getBrandName() );
            $this->setListPrice( $parser?->getListPrice() ?? $item?->getListPrice() );
            $this->setCostToUs( $parser?->getCostToUs() ?? $item?->getCostToUs() );
            $this->setNewMapPrice( $parser?->getMinimumPrice() ?? $item?->getNewMapPrice() );
            $this->setUpc( $parser?->getUpc() ?? $item?->getUpc() );
            $this->setImages( $parser?->getImages() ?? $item?->getImages() );
            $this->setMinAmount( ( $parser?->getMinAmount() ?? $item?->getMinAmount() ) ?? $this->getMinAmount() );
            $this->setMultOrderQuantity( ( $parser?->getMultOrderQuantity() ?? $item?->getMultOrderQuantity() ) ?? $this->getMultOrderQuantity() );
            $this->setCategories( $parser?->getCategories() ?? $item?->getCategories() );
            $this->setSupplierInternalId( $parser?->getInternalId() ?? $item?->getSupplierInternalId() );
            $this->setBrandNormalized( $parser?->getBrandNormalized() ?? $item?->getBrandNormalized() );
            $this->setWeight( $parser?->getWeight() ?? $item?->getWeight() );
            $this->setShippingWeight( $parser?->getShippingWeight() ?? $item?->getShippingWeight() );
            $this->setDimX( $parser?->getDimX() ?? $item?->getDimX() );
            $this->setDimY( $parser?->getDimY() ?? $item?->getDimY() );
            $this->setDimZ( $parser?->getDimZ() ?? $item?->getDimZ() );
            $this->setShippingDimX( $parser?->getShippingDimX() ?? $item?->getShippingDimX() );
            $this->setShippingDimY( $parser?->getShippingDimY() ?? $item?->getShippingDimY() );
            $this->setShippingDimZ( $parser?->getShippingDimZ() ?? $item?->getShippingDimZ() );
            $this->setEtaDateMmDdYyyy( $parser?->getEtaDate() ?? $item?->getEtaDateMmDdYyyy() );
            $this->setLeadTimeMessage( $parser?->getLeadTimeMessage() ?? $item?->getLeadTimeMessage() );
            $this->setAttributes( $parser?->getAttributes() ?? $item?->getAttributes() );
            $this->setOptions( $parser?->getOptions() ?? $item?->getOptions() );
            $this->setVideos( $parser?->getVideos() ?? $item?->getVideos() );
            $this->setProductFiles( $parser?->getProductFiles() ?? $item?->getProductFiles() );
        }
    }

    /**
     * @param string $productcode Устанавливает код товара
     */
    public function setProductCode( string $productcode ): void
    {
        $this->productcode = (string)mb_strtoupper( str_replace( ' ', '-', StringHelper::removeSpaces( StringHelper::trim( $productcode ) ) ) );
    }

    /**
     * @return string Возвращает код товара
     */
    public function getProductcode(): string
    {
        return $this->productcode;
    }

    /**
     * @param string|null $ASIN Устанавливает идентификатор товара в Amazon
     */
    public function setASIN( string $ASIN = null ): void
    {
        $this->ASIN = $ASIN;
    }

    /**
     * @return string|null Возвращает идентификатор товара в Amazon
     */
    public function getASIN(): ?string
    {
        return $this->ASIN;
    }

    /**
     * @param string $product Устанавливает название товара
     */
    public function setProduct( string $product ): void
    {
        if ( $this->getMpn() && str_contains( strtolower( $product ), strtolower( $this->getMpn() ) ) ) {
            $product = preg_replace( "~(\s+)?(-|,|)?(\s+)?{$this->getMpn()}(\s+)?(-|,|)?(\s+)?~i", ' ', $product );
        }
        $product = StringHelper::ucWords( mb_strtolower( StringHelper::trim( FeedHelper::cleaning( html_entity_decode( $product ), [], true ), '\s\.' ) ) );
        $this->product = $product;
    }

    /**
     * @return string Возвращает название товара
     */
    public function getProduct(): string
    {
        return $this->product;
    }

    /**
     * @param float $cost_to_us Устанавливает стоимость товара со скидкой
     */
    public function setCostToUs( float $cost_to_us ): void
    {
        $this->cost_to_us = round( StringHelper::trim( $cost_to_us ), 2 );
    }

    /**
     * @return float Возвращает стоимость товара со скидкой
     */
    public function getCostToUs(): float
    {
        return (float)$this->cost_to_us;
    }

    /**
     * @param null|float $list_price Устанавливает оригинальную стоимость товара
     */
    public function setListPrice( ?float $list_price ): void
    {
        $this->list_price = null;
        if ( $list_price ) {
            $this->list_price = round( $list_price, 2 );
        }
    }

    /**
     * @return null|float Возвращает оригинальную стоимость товара
     */
    public function getListPrice(): ?float
    {
        return $this->list_price;
    }

    /**
     * @param array $descr Устанавливает ключевые особенности товара
     */
    public function setShortdescr( array $descr = [] ): void
    {
        $descr = FeedHelper::cleanShortDescription( $descr );
        if ( $descr ) {
            $this->descr = '<ul><li>' . html_entity_decode( implode( '</li><li>', $descr ) ) . '</li></ul>';
        }
    }

    /**
     * @return null|string Возвращает ключевые особенности товара
     */
    public function getShortdescr(): ?string
    {
        return $this->descr;
    }

    /**
     * @param string $fulldescr Устанавливает описание товара
     */
    public function setFulldescr( string $fulldescr ): void
    {
        if ( StringHelper::isNotEmpty( $fulldescr ) ) {
            $fulldescr = FeedHelper::cleanProductDescription( $fulldescr ) ?: $this->getProduct();
        }
        $this->fulldescr = $fulldescr;
    }

    /**
     * @return string Возвращает описание товара
     */
    public function getFulldescr(): string
    {
        return $this->fulldescr;
    }

    /**
     * @param string|null $brand_name Устанавливает бренд товара
     */
    public function setBrandName( ?string $brand_name ): void
    {
        $this->brand_name = $brand_name ? StringHelper::ucWords( mb_strtolower( StringHelper::trim( $brand_name ) ) ) : $brand_name;
    }

    /**
     * @return string|null Возвращает бренд товара
     */
    public function getBrandName(): ?string
    {
        return $this->brand_name;
    }

    /**
     * @param bool $brand_normalized
     */
    public function setBrandNormalized( bool $brand_normalized ): void
    {
        $this->brand_normalized = $brand_normalized;
    }

    /**
     * @return bool
     */
    public function getBrandNormalized(): bool
    {
        return $this->brand_normalized;
    }

    /**
     * @param string $forsale Устанавливает можно ли продавать товар
     */
    public function setForsale( string $forsale ): void
    {
        $this->forsale = $forsale;
    }

    /**
     * @return string Возвращает можно ли продавать товар
     */
    public function getForsale(): string
    {
        return $this->forsale;
    }

    /**
     * @param DateTime|null $eta Устанавливает дату поступления товара на склад
     */
    public function setEtaDateMmDdYyyy( DateTime $eta = null ): void
    {
        if ( $eta ) {
            $this->eta_date_mm_dd_yyyy = $eta->format( 'm/d/Y' );
        }
    }

    /**
     * @return DateTime|null Возвращает дату поступления товара на склад
     */
    public function getEtaDateMmDdYyyy(): ?DateTime
    {
        $date = null;
        if ( $this->eta_date_mm_dd_yyyy ) {
            $date = DateTime::createFromFormat( 'm/d/Y', $this->eta_date_mm_dd_yyyy, new DateTimeZone( 'EST' ) );
        }
        return $date ?: null;
    }

    /**
     * @param string|null $upc Устанавливает штрихкод товара
     */
    public function setUpc( ?string $upc ): void
    {
        if ( $upc !== null ) {
            $this->upc = StringHelper::calculateUPC( StringHelper::trim( $upc ) );
        }
    }

    /**
     * @return string|null Возвращает штрихкод товара
     */
    public function getUpc(): ?string
    {
        return $this->upc;
    }

    /**
     * @param string $supplier_internal_id Устанавливает ссылку на страницу товара
     */
    public function setSupplierInternalId( string $supplier_internal_id ): void
    {
        $this->supplier_internal_id = $supplier_internal_id;
    }

    /**
     * @return string Возвращает ссылку на страницу товара
     */
    public function getSupplierInternalId(): string
    {
        return $this->supplier_internal_id;
    }

    /**
     * @param array $images Устанавливает ссылки на изображения товара
     */
    public function setImages( array $images ): void
    {
        $this->images = array_values( array_filter( array_unique( $images ) ) );
    }

    /**
     * @return array Возвращает ссылки на изображения товара
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * @param array $alt_names Устанавливает альтернативные названия изображений
     */
    public function setAltNames( array $alt_names ): void
    {
        $this->alt_names = $alt_names;
    }

    /**
     * @param array Возвращает альтернативные названия изображений
     */
    public function getAltNames(): array
    {
        return $this->alt_names;
    }

    /**
     * @param float|null $dim_x Устанавливает размер товара по "X"
     */
    public function setDimX( float $dim_x = null ): void
    {
        $this->dim_x = StringHelper::normalizeFloat( $dim_x );
    }

    /**
     * @param float|null Возвращает размер товара по "X"
     */
    public function getDimX(): ?float
    {
        return $this->dim_x;
    }

    /**
     * @param float|null $dim_y Устанавливает размер товара по "Y"
     */
    public function setDimY( float $dim_y = null ): void
    {
        $this->dim_y = StringHelper::normalizeFloat( $dim_y );
    }

    /**
     * @param float|null Возвращает размер товара по "Y"
     */
    public function getDimY(): ?float
    {
        return $this->dim_y;
    }

    /**
     * @param float|null $dim_z Устанавливает размер товара по "Z"
     */
    public function setDimZ( float $dim_z = null ): void
    {
        $this->dim_z = StringHelper::normalizeFloat( $dim_z );
    }

    /**
     * @param float|null Возвращает размер товара по "Z"
     */
    public function getDimZ(): ?float
    {
        return $this->dim_z;
    }

    /**
     * @param float|null $weight Устанавливает вес товара для доставки
     */
    public function setShippingWeight( float $weight = null ): void
    {
        $this->shipping_weight = StringHelper::normalizeFloat( $weight );
    }

    /**
     * @return float|null Возвращает вес товара для доставки
     */
    public function getShippingWeight(): ?float
    {
        return $this->shipping_weight;
    }

    /**
     * @param float|null $dim_x Устанавливает размер товара для доставки по "X"
     */
    public function setShippingDimX( float $dim_x = null ): void
    {
        $this->shipping_dim_x = StringHelper::normalizeFloat( $dim_x );
    }

    /**
     * @param float|null Возвращает размер товара по "X"
     */
    public function getShippingDimX(): ?float
    {
        return $this->shipping_dim_x;
    }

    /**
     * @param float|null $dim_y Устанавливает размер товара для доставки по "Y"
     */
    public function setShippingDimY( float $dim_y = null ): void
    {
        $this->shipping_dim_y = StringHelper::normalizeFloat( $dim_y );
    }

    /**
     * @param float|null Возвращает размер товара по "Y"
     */
    public function getShippingDimY(): ?float
    {
        return $this->shipping_dim_y;
    }

    /**
     * @param float|null $dim_z Устанавливает размер товара для доставки по "Z"
     */
    public function setShippingDimZ( float $dim_z = null ): void
    {
        $this->shipping_dim_z = StringHelper::normalizeFloat( $dim_z );
    }

    /**
     * @param float|null Возвращает размер товара по "Z"
     */
    public function getShippingDimZ(): ?float
    {
        return $this->shipping_dim_z;
    }

    /**
     * @param float|null $weight Устанавливает вес товара
     */
    public function setWeight( float $weight = null ): void
    {
        $this->weight = StringHelper::normalizeFloat( $weight );
    }

    /**
     * @return float|null Возвращает вес товара
     */
    public function getWeight(): ?float
    {
        return $this->weight;
    }

    /**
     * @param int $min_amount Устанавливает минимальное количество покупки товара
     */
    public function setMinAmount( int $min_amount ): void
    {
        $this->min_amount = $min_amount;
    }

    /**
     * @return int Возвращает минимальное количество покупки товара
     */
    public function getMinAmount(): int
    {
        return $this->min_amount;
    }

    /**
     * @param string $mult_order_quantity Устанавливает продавать товар упаковкой или поштучно
     */
    public function setMultOrderQuantity( string $mult_order_quantity ): void
    {
        $this->mult_order_quantity = $mult_order_quantity;
    }

    /**
     * @return string|null Возвращает продавать товар упаковкой или поштучно
     */
    public function getMultOrderQuantity(): ?string
    {
        return $this->mult_order_quantity;
    }

    /**
     * @param bool $is_group Устанавливает является ли товар групповым
     */
    public function setIsGroup( bool $is_group ): void
    {
        $this->is_group = $is_group;
    }

    /**
     * @return bool Возвращает является ли товар групповым
     */
    public function isGroup(): bool
    {
        return $this->is_group;
    }

    /**
     * @param array $child_products Устанавливает дочерние товары
     */
    public function setChildProducts( array $child_products ): void
    {
        $this->child_products = $child_products;
    }

    /**
     * @return array Возвращает дочерние товары
     */
    public function getChildProducts(): array
    {
        return $this->child_products;
    }

    /**
     * @param string|null $group_mask Устанавливает общую часть названия для групповых товаров
     */
    public function setGroupMask( ?string $group_mask ): void
    {
        $this->group_mask = $group_mask;
    }

    /**
     * @return string|null Возвращает общую часть названия для групповых товаров
     */
    public function getGroupMask(): ?string
    {
        return $this->group_mask;
    }

    /**
     * @param float|null $new_map_price Устанавливает минимальную цену продажи товара
     */
    public function setNewMapPrice( float $new_map_price = null ): void
    {
        $this->new_map_price = $new_map_price ? round( $new_map_price, 2 ) : $new_map_price;
    }

    /**
     * @return float|null Возвращает минимальную цену продажи товара
     */
    public function getNewMapPrice(): ?float
    {
        return $this->new_map_price;
    }

    /**
     * @param int|null $r_avail Устанавливает количество единиц на складе
     */
    public function setRAvail( ?int $r_avail = null ): void
    {
        $this->r_avail = $r_avail;
    }

    /**
     * @return int|null Возвращает количество единиц на складе
     */
    public function getRAvail(): ?int
    {
        return $this->r_avail;
    }

    /**
     * @param string $mpn Устанавливает уникальный идентификатор товара
     */
    public function setMpn( string $mpn ): void
    {
        $this->mpn = $mpn;
    }

    /**
     * @return string Возвращает уникальный идентификатор товара
     */
    public function getMpn(): string
    {
        return $this->mpn ?? '';
    }

    /**
     * @param string|null $lead_time_message Устанавливает сообщение о требуемом времени для обработки заказа
     */
    public function setLeadTimeMessage( ?string $lead_time_message ): void
    {
        $this->lead_time_message = $lead_time_message;
    }

    /**
     * @return string|null Возвращает сообщение о требуемом времени для обработки заказа
     */
    public function getLeadTimeMessage(): ?string
    {
        return $this->lead_time_message;
    }

    /**
     * @param array|null $get_attributes Устанавливает характеристики товара
     */
    public function setAttributes( ?array $get_attributes ): void
    {
        $get_attributes = $get_attributes ? array_map( static fn( string $attribute ) => html_entity_decode( $attribute ), $get_attributes ) : $get_attributes;
        if ( $get_attributes ) {
            $attributes = [];
            foreach ( $get_attributes as $key => $value ) {
                $attributes[ StringHelper::mb_ucfirst( strtolower( str_replace( '_', ' ', $key ) ) ) ] = $value;
            }
            $get_attributes = $attributes;
        }

        if ( $get_attributes && !array_key_exists( 'Color', $get_attributes ) ) {
            $product_name = $this->getProduct();
            foreach ( $this->getTableColors() as $color ) {
                if ( str_contains( strtolower( $product_name ), strtolower( $color ) ) ) {
                    $get_attributes[ 'Color' ] = $color;
                }
            }
        }

        $this->attributes = $attributes ?? $get_attributes;
    }

    /**
     * @param array|null Возвращает характеристики товара
     */
    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    /**
     * @param array $product_files Устанавливает файлы к товару
     */
    public function setProductFiles( array $product_files ): void
    {
        $this->product_files = $product_files;
    }

    /**
     * @return array Возвращает файлы к товару
     */
    public function getProductFiles(): array
    {
        return $this->product_files;
    }

    /**
     * @param array $options Устанавливает опции товара
     */
    public function setOptions( array $options ): void
    {
        $this->options = $options;
    }

    /**
     * @return array Возвращает опции товара
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array $videos Устанавливает видео товара
     */
    public function setVideos( array $videos ): void
    {
        $this->videos = $videos;
    }

    /**
     * @return array Возвращает видео товара
     */
    public function getVideos(): array
    {
        return $this->videos;
    }

    /**
     * Устанавливает хэш сумму товара
     * @param bool $force Нужно сбросить хэш товара или нет
     * @throws Exception
     */
    public function setHashProduct( bool $force ): void
    {
        $attrs = $this->propsToArray();
        unset( $attrs[ 'images' ] );
        if ( $force ) {
            $this->hash_product = md5( time() );
        }
        else {
            $this->hash_product = md5( json_encode( $attrs, JSON_THROW_ON_ERROR ) );
        }
    }

    /**
     * @return string Возвращает хэш сумму товара
     */
    public function getHashProduct(): string
    {
        return $this->hash_product;
    }

    /**
     * Преобразует свойства объекта в массив
     *
     * @param array $attrs
     * @return array
     */
    public function propsToArray( array $attrs = [] ): array
    {
        $result = get_object_vars( $this );

        return !$attrs ? $result : array_intersect_key( $result, array_flip( $attrs ) );
    }

    /**
     * @return string Преобразует свойства объекта в json строку
     * @throws Exception
     */
    public function propsToJson(): string
    {
        return json_encode( $this->propsToArray(), JSON_THROW_ON_ERROR );
    }

    /**
     * @param array $categories
     */
    public function setCategories( array $categories ): void
    {
        $this->supplier_categories = array_map( 'mb_strtolower', $categories );
        $this->supplier_categories = array_map( [ StringHelper::class, 'mb_ucfirst' ], $this->supplier_categories );
    }

    /**
     * @return array
     */
    public function getCategories(): array
    {
        return $this->supplier_categories;
    }

    private function getTableColors(): array
    {
        $colors = [
            'Yellow',
            'Pink',
            'Navy',
            'Neon Pink',
            'Grey',
            'Brown',
            'Steel',
            'Green',
            'Forest Green',
            'Khaki',
            'Blue',
            'Royal Blue',
            'Black',
            'White',
            'Gray',
            'Orange',
            'Wine Red',
            'Red',
            'Apricot',
            'Purple',
            'Beige',
            'Blush',
            'Mint',
            'Lime',
        ];
        $variants = [ 'Light', 'Hot', 'Dark', 'Medium' ];

        $table_colors = [];
        foreach ( $colors as $color ) {
            foreach ( $variants as $variant ) {
                $table_colors[] = "$variant $color";
            }
            $table_colors[] = $color;
        }
        return $table_colors;
    }
}
