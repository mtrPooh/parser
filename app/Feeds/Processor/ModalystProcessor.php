<?php

namespace App\Feeds\Processor;

use App\Feeds\Downloader\HttpDownloader;
use App\Feeds\Feed\FeedItem;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;
use Exception;
use Ms48\LaravelConsoleProgressBar\ConsoleProgressBar;

abstract class ModalystProcessor extends HttpProcessor
{
    /**
     * Значение параметра "brands" в строке запроса "https://modalyst.co/api/v3/marketplace/items",
     * отправленного со страницы товара нужного бренда
     */
    protected const BRAND_UUID = '';
    protected const BASE_API_URL = 'https://modalyst.co/api/v3/marketplace/items';
    protected const SAVE_SINGLE_PRODUCT = false;

    public const DUAL_PARSER = false;

    protected string $custom_product_id = '';

    public function processInit(): void
    {
        $this->initModalyst();
        $this->processModalystProductsLinks();

        if ( static::DUAL_PARSER ) {
            [ $this->feed_items, $this->price_items ] = [ $this->price_items, $this->feed_items ];
            parent::processInit();
        }
    }

    protected function getFeedItems( Data $data, string $url ): array
    {
        if ( static::DUAL_PARSER ) {
            return $this->getParser( 'Price' )->parseContent( $data, [ 'url' => $url ] );
        }
        return parent::getFeedItems( $data, $url );
    }

    /**
     * Инициализирует объект класса HttpDownloader и производит авторизацию на сайте modalyst.co
     * @throws Exception
     */
    private function initModalyst(): void
    {
        $params = [
            'check_login_text' => '{"redirect_to":"/search/"}',
            'auth_url' => 'https://modalyst.co/api/v3/accounts/login/',
            'auth_form_url' => 'https://modalyst.co/login/',
            'auth_info' => [
                'email' => $this->getLogin(),
                'password' => $this->getPassword(),
            ],
            'api_auth' => true,
        ];
        $this->downloader = new HttpDownloader( params: $params, source: 'https://modalyst.co' );

        $this->getDownloader()->setHeaders(
            [
                'x-csrftoken' => $this->getDownloader()->getCookie( 'csrftoken' ),
                'accept' => 'application/json, text/plain, */*',
            ]
        );
    }

    /**
     * Собирает и обрабатывает все ссылки на товары указанного бренда
     * @throws Exception
     */
    public function processModalystProductsLinks(): void
    {
        $json_url = $this->apiLinkGenerator();
        $page_json = $this->getDownloader()->get( $json_url );
        $page_data = json_decode( $page_json, true, 512, JSON_THROW_ON_ERROR );

        $links = [];
        $product_links = [];

        $count_items = $page_data[ 'count' ];
        $count_pages = ceil( $count_items / 120 );
        for ( $i = 1; $i <= $count_pages; $i++ ) {
            $links[] = $this->apiLinkGenerator( $i );
        }

        $sliced_links = array_chunk( $links, 20 );
        foreach ( $sliced_links as $links_chunk ) {
            foreach ( $this->downloader->fetch( $links_chunk ) as $data_page ) {
                $json_result = json_decode( $data_page->getData(), true, 512, JSON_THROW_ON_ERROR );

                if ( $this->custom_product_id ) {
                    $product_links[] = $this->apiLinkGenerator( product_id: $this->custom_product_id );
                    break 2;
                }

                foreach ( $json_result[ 'results' ] as $result ) {
                    $product_links[] = $this->apiLinkGenerator( product_id: $result[ 'identifier' ] );
                }
            }
        }

        $console_bar = new ConsoleProgressBar();

        $product_links = array_chunk( $product_links, 20 );
        foreach ( $product_links as $product_link_chunk ) {
            $i = 0;
            foreach ( $this->getDownloader()->fetch( $product_link_chunk ) as $data ) {
                $this->feed_items += $this->getParser()->parseContent( $data, [ 'url' => $data->getPageLink()->getUrl() ] );

                $i++;
            }
            $console_bar->showProgress( $i, $count_items );
        }
    }

    /**
     * Удаляет товары в feed_items с одинаковым МПН у дочерних товаров
     */
    private function filterProducts(): void
    {
        $temp_array = $this->feed_items;

        foreach ( $temp_array as $key_item => $item ) {
            if ( $item->isGroup() ) {
                foreach ( $item->getChildProducts() as $child_item ) {
                    foreach ( $this->feed_items as $feed_item_key => $feed_item ) {
                        if ( $key_item !== $feed_item_key && $feed_item->isGroup() ) {
                            $feed_children = $feed_item->getChildProducts();
                            foreach ( $feed_children as $feed_item_child ) {
                                if ( $child_item->getMpn() === $feed_item_child->getMpn() ) {
                                    unset( $this->feed_items[ $feed_item_key ] );
                                }
                            }
                        }
                    }
                }
            }
        }
        $this->feed_items = array_values( $this->feed_items );
    }

    /**
     * В зависимости от переданных атрибутов, возвращает объект Link либо
     * на товар, либо на страницу с товарами
     * @param int $page
     * @param string|null $product_id
     * @return Link
     */
    private function apiLinkGenerator( int $page = 1, ?string $product_id = null ): Link
    {
        if ( is_null( $product_id ) ) {
            $params = [
                'ordering' => 'all',
                'omit' => 'show_color,show_size,options',
                'page_size' => 120,
                'brands' => static::BRAND_UUID,
                'page' => $page,
            ];
            return new Link( static::BASE_API_URL, params: $params );
        }
        return new Link( static::BASE_API_URL . '/' . $product_id );
    }

    public function afterProcess(): void
    {
        $items = [];
        foreach ( $this->price_items as $item ) {
            if ( $item->isGroup() ) {
                foreach ( $item->getChildProducts() as $child_product ) {
                    $items[ $child_product->getMpn() ] = $child_product;
                }
            }
            else {
                $items[ $item->getMpn() ] = $item;
            }
        }
        $this->price_items = $items;

        $this->filterProducts();
    }

    /**
     * Устанавливает фид-айтему МПН из атрибута Modalystsku
     * @param FeedItem $fi
     */
    private function changeSku( FeedItem $fi ): void
    {
        $attributes = $fi->getAttributes();

        $fi->setMpn( $attributes[ 'Modalystsku' ] );
        $attributes[ 'Modalystsku' ] = '';

        $fi->setProductCode( strtoupper( $this->getPrefix() . $fi->getMpn() ) );
        $fi->setAttributes( array_filter( $attributes ) ?: null );
    }

    public function afterFeedItemMerge( FeedItem $fi ): void
    {
        if ( !empty( $fi->getAttributes()[ 'Modalystsku' ] ) ) {
            $this->changeSku( $fi );
        }
    }

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        if ( $fi->isGroup() ) {
            $fi->setChildProducts( array_values( array_filter( $fi->getChildProducts(), static fn( FeedItem $item ) => !empty( $item->getMpn() ) && $item->getCostToUs() > 0 && count( $item->getImages() ) > 0 ) ) );
            return count( $fi->getChildProducts() );
        }
        return !empty( $fi->getMpn() ) && $fi->getCostToUs() > 0 && count( $fi->getImages() ) > 0;
    }
}
