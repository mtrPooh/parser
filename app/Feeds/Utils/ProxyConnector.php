<?php

namespace App\Feeds\Utils;

use App\Feeds\Downloader\HttpDownloader;
use App\Helpers\HttpHelper;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;

class ProxyConnector
{
    public function connect( HttpDownloader $downloader, Link $link, int $connection_limit = 50 ): void
    {
        $connect = 0;
        while ( true ) {
            if ( !$downloader->getStaticUserAgent() ) {
                $downloader->setUserAgent( HttpHelper::getUserAgent() );
            }

            if ( $connect >= $connection_limit ) {
                $downloader->setUseProxy( false );
                $downloader->getClient()->setProxy( null );
                break;
            }

            if ( $this->callConnect( $downloader, $link ) ) {
                break;
            }
            $connect++;
        }
    }

    private function callConnect( HttpDownloader $downloader, Link $link ): bool
    {
        $downloader->getClient()->setRequestTimeOut( 10 );

        $connection = false;
        $proxy = Proxy::getProxy();
        $downloader->getClient()->setProxy( $proxy );

        $response = null;

        $promise = $downloader->getClient()->request( $link->getUrl(), $link->getParams(), $link->getMethod(), $link->getTypeParams() )->then(
            function ( Response $res ) use ( &$response ) {
                $response = $res;
            },
            function ( RequestException $exc ) use ( &$exception ) {
                $exception = $exc;
            }
        );
        $promise->wait();

        if ( $response ) {
            $connection = true;
            print PHP_EOL . "Use proxy: $proxy" . PHP_EOL;
        }
        elseif ( $response = $exception->getResponse() ) {
            $status = $response->getStatusCode();
            if ( in_array( $status, [ 200, 404 ], true ) ) {
                $connection = true;
                print PHP_EOL . "Use proxy: $proxy" . PHP_EOL;
            }
            else {
                print PHP_EOL . 'Proxy response code: ' . $status . PHP_EOL;
            }
        }
        $downloader->getClient()->setRequestTimeOut( $downloader->timeout_s );
        return $connection;
    }
}
