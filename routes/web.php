<?php

use App\Http\Controllers\GlobalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('sendtwilio',[GlobalController::class,'sendtwilio']);

Route::get('sendsms',[GlobalController::class,'sendSms']);

Route::get('sendmail',[GlobalController::class,'sendmail']);

Route::get('downloadticket/{id}',[GlobalController::class,'ticketdownload']);

