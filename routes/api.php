<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

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

Route::get('/posts', [PostController::class, 'index']);
Route::get('/download/{slug}', [PostController::class, 'download']);

Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
    Route::post('/forgot', 'forgotPassword');
    Route::post('/reset', 'resetPassword');
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::controller(AuthController::class)->group(function () {
        Route::get('/user', 'user');
        Route::delete('/logout', 'logout');
    });

    Route::controller(PostController::class)->group(function () {
        Route::get('/posts_auth', 'posts');
        Route::post('/post', 'create');
        Route::get('/post/{slug}', 'show');
        Route::get('/favorite_posts', 'favoritesPosts');
        Route::get('/my_posts', 'myPosts');
        Route::post('/update_post', 'update');
        Route::delete('/delete_post/{slug}', 'delete');
        Route::post('/like_post', 'likePost');
    });

    Route::controller(ProfileController::class)->group(function () {
        Route::post('update_profile', 'updateProfileData');
        Route::put('update_password', 'updatePassword');
        Route::put('change_nsfw', 'changeNSFW');
        Route::delete('delete_user', 'deleteUser');
    });
});
