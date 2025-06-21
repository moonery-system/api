<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::controller(InviteController::class)->group(function () {
        Route::get('/invite', 'validateToken');
        Route::post('/invite', 'store');
    });

    Route::post('/changePassword', [UserController::class, 'changePassword']);
});

Route::middleware('auth:api')->group(function () {
    Route::prefix('/auth')
        ->controller(AuthController::class)
        ->group(function () {
            Route::get('/user', 'user');
            Route::post('/logout', 'logout');
        });

    //users routes
    Route::controller(UserController::class)->group(function () {
        Route::get('/users', 'index')->middleware('can:users.viewAny');
        Route::post('/users', 'store')->middleware('can:users.create');
        Route::get('/users/{id}', 'show')->middleware('can:users.view');
        Route::put('/users/{id}', 'update')->middleware('can:users.update');
        Route::delete('/users/{id}', 'destroy')->middleware('can:users.delete');
    });

    //clients routes
    Route::controller(ClientController::class)->group(function () {
        Route::get('/clients', 'index')->middleware('can:clients.viewAny');
        Route::post('/clients', 'store')->middleware('can:clients.create');
        Route::get('/clients/{id}', 'show')->middleware('can:clients.view');
        Route::put('/clients/{id}', 'update')->middleware('can:clients.update');
        Route::delete('/clients/{id}', 'destroy')->middleware('can:clients.delete');
    });

    //delivery routes
    Route::controller(DeliveryController::class)->group(function (){
        Route::get('/deliveries', 'index')->middleware('can:deliveries.viewAny');
        Route::post('/deliveries', 'store')->middleware('can:deliveries.create');
        Route::get('/deliveries/{id}', 'show')->middleware('can:deliveries.view');
        Route::put('/deliveries/update-status/{id}', 'updateStatus')->middleware('can:deliveries.update');
        Route::delete('/deliveries/{id}', 'delete')->middleware('can:deliveries.delete');
    });
});
