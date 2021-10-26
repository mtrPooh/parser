<?php

namespace App\Helpers;

class HtmlHelper
{
    private static array $allowed_tags = [
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

    private static array $single_tags = [
        'br', 'hr', 'img'
    ];

    /**
     * Вырезает теги с javascript кодом, а также теги стилей и гиперссылки.
     * @param string $string
     * @return string
     */
    public static function sanitiseHtml( string $string ): string
    {
        $regexps = [
            '/<script[^>]*?>.*?<\/script>/i',
            '/<noscript[^>]*?>.*?<\/noscript>/i',
            '/<style[^>]*?>.*?<\/style>/i',
            '/<video[^>]*?>.*?<\/video>/i',
            '/<a[^>]*?>.*?<\/a>/i',
            '/<iframe[^>]*?>.*?<\/iframe>/i'
        ];
        return (string)preg_replace( $regexps, '', $string );
    }

    /**
     * Вырезает блочные теги, очищая оставшиеся теги от всех атрибутов.
     * @param string $string
     * @param bool $flag
     * @param array $tags
     * @return string
     */
    public static function cutTags( string $string, bool $flag = true, array $tags = [] ): string
    {
        $string = self::sanitiseHtml( $string );
        $string = self::cutTagsAttributes( $string );

        $string = StringHelper::trim( $string );
        if ( !$flag ) {
            self::$allowed_tags = [];
        }

        if ( !empty( $tags ) ) {
            foreach ( $tags as $tag ) {
                $regexp = '/<(\D+)\s?[^>]*?>/';
                if ( preg_match( $regexp, $tag, $matches ) ) {
                    self::$allowed_tags[] = $matches[ 1 ];
                }
                else {
                    self::$allowed_tags[] = $tag;
                }
            }
        }

        $tags_string = '<' . implode( '><', self::$allowed_tags ) . '>';
        return strip_tags( $string, $tags_string );
    }

    /**
     * Вырезает все атрибуты тегов
     * @param string $string
     * @return string
     */
    public static function cutTagsAttributes( string $string ): string
    {
        return preg_replace( '/(<\w+)([^>]*)(>)/', '$1$3', $string );
    }

    /**
     * Вырезает пустые теги
     * @param string $string
     * @return string
     */
    public static function cutEmptyTags( string $string ): string
    {
        if ( preg_match_all( '/<(?<tag>\w+)>/', $string, $m ) ) {
            $ts = array_unique( $m[ 'tag' ] );
            foreach ( $ts as $t ) {
                if ( !in_array( $t, self::$single_tags, true ) && preg_match_all( "/<$t>(?<content>.*?)<\/$t>/s", $string, $c ) ) {
                    foreach ( $c[ 'content' ] as $e ) {
                        if ( !StringHelper::isNotEmpty( $e ) ) {
                            $string = str_replace( $e, '', $string );
                            $string = preg_replace( "/<$t>(\s+)?<\/$t>/s", '', $string );
                        }
                    }
                }
            }
        }
        return $string;
    }
}