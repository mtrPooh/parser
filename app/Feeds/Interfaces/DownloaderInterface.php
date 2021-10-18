<?php

namespace App\Feeds\Interfaces;

use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;

interface DownloaderInterface
{
    public function get( string|Link $link, array $params = [] ): Data;
}
