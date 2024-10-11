<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PassportAuthController;
use App\Http\Controllers\Api\AndroidApiSmsController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::post('login', [PassportAuthController::class, 'login']);
Route::get('init', [AndroidApiSmsController::class, 'init']);

Route::middleware('auth:api')->group(function () {

    Route::post('configure/sim', [AndroidApiSmsController::class, 'configureSim']);
    Route::post('sms/logs', [AndroidApiSmsController::class, 'smsfind']);
    Route::post('sms/status/update', [AndroidApiSmsController::class, 'smsStatusUpdate']);
    Route::post('sim/status/update', [AndroidApiSmsController::class, 'simClosed']);
});


Route::middleware('incoming.api', 'sanitizer')->name('incoming.')->group(function () {

    Route::post('email/send', [\App\Http\Controllers\Api\IncomingApi\EmailController::class, 'store'])->name('email.send');
    Route::get('get/email/{uid?}', [\App\Http\Controllers\Api\IncomingApi\EmailController::class, 'getEmailLog']);

    Route::post('sms/send', [\App\Http\Controllers\Api\IncomingApi\SmsController::class, 'store'])->name('sms.send');
    Route::get('get/sms/{uid?}', [\App\Http\Controllers\Api\IncomingApi\SmsController::class, 'getSmsLog']);

    Route::post('whatsapp/send', [\App\Http\Controllers\Api\IncomingApi\WhatsAppController::class, 'store'])->name('whatsapp.send');
    Route::get('get/whatsapp/{uid?}', [\App\Http\Controllers\Api\IncomingApi\WhatsAppController::class, 'getWhatsAppLog']);
});