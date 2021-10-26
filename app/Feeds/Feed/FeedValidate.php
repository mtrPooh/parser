<?php

namespace App\Feeds\Feed;

use App\Feeds\Traits\HandleDataTrait;
use App\Helpers\StringHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use JetBrains\PhpStorm\Pure;
use Ms48\LaravelConsoleProgressBar\ConsoleProgressBar;

class FeedValidate
{
    use HandleDataTrait;

    protected FeedItem $current_item;
    private ?string $storefront;
    private string $dx;
    private string $feed_type;
    private array $fails = [];

    private bool $fail = false;

    public function __construct( array $feed_items, array $dx_info, string $feed_type )
    {
        print PHP_EOL . 'Validate feeds' . PHP_EOL;
        $this->storefront = $dx_info[ 'storefront' ];
        $this->dx = rtrim( $dx_info[ 'prefix' ], '-' );
        $this->feed_type = $feed_type;

        $this->validateItems( $feed_items );
        if ( $this->fail ) {
            $this->saveValidateErrors();
            print PHP_EOL . 'Validate fail. Check storage/app/logs/' . $this->dx . '_error.json for more information';
        }
        else {
            $this->removeValidateErrors();
            print PHP_EOL . 'Validate complete';
        }
    }

    private function validateItems( array $feed_items ): void
    {
        $console = new ConsoleProgressBar();
        $count_items = 1;
        foreach ( $feed_items as $feed_item ) {
            if ( $feed_item->isGroup() && count( $feed_item->getChildProducts() ) ) {
                $count_items += count( $feed_item->getChildProducts() );
                foreach ( $feed_item->getChildProducts() as $child_item ) {
                    $this->validateItem( $child_item );
                }
                $console->showProgress( count( $feed_item->getChildProducts() ), $count_items );
            }
            else {
                ++$count_items;
                $console->showProgress( 1, $count_items );
            }
            $this->validateItem( $feed_item );
        }
        $console->showProgress( 0, $count_items - 1 );
    }

    private function setCurrentItem( FeedItem $item ): void
    {
        $this->current_item = $item;
    }

    #[Pure] private function getMpnCurrentProduct(): string
    {
        return $this->current_item->getProductcode() ?: $this->current_item->getHashProduct();
    }

    private function saveValidateErrors(): void
    {
        $error_str = ( new JsonResponse( $this->fails, 200, [], JSON_PRETTY_PRINT ) )->getContent();

        if ( $this->storefront ) {
            $file_name = "logs/{$this->dx}__{$this->storefront}_error.json";
        }
        else {
            $file_name = "logs/{$this->dx}_error.json";
        }

        Storage::put( $file_name, $error_str );
    }

    private function removeValidateErrors(): void
    {
        if ( $this->storefront ) {
            $file_name = "logs/{$this->dx}__{$this->storefront}_error.json";
        }
        else {
            $file_name = "logs/{$this->dx}_error.json";
        }

        if ( Storage::exists( $file_name ) ) {
            Storage::delete( $file_name );
        }
    }

    private function attachFailProduct( string $fail_type, string $message ): void
    {
        $this->fail = true;
        $fail_type = StringHelper::ucWords( str_replace( '_', ' ', $fail_type ) );
        $this->fails[ $fail_type ][ $message ][] = $this->getMpnCurrentProduct();
    }

    private function findPriceInString( string $string ): ?string
    {
        return StringHelper::existsMoney( $string ) ?: null;
    }

    private function validateItem( FeedItem $item ): void
    {
        $this->setCurrentItem( $item );

        $this->validateProductName( $item->getProduct(), $item->isGroup() );

        if ( $this->feed_type === 'product' ) {
            $this->validateCostToUs( $item->getCostToUs(), $item->isGroup() );
            $this->validateListPrice( $item->getListPrice(), $item->isGroup() );
        }

        $this->validateCategories( $item->getCategories() );
        $this->validateShortDesc( $item->getShortdescr() );
        $this->validateDescription( $item->getFulldescr() );
        $this->validateImages( $item->getImages(), $item->isGroup() );
        $this->validateAvail( $item->getRAvail() );
        $this->validateMpn( $item->mpn, $item->isGroup() );
        $this->validateAttributes( $item->attributes );
        $this->validateProductFiles( $item->getProductFiles() );
        $this->validateVideos( $item->getVideos() );
        $this->validateOptions( $item->getOptions() );

        if ( $item->isGroup() ) {
            $this->validateChildProducts( $item->getChildProducts() );
        }
    }

    private function validateChildProducts( array $child_products ): void
    {
        if ( !count( $child_products ) ) {
            $this->attachFailProduct( 'child_products', 'Empty children array' );
        }
    }

    private function validateProductName( string $product_name, bool $group ): void
    {
        if ( $group ) {
            if ( $product_name === 'Dummy' ) {
                $this->attachFailProduct( 'product_name', 'Is "Dummy"' );
            }
        }
        else if ( $product_name === "" || $product_name === 'Dummy' ) {
            $this->attachFailProduct( 'product_name', 'Empty or "Dummy"' );
        }
        elseif ( $currency = $this->findPriceInString( $product_name ) ) {
            $this->attachFailProduct( 'product_name', 'Contains price: ' . $currency );
        }
        elseif ( $product_name !== strip_tags( $product_name ) ) {
            $this->attachFailProduct( 'product_name', 'Contains html tags' );
        }
    }

    private function validateCostToUs( float $cost, bool $group ): void
    {
        if ( !$group && $cost <= 0 ) {
            $this->attachFailProduct( 'cost_to_us', 'Equal to zero' );
        }
    }

    private function validateListPrice( ?float $list, bool $group ): void
    {
        if ( !$group && $list <= 0 && !is_null( $list ) ) {
            $this->attachFailProduct( 'list_price', 'Equal to zero' );
        }
    }

    private function validateCategories( array $categories ): void
    {
        $filter_categories = array_filter( $categories );
        if ( count( $categories ) > count( $filter_categories ) ) {
            $this->attachFailProduct( 'categories', 'Array contains empty values' );
        }

        if ( array_values( $categories ) !== $categories ) {
            $this->attachFailProduct( 'categories', 'The sequence of keys in the category array is broken' );
        }

        if ( count( $categories ) > 5 ) {
            $this->attachFailProduct( 'categories', 'The number is more than 5' );
        }
    }

    private function validateShortDesc( ?string $desc ): void
    {
        if ( is_null( $desc ) ) {
            return;
        }
        if ( $currency = $this->findPriceInString( $desc ) ) {
            $this->attachFailProduct( 'short_desc', 'Contains price: ' . $currency );
        }
        if ( substr_count( $desc, '<ul>' ) > 1 ) {
            $this->attachFailProduct( 'short_desc', 'Contains html tags' );
        }
    }

    private function validateDescription( string $desc ): void
    {
        $data = $this->getShortsAndAttributesInDescription( $desc );
        if ( !is_null( $data[ 'attributes' ] ) ) {
            $this->attachFailProduct( 'description', 'Contains a set of specifications' );
        }

        if ( count( $data[ 'short_description' ] ) ) {
            $this->attachFailProduct( 'description', 'Contains a set of features' );
        }

        if ( $currency = $this->findPriceInString( $data[ 'description' ] ) ) {
            $this->attachFailProduct( 'description', 'Contains price: ' . $currency );
        }

        if ( $data[ 'description' ] === 'Dummy' ) {
            $this->attachFailProduct( 'description', 'Is "Dummy"' );
        }

        if ( !StringHelper::isNotEmpty( strip_tags( $data[ 'description' ] ) ) ) {
            $this->attachFailProduct( 'description', 'Is empty' );
        }
    }

    private function validateImages( array $images, bool $group ): void
    {
        if ( !$group && !count( $images ) ) {
            $this->attachFailProduct( 'images', 'No images' );
            return;
        }

        foreach ( $images as $image ) {
            if ( !str_contains( $image, 'http:/' ) && !str_contains( $image, 'https:/' ) ) {
                $this->attachFailProduct( 'images', 'Link address must contain the http or https protocol' );
            }
            elseif ( str_contains( $image, 'youtube' ) || str_contains( $image, 'vimeo' ) ) {
                $this->attachFailProduct( 'images', 'Link address points to the video file' );
            }
            elseif ( $image === 'http://' || $image === 'https://' ) {
                $this->attachFailProduct( 'images', 'Link address contain only http or https protocol' );
            }
            elseif ( str_contains( substr( $image, 5 ), 'http:/' ) || str_contains( substr( $image, 6 ), 'https:/' ) ) {
                $this->attachFailProduct( 'images', 'Link address contain many http or https protocols' );
            }
        }
    }

    private function validateAvail( ?int $avail ): void
    {
        if ( is_null( $avail ) ) {
            $this->attachFailProduct( 'avail', 'Is null' );
        }
    }

    private function validateMpn( string $mpn, bool $group ): void
    {
        if ( !$group && empty( $mpn ) ) {
            $this->attachFailProduct( 'mpn', 'Is empty' );
        }
    }

    private function validateAttributes( ?array $attributes ): void
    {
        if ( !is_null( $attributes ) ) {
            if ( !count( $attributes ) ) {
                $this->attachFailProduct( 'attributes', 'Array is empty' );
                return;
            }

            if ( count( array_filter( $attributes, static fn( $attribute ) => trim( $attribute ) !== '' ) ) !== count( $attributes ) ) {
                $this->attachFailProduct( 'attributes', 'Array contains empty values' );
            }
            else {
                foreach ( $attributes as $key => $value ) {
                    if ( is_array( $value ) ) {
                        $this->attachFailProduct( 'attributes', 'Attribute value must not be an array' );
                    }
                    if ( is_null( $value ) ) {
                        $this->attachFailProduct( 'attributes', 'Attribute value must not be an null' );
                    }
                    if ( $currency = $this->findPriceInString( $value ) ) {
                        $this->attachFailProduct( 'attributes', 'Attribute value contains price: ' . $currency );
                    }
                    if ( trim( $key ) === '' ) {
                        $this->attachFailProduct( 'attributes', 'Attribute key is empty' );
                    }
                    if ( trim( $value ) === '' || mb_strlen( $value, 'utf8' ) > 500 ) {
                        $this->attachFailProduct( 'attributes', 'Attribute value is empty or length more 500 symbols' );
                    }
                }
            }
        }
    }

    private function validateProductFiles( array $files ): void
    {
        if ( count( $files ) ) {
            foreach ( $files as $file ) {
                if ( !is_array( $file ) || count( $file ) !== 2 ) {
                    $this->attachFailProduct( 'product_files', 'Array has an invalid format' );
                }
                elseif ( !array_key_exists( 'name', $file ) || !array_key_exists( 'link', $file ) ) {
                    $this->attachFailProduct( 'product_files', 'Array has an invalid format' );
                }
            }
        }
    }

    private function validateOptions( array $options ): void
    {
        if ( count( $options ) ) {
            if ( count( array_filter( $options ) ) !== count( $options ) ) {
                $this->attachFailProduct( 'options', 'Array contains empty values' );
            }
            else {
                foreach ( $options as $key => $values ) {
                    if ( preg_match( '/(\d+\.\d+|\.\d+|\d+)/', $key, $match_key ) && $match_key[ 1 ] === trim( $key ) ) {
                        $this->attachFailProduct( 'options', 'Options name has a numeric format' );
                    }
                    if ( empty( trim( $key ) ) || str_contains( $key, ':' ) || stripos( $key, 'required' ) !== false ) {
                        $this->attachFailProduct( 'options', 'Option name contains an empty value or forbidden characters' );
                    }

                    if ( is_array( $values ) ) {
                        if ( count( array_filter( $values ) ) !== count( $values ) ) {
                            $this->attachFailProduct( 'options', 'Option value is empty' );
                        }
                    }
                    else if ( empty( trim( $values ) ) ) {
                        $this->attachFailProduct( 'options', 'Option value is empty' );
                    }
                }
            }
        }
    }

    private function validateVideos( array $videos ): void
    {
        if ( count( $videos ) ) {
            foreach ( $videos as $video ) {
                if ( !is_array( $video ) || count( $video ) !== 3 ) {
                    $this->attachFailProduct( 'videos', 'Array has an invalid format' );
                }

                if ( !array_key_exists( 'name', $video ) || !array_key_exists( 'video', $video ) || !array_key_exists( 'provider', $video ) ) {
                    $this->attachFailProduct( 'videos', 'Array has an invalid format' );
                }
            }
        }
    }
}
