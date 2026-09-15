<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\_Test\OnlineUsers;
use App\Http\Controllers\Books\BooksController;
use App\Http\Controllers\Books\AuthorController;
use App\Http\Controllers\Books\DeweyDecimalController;
use Illuminate\Http\Request;
// use App\Http\Controllers\Books\AuthorController;


    // User Route
        Route::post('user/register/admin', [RegisterController::class, 'createAdmin']);
        Route::post('user/register/client', [RegisterController::class, 'clientRegister']);
        Route::get('user/admin/index', [RegisterController::class, 'indexAdmin']);
        Route::get('user/client/index', [RegisterController::class, 'indexClient']);

        Route::post('user/login', [LoginController::class, 'login']);
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('user/logout', [LogoutController::class, 'logout']);
        });

        Route::middleware('auth:sanctum')->get('/user/me', [LoginController::class, 'me']);
    // User Route

    // Books Route
        Route::post('/book/register', [BooksController::class, 'registerBook']);

        Route::get('/book/index', [BooksController::class, 'bookIndex']); // <- Show all books and its copy(ies)

        Route::post('book/dewey-decimal/register', [DeweyDecimalController::class, 'callnumberRegister']);


        Route::post('book/author/register', [AuthorController::class, 'authorRegister']);
        Route::get('book/author/index', [AuthorController::class, 'authorIndex']);


        Route::post('book/dewey-decimal/batch-import', [DeweyDecimalController::class, 'batchImport']);
        Route::post('book/dewey-decimal/callnumber-register', [DeweyDecimalController::class, 'callnumberRegister']);
    // Books Route



    // API Checker
        Route::get('test', function () {
            return ['message' => 'API routes working'];
        });

        Route::get('/online-check', [OnlineUsers::class, 'checkStatus']);
    // API Checker
