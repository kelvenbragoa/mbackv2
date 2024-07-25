<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserAuthController;
use App\Http\Controllers\Api\web\admin\AdminTicketsController;
use App\Http\Controllers\Api\web\admin\AdminTransactionController;
use App\Http\Controllers\Api\web\NotficationController;
use App\Http\Controllers\Api\web\promotor\PromotorBarController;
use App\Http\Controllers\Api\web\promotor\PromotorBarmanController;
use App\Http\Controllers\Api\web\promotor\PromotorCustomerInviteController;
use App\Http\Controllers\Api\web\promotor\PromotorDashboardController;
use App\Http\Controllers\Api\web\promotor\PromotorEventsController;
use App\Http\Controllers\Api\web\promotor\PromotorInviteController;
use App\Http\Controllers\Api\web\promotor\PromotorLineUpsController;
use App\Http\Controllers\Api\web\promotor\PromotorPackageController;
use App\Http\Controllers\Api\web\promotor\PromotorProductsController;
use App\Http\Controllers\Api\web\promotor\PromotorProfileController;
use App\Http\Controllers\Api\web\promotor\PromotorProtocoloController;
use App\Http\Controllers\Api\web\promotor\PromotorTicketController;
use App\Http\Controllers\Api\web\user\UserCashlessController;
use App\Http\Controllers\Api\web\user\UserCategoriesController;
use App\Http\Controllers\Api\web\user\UserCheckOutController;
use App\Http\Controllers\Api\web\user\UserEventsController;
use App\Http\Controllers\Api\web\user\WelcomePageController;
use App\Http\Middleware\Sanctum;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('register',[UserAuthController::class,'register']);
Route::post('login',[UserAuthController::class,'login']);
Route::post('updatepassword',[UserAuthController::class,'login']);
Route::post('logout',[UserAuthController::class,'logout'])->middleware('auth:sanctum');;
// ->middleware('auth:sanctum');

Route::resource('homepage', WelcomePageController::class);
Route::resource('eventos', UserEventsController::class);
Route::resource('checkout', UserCheckOutController::class);
Route::resource('categories', UserCategoriesController::class);
Route::resource('cashless', UserCashlessController::class);
Route::post('cashless-recharge',[UserCashlessController::class,'recharge']);




Route::middleware('auth:sanctum')->group(function () {
    Route::get('auxiliar-event/{id}',[PromotorEventsController::class,'auxiliar']);
    Route::get('promotor-bar/{id}/copy',[PromotorBarController::class,'copy']);
    Route::resource('promotor-eventos', PromotorEventsController::class);
    Route::resource('promotor-tickets', PromotorTicketController::class);
    Route::resource('promotor-invites', PromotorInviteController::class);
    Route::resource('promotor-packages', PromotorPackageController::class);
    Route::resource('promotor-bar', PromotorBarController::class);
    Route::resource('promotor-lineups', PromotorLineUpsController::class);
    Route::resource('promotor-products', PromotorProductsController::class);
    Route::resource('promotor-dashboard', PromotorDashboardController::class);
    Route::resource('promotor-protocolo', PromotorProtocoloController::class);
    Route::resource('promotor-barman', PromotorBarmanController::class);
    Route::resource('promotor-customers', PromotorCustomerInviteController::class);
    Route::resource('promotor-profile', PromotorProfileController::class);
    Route::resource('notifications', NotficationController::class);

    Route::resource('admin-transacoes', AdminTransactionController::class);
    Route::resource('admin-tickets', AdminTicketsController::class);

});

//ROTAS PROTOCOLO
Route::post('/protocol-login', [\App\Http\Controllers\Api\mobile\protocols\AuthController::class, 'login']);
Route::get('/protocol-user/{id}', [\App\Http\Controllers\Api\mobile\protocols\AuthController::class, 'user']);
// Route::get('/category',[\App\Http\Controllers\Api\mobile\CategoryController::class,'index']);
// Route::get('/category/{id}',[\App\Http\Controllers\Api\mobile\CategoryController::class,'show']);
Route::get('/home/{id}', [\App\Http\Controllers\Api\mobile\protocols\HomeController::class, 'index']);
Route::get('/alltickets/{id}', [\App\Http\Controllers\Api\mobile\protocols\TicketsController::class, 'index']);
Route::get('/donetickets/{id}', [\App\Http\Controllers\Api\mobile\protocols\TicketsController::class, 'done']);
Route::get('/pendingtickets/{id}', [\App\Http\Controllers\Api\mobile\protocols\TicketsController::class, 'pending']);
Route::get('/ticket/{id}', [\App\Http\Controllers\Api\mobile\protocols\TicketsController::class, 'ticketdetail']);
Route::get('/verifyticket/{id}', [\App\Http\Controllers\Api\mobile\protocols\TicketsController::class, 'verifyticket']);
Route::get('/products/{id}', [\App\Http\Controllers\Api\mobile\protocols\ProductsController::class, 'index']);
Route::get('/product/{id}', [\App\Http\Controllers\Api\mobile\protocols\ProductsController::class, 'productdetail']);
Route::post('carts', [\App\Http\Controllers\Api\mobile\protocols\CartsController::class, 'store']);
Route::get('/cart/{id}', [\App\Http\Controllers\Api\mobile\protocols\CartsController::class, 'index']);
Route::post('/sells', [\App\Http\Controllers\Api\mobile\protocols\SellController::class, 'store']);
Route::get('/sells/{id}', [\App\Http\Controllers\Api\mobile\protocols\SellController::class, 'index']);
Route::get('/sells-detail/{id}', [\App\Http\Controllers\Api\mobile\protocols\SellController::class, 'selldetails']);
Route::delete('/cart/{id}/user/{userid}',[\App\Http\Controllers\Api\mobile\protocols\CartsController::class,'destroy']);
Route::get('/get-status/{id}', [\App\Http\Controllers\Api\mobile\protocols\TicketsController::class, 'status']);
//ROTAS BARMAN

Route::post('/barman-login', [\App\Http\Controllers\Api\mobile\barman\AuthController::class, 'login']);
Route::get('/barman-user/{id}', [\App\Http\Controllers\Api\mobile\barman\AuthController::class, 'user']);
Route::get('/barman-home/{id}', [\App\Http\Controllers\Api\mobile\barman\HomeController::class, 'index']);
Route::get('/barman-products/{id}/barstore/{bar_store_id}', [\App\Http\Controllers\Api\mobile\barman\ProductsController::class, 'index']);
Route::get('/barman-product/{id}', [\App\Http\Controllers\Api\mobile\barman\ProductsController::class, 'productdetail']);
Route::post('barman-carts', [\App\Http\Controllers\Api\mobile\barman\CartController::class, 'store']);
Route::get('barman-cart/{id}', [\App\Http\Controllers\Api\mobile\barman\CartController::class, 'index']);
Route::delete('/barman-cart/{id}/user/{userid}',[\App\Http\Controllers\Api\mobile\barman\CartController::class,'destroy']);
Route::post('/barman-sells', [\App\Http\Controllers\Api\mobile\barman\SellController::class, 'store']);
Route::get('/barman-sells/{id}', [\App\Http\Controllers\Api\mobile\barman\SellController::class, 'index']);
Route::get('/barman-sells-detail/{id}', [\App\Http\Controllers\Api\mobile\barman\SellController::class, 'selldetails']);
Route::delete('/barman-sell/{id}/user/{userid}',[\App\Http\Controllers\Api\mobile\barman\SellController::class,'destroy']);
Route::get('/verifyreceipt/{id}/user/{userid}', [\App\Http\Controllers\Api\mobile\barman\SellController::class, 'verifyreceipt']);
Route::get('/barman-register-card/{id}/{userid}', [\App\Http\Controllers\Api\mobile\barman\CardController::class, 'registerCard']);
Route::get('/barman-view-card/{id}', [\App\Http\Controllers\Api\mobile\barman\CardController::class, 'viewCard']);
Route::get('/barman-topup-card/{id}/{top}/{userid}', [\App\Http\Controllers\Api\mobile\barman\CardController::class, 'topUpCard']);
Route::get('/barman-get-status/{id}', [\App\Http\Controllers\Api\mobile\barman\SellController::class, 'status']);
Route::get('/barman-operation/{id}', [\App\Http\Controllers\Api\mobile\barman\SellController::class, 'operation']);
Route::get('/barman-refund-card/{id}/{userid}', [\App\Http\Controllers\Api\mobile\barman\CardController::class, 'refund']);
