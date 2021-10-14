<?php

namespace App\Helpers;

use Exception;

class StringHelper
{
    /**
     * Удаляет переносы строк и повторяющиеся пробельные символы
     * @param string $string
     * @return string
     */
    public static function removeSpaces( string $string ): string
    {
        $string = str_replace( "\n", ' ', $string );
        return trim( preg_replace( '/[ \s]+/u', ' ', $string ) );
    }

    /**
     * Удаляет табуляцию, перенос каретки. Удаляет повторяющиеся переносы строк и пробельные символы
     * @param string $string
     * @return string
     */
    public static function normalizeSpaceInString( string $string ): string
    {
        $string = trim( str_replace( ' ', ' ', $string ) );
        $string = preg_replace( '/( )+/', " ", $string );
        $string = preg_replace( [ '/\t+(( )+)?/', '/\r+(( )+)?/' ], '', $string );
        $string = preg_replace( '/\n(( )+)?/', "\n", $string );
        return preg_replace( '/\n+/', "\n", $string );
    }

    /**
     * Приводит json строку к валидному виду, путем экранирования двойных кавычек в тексте
     * @param string $string
     * @return string
     */
    public static function normalizeJsonString( string $string ): string
    {
        try {
            json_decode( $string, true, 512, JSON_THROW_ON_ERROR );

            return $string;
        } catch ( Exception ) {
            $string = stripslashes( self::removeSpaces( self::cutTagsAttributes( $string ) ) );
            $string = str_replace( [ 'true', 'false', 'null' ], [ '"true"', '"false"', '"null"' ], $string );
            $clear_string = '';

            $symbols_in_string = preg_split( "//u", $string, -1, PREG_SPLIT_NO_EMPTY );
            foreach ( $symbols_in_string as $key => $symbol ) {

                /** Является ли кавычка открывающей в ключе или значении в json строке **/
                $is_left = true;

                /** Является ли кавычка закрывающей в ключе или значении в json строке **/
                $is_right = true;

                if ( $symbol === '"' ) {

                    /** Получаем первый символ слева от кавычки **/
                    $symbol_before_quote = $symbols_in_string[ $key - 1 ];

                    /** Если символ является числом, кавычка может являться указателем на единицу измерения (дюйм) **/
                    if ( is_numeric( $symbol_before_quote ) ) {
                        $is_left = false;
                    }

                    /** Если символ является пробелом или запятой, получаем следующий символ перед ним **/
                    if ( $symbol_before_quote === ' ' ) {
                        $symbol_before_quote = $symbols_in_string[ $key - 2 ];
                        if ( $symbol_before_quote === ',' ) {
                            $symbol_before_quote = $symbols_in_string[ $key - 3 ];
                            if ( $symbol_before_quote === ' ' ) {
                                $symbol_before_quote = $symbols_in_string[ $key - 4 ];
                            }
                        }
                    }
                    elseif ( $symbol_before_quote === ',' ) {
                        $symbol_before_quote = $symbols_in_string[ $key - 2 ];
                        if ( $symbol_before_quote === ' ' ) {
                            $symbol_before_quote = $symbols_in_string[ $key - 3 ];
                        }
                    }

                    if ( !is_numeric( $symbol_before_quote ) && !in_array( $symbol_before_quote, [ '[', '{', ':', '"', '}', ']' ] ) ) {
                        $is_left = false;
                    }

                    /** Получаем первый символ справа от кавычки **/
                    $symbol_after_quote = $symbols_in_string[ $key + 1 ];

                    /** Если символ является кавычкой, значит текущая кавычка может являться указателем на единицу измерения (дюйм) **/
                    if ( $symbol_after_quote === '"' ) {
                        $is_right = false;
                    }

                    /** Если символ является пробелом или запятой, получаем следующий символ после него **/
                    if ( $symbol_after_quote === ' ' ) {
                        $symbol_after_quote = $symbols_in_string[ $key + 2 ];
                        if ( $symbol_after_quote === ',' ) {
                            $symbol_after_quote = $symbols_in_string[ $key + 3 ];
                            if ( $symbol_after_quote === ' ' ) {
                                $symbol_after_quote = $symbols_in_string[ $key + 4 ];
                            }
                        }
                    }
                    elseif ( $symbol_after_quote === ',' ) {
                        $symbol_after_quote = $symbols_in_string[ $key + 2 ];
                        if ( $symbol_after_quote === ' ' ) {
                            $symbol_after_quote = $symbols_in_string[ $key + 3 ];
                        }
                    }

                    /** Если символ является кавычкой, и текущая кавычка является открывающей ищем следующую кавычку в строке **/
                    if ( $symbol_after_quote === '"' && $is_right ) {
                        foreach ( $symbols_in_string as $sub_key => $sub_symbol ) {

                            /** Если символ является кавычкой и его индекс в строке больше индекса последней найденной кавычки, начинаем его обработку **/
                            if ( $sub_key > $key + 4 && $sub_symbol === '"' ) {

                                /** Получаем первый символ справа от кавычки **/
                                $symbol_after_quote = $symbols_in_string[ $sub_key + 1 ];

                                /** Если символ является пробелом или запятой, получаем следующий символ после него **/
                                if ( $symbol_after_quote === ' ' ) {
                                    $symbol_after_quote = $symbols_in_string[ $sub_key + 2 ];
                                    if ( $symbol_after_quote === ',' ) {
                                        $symbol_after_quote = $symbols_in_string[ $sub_key + 3 ];
                                        if ( $symbol_after_quote === ' ' ) {
                                            $symbol_after_quote = $symbols_in_string[ $sub_key + 4 ];
                                        }
                                    }
                                }
                                elseif ( $symbol_after_quote === ',' ) {
                                    $symbol_after_quote = $symbols_in_string[ $sub_key + 2 ];
                                    if ( $symbol_after_quote === ' ' ) {
                                        $symbol_after_quote = $symbols_in_string[ $sub_key + 3 ];
                                    }
                                }

                                /** После первого выполнения условия выходим из цикла, чтобы не перебирать все символы до конца строки **/
                                break;
                            }
                        }
                    }

                    if ( !in_array( $symbol_after_quote, [ ':', '"', '}', ']' ] ) ) {
                        $is_right = false;
                    }

                    /** Если кавычка не является ни открывающей, ни закрывающей, экранируем ее **/
                    if ( !$is_left && !$is_right ) {
                        $symbols_in_string[ $key ] = "\\$symbol";
                    }
                }
                $clear_string .= $symbols_in_string[ $key ];
            }
            return str_replace( [ '"true"', '"false"', '"null"' ], [ 'true', 'false', 'null' ], $clear_string );
        }
    }

    /**
     * Разбивает текст на абзацы по указанному количеству предложений, если исходный текст не содержит html тегов
     * @param string $string Текст без html тегов
     * @param int $size Количество предложений в одном абзаце
     * @return string Отформатированный текст
     */
    public static function paragraphing( string $string, int $size = 3 ): string
    {
        if ( $string === strip_tags( $string ) ) {
            $text = '';
            $paragraphs = array_chunk( preg_split( '/(?<=[.?!;])\s+(?=\p{Lu})/u', $string, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ), $size );
            foreach ( $paragraphs as $paragraph ) {
                $string = implode( ' ', $paragraph );
                $text .= "<p>$string</p>";
            }
            return $text;
        }
        return $string;
    }

    /**
     * Проверяет, является ли строка не пустой
     * @param string|null $string
     * @return bool
     */
    public static function isNotEmpty( ?string $string ): bool
    {
        if ( empty( $string ) ) {
            return false;
        }
        return !empty( preg_replace( '/\s+/', '', self::removeSpaces( strip_tags( $string ) ) ) );
    }

    /**
     * Вырезает блочные теги и теги гиперссылки с их содержимым, очищая оставшиеся теги от всех атрибутов.
     * @param string $string
     * @param bool $flag
     * @param array $tags
     * @return null|string
     */
    public static function cutTags( string $string, bool $flag = true, array $tags = [] ): ?string
    {
        $mass = [
            'span',
            'p',
            'br',
            'b',
            'strong',
            'ol',
            'ul',
            'li',
            'table',
            'thead',
            'tbody',
            'th',
            'tr',
            'td',
        ];

        $regexps = [
            '/<script[^>]*?>.*?<\/script>/i',
            '/<noscript[^>]*?>.*?<\/noscript>/i',
            '/<style[^>]*?>.*?<\/style>/i',
            '/<video[^>]*?>.*?<\/video>/i',
            '/<a[^>]*?>.*?<\/a>/i',
            '/<iframe[^>]*?>.*?<\/iframe>/i'
        ];
        foreach ( $regexps as $regexp ) {
            if ( preg_match( $regexp, $string ) ) {
                $string = (string)preg_replace( $regexp, '', $string );
            }
        }

        $string = self::mb_trim( $string );
        if ( !$flag ) {
            $mass = [];
        }

        if ( !empty( $tags ) && is_array( $tags ) ) {
            foreach ( $tags as $tag ) {
                $regexp = '/<(\D+)\s?[^>]*?>/';
                if ( preg_match( $regexp, $tag, $matches ) ) {
                    $mass[] = $matches[ 1 ];
                }
                else {
                    $mass[] = $tag;
                }
            }
        }

        $tags_string = '';
        foreach ( $mass as $tag ) {
            $tags_string .= "<$tag>";
        }

        $string = preg_replace( '~(<)(</?\w+?>)~', '$1 $2', $string );
        $string = strip_tags( $string, $tags_string );
        foreach ( $mass as $tag ) {

            $regexp = "/(<$tag)([^r>]*)(>)/i";

            if ( preg_match( $regexp, $string ) ) {
                $string = (string)preg_replace( $regexp, '$1$3', $string );
            }
        }
        return $string;
    }

    /**
     * Вырезает все атрибуты тегов
     * @param string $string
     * @return string
     */
    public static function cutTagsAttributes( string $string ): string
    {
        return preg_replace( '/(<[a-z0-9]+)([^>]*)(>)/i', '$1$3', $string );
    }

    /**
     * Вырезает пустые теги
     * @param string $string
     * @return string
     */
    public static function cutEmptyTags( string $string ): string
    {
        $clean_regex = [
            '/<[u|o]l>((\s+)?<li>(\s+)?)+<\/[u|o]l>/is',
            '/<(\w+){0,1}[^br]>(\s+)?((<br>(\s+)?)+)?(\s+)?<\/\w+>/i'
        ];
        $string = preg_replace( $clean_regex, '', self::normalizeSpaceInString( $string ) );
        foreach ( $clean_regex as $regex ) {
            if ( preg_match( $regex, $string ) ) {
                $string = self::cutEmptyTags( $string );
            }
        }
        return $string;
    }


    public static function mb_ucfirst( $string, $encoding = 'UTF-8' ): string
    {
        $strlen = mb_strlen( $string, $encoding );
        $firstChar = mb_substr( $string, 0, 1, $encoding );
        $then = mb_substr( $string, 1, $strlen - 1, $encoding );
        return mb_strtoupper( $firstChar, $encoding ) . $then;
    }

    public static function ucWords( $string ): string
    {
        return preg_replace_callback( '/([a-z])([a-z0-9\'"]+|\W+)/iu', static function ( $match ) {
            return strtoupper( $match[ 1 ] ) . $match[ 2 ];
        }, $string );
    }

    /**
     * @param $string
     * @param string|string[] $trim_chars
     * @return string
     */
    public static function mb_trim( $string, array|string $trim_chars = "\s" ): string
    {
        return (string)preg_replace( '/^[' . $trim_chars . ']*(?U)(.*)[' . $trim_chars . ']*$/u', '\\1', $string );
    }


    private static function UPC_calculate_check_digit( $upc_code ): int
    {
        $sum = 0;
        $mult = 3;
        for ( $i = ( strlen( $upc_code ) - 2 ); $i >= 0; $i-- ) {
            $sum += $mult * $upc_code[ $i ];
            if ( $mult === 3 ) {
                $mult = 1;
            }
            else {
                $mult = 3;
            }
        }
        if ( $sum % 10 === 0 ) {
            $sum %= 10;
        }
        else {
            $sum = 10 - ( $sum % 10 );
        }
        return $sum;
    }

    private static function isISBN( $sCode ): bool
    {
        $bResult = false;
        if ( in_array( strlen( $sCode ), [ 10, 13 ], true ) && in_array( substr( $sCode, 0, 3 ), [ 978, 979 ], true ) ) {
            $bResult = true;
        }
        return $bResult;
    }

    public static function calculateUPC( $upc_code ): array|string|null
    {
        $upc_code = preg_replace( '/[\D]/', '', $upc_code );
        switch ( strlen( $upc_code ) ) {
            case 14:
                $cd = self::UPC_calculate_check_digit( $upc_code );
                if ( $cd !== $upc_code[ strlen( $upc_code ) - 1 ] ) {
                    return substr( $upc_code, 0, -1 ) . $cd;
                }
                return $upc_code;
            case 11:
            case 12:
            case 13:
                $cd = self::UPC_calculate_check_digit( $upc_code );
                if ( $cd !== $upc_code[ strlen( $upc_code ) - 1 ] ) {
                    if ( !self::isISBN( $upc_code ) || ( self::isISBN( $upc_code ) && strlen( $upc_code ) === 12 ) ) {
                        $cd = self::UPC_calculate_check_digit( $upc_code . '1' );
                        return $upc_code . $cd;
                    }
                    return '';
                }
                return $upc_code;
        }
        return '';
    }


    /**
     * parser size inch or foot from string to inch float
     *
     * @param string $size inch/foot string ex. 1 2/3" or 2.5'
     * @return null|float float when successful parse else false
     */
    public static function parseInch( string $size ): ?float
    {
        $replacements = [
            '”' => '"',
            '’' => '\'',
            '¼' => '1/4',
            '½' => '1/2',
            '¾' => '3/4',
        ];

        $size = str_replace( array_keys( $replacements ), array_values( $replacements ), $size );
        $size = trim( $size );

        if ( preg_match( '/[\d]+\.?[\d]*?/', $size ) === 0 ) {
            return null;
        }

        $mul = $size[ strlen( $size ) - 1 ] === '"' ? 1 : 12;
        $size = trim( $size, '"\'' );
        $parts = explode( ' ', $size );
        $int = 0;

        if ( is_numeric( $parts[ 0 ] ) ) {
            $int = $parts[ 0 ];
            $float = $parts[ 1 ] ?? null ?: 0;
        }
        else {
            $float = $parts[ 0 ];
        }

        if ( !is_numeric( $float ) && str_contains( $float, '/' ) ) {
            $parts = explode( '/', $float );
            if ( is_numeric( $parts[ 0 ] ) && is_numeric( $parts[ 1 ] ) ) {
                $float = (float)$parts[ 0 ] / (float)$parts[ 1 ];
            }
        }

        return ( (float)$int + (float)$float ) * $mul;
    }

    public static function getMoney( string $price ): float
    {
        $price = str_replace( ',', '', $price );
        preg_match( '/(\d+)?(\.?\d+(\.?\d+)?)/', $price, $matches );
        return (float)( $matches[ 0 ] ?? 0.0 );
    }

    public static function existsMoney( string $string ): string
    {
        $currency = [
            '\\\u00a3', '&pound;', '\$', '£'
        ];
        foreach ( $currency as $c ) {
            if ( preg_match( "/$c(\s+)?((\d+)?(\.?\d+))/", $string, $match ) ) {
                return $match[ 0 ];
            }
        }
        return '';
    }

    public static function getFloat( ?string $string, ?float $default = null ): ?float
    {
        $replacements = [
            '¼' => ' 1/4',
            '½' => ' 1/2',
            '¾' => ' 3/4',
        ];

        $string = str_replace( array_keys( $replacements ), array_values( $replacements ), $string );
        $string = trim( $string );
        if ( preg_match( '/(?<integer>\d+\s)?(-)?(\s+)?(?<fractional>\.?\d+(\.?\/?\d+)?)/', str_replace( ',', '', $string ), $match_float ) ) {
            if ( isset( $match_float[ 'fractional' ] ) && str_contains( $match_float[ 'fractional' ], '/' ) ) {
                [ $divisible, $divisor ] = explode( '/', $match_float[ 'fractional' ] );
                $match_float[ 'fractional' ] = $divisible / $divisor;
            }
            return self::normalizeFloat( isset( $match_float[ 'integer' ] )
                ? (float)$match_float[ 'integer' ] + (float)$match_float[ 'fractional' ]
                : (float)$match_float[ 'fractional' ], $default );
        }
        return $default;
    }

    public static function normalizeFloat( ?float $float, ?float $default = null ): ?float
    {
        $float = round( $float, 2 );
        return $float > 0.00 ? $float : $default;
    }


    public static function normalizeSrcLink( $link, $url, $always_root = false ): string
    {
        if ( empty( trim( $link ) ) ) {
            return '';
        }
        $first_slash = str_starts_with( $link, '/' );
        $link = ltrim( str_replace( [ '../', './', '\\' ], '', trim( $link ) ), '/' );
        $parsed_domain = parse_url( $url );
        $path = '';

        if ( !$first_slash && !$always_root ) {
            $path = $parsed_domain[ 'path' ] ?? '';
            if ( $path ) {
                $path = trim( $path, '/' );

                if ( !str_contains( $path, '/' ) && str_contains( $path, '.' ) ) {
                    $path = '';
                }
                elseif ( str_contains( $path, '/' ) ) {
                    $path = str_contains( $path, '.' ) ? substr( $path, 0, ( strrpos( $path, '/' ) ) ) : $path;
                }
                $path = str_ends_with( $path, '/' ) || empty( $path ) ? $path : "$path/";
            }
        }
        $cleared_link = str_contains( $link, '/' ) ? $link : "/$link";

        preg_match( '~^(?:(?<protocol>(?:ht|f)tps?)://)?(?<domain_name>[\pL\d.-]+\.(?<zone>\pL{2,4}))~iu', $cleared_link, $matches );

        if ( empty( $matches[ 'domain_name' ] ) ) {
            $cleared_link = $parsed_domain[ 'scheme' ] . '://' . $parsed_domain[ 'host' ] . '/' . $path . trim( $cleared_link, '/' );
        }
        elseif ( empty( $matches[ 'protocol' ] ) ) {
            $cleared_link = $parsed_domain[ 'scheme' ] . '://' . trim( $cleared_link, '/' );
        }

        return str_replace( ' ', '%20', $cleared_link );
    }


}
