<?php

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;

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

//show home page
Route::get('/', [Controller::class, 'home']);

//show pdf preview page
Route::get('pdfpreview', [Controller::class, 'pdfpreview']);

//show pdf
Route::get('pdf', [Controller::class, 'pdf']);
