<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Feeds\Visualization\VisualizationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get( '/', function () {
    return view( 'welcome' );
} );

Route::get( '/feeds/visual/{dx_code}/products', [ VisualizationController::class, 'index' ] );
Route::get( '/feeds/visual/{dx_code}/search', [ VisualizationController::class, 'search' ] );
Route::get( '/feeds/visual/{dx_code}/product/{hash_product}', [ VisualizationController::class, 'product' ] );
Route::get( '/feeds/visual/{dx_code}/products/errors/{general_type}/{type}', [ VisualizationController::class, 'errors' ] );
Route::get( '/feeds/visual/{dx_code}/products/valid', [ VisualizationController::class, 'valid' ] );
