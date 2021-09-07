<?php

namespace App\Helpers;

use App\Feeds\Utils\ParserCrawler;

class FeedHelper
{
    /**
     * Clears the product description from unnecessary line breaks, spaces, unnecessary and empty tags, garbage in sentences and paragraphs
     * @param string $description Product Description
     * @return string Cleared description
     */
    public static function cleanProductDescription( string $description ): string
    {
        if ( StringHelper::isNotEmpty( $description ) ) {
            $description = self::cleanProductData( $description );
            $description = StringHelper::cutTagsAttributes( $description );
            $description = str_replace( [ '<div>', '</div>' ], [ '<p>', '</p>' ], html_entity_decode( StringHelper::removeSpaces( $description ) ) );

            /** Removes empty tags from the product description **/
            $description = StringHelper::cutEmptyTags( StringHelper::cutTags( $description ) );
        }
        return $description;
    }

    /**
     * Clears the array of product features from empty elements
     * @param array $short_description Product Features
     * @return array Cleared features
     */
    public static function cleanShortDescription( array $short_description ): array
    {
        $short_description = array_map( static fn( $desc ) => StringHelper::removeSpaces( self::cleanProductData( $desc ) ), $short_description );
        return array_filter( $short_description, static fn( $description ) => StringHelper::isNotEmpty( $description ) );
    }

    /**
     * Clears the array of product characteristics from empty elements
     * @param array|null $attributes Product Characteristics
     * @return array|null Cleared characteristics
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
     * Clears the line from garbage in sentences and paragraphs
     * @param string $string
     * @return string
     */
    public static function cleanProductData( string $string ): string
    {
        if ( str_starts_with( trim( StringHelper::removeSpaces( $string ) ), '<' ) ) {
            $crawler = new ParserCrawler( $string );
            $children = $crawler->filter( 'body' )->count() ? $crawler->filter( 'body' )->children() : [];
            foreach ( $children as $child ) {
                /** If the current node contains child nodes, we process them separately **/
                if ( $child->childElementCount ) {
                    foreach ( $child->childNodes as $node ) {
                        $content = $node->ownerDocument->saveHTML( $node );
                        $string = str_replace( $content, self::cleanProductData( $content ), $string );
                    }
                }
                else {
                    $content = $child->ownerDocument->saveHTML( $child );
                    $string = str_replace( $content, self::cleaning( $content ), $string );
                }
            }
        }
        else {
            $string = str_replace( $string, self::cleaning( $string ), $string );
        }
        return $string;
    }

    /**
     * Searches for a substring in a string by a regular expression and deletes or replaces it
     * @param string $string The string in which the search will occur
     * @param array $user_regex Array of custom regular expressions
     * @param bool $replace Clear the entire string if a match was found in it, or delete only the found substring
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
            '/ship(ping)? (methods)? (is)? free/is',
            '/drop ship(ping)?/is',
        ];

        $regexes_other = [
            '/Product Code(:)?(\s+)?.*?(\.|\!|\?|\W)/is',
            '/(\s*)?(\+)?([- _():=+]?\d[- _():=+]?){10,14}(\s*)/',
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
     * Returns the features and characteristics of the product found in the ordered list
     * @param string $list A list containing the "li"tags
     * @param array $short_description Array of product features
     * @param array $attributes Array of product characteristics
     * @return array{short_description: array, attributes: array|null} Returns an array containing
     * [
     *     'short_description' = > array-array of product features
     *     'attributes' => array|null-an array of product characteristics
     * ]
     */
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
     * Returns the features and characteristics of the product found in its description with a regular expression
     * @param string $description Product Description
     * @param array $user_regexes Array of regular expressions
     * @param array $short_description Array of product features
     * @param array $attributes Array of product characteristics
     * @return array{description: string, short_description: array, attributes: array|null} Returns an array containing
     * [
     *     'description' => string - product description cleared of features and characteristics
     *     'short_description' = > array-array of product features
     *     'attributes' => array|null-an array of product characteristics
     * ]
     */
    public static function getShortsAndAttributesInDescription( string $description, array $user_regexes = [], array $short_description = [], array $attributes = [] ): array
    {
        $description = StringHelper::cutTagsAttributes( $description );

        $product_data = [
            'short_description' => $short_description,
            'attributes' => $attributes
        ];

        $regex_pattern = '<(div|p|span|b|strong|h\d|em)>%s(\s+)?((<\/\w+>)+)?:?(\s+)?<\/(div|p|span|b|strong|h\d|em)>(\s+)?((<\w+>)+)?((<\/\w+>)+)?((<\w+>)+)?(\s+)?';

        $keys = [
            'Dimension(s)?',
            'Specification(s)?',
            '(Key)?(\s+)?Benefit(s)?',
            '(Key)?(\s+)?Feature(s)?',
            'Detail(s)?',
        ];

        $regexes_list = [
            '(<(u|o)l>)?(\s+)?(?<content_list><li>.*?<\/li>)(\s+)?<\/(u|o)l>',
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
                foreach ( $match[ 'content_list' ] as $content_list ) {
                    $list_data = [
                        'short_description' => [],
                        'attributes' => []
                    ];
                    if ( isset( $match[ 'delimiter' ] ) ) {
                        $delimiter = array_shift( $match[ 'delimiter' ] );
                        if ( !str_starts_with( $delimiter, '<' ) ) {
                            $delimiter = "<$delimiter>";
                        }
                        $list_data = self::getShortsAndAttributesInList( str_replace( [ "<$delimiter>", "</$delimiter>" ], [ '<li>', '</li>' ], $content_list ) );
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
                    $product_data[ 'short_description' ] = array_merge( $product_data[ 'short_description' ], $list_data[ 'short_description' ] );
                    $product_data[ 'attributes' ] = array_merge( $product_data[ 'attributes' ], $list_data[ 'attributes' ] );
                }
                $description = preg_replace( $regex, '', $description );
            }
        }

        return [
            'description' => self::cleanProductDescription( $description ),
            'short_description' => $product_data[ 'short_description' ],
            'attributes' => $product_data[ 'attributes' ] ?: null
        ];
    }





    /**
     * Gets the dimensions of the product from the line
     * @param string $string A string containing the dimensions
     * @param string $separator Separator, which is used to convert a string into an array with the dimensions of the product
     * @param int $x_index Product length index
     * @param int $y_index Product height index
     * @param int $z_index Product width index
     * @return array{x: float|null, y: float|null, z: float|null} An array containing the dimensions of the product
     */
    public static function getDimsInString( string $string, string $separator, int $x_index = 0, int $y_index = 1, int $z_index = 2 ): array
    {
        $raw_dims = explode( $separator, $string );

        $dims[ 'x' ] = isset( $raw_dims[ $x_index ] ) ? StringHelper::getFloat( $raw_dims[ $x_index ] ) : null;
        $dims[ 'y' ] = isset( $raw_dims[ $y_index ] ) ? StringHelper::getFloat( $raw_dims[ $y_index ] ) : null;
        $dims[ 'z' ] = isset( $raw_dims[ $z_index ] ) ? StringHelper::getFloat( $raw_dims[ $z_index ] ) : null;

        return $dims;
    }

    /**
     * Gets the product dimensions from a string using regular expressions
     * @param string $string A string containing the dimensions
     * @param array $regexes Array of regular expressions for substring search
     * @param int $x_index Product length index
     * @param int $y_index Product height index
     * @param int $z_index Product width index
     * @return array{x: float|null, y: float|null, z: float|null} An array containing the dimensions of the product
     */
    public static function getDimsRegexp( string $string, array $regexes, int $x_index = 1, int $y_index = 2, int $z_index = 3 ): array
    {
        $dims = [
            'x' => null,
            'y' => null,
            'z' => null
        ];

        foreach ( $regexes as $regex ) {
            if ( preg_match( $regex, $string, $matches ) ) {
                $dims[ 'x' ] = isset( $matches[ $x_index ] ) ? StringHelper::getFloat( $matches[ $x_index ] ) : null;
                $dims[ 'y' ] = isset( $matches[ $y_index ] ) ? StringHelper::getFloat( $matches[ $y_index ] ) : null;
                $dims[ 'z' ] = isset( $matches[ $z_index ] ) ? StringHelper::getFloat( $matches[ $z_index ] ) : null;

                return $dims;
            }
        }

        return $dims;
    }

    /**
     * Converts weight from grams to pounds
     * @param float|null $g_value Weight in grams
     * @return float|null
     */
    public static function convertLbsFromG( ?float $g_value ): ?float
    {
        return self::convert( $g_value, 0.0022 );
    }

    /**
     * Converts weight from an ounce to pounds
     * @param float|null $g_value Weight in ounces
     * @return float|null
     */
    public static function convertLbsFromOz( ?float $g_value ): ?float
    {
        return self::convert( $g_value, 0.063 );
    }

    /**
     * Converts a number from an arbitrary unit of measurement to an arbitrary unit of measurement
     * @param float|null $value The value of the unit of measurement to be translated
     * @param float $contain_value The value of one unit of measurement relative to another
     * @return float|null
     */
    public static function convert( ?float $value, float $contain_value ): ?float
    {
        return StringHelper::normalizeFloat( $value * $contain_value );
    }


}