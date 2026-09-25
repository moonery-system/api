<?php

use App\Http\Controllers\AssistantActionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientAddressController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::controller(InviteController::class)->group(function () {
        Route::get('/invite', 'validateToken');
        Route::post('/invite', 'store');
    });

    Route::post('/changePassword', [UserController::class, 'changePassword']);

    // Mesma acao do POST /invite, com publico diferente: aqui e o usuario pedindo,
    // la e o Admin reenviando.
    Route::post('/auth/forgot-password', [InviteController::class, 'store']);
});

Route::middleware('auth:api')->group(function () {
    Route::prefix('/auth')
        ->controller(AuthController::class)
        ->group(function () {
            Route::get('/user', 'user');
            Route::post('/refresh', 'refresh');
            Route::post('/logout', 'logout');
        });

    //chat routes -- a propria conversa nao exige permissao; a caixa de entrada sim.
    //As rotas literais vem antes de /{id}, senao "me" casaria como id.
    Route::controller(ConversationController::class)->group(function () {
        Route::get('/conversations/me', 'me');
        Route::get('/conversations/unread-count', 'unreadCount');
        Route::get('/conversations', 'index')->middleware('can:chat.viewAll');
        Route::get('/conversations/{id}', 'show');
        Route::post('/conversations/{id}/messages', 'storeMessage');
        Route::put('/conversations/{id}/read', 'markAsRead');
    });

    //assistant routes -- the customer's answer to a confirmation the assistant asked for.
    //Only these run the cancellation; nothing typed in the chat does.
    Route::controller(AssistantActionController::class)->group(function () {
        Route::post('/assistant/actions/{id}/confirm', 'confirm')->middleware('can:deliveries.cancel');
        Route::post('/assistant/actions/{id}/reject', 'reject')->middleware('can:deliveries.cancel');
    });

    //notifications routes -- recurso proprio do usuario, sem permissao
    Route::controller(NotificationController::class)->group(function () {
        Route::get('/notifications', 'index');
        Route::get('/notifications/unread-count', 'unreadCount');
        Route::put('/notifications/{id}/read', 'markAsRead');
    });

    //users routes
    Route::controller(UserController::class)->group(function () {
        Route::get('/users', 'index')->middleware('can:users.viewAny');
        Route::post('/users', 'store')->middleware('can:users.create');
        Route::get('/users/{id}', 'show')->middleware('can:users.view');
        Route::put('/users/{id}', 'update')->middleware('can:users.update');
        Route::delete('/users/{id}', 'destroy')->middleware('can:users.delete');
    });

    //roles routes -- lookup do formulario de usuario
    Route::get('/roles', [RoleController::class, 'index'])->middleware('can:users.create');

    //clients routes
    Route::controller(ClientController::class)->group(function () {
        Route::get('/clients', 'index')->middleware('can:clients.viewAny');
        Route::post('/clients', 'store')->middleware('can:clients.create');
        Route::get('/clients/{id}', 'show')->middleware('can:clients.view');
        Route::put('/clients/{id}', 'update')->middleware('can:clients.update');
        Route::delete('/clients/{id}', 'destroy')->middleware('can:clients.delete');
    });

    //clientes addresses routes
    Route::controller(ClientAddressController::class)->group(function () {
        Route::post('/clients/{id}/addresses', 'store')->middleware('can:clients.create');
        Route::put('/clients/{id}/addresses/{addressId}', 'update')->middleware('can:clients.update');
        Route::delete('/clients/{id}/addresses/{addressId}', 'destroy')->middleware('can:clients.delete');
    });

    //delivery routes
    Route::controller(DeliveryController::class)->group(function (){
        Route::get('/deliveries', 'index')->middleware('can:deliveries.viewAny');
        Route::post('/deliveries', 'store')->middleware('can:deliveries.create');
        Route::get('/deliveries/{id}', 'show')->middleware('can:deliveries.view');
        Route::put('/deliveries/{id}/status', 'updateStatus')->middleware('can:deliveries.update');
        Route::post('/deliveries/{id}/cancel', 'cancel')->middleware('can:deliveries.cancel');
        Route::post('/deliveries/{id}/attach', 'attach')->middleware('can:deliveries.attach');
        Route::delete('/deliveries/{id}/attach', 'detach')->middleware('can:deliveries.attach');
        Route::put('/deliveries/{id}/deliveryman', 'assign')->middleware('can:deliveries.assign');
        Route::delete('/deliveries/{id}', 'destroy')->middleware('can:deliveries.delete');
    });
});
