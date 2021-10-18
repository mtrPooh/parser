<?php

namespace App\Feeds\Interfaces;

interface DxRepositoryInterface
{
    public function getDxInfo( string $dx_code, string $storefront = '' ): array;
}
