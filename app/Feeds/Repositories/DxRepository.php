<?php

namespace App\Feeds\Repositories;

use App\Feeds\Interfaces\DxRepositoryInterface;
use App\Feeds\Utils\HttpClient;
use Exception;

class DxRepository implements DxRepositoryInterface
{
    private HttpClient $client;

    private const API_DX_URL = 'https://www.artistsupplysource.com/api/dx/';

    public function __construct()
    {
        $this->client = new HttpClient( 'https://www.artistsupplysource.com' );
    }

    public function getDxInfo( string $dx_code, ?string $storefront = null ): array
    {
        $response = $this->client->request( self::API_DX_URL . $dx_code . ( $storefront ? "/$storefront" : '' ) )->wait();
        try {
            $result = json_decode( $response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR );
        } catch ( Exception ) {
            $result = [];
        }
        return $result;
    }
}
