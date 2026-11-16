<?php

use App\Http\Controllers\PrivateFeedController;
use App\Http\Controllers\AuthController;
use App\Livewire\FeedHub;
use App\Livewire\FilterRuleManager;
use App\Livewire\ProfileSettings;
use App\Livewire\SourceManager;
use App\Livewire\TopicManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/feed', FeedHub::class)->name('feed.index');
    Route::get('/topics', TopicManager::class)->name('topics.index');
    Route::get('/sources', SourceManager::class)->name('sources.index');
    Route::get('/rules', FilterRuleManager::class)->name('rules.index');
    Route::get('/settings', ProfileSettings::class)->name('settings.index');
});

Route::get('/feed/{token}.xml', [PrivateFeedController::class, 'show'])->name('feeds.private');
Route::get('/feed/{token}/{topicSlug}.xml', [PrivateFeedController::class, 'show'])->name('feeds.private.topic');