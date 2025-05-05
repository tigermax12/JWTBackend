<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PeticioneController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\UserController;
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
Route::controller(UserController::class)->group(function(){
    Route::post('register', 'register');
    Route::get('user/{user}', 'show');
});
Route::controller(PeticioneController::class)->group(function () {
    Route::get('peticiones', 'index');
    //Route::get('mispeticiones', 'listmine');
    Route::get('peticiones/{id}', 'show');
    Route::post('peticiones', 'store');
});
Route::controller(CategoriaController::class)->group(function () {
    Route::post('categorias', 'store');
});
Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('register', 'register');
    Route::post('logout', 'logout');
    Route::post('refresh', 'refresh');
    Route::get('me', 'me');
});
Route::middleware(['auth'])->group(function () {
    //Route::get('peticiones', [PeticioneController::class, 'index']);
    Route::put('/peticiones/{peticion}', [PeticioneController::class, 'update']);
    Route::delete('/peticiones/{peticion}', [PeticioneController::class, 'delete']);
    Route::put('/peticiones/{id}/cambiarEstado', [PeticioneController::class, 'cambiarEstado']);
    Route::post('/peticiones/{peticion}/firmar', [PeticioneController::class, 'firmar']);
    Route::get('/peticiones/user/mine', [PeticioneController::class, 'listMine']);
});
