<?php

namespace App\Feeds\Utils;

use App\Helpers\HttpHelper;
use DateTime;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class Proxy
{
    /**
     * @return string Возвращает валидный адрес прокси для использования
     * @throws Exception
     */
    public static function getProxy(): string
    {
        $proxy_invalid = null;
        $proxies = self::getProxyCheckerNetProxies();

        if ( $proxies ) {
            while ( !$proxy_invalid ) {
                $current_proxy = $proxies[ $proxy_index = random_int( 0, count( $proxies ) - 1 ) ];

                try {
                    $proxy_invalid = true;
                } catch ( Exception $e ) {
                    print "Error: {$e->getMessage()}\n";
                    unset( $proxies[ $proxy_index ] );
                }
            }
            return $current_proxy ?? '';
        }
        return '';
    }

    /**
     * Получает по API список доступных прокси и помещает их в кэш
     * @return array Массив доступных валидных прокси
     */
    private static function getProxyCheckerNetProxies(): array
    {
        if ( $valid_proxies = Cache::get( 'proxies' ) ) {
            return $valid_proxies;
        }

        $valid_proxies = [];
        $total = 0;

        while ( !$total ) {
            $date = new DateTime( 'now' );

            $api_urls = [
                'https://www.proxyscan.io/download?type=https&country=all',
                'https://api.proxyscrape.com/?request=getproxies&proxytype=http&timeout=1000&country=all&ssl=yes&anonymity=all'
            ];

            foreach ( $api_urls as $api_url ) {
                if ( ( $response = self::fetchProxies( $api_url ) ) ) {
                    $response = str_replace( "\r", '', $response );

                    foreach ( explode( "\n", $response ) as $item ) {
                        if ( HttpHelper::validateProxyIpPort( $item ) ) {
                            $valid_proxies[] = $item;
                        }
                    }
                }
            }

            if ( !count( $valid_proxies ) ) {
                $url = 'https://checkerproxy.net/api/archive/' . $date->format( 'Y-m-d' );
                if ( ( $response = self::fetchProxies( $url ) ) && $json = json_decode( $response, true ) ) {
                    foreach ( $json as $items ) {
                        if ( (int)$items[ 'type' ] === 2 && HttpHelper::validateProxyIpPort( $items[ 'addr' ] ) ) {
                            $valid_proxies[] = $items[ 'addr' ];
                        }
                    }
                }
            }

            $total = count( $valid_proxies );
        }
        return Cache::remember( 'proxies', 6 * 60, function () use ( $valid_proxies ) {
            return $valid_proxies;
        } );
    }

    private static function fetchProxies( $url ): ?string
    {
        echo "Get proxies list $url \n";
        $c = new Client();
        try {
            return $c->get( $url )->getBody()->getContents();
        } catch ( Exception ) {
            return '';
        }
    }
}
