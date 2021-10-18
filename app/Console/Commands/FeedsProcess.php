<?php

namespace App\Console\Commands;

use App\Feeds\Processor\AbstractProcessor;
use App\Feeds\Repositories\DxRepository;
use App\Feeds\Storage\AbstractFeedStorage;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class FeedsProcess extends Command
{
    private const VENDOR_ROOT_PATTERN = 'App\\Feeds\\Vendors\\%s\\Vendor';
    private const STORAGE_ROOT_PATTERN = 'App\\Feeds\\Storage\\';

    /**
     * Команда, которая запускает парсер
     *
     * Dx_code - код дистрибьютора, который состоит из 3-4 букв и цифр.
     * Dev - режим, в котором запускается парсер (dev - режим разработки и отладки, production - режим работы на сервере).
     * Storage - хранилище, в котором будет сохранена информация о товарах.
     *      File - информация сохраняется в json файле локально.
     *      Rabbit - информация отправляется в брокера сообщений - RabbitMQ и после этого сохраняется в базе данных на боевом сервере.
     * Force - опция, с помощью которой система решает как генерировать хэш сумму каждого товара.
     *      Если опция указана, то хэш будет формироваться из метки времени, в которой был обработан товар.
     *      В обратном случае, хэш формируется из информации, которую содержит товар
     * @var string
     */
    protected $signature = 'feed {dx_code} {dev=dev} {storage=file} {--force}';

    protected $description = 'Process feed';

    /**
     * @return mixed
     * @throws Exception
     */
    public function handle(): void
    {
        if ( $this->argument( 'dev' ) === 'dev' ) {
            Config::set( 'env', 'dev' );
        }

        $dx_code = $this->argument( 'dx_code' );
        $processor = self::getVendor( $dx_code, $this->argument( 'storage' ) );

        if ( $this->option( 'force' ) ) {
            $processor->setForce( true );
        }

        print "\nStart feed $dx_code\n";

        $processor->process();

        print "\nDONE!\n";
    }

    /**
     * @throws Exception
     */
    private static function getVendor( string $dx_code, string $storage ): AbstractProcessor
    {
        $class = sprintf( self::VENDOR_ROOT_PATTERN, $dx_code = strtoupper( $dx_code ) );
        if ( class_exists( $class ) ) {
            return new $class( $dx_code, new DxRepository(), self::getStorage( $storage ) );
        }

        // TODO реализовать создание задачи в TeamWork если в продакшн среде не был найден класс для полученного dx_code

        throw new Exception( "Class $class does not exists" );
    }

    /**
     * @throws Exception
     */
    private static function getStorage( string $storage_name ): AbstractFeedStorage
    {
        $storage = self::STORAGE_ROOT_PATTERN . ucfirst( $storage_name ) . 'Storage';
        if ( class_exists( $storage ) ) {
            return new $storage;
        }

        throw new Exception( "Class $storage does not exists" );
    }
}