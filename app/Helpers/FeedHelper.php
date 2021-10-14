<?php

namespace App\Helpers;

use App\Feeds\Utils\ParserCrawler;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

class FeedHelper
{
    /**
     * Очищает описание товара от лишних переносов строки, пробелов, лишних и пустых тегов, мусора в предложениях и параграфах
     * @param string $description Описание товара
     * @return string Очищенное описание
     */
    public static function cleanProductDescription( string $description ): string
    {
        if ( StringHelper::isNotEmpty( $description ) ) {
            $description = self::cleanProductData( $description );
            $description = StringHelper::cutTagsAttributes( $description );
            $description = str_replace( [ '<div>', '</div>' ], [ '<p>', '</p>' ], html_entity_decode( StringHelper::removeSpaces( $description ) ) );
            $description = (string)preg_replace( '/<h[\d]?>(.*?)<\/h[\d]?>/', '<b>$1</b><br>', $description );

            /** Удаляет пустые теги из описания товара **/
            $description = StringHelper::cutEmptyTags( StringHelper::cutTags( $description ) );
        }
        return $description;
    }

    /**
     * Очищает массив особенностей товара от пустых элементов
     * @param array $short_description Особенности товара
     * @return array Очищенные особенности
     */
    public static function cleanShortDescription( array $short_description ): array
    {
        $short_description = array_map( static fn( $desc ) => StringHelper::removeSpaces( self::cleanProductData( str_replace( '•', '', $desc ) ) ), $short_description );
        return array_filter( $short_description, static fn( $description ) => StringHelper::isNotEmpty( $description ) && self::cleaning( $description, [ '/\$(\d+)?(\.?\d+(\.?\d+)?)/' ] ) && mb_strlen( $description ) < 500 );
    }

    /**
     * Очищает массив характеристик товара от пустых элементов
     * @param array|null $attributes Характеристики товара
     * @return array|null Очищенные характеристики
     */
    public static function cleanAttributes( ?array $attributes ): ?array
    {
        if ( is_null( $attributes ) ) {
            return null;
        }

        $clean_attributes = [];
        foreach ( $attributes as $key => $value ) {
            if ( $clean_key_attribute = self::cleanProductData( $key ) ) {
                $clean_attributes[ $clean_key_attribute ] = StringHelper::removeSpaces( self::cleanProductData( $value ) );
            }
        }
        return array_filter( $clean_attributes, static fn( $attribute ) => StringHelper::isNotEmpty( $attribute ) );
    }

    /**
     * Очищает текст от мусора в предложениях и параграфах. Если текст обернут в html теги, обрабатывает все вложенные узлы вглубь
     * @param string $string
     * @param array $user_regex
     * @return string
     */
    public static function cleanProductData( string $string, array $user_regex = [] ): string
    {
        if ( str_starts_with( trim( StringHelper::removeSpaces( $string ) ), '<' ) ) {
            $crawler = new ParserCrawler( $string );
            $children = $crawler->filter( 'body' )->count() ? $crawler->filter( 'body' )->children() : [];
            foreach ( $children as $child ) {
                /** Если текущий узел содержит дочерние узлы, обрабатываем их по отдельности **/
                if ( $child->childElementCount ) {
                    foreach ( $child->childNodes as $node ) {
                        $content = $node->ownerDocument->saveHTML( $node );
                        $string = str_replace( $content, self::cleanProductData( $content, $user_regex ), $string );
                    }
                }
                else {
                    $content = $child->ownerDocument->saveHTML( $child );
                    $string = str_replace( $content, self::cleaning( $content, $user_regex ), $string );
                }
            }
        }
        else {
            $string = str_replace( $string, self::cleaning( $string, $user_regex ), $string );
        }
        return $string;
    }

    /**
     * Производит поиск подстроки в строке по регулярному выражению и удаляет или заменяет ее
     * @param string $string Строка, в которой будет происходить поиск
     * @param array $user_regex Массив пользовательских регулярных выражений
     * @param bool $replace Очищать всю строку, если в ней было найдено совпадение или удалять только найденную подстроку
     * @return string
     */
    public static function cleaning( string $string, array $user_regex = [], bool $replace = false ): string
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

        $regexes = array_merge( $regexes_other, $regexes_shipping, $regexes_price, $user_regex );
        foreach ( $regexes as $regex ) {
            if ( preg_match( $regex, $string ) ) {
                $string = $replace ? (string)preg_replace( $regex, '', $string ) : '';
            }
        }
        return $string;
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
    public static function getShortsAndAttributesInList( string $list, array $short_description = [], array $attributes = [] ): array
    {
        $crawler = new ParserCrawler( $list );
        $crawler->filter( 'li' )->each( static function ( ParserCrawler $c ) use ( &$short_description, &$attributes ) {
            $text = $c->text();
            if ( str_contains( $text, ':' ) ) {
                [ $key, $value ] = explode( ':', $text, 2 );
                $attributes[ trim( $key ) ] = trim( StringHelper::normalizeSpaceInString( $value ) );
            }
            else {
                $short_description[] = $text;
            }
        } );

        return [
            'short_description' => self::cleanShortDescription( $short_description ),
            'attributes' => self::cleanAttributes( $attributes )
        ];
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
    public static function getShortsAndAttributesInDescription( string $description, array $user_regexes = [], array $short_description = [], array $attributes = [] ): array
    {
        $description = StringHelper::cutTagsAttributes( $description );
        $description = StringHelper::cutEmptyTags( $description );
        $description = StringHelper::normalizeSpaceInString( $description );

        $product_data = [
            'short_description' => $short_description,
            'attributes' => $attributes
        ];

        $regex_pattern = '<(div|p|span|b|strong|h[\d]?|em)>(\s+)?%s(\s+)?((<\/\w+>)+)?:?(\s+)?<\/(div|p|span|b|strong|h[\d]?|em)>(\s+)?((<\w+>)+)?((<\/\w+>)+)?((<\w+>)+)?(\s+)?';

        $keys = [
            '(Product[s]?)?(\s+)?Dimension[s]?',
            '(Product[s]?)?(\s+)?Specification[s]?',
            '(Product[s]?|Key)?(\s+)?Benefit[s]?',
            '(Product[s]?|Key)?(\s+)?Feature[s]?',
            '(Product[s]?)?(\s+)?Detail[s]?',
            'Features & Benefits',
        ];

        $regexes_list = [
            '(<[u|o]l>)?(\s+)?(?<content_list><li>.*?<\/li>)(\s+)?<\/[u|o]l>',
            '(?<content_list><li>.*<\/li>)(\s+)?'
        ];

        $regexes = [];
        foreach ( $keys as $key ) {
            $regex = sprintf( $regex_pattern, $key );
            foreach ( $regexes_list as $regex_list ) {
                $regexes[] = "/$regex$regex_list/is";
            }
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
                        $list_data = self::getShortsAndAttributesInList( str_replace( [ $delimiter, str_replace( '<', '</', $delimiter ) ], [ '<li>', '</li>' ], $content_list ) );
                    }
                    elseif ( str_contains( $content_list, '<li>' ) ) {
                        $list_data = self::getShortsAndAttributesInList( $content_list );
                    }
                    elseif ( str_contains( $content_list, '<p>' ) ) {
                        $list_data = self::getShortsAndAttributesInList( str_replace( [ '<p>', '</p>' ], [ '<li>', '</li>' ], $content_list ) );
                    }
                    elseif ( str_contains( $content_list, '<br>' ) ) {
                        $raw_content_list = explode( '<br>', $content_list );
                        $list_data = self::getShortsAndAttributesInList( '<li>' . implode( '</li><li>', $raw_content_list ) . '</li>' );
                    }
                    $description_data[ 'short_description' ] = (array)array_merge( $description_data[ 'short_description' ], $list_data[ 'short_description' ] );
                    $description_data[ 'attributes' ] = (array)array_merge( $description_data[ 'attributes' ], $list_data[ 'attributes' ] );
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
            'description' => self::cleanProductDescription( $description ),
            'short_description' => $product_data[ 'short_description' ],
            'attributes' => $product_data[ 'attributes' ] ?: null
        ];
    }

    /**
     * Получает размеры товара из строки
     * @param string $string Строка, содержащая размеры
     * @param string|string[] $separator Разделитель, с помощью которого строка преобразуется в массив с размерами товара
     * @param int $x_index Индекс длины товара
     * @param int $y_index Индекс высоты товара
     * @param int $z_index Индекс ширины товара
     * @param string $measure Единица измерения (по умолчанию in - дюймы)
     * @return array Массив, содержащий размеры товара
     */
    #[ArrayShape( [ 'x' => "float|null", 'y' => "float|null", 'z' => "float|null" ] )]
    public static function getDimsInString( string $string, string|array $separator, int $x_index = 0, int $y_index = 1, int $z_index = 2, string $measure = 'in' ): array
    {
        $raw_dims = [];
        if ( is_array( $separator ) ) {
            foreach ( $separator as $sep ) {
                if ( str_contains( $string, $sep ) ) {
                    $raw_dims = explode( $sep, $string );
                }
            }
        }
        else {
            $raw_dims = explode( $separator, $string );
        }

        return self::extractDims( $string, $raw_dims, $x_index, $y_index, $z_index, $measure );
    }

    /**
     * Получает размеры товара из строки по регулярным выражениям
     * @param string $string Строка, содержащая размеры
     * @param array $regexes Массив регулярных выражений для поиска подстроки
     * @param int $x_index Индекс длины товара
     * @param int $y_index Индекс высоты товара
     * @param int $z_index Индекс ширины товара
     * @param string $measure Единица измерения (по умолчанию in - дюймы)
     * @return array{x: float|null, y: float|null, z: float|null} Массив, содержащий размеры товара
     */
    public static function getDimsRegexp( string $string, array $regexes, int $x_index = 1, int $y_index = 2, int $z_index = 3, string $measure = 'in' ): array
    {
        $dims = [
            'x' => null,
            'y' => null,
            'z' => null
        ];

        foreach ( $regexes as $regex ) {
            if ( preg_match( $regex, $string, $matches ) ) {
                return self::extractDims( $string, $matches, $x_index, $y_index, $z_index, $measure );
            }
        }

        return $dims;
    }

    /**
     * Автоматически конвертирует размер из наиболее распространенных единиц измерения в указанную
     * @param float|null $dimension Произвольный размер товара
     * @param string|null $string Строка, в которой находится данный размер. Необходима для получения информации об исходной единице измерения
     * @param string $measure Единица измерения (по умолчанию in - дюймы)
     * @return float|null
     */
    public static function autoConvertDims( ?float $dimension, ?string $string, string $measure = 'in' ): ?float
    {
        $regexes = [
            'mm' => '/([^a-z]|^)(mm)([^a-z]|$)/i',
            'cm' => '/([^a-z]|^)(cm)([^a-z]|$)/i',
            'ft' => '/([^a-z]|^)(feet|ft|\'|`)([^a-z]|$)/i',
        ];

        $coincidence = false;
        foreach ( $regexes as $key => $regex ) {
            if ( preg_match( $regex, $string ) ) {
                $coincidence = true;
                break;
            }
        }

        $key = $coincidence ? $key : '';
        if ( $measure === 'in' ) {
            return match ( $key ) {
                'mm' => self::convert( $dimension, 0.039 ),
                'cm' => self::convert( $dimension, 0.39 ),
                'ft' => self::convert( $dimension, 12 ),
                default => $dimension,
            };
        }

        return throw new InvalidArgumentException( "Invalid measure value: '$measure'" );
    }

    #[ArrayShape( [ 'x' => "float|null", 'y' => "float|null", 'z' => "float|null" ] )]
    private static function extractDims( string $original_string, array $match_dims, int $x_index, int $y_index, int $z_index, string $measure = 'in' ): array
    {
        return [
            'x' => isset( $match_dims[ $x_index ] ) ? self::autoConvertDims( StringHelper::getFloat( $match_dims[ $x_index ] ), $original_string, $measure ) : null,
            'y' => isset( $match_dims[ $y_index ] ) ? self::autoConvertDims( StringHelper::getFloat( $match_dims[ $y_index ] ), $original_string, $measure ) : null,
            'z' => isset( $match_dims[ $z_index ] ) ? self::autoConvertDims( StringHelper::getFloat( $match_dims[ $z_index ] ), $original_string, $measure ) : null
        ];
    }

    /**
     * Конвертирует вес из грамм в фунты
     * @param float|null $g_value Вес в граммах
     * @return float|null
     */
    #[Pure] public static function convertLbsFromG( ?float $g_value ): ?float
    {
        return self::convert( $g_value, 0.0022 );
    }

    /**
     * Конвертирует вес из унции в фунты
     * @param float|null $g_value Вес в унции
     * @return float|null
     */
    #[Pure] public static function convertLbsFromOz( ?float $g_value ): ?float
    {
        return self::convert( $g_value, 0.063 );
    }

    /**
     * Конвертирует число из произвольной единицы измерения в произвольную единицу измерения
     * @param float|null $value Значение единицы измерения которую необходимо перевести
     * @param float $contain_value Значение одной единицы измерения по отношению к другой
     * @return float|null
     */
    #[Pure] public static function convert( ?float $value, float $contain_value ): ?float
    {
        return StringHelper::normalizeFloat( $value * $contain_value );
    }
}
