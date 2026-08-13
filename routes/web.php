<?php

use App\Http\Controllers\GlobalController;
use App\Http\Controllers\OpenGraphController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Previews de partilha (WhatsApp, Facebook, ...). O Nginx do frontend
// encaminha os crawlers para aqui; utilizadores normais recebem o SPA.
Route::get('og/eventos/{slug}', [OpenGraphController::class, 'event'])->where('slug', '[A-Za-z0-9._-]+');
Route::get('og/p/{slug}', [OpenGraphController::class, 'promotor'])->where('slug', '[A-Za-z0-9._-]+');
Route::get('og/imagem/{token}', [OpenGraphController::class, 'image'])->where('token', '[A-Za-z0-9_-]+');

// Route::get('sendtwilio',[GlobalController::class,'sendtwilio']);

Route::get('sendsms',[GlobalController::class,'sendSms']);

// Route::get('sendmail',[GlobalController::class,'sendmail']);

// Route::get('downloadticket/{id}',[GlobalController::class,'ticketdownload']);

