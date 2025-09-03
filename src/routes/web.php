<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DocumentController;
Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister']);
Route::post('/register', [AuthController::class, 'register']);

// auth routes

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DocumentController::class, 'index'])->name('dashboard');
    Route::post('/documents/upload', [DocumentController::class, 'upload'])->name('documents.upload');
    Route::get('/documents/{id}/check', [DocumentController::class, 'check'])->name('documents.check');
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::post('/chat/ask', [ChatController::class, 'ask'])->name('chat.ask'); // ✅ tambahin ini
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::get('/documents/{id}/receipt', [DocumentController::class, 'receipt'])
     ->middleware('auth')
     ->name('documents.receipt');
