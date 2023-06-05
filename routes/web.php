<?php
use App\Http\Controllers\MainController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get('/youtube', [MainController::class, 'index']);
Route::get('/youtube/auth', [MainController::class, 'create']);
Route::get('/youtube/update-title', [MainController::class, 'update']);
