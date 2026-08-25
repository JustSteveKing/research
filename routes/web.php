<?php

use App\Http\Controllers\ContradictionController;
use App\Http\Controllers\FactController;
use App\Http\Controllers\FollowUpQuestionController;
use App\Http\Controllers\ResearchRunController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ThreadChatController;
use App\Http\Controllers\ThreadController;
use App\Http\Controllers\ThreadExportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/threads')->name('home');

Route::resource('threads', ThreadController::class)->except(['create', 'edit']);

Route::post('threads/{thread}/pin', [ThreadController::class, 'pin'])->name('threads.pin');
Route::post('threads/{thread}/rebuild', [ThreadController::class, 'rebuild'])->name('threads.rebuild');

Route::post('threads/{thread}/runs', [ResearchRunController::class, 'store'])->name('threads.runs.store');
Route::delete('threads/{thread}/runs/{run}', [ResearchRunController::class, 'destroy'])->name('threads.runs.destroy');

Route::patch('threads/{thread}/facts/{fact}', [FactController::class, 'update'])->name('threads.facts.update');
Route::delete('threads/{thread}/facts/{fact}', [FactController::class, 'destroy'])->name('threads.facts.destroy');
Route::post('threads/{thread}/facts/{fact}/recheck', [FactController::class, 'recheck'])->name('threads.facts.recheck');

Route::patch('threads/{thread}/contradictions/{contradiction}', [ContradictionController::class, 'update'])->name('threads.contradictions.update');

Route::get('threads/{thread}/export/{format}', ThreadExportController::class)->name('threads.export');

Route::patch('threads/{thread}/follow-ups/{question}', [FollowUpQuestionController::class, 'update'])->name('threads.follow-ups.update');

Route::post('threads/{thread}/chat', [ThreadChatController::class, 'store'])->name('threads.chat.store');
Route::get('threads/{thread}/chat/stream', [ThreadChatController::class, 'stream'])->name('threads.chat.stream');

Route::get('search', SearchController::class)->name('search');

Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
Route::post('settings/skip-setup', [SettingsController::class, 'skipSetup'])->name('settings.skip-setup');
