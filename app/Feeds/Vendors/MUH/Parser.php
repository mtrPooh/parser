<?php

namespace App\Feeds\Vendors\MUH;

use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    public function beforeParse(): void
    {
    }

    public function getMpn(): string
    {
        return $this->getText( 'span[itemprop="sku"]' ) ?? '';
    }

    public function getProduct(): string
    {
        return $this->getText( 'h1[itemprop="name"]' ) ?? '';
    }

    public function getCostToUs(): float
    {
        return StringHelper::getMoney( $this->getMoney( 'span[itemprop="price"]' ) );
    }

    public function getImages(): array
    {
        return array_values( array_unique( $this->getSrcImages( 'li.pinterest-image a img' ) ) );
    }
    
    public function getAvail(): ?int
    {
        return self::DEFAULT_AVAIL_NUMBER;
    }
}
