<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ImageUploadController;
use App\Http\Controllers\GoogleWalletController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/upload-image', [ImageUploadController::class, 'uploadImage']);

Route::post('/upload-google-wallet', [GoogleWalletController::class, 'uploadImage']);

Route::post('/create-wallet-pass', [GoogleWalletController::class, 'createGoogleWalletPass']);


Route::get('/test-google-wallet-auth', [GoogleWalletController::class, 'testGoogleWalletAuth']);

Route::get('/check-review', [GoogleWalletController::class, 'checkReviewStatus']);