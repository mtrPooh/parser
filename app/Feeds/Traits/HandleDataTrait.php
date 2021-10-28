<?php

namespace App\Feeds\Traits;

use App\Feeds\Utils\ParserCrawler;
use App\Helpers\HtmlHelper;
use App\Helpers\StringHelper;
use JetBrains\PhpStorm\ArrayShape;

trait HandleDataTrait
{
    /**
     * @var array Набор регулярных выражений для мягкой очистки названия товара
     */
    protected array $clean_product_patterns = [];
    /**
     * @var array Набор регулярных выражений для грубой очистки названия товара (если будет найдено совпадение, название удалится целиком)
     */
    protected array $remove_product_patterns = [];
    /**
     * @var array Набор регулярных выражений для мягкой очистки описания (все найденные совпадения будут вырезаны из описания)
     */
    protected array $clean_description_patterns = [];
    /**
     * @var array Набор регулярных выражений для грубой очистки описания (помимо совпадения, из описания будет вырезан и весь контекст, в котором оно было найдено;
     * Если в описании есть html теги, тогда тег, в котором найдено совпадение, будет вырезан целиком
     */
    protected array $remove_description_patterns = [];

    private string $general_list_header_pattern = '<(div|p|span|b|strong|h[\d]?|em)>(<\w+>)?(\s+)?%s(\s+)?((<\/\w+>)+)?:?(\s+)?(<\/(div|p|span|b|strong|h[\d]?|em)>)?(\s+)?((<\w+>(\s+)?)+)?((<\/\w+>(\s+)?)+)?((<\w+>(\s+)?)+)?(\s+)?';
    private string $list_pattern = '(<[u|o]l>)?(\s+)?(?<content_list><li>.*?<\/li>)(\s+)?<\/[u|o]l>';
    private array $list_header_variants_pattern = [
        '(Product[s]?)?(\s+)?Dimension[s]?',
        '(Product[s]?)?(\s+)?Specification[s]?',
        '(Product[s]?|Key)?(\s+)?Benefit[s]?',
        '(Product[s]?|Key)?(\s+)?Feature[s]?',
        '(Product[s]?)?(\s+)?Detail[s]?',
        'Features (&(amp;)?|and) Benefits',
    ];

    /**
     * Производит поиск подстроки в строке по регулярному выражению и удаляет или заменяет ее
     * @param string $string Строка, в которой будет происходить поиск
     * @param array $custom_regex Массив пользовательских регулярных выражений
     * @param bool $replace Очищать всю строку, если в ней было найдено совпадение или удалять только найденную подстроку
     * @return string
     */
    public function cleaning( string $string, array $custom_regex = [], bool $replace = false ): string
    {
        $regexes_price = [
            '/save((\s+)?(over)?)(\s+)?\$?(\d+(\.?\d+)?)/is',
            '/((map(-|s)?)(\s+)?(price(\s+)?)?)\$?(\s+)?(\d+(\.?\d+)?)/is',
            '/(retail)?(\s+)?price(:)?(\s+)?\$?(\d+(\.?\d+)?)/is',
            '/msrp(:)?(\s+)?\$?(\d+(\.?\d+)?)/is',
            '/\$(\d+(\.?\d+)?).*?price/i',
        ];
        $regexes_shipping = [
            '/([–]|[-])?(\s+)?(\()?free shipping(\))?([.]|[!])?/iu',
            '/ship(ping|s)?(\s+)?(methods)?(\s+)?(is)?(\s+)?free/is',
            '/drop ship(ping)?/is',
        ];

        $regexes_other = [
            '/Product Code(:)?(\s+)?.*?(\.|\!|\?|\W)/is',
        ];

        $regexes = array_merge( $regexes_other, $regexes_shipping, $regexes_price, $custom_regex );
        foreach ( $regexes as $regex ) {
            if ( preg_match( $regex, $string ) ) {
                $string = $replace ? (string)preg_replace( $regex, '', $string ) : '';
            }
        }
        return $string;
    }

    /**
     * Производит очистку текста по регулярным выражениям. Регулярные выражения, используемые по умолчанию,
     * очищают текст от самых частых вариантов отображения стоимости товаров, а также от информации о доставке.
     *
     * Метод проходит вглубь по вложенным html тегам и обрабатывает каждый из них индивидуально
     *
     * Когда метод находит совпадение по регулярному выражению, он удаляет весь текст html тега, в котором оно было найдено.
     * Данное поведение нельзя изменить. Если необходимо вырезать только ту часть, которая совпала по регулярному выражению,
     * можно использовать метод "cleaning"
     * @param string $string
     * @param array $custom_regex
     * @return string
     */
    public function cleanProductData( string $string, array $custom_regex = [] ): string
    {
        if ( str_starts_with( trim( StringHelper::removeSpaces( $string ) ), '<' ) ) {
            $crawler = new ParserCrawler( $string );
            $children = $crawler->filter( 'body' )->count() ? $crawler->filter( 'body' )->children() : [];
            foreach ( $children as $child ) {
                /** Если текущий узел содержит дочерние узлы, обрабатываем их по отдельности **/
                if ( $child->childElementCount ) {
                    foreach ( $child->childNodes as $node ) {
                        $content = $node->ownerDocument->saveHTML( $node );
                        $string = str_replace( $content, self::cleanProductData( $content, $custom_regex ), $string );
                    }
                }
                else {
                    $content = $child->ownerDocument->saveHTML( $child );
                    $string = str_replace( $content, $this->cleaning( $content, $custom_regex ), $string );
                }
            }
        }
        else {
            $string = str_replace( $string, $this->cleaning( $string, $custom_regex ), $string );
        }
        return $string;
    }

    /**
     * Очищает название товара.
     *
     * Метод автоматически используется в методе "setProduct" класса App\Feeds\Feed\FeedItem
     * Использовать его принудительно без особой необходимости бессмысленно!
     * @param string $product
     * @return string
     */
    public function cleanProduct( string $product ): string
    {
        if ( $this->getMpn() && mb_strtolower( $product ) !== mb_strtolower( $this->getMpn() ) && str_contains( mb_strtolower( $product ), mb_strtolower( $this->getMpn() ) ) ) {
            $product = preg_replace( "~(\s+)?(-|,|\(|)?(\s+)?{$this->getMpn()}(\s+)?(-|,|\)|)?(\s+)?~i", ' ', $product );
        }

        if ( $this->remove_product_patterns ) {
            foreach ( $this->remove_product_patterns as $product_pattern ) {
                if ( preg_match( $product_pattern, $product ) ) {
                    $product = '';
                    break;
                }
            }
        }
        return $this->cleaning( $product, $this->clean_product_patterns, true );
    }

    /**
     * Очищает описание товара
     *
     * Метод автоматически используется в методе "setFulldescr" класса App\Feeds\Feed\FeedItem
     * Использовать его принудительно без особой необходимости бессмысленно!
     * @param string $description
     * @return string
     */
    public function cleanDescription( string $description ): string
    {
        if ( $description !== 'Dummy' && StringHelper::isNotEmpty( $description ) ) {
            if ( $this->clean_description_patterns ) {
                $description = $this->cleaning( $description, $this->clean_description_patterns, true );
            }
            $description = $this->cleanProductData( $description, $this->remove_description_patterns );
            $description = HtmlHelper::cutTagsAttributes( $description );
            $description = str_replace( [ '<div>', '</div>' ], [ '<p>', '</p>' ], html_entity_decode( StringHelper::removeSpaces( $description ) ) );
            $description = (string)preg_replace( [ '/((\s+)?<p>(\s+)?)+/', '/((\s+)?<\/p>(\s+)?)+/' ], [ '<p>', '</p>' ], $description );
            $description = (string)preg_replace( '/<h[\d]?>(.*?)<\/h[\d]?>/', '<b>$1</b><br>', $description );

            /** Удаляет пустые теги из описания товара **/
            $description = HtmlHelper::cutEmptyTags( HtmlHelper::cutTags( $description ) );

            /** Закрывает все не закрытые html теги **/
            $description = ( new ParserCrawler( $description ) )->getHtml( 'body' );
        }
        return $description;
    }

    /**
     * Очищает массив особенностей товара от пустых элементов
     *
     * Метод автоматически используется в методе "setShortdescr" класса App\Feeds\Feed\FeedItem
     * Использовать его принудительно без особой необходимости бессмысленно!
     * @param array $short_description Особенности товара
     * @return array Очищенные особенности
     */
    public function cleanShortDescription( array $short_description ): array
    {
        $short_description = array_map( fn( $desc ) => StringHelper::removeSpaces( $this->cleanProductData( str_replace( '•', '', $desc ) ) ), $short_description );
        return array_filter( $short_description, fn( $description ) => StringHelper::isNotEmpty( $description ) && $this->cleaning( $description, [ '/\$(\d+)?(\.?\d+(\.?\d+)?)/' ] ) && mb_strlen( $description ) < 500 );
    }

    /**
     * Очищает массив характеристик товара от пустых элементов
     *
     * Метод автоматически используется в методе "setAttributes" класса App\Feeds\Feed\FeedItem
     * Использовать его принудительно без особой необходимости бессмысленно!
     * @param array|null $attributes Характеристики товара
     * @return array|null Очищенные характеристики
     */
    public function cleanAttributes( ?array $attributes ): ?array
    {
        if ( is_null( $attributes ) ) {
            return null;
        }

        $clean_attributes = [];
        foreach ( $attributes as $key => $value ) {
            if ( $clean_key_attribute = $this->cleanProductData( $key ) ) {
                $clean_attributes[ $clean_key_attribute ] = StringHelper::removeSpaces( $this->cleanProductData( $value ) );
            }
        }
        return array_filter( $clean_attributes, static fn( $attribute ) => StringHelper::isNotEmpty( $attribute ) ) ?: null;
    }

    /**
     * Возвращает особенности и характеристики товара, найденные в его описании, регулярным выражением
     * @param string $description Описание товара
     * @param array $user_regexes Массив регулярных выражений
     * @param array $short_description Массив особенностей товара
     * @param array $attributes Массив характеристик товара
     * @return array Возвращает массив, содержащий
     *  [
     *      'description' => string - описание товара очищенное от особенностей и характеристик
     *      'short_description' => array - массив особенностей товара
     *      'attributes' => array|null - массив характеристик товара
     *  ]
     */
    #[ArrayShape( [ 'description' => "string", 'short_description' => "array", 'attributes' => "array|null" ] )]
    public function getShortsAndAttributesInDescription( string $description, array $user_regexes = [], array $short_description = [], array $attributes = [] ): array
    {
        $description = StringHelper::removeSpaces( $description );
        $description = HtmlHelper::cutTagsAttributes( $description );
        $description = HtmlHelper::cutEmptyTags( $description );

        $product_data = [
            'short_description' => $short_description,
            'attributes' => $attributes
        ];

        $regexes = [];
        foreach ( $this->list_header_variants_pattern as $header_variant ) {
            $regex = sprintf( $this->general_list_header_pattern, $header_variant );
            $regexes[] = "/$regex$this->list_pattern/is";
        }

        $regexes = array_merge( $regexes, $user_regexes );
        foreach ( $regexes as $regex ) {
            if ( preg_match_all( $regex, $description, $match ) ) {
                $wrong_shorts = false;
                $wrong_attributes = false;

                $description_data = [
                    'short_description' => [],
                    'attributes' => []
                ];
                $list_data = [
                    'short_description' => [],
                    'attributes' => []
                ];
                foreach ( $match[ 'content_list' ] as $content_list ) {
                    if ( isset( $match[ 'delimiter' ] ) ) {
                        $delimiter = array_shift( $match[ 'delimiter' ] );
                        if ( !str_starts_with( $delimiter, '<' ) ) {
                            $delimiter = "<$delimiter>";
                        }
                        $list_data = $this->getShortsAndAttributesInList( str_replace( [ $delimiter, str_replace( '<', '</', $delimiter ) ], [ '<li>', '</li>' ], $content_list ) );
                    }
                    elseif ( str_contains( $content_list, '<li>' ) ) {
                        $list_data = $this->getShortsAndAttributesInList( $content_list );
                    }
                    elseif ( str_contains( $content_list, '<p>' ) ) {
                        $list_data = $this->getShortsAndAttributesInList( str_replace( [ '<p>', '</p>' ], [ '<li>', '</li>' ], $content_list ) );
                    }
                    elseif ( str_contains( $content_list, '<br>' ) ) {
                        $raw_content_list = explode( '<br>', $content_list );
                        $list_data = $this->getShortsAndAttributesInList( '<li>' . implode( '</li><li>', $raw_content_list ) . '</li>' );
                    }
                    $description_data[ 'short_description' ] = array_merge( $description_data[ 'short_description' ], $list_data[ 'short_description' ] );
                    $description_data[ 'attributes' ] = array_merge( $description_data[ 'attributes' ], $list_data[ 'attributes' ] ?? [] );
                }

                foreach ( $description_data[ 'short_description' ] as $short ) {
                    if ( count( $description_data[ 'short_description' ] ) > 10 || mb_strlen( $short ) > 250 ) {
                        $wrong_shorts = true;
                        break;
                    }
                }

                foreach ( $description_data[ 'attributes' ] as $attribute ) {
                    if ( mb_strlen( $attribute ) > 300 ) {
                        $wrong_attributes = true;
                        break;
                    }
                }

                if ( $wrong_shorts || $wrong_attributes ) {
                    continue;
                }

                $description = (string)preg_replace( $regex, '', $description );

                $product_data[ 'short_description' ] = array_merge( $product_data[ 'short_description' ], $description_data[ 'short_description' ] );
                $product_data[ 'attributes' ] = array_merge( $product_data[ 'attributes' ], $description_data[ 'attributes' ] );
            }
        }

        return [
            'description' => $description,
            'short_description' => $product_data[ 'short_description' ],
            'attributes' => $product_data[ 'attributes' ] ?: null
        ];
    }

    /**
     * Возвращает особенности и характеристики товара, найденные в упорядоченном списке
     * @param string $list Список, содержащий теги "li"
     * @param array $short_description Массив особенностей товара
     * @param array $attributes Массив характеристик товара
     * @return array Возвращает массив, содержащий
     *  [
     *      'short_description' => array - массив особенностей товара
     *      'attributes' => array|null - массив характеристик товара
     *  ]
     */
    #[ArrayShape( [ 'short_description' => "array", 'attributes' => "array|null" ] )]
    public function getShortsAndAttributesInList( string $list, array $short_description = [], array $attributes = [] ): array
    {
        $crawler = new ParserCrawler( $list );
        $crawler->filter( 'li' )->each( static function ( ParserCrawler $c ) use ( &$short_description, &$attributes ) {
            $text = $c->text();
            if ( str_contains( $text, ':' ) ) {
                [ $key, $value ] = explode( ':', $text, 2 );
                $attributes[ trim( $key ) ] = trim( StringHelper::removeSpaces( $value ) );
            }
            else {
                $short_description[] = $text;
            }
        } );

        return [
            'short_description' => $this->cleanShortDescription( $short_description ),
            'attributes' => $this->cleanAttributes( $attributes )
        ];
    }
}