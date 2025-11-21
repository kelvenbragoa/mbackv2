<?php

use App\Http\Controllers\Api\mobile\client\ClientAuthController;
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

Route::post('register', [UserAuthController::class, 'register']);
Route::post('login', [UserAuthController::class, 'login']);
Route::post('updatepassword', [UserAuthController::class, 'login']);
Route::post('logout', [UserAuthController::class, 'logout'])->middleware(Sanctum::class);;
// ->middleware('auth:sanctum');

Route::resource('homepage', WelcomePageController::class);
Route::resource('eventos', UserEventsController::class);
Route::resource('checkout', UserCheckOutController::class);
Route::resource('categories', UserCategoriesController::class);
Route::resource('cashless', UserCashlessController::class);
Route::post('cashless-recharge', [UserCashlessController::class, 'recharge']);




Route::middleware([Sanctum::class])->group(function () {
    Route::get('auxiliar-event/{id}', [PromotorEventsController::class, 'auxiliar']);
    Route::get('promotor-bar/{id}/copy', [PromotorBarController::class, 'copy']);
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
    Route::post('promotor-customers-bulk', [PromotorCustomerInviteController::class, 'storebulk']);


    Route::resource('admin-transacoes', AdminTransactionController::class);
    Route::resource('admin-tickets', AdminTicketsController::class);

    Route::get('promotor-dashboard/{id}/bilhetes', [PromotorDashboardController::class, 'bilhetes']);
    Route::get('promotor-dashboard/{id}/pacotes', [PromotorDashboardController::class, 'pacotes']);
    Route::get('promotor-dashboard/{id}/convites', [PromotorDashboardController::class, 'convites']);
    Route::get('promotor-dashboard/{id}/lineups', [PromotorDashboardController::class, 'lineups']);

    Route::get('download-report/{id}/products', [PromotorDashboardController::class, 'bar_report']);

    Route::get('download-report/{id}/tickets', [PromotorDashboardController::class, 'ticket_report']);


    Route::get('download-invite/{id}', [PromotorCustomerInviteController::class, 'downloadinvite']);
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
Route::delete('/cart/{id}/user/{userid}', [\App\Http\Controllers\Api\mobile\protocols\CartsController::class, 'destroy']);
Route::get('/get-status/{id}', [\App\Http\Controllers\Api\mobile\protocols\TicketsController::class, 'status']);


Route::get('/allinvites/{id}', [\App\Http\Controllers\Api\mobile\protocols\InvitesController::class, 'index']);
Route::get('/doneinvites/{id}', [\App\Http\Controllers\Api\mobile\protocols\InvitesController::class, 'done']);
Route::get('/pendinginvites/{id}', [\App\Http\Controllers\Api\mobile\protocols\InvitesController::class, 'pending']);
Route::get('/invite/{id}', [\App\Http\Controllers\Api\mobile\protocols\InvitesController::class, 'invitesdetail']);
Route::get('/verifyinvite/{id}', [\App\Http\Controllers\Api\mobile\protocols\InvitesController::class, 'verifyinvites']);
Route::get('/get-status-invite/{id}', [\App\Http\Controllers\Api\mobile\protocols\InvitesController::class, 'status']);

//ROTAS BARMAN

Route::post('/barman-login', [\App\Http\Controllers\Api\mobile\barman\AuthController::class, 'login']);
Route::get('/barman-user/{id}', [\App\Http\Controllers\Api\mobile\barman\AuthController::class, 'user']);
Route::get('/barman-home/{id}', [\App\Http\Controllers\Api\mobile\barman\HomeController::class, 'index']);
Route::get('/barman-products/{id}/barstore/{bar_store_id}', [\App\Http\Controllers\Api\mobile\barman\ProductsController::class, 'index']);
Route::get('/barman-product/{id}', [\App\Http\Controllers\Api\mobile\barman\ProductsController::class, 'productdetail']);
Route::post('barman-carts', [\App\Http\Controllers\Api\mobile\barman\CartController::class, 'store']);
Route::get('barman-cart/{id}', [\App\Http\Controllers\Api\mobile\barman\CartController::class, 'index']);
Route::delete('/barman-cart/{id}/user/{userid}', [\App\Http\Controllers\Api\mobile\barman\CartController::class, 'destroy']);
Route::post('/barman-sells', [\App\Http\Controllers\Api\mobile\barman\SellController::class, 'store']);
Route::get('/barman-sells/{id}', [\App\Http\Controllers\Api\mobile\barman\SellController::class, 'index']);
Route::get('/barman-sells-detail/{id}', [\App\Http\Controllers\Api\mobile\barman\SellController::class, 'selldetails']);
Route::delete('/barman-sell/{id}/user/{userid}', [\App\Http\Controllers\Api\mobile\barman\SellController::class, 'destroy']);
Route::get('/verifyreceipt/{id}/user/{userid}', [\App\Http\Controllers\Api\mobile\barman\SellController::class, 'verifyreceipt']);
Route::get('/barman-register-card/{id}/{userid}', [\App\Http\Controllers\Api\mobile\barman\CardController::class, 'registerCard']);
Route::get('/barman-view-card/{id}', [\App\Http\Controllers\Api\mobile\barman\CardController::class, 'viewCard']);
Route::get('/barman-topup-card/{id}/{top}/{userid}', [\App\Http\Controllers\Api\mobile\barman\CardController::class, 'topUpCard']);
Route::get('/barman-get-status/{id}', [\App\Http\Controllers\Api\mobile\barman\SellController::class, 'status']);
Route::get('/barman-operation/{id}', [\App\Http\Controllers\Api\mobile\barman\SellController::class, 'operation']);
Route::get('/barman-refund-card/{id}/{userid}', [\App\Http\Controllers\Api\mobile\barman\CardController::class, 'refund']);




Route::prefix('client')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [ClientAuthController::class, 'login']);
        Route::post('/register', [ClientAuthController::class, 'register']);
    });

    // Rotas protegidas (autenticadas)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [ClientAuthController::class, 'logout']);
        Route::post('/logout-all', [ClientAuthController::class, 'logoutAll']);
        Route::get('/me', [ClientAuthController::class, 'me']);

        // ========== EVENTOS ==========
        Route::prefix('events')->group(function () {
            Route::get('/featured', [\App\Http\Controllers\Api\Mobile\Client\EventController::class, 'featured']);
            Route::get('/upcoming', [\App\Http\Controllers\Api\Mobile\Client\EventController::class, 'upcoming']);
            Route::get('/search', [\App\Http\Controllers\Api\Mobile\Client\EventController::class, 'search']);
            Route::get('/suggestions', [\App\Http\Controllers\Api\Mobile\Client\EventController::class, 'suggestions']);
            Route::get('/favorites', [\App\Http\Controllers\Api\Mobile\Client\EventController::class, 'favorites']);
            Route::post('/{id}/toggle-favorite', [\App\Http\Controllers\Api\Mobile\Client\EventController::class, 'toggleEvent']);
            Route::get('/{id}', [\App\Http\Controllers\Api\Mobile\Client\EventController::class, 'show']);
        });

        // ========== CATEGORIAS ==========
        Route::prefix('categories')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\mobile\client\CategoryController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Api\mobile\client\CategoryController::class, 'show']);
            Route::get('/{id}/events', [\App\Http\Controllers\Api\mobile\client\CategoryController::class, 'events']);
        });

        // ========== FAVORITOS ==========
        Route::prefix('favorites')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\mobile\client\FavoriteController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\mobile\client\FavoriteController::class, 'store']);
            Route::delete('/{eventId}', [\App\Http\Controllers\Api\mobile\client\FavoriteController::class, 'destroy']);
            Route::get('/check', [\App\Http\Controllers\Api\mobile\client\FavoriteController::class, 'check']);
            Route::get('/count', [\App\Http\Controllers\Api\mobile\client\FavoriteController::class, 'count']);
        });

        // ========== INGRESSOS ==========
        Route::prefix('tickets')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\mobile\client\TicketController::class, 'index']);
            Route::get('/count', [\App\Http\Controllers\Api\mobile\client\TicketController::class, 'count']);
            Route::get('/{ticketId}', [\App\Http\Controllers\Api\mobile\client\TicketController::class, 'show']);
            Route::post('/{ticketId}/validate', [\App\Http\Controllers\Api\mobile\client\TicketController::class, 'validate']);
            Route::get('/{ticketId}/transfer-options', [\App\Http\Controllers\Api\mobile\client\TicketController::class, 'transferOptions']);
        });

        // ========== BUSCA ==========
        Route::prefix('search')->group(function () {
            Route::get('/popular', [\App\Http\Controllers\Api\mobile\client\EventController::class, 'popularSearches']);
        });

        // ========== BANNERS ==========
        Route::prefix('banners')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\mobile\client\BannerController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Api\mobile\client\BannerController::class, 'show']);
        });

        Route::post('/usercheckout', [\App\Http\Controllers\Api\mobile\client\ClientCheckOutController::class, 'store']);

    });

            



    // ========== ROTAS PÚBLICAS (sem autenticação) ==========
    Route::get('/categories', [\App\Http\Controllers\Api\mobile\client\CategoryController::class, 'index']);
    Route::get('/events/featured', [\App\Http\Controllers\Api\mobile\client\EventController::class, 'featured']);
    Route::get('/events/search', [\App\Http\Controllers\Api\mobile\client\EventController::class, 'search']);
    Route::get('/events/{id}', [\App\Http\Controllers\Api\mobile\client\EventController::class, 'show']);
    Route::get('/search/popular', [\App\Http\Controllers\Api\mobile\client\EventController::class, 'popularSearches']);
    Route::get('/banners', [\App\Http\Controllers\Api\mobile\client\BannerController::class, 'index']);
    
});
