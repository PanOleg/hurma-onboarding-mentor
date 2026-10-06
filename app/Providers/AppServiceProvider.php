<?php

namespace App\Providers;

use App\Chat\Models\Conversation;
use App\Chat\Policies\ConversationPolicy;
use App\Knowledge\Models\Document;
use App\Knowledge\Policies\DocumentPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        RateLimiter::for('chat', fn (Request $request) => Limit::perMinute(20)->by((string) $request->user()?->id));
    }
}
