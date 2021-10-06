<?php

namespace App\Feeds\Processor;

use App\Feeds\Downloader\HttpDownloader;
use App\Feeds\Feed\FeedItem;
use App\Feeds\Feed\FeedValidate;
use App\Feeds\Parser\ParserInterface;
use App\Feeds\Parser\XLSNParser;
use App\Feeds\Storage\AbstractFeedStorage;
use App\Feeds\Storage\FileStorage;
use App\Feeds\Utils\Collection;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;
use App\Feeds\Utils\ParserCrawler;
use App\Repositories\DxRepositoryInterface;
use DateTime;
use Exception;
use Ms48\LaravelConsoleProgressBar\Facades\ConsoleProgressBar;

/**
 * Hooks
 * @method processInit
 * @method afterProcessItem
 * @method beforeProcess
 * @method afterProcess
 * @method afterFeedItemMerge( FeedItem $fi )
 */
abstract class AbstractProcessor
{
    public const PRICE_ACTIVE_MULTIPLE_SHEET = [];

    public const FEED_TYPE_INVENTORY = 'inventory';
    public const FEED_TYPE_PRODUCT = 'product';
    public const FEED_SOURCE_PRICE = 'price';
    public const FEED_SOURCE_SITE = 'site';
    public const DX_ID = null;
    public const DX_NAME = null;
    public const DX_PREFIX = null;
    public const DX_SOURCE = null;
    /**
     * Массив css селекторов, выбирающих элементы ссылок (<a>), на категории товаров для их дальнейшего обхода
     */
    public const CATEGORY_LINK_CSS_SELECTORS = [];
    /**
     * Массив css селекторов, выбирающих элементы ссылок (<a>), на страницы товаров для сбора информации с них
     */
    public const PRODUCT_LINK_CSS_SELECTORS = [];
    /**
     * Определяет количество ссылок, которое будет обработано за один запрос
     */
    protected const CHUNK_SIZE = 20;
    /**
     * Определяет время ожидания обработки запроса в секундах
     */
    protected const REQUEST_TIMEOUT_S = 60;
    /**
     * Определяет задержку между отправкой запросов в секундах
     */
    protected const DELAY_S = 0;
    /**
     * Определяет использовать статичный user agent или менять его при каждом запросе
     */
    protected const STATIC_USER_AGENT = false;
    /**
     * Определяет использовать прокси или нет
     */
    protected const USE_PROXY = false;
    /**
     * Отвечает за способ отправки товаров в очередь в продакшн среде. По умолчанию система отправляет по одному товару.
     */
    protected const SAVE_SINGLE_PRODUCT = true;

    private const E_PARSER_NOT_FOUND = 'Class %s does not exists';

    public ?AbstractFeedStorage $storage;
    /**
     * @var HttpDownloader
     */
    protected HttpDownloader $downloader;
    /**
     * @var FeedItem[] Массив объектов app/Feeds/Feed/FeedItem содержащих информацию о товарах, взятых из прайс-листа
     */
    public array $price_items = [];
    /**
     * @var FeedItem[] Массив объектов app/Feeds/Feed/FeedItem содержащих информацию о товарах, взятых с сайта
     */
    public array $feed_items = [];
    /**
     * @var array Массив url адресов товаров, которые должен обработать парсер, чтобы не парсить весь сайт
     * Используется только для тестирования работоспособности парсера
     * Перед отправкой парсера в продакшен необходимо удалить данное свойство
     */
    public array $custom_products = [];
    public array $dx_info = [];

    protected array $params = [
        'check_login_text' => '',
        'auth_url' => '',
        'auth_form_url' => '',
        'auth_info' => [],
    ];
    protected array $first = [];
    protected array $headers = [];
    /**
     * @var int|null количество товаров, которое должен собрать парсер, чтобы не парсить весь сайт
     * Используется только для тестирования работоспособности парсера
     * Перед отправкой парсера в продакшен необходимо удалить данное свойство
     */
    protected ?int $max_products = null;
    protected Collection $process_queue;

    public function __construct(
        string                $code = null,
        DxRepositoryInterface $dxRepo = null,
        AbstractFeedStorage   $storage = null
    )
    {
        if ( $code && $dxRepo ) {
            //мультифиды на разные магазины
            $codeSplit = explode( '__', $code );
            //Замена в префиксе Dx _ на -
            $code = str_replace( '_', '-', $codeSplit[ 0 ] );
            $this->dx_info = $dxRepo->get( $code, $codeSplit[ 1 ] ?? null );
        }
        if ( $storage ) {
            $this->storage = $storage;
        }
        $this->process_queue = app( Collection::class );
    }

    public function __call( $name, $arguments )
    {
    }

    /**
     * проверка на dev mode
     * @return bool
     */
    public function isDevMode(): bool
    {
        return config( 'env', 'production' ) === 'dev';
    }

    public function getFeedType(): string
    {
        return !empty( $this->dx_info[ 'feeds' ] ) ? array_values( $this->dx_info[ 'feeds' ] )[ 0 ][ 'type' ] : self::FEED_TYPE_PRODUCT;
    }

    public function getFeedSource(): string
    {
        return self::FEED_SOURCE_SITE;
    }

    public function getFeedDate(): DateTime
    {
        return new DateTime();
    }

    public function getQueue(): Collection
    {
        return $this->process_queue;
    }

    public function getDownloader(): HttpDownloader
    {
        return $this->downloader;
    }

    /**
     * Возврает все ссылки на страницы категорий, которые были найденны по селекторам, указанным в константе "CATEGORY_LINK_CSS_SELECTORS"
     * @param Data $data Html разметка загружаемой страницы
     * @param string $url url адрес загружаемой страницы
     * @return array Массив ссылок, содержащий объекты app/Feeds/Utils/Link
     */
    public function getCategoriesLinks( Data $data, string $url ): array
    {
        $links = [];
        if ( count( static::CATEGORY_LINK_CSS_SELECTORS ) ) {
            foreach ( static::CATEGORY_LINK_CSS_SELECTORS as $selector ) {
                $links = (array)array_merge( $links, ( new ParserCrawler( $data->getData(), $url ) )
                    ->filter( $selector )
                    ->each( static fn( ParserCrawler $node ) => new Link( static::getNormalizedCategoryLink( $node->link()->getUri() ) ) ) );
            }
        }
        return array_values( array_filter( $links ) );
    }

    /**
     * Возврает все ссылки на страницы товаров, которые были найденны по селекторам, указанным в константе "PRODUCT_LINK_CSS_SELECTORS"
     * @param Data $data Html разметка загружаемой страницы
     * @param string $url url адрес загружаемой страницы
     * @return array Массив ссылок, содержащий объекты app/Feeds/Utils/Link
     */
    public function getProductsLinks( Data $data, string $url ): array
    {
        $links = [];
        if ( count( static::PRODUCT_LINK_CSS_SELECTORS ) ) {
            foreach ( static::PRODUCT_LINK_CSS_SELECTORS as $selector ) {
                $links = (array)array_merge( $links, ( new ParserCrawler( $data->getData(), $url ) )
                    ->filter( $selector )
                    ->each( static fn( ParserCrawler $node ) => new Link( static::getNormalizedLink( $node->link()->getUri() ) ) ) );
            }
        }
        return array_values( array_filter( $links ?? [], [ $this, 'filterProductLinks' ] ) );
    }

    final public function getSupplierId(): int
    {
        return $this->dx_info[ 'id' ] ?? static::DX_ID;
    }

    final public function getSupplierName(): string
    {
        return $this->dx_info[ 'name' ] ?? static::DX_NAME;
    }

    final public function getPrefix(): string
    {
        return $this->dx_info[ 'prefix' ] ?? static::DX_PREFIX;
    }

    final public function getSource(): string
    {
        return static::DX_SOURCE ?? $this->dx_info[ 'source' ] ?? '';
    }

    /**
     * Возвращает объект парсера
     * @param string $prefix
     * @return ParserInterface
     */
    public function getParser( string $prefix = '' ): ParserInterface
    {
        $class = substr( static::class, 0, strrpos( static::class, '\\' ) + 1 ) . $prefix . 'Parser';
        if ( class_exists( $class ) ) {
            return new $class( $this );
        }

        die( sprintf( self::E_PARSER_NOT_FOUND, $class ) );
    }

    public static function getNormalizedCategoryLink( string $link ): string
    {
        return $link;
    }

    public static function getNormalizedLink( string $link ): string
    {
        return $link;
    }

    /**
     * Возвращает объект прайс парсера
     * @param array $sheet
     * @param null $price
     * @return ParserInterface
     */
    public function getPriceParser( array $sheet, $price = null ): ParserInterface
    {
        $parser = 'Price';
        if ( !is_null( $price ) && key( $sheet ) && count( static::PRICE_ACTIVE_MULTIPLE_SHEET ) ) {
            $parser .= $price ? "{$price}_" . key( $sheet ) : '_' . key( $sheet );
        }
        else if ( $price ) {
            $parser .= $price;
        }
        else {
            $parser .= key( $sheet ) ?: '';
        }
        return $this->getParser( $parser );
    }

    /**
     * Собирает информацию о товаре со страницы
     * @param Data $data html содержимое страницы товара
     * @param string $url url адрес текущей страницы товара
     * @return FeedItem[]
     */
    protected function getFeedItems( Data $data, string $url ): array
    {
        return $this->getParser()->parseContent( $data, [ 'url' => $url ] );
    }

    /**
     * @param int $max_products Устанавливает максимальное количество товаров, которое должен собрать парсер, чтобы не парсить весь сайт
     * Используетсяя только для тестирования работоспособности парсера
     */
    public function setMaxProducts( int $max_products ): void
    {
        $this->max_products = $max_products;
    }

    /**
     * Загружает ссылки, переданные в массиве
     * @param Link[] $links
     * @return Data[]
     */
    protected function fetchLinks( array $links ): array
    {
        return $this->getDownloader()->fetch( $links );
    }

    /**
     * Собирает ссылки на категории и страницы товаров
     * @param $crawler
     * @param string $url
     */
    protected function parseLinks( $crawler, string $url ): void
    {
        $this->process_queue->addLinks( $this->getCategoriesLinks( $crawler, $url ), Collection::LINK_TYPE_CATEGORY );
        $this->process_queue->addLinks( $this->getProductsLinks( $crawler, $url ), Collection::LINK_TYPE_PRODUCT );

        $num_product_links = $this->process_queue->where( 'type', Collection::LINK_TYPE_PRODUCT )->count();

        if (
            $this->max_products
            && $num_product_links >= $this->max_products
            && $this->isDevMode()
        ) {
            $categories = $this->process_queue->where( 'type', Collection::LINK_TYPE_CATEGORY )->all();
            array_walk( $categories, static fn( array $item ) => $item[ 'link' ]->setVisited() );
        }
    }

    /**
     * Производит слияние товаров, взятых из прайс-листа и с сайта
     * У товаров, взятых с сайта приоритет ниже, поэтому информация из прай-листа заменит собой информацию с сайта
     * За исключением нескольких полей
     * @param FeedItem[] $feed_target
     * @param FeedItem[] $feed_source
     */
    public function mergeFeeds( array $feed_target, array $feed_source ): void
    {
        foreach ( $feed_target as $fi_target ) {
            if ( $fi_target->isGroup() ) {
                $this->mergeFeeds( $fi_target->getChildProducts(), $feed_source );
            }

            if (
                isset( $feed_source[ $fi_target->getMpn() ] )
                && $fi_source = $feed_source[ $fi_target->getMpn() ]
            ) {
                foreach ( get_object_vars( $fi_source ) as $name => $source_value ) {
                    if ( !empty( $source_value ) ) {
                        switch ( $name ) {
                            case 'product':
                            case 'fulldescr':
                                if ( $fi_target->$name !== XLSNParser::DUMMY_PRODUCT_NAME ) {
                                    continue 2;
                                }
                                break;
                            case 'min_amount':
                                if ( $fi_target->$name !== 1 ) {
                                    continue 2;
                                }
                                break;
                            case 'forsale':
                                if ( $fi_target->$name !== 'Y' ) {
                                    continue 2;
                                }
                                break;
                            case 'images':
                                if ( count( $fi_target->$name ) ) {
                                    continue 2;
                                }
                                break;
                            case 'brand':
                                if ( $fi_target->$name ) {
                                    continue 2;
                                }
                                break;
                        }
                        $fi_target->$name = $source_value;
                    }
                }
            }

            $this->afterFeedItemMerge( $fi_target );
        }
    }

    /**
     * Используется для удаления невалидных товаров из фида перед сохранением
     * @param FeedItem $fi
     * @return bool
     */
    protected function isValidFeedItem( FeedItem $fi ): bool
    {
        return true;
    }

    public function processError( $e, $key = null ): void
    {

    }

    /**
     * loading errors handler hook
     */
    protected function loadExceptionHandler( Exception $e ): void
    {
        print PHP_EOL . $e->getMessage() . PHP_EOL;
    }

    /**
     * Обходит страницы категорий и собирает информацию о товарах
     */
    final public function process(): void
    {
        $this->processInit();

        $this->beforeProcess();

        $total = $this->process_queue->count();

        if ( $this->custom_products && $this->isDevMode() ) {
            $this->process_queue = $this->process_queue->clear();
            $this->process_queue->addLinks( $this->custom_products, Collection::LINK_TYPE_PRODUCT );
        }

        while ( $links = $this->process_queue->next( null, static::CHUNK_SIZE ) ) {
            try {
                $new_links = $this->fetchLinks( $links );
            } catch ( Exception $e ) {
                $this->loadExceptionHandler( $e );
                sleep( 1 );
                continue;
            }

            foreach ( $new_links as $data ) {
                if ( $data ) {
                    if ( $data->getPageLink()?->getMethod() === 'POST' ) {
                        $current_link = $data->getPageLink()?->getUrl() . '@post_params=' . http_build_query( $data->getPageLink()?->getParams() );
                    }
                    else {
                        $current_link = $data->getPageLink()?->getUrl();
                    }
                    switch ( $this->process_queue->get( $current_link )[ 'type' ] ) {
                        case  Collection::LINK_TYPE_CATEGORY:
                            try {
                                $this->parseLinks( $data, $data->getPageLink()?->getUrl() );
                            } catch ( Exception $e ) {
                                echo PHP_EOL . 'Loading error: ' . $data->getPageLink()?->getUrl() . PHP_EOL . $e->getMessage() . PHP_EOL;
                                continue 2;
                            }
                            break;
                        case  Collection::LINK_TYPE_PRODUCT:
                            $feed_item = $this->getFeedItems( $data, $data->getPageLink()?->getUrl() );
                            if ( $this->storage instanceof FileStorage || static::SAVE_SINGLE_PRODUCT === false ) {
                                $this->feed_items += $feed_item;
                            }
                            else {
                                $this->mergeFeeds( $feed_item, $this->price_items );
                                $fi = array_shift( $feed_item );
                                if ( $fi && $this->isValidFeedItem( $fi ) ) {
                                    $this->storage->saveFeed( $this, [ $fi ] );
                                }
                            }
                            break;
                    }

                    if (
                        $this->max_products
                        && count( $this->feed_items ) >= $this->max_products
                        && $this->isDevMode()
                    ) {
                        break 2;
                    }
                }

                $this->afterProcessItem();

                $total = $this->process_queue->count();
                ConsoleProgressBar::showProgress( 1, $total );
            }

            array_walk( $links, static fn( Link $link ) => $link->setVisited( true ) );

            if ( $this->max_products && $this->isDevMode() && count( $this->feed_items ) >= $this->max_products ) {
                break;
            }
        }

        $this->afterProcess();

        if ( $total ) {
            ConsoleProgressBar::showProgress( $total, $total );
        }

        if ( $this->feed_items ) {
            $this->mergeFeeds( $this->feed_items, $this->price_items );
            foreach ( $this->feed_items as $key => $fi ) {
                if ( !$this->isValidFeedItem( $fi ) ) {
                    unset( $this->feed_items[ $key ] );
                }
            }

            if ( $this->isDevMode() ) {
                new FeedValidate( $this->feed_items, $this->dx_info );
            }

            $this->storage->saveFeed( $this, $this->feed_items );
        }
        $this->storage->shutdown();
    }
}
