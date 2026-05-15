<?php

namespace App\Providers;

use App\Domains\Search\Repositories\Eloquent\EloquentPopularSearchRepository;
use App\Domains\Search\Repositories\Eloquent\EloquentSearchIndexRepository;
use App\Domains\Search\Repositories\Eloquent\EloquentSearchRepository;
use App\Domains\Search\Repositories\Eloquent\EloquentSuggestionRepository;
use App\Domains\Search\Repositories\Eloquent\EloquentSynonymSuggestionRepository;
use App\Domains\Search\Repositories\Eloquent\EloquentUserBehaviorRepository;
use App\Domains\Search\Repositories\Interfaces\PopularSearchRepositoryInterface;
use App\Domains\Search\Repositories\Interfaces\SearchIndexRepositoryInterface;
use App\Domains\Search\Repositories\Interfaces\SearchRepositoryInterface;
use App\Domains\Search\Repositories\Interfaces\SuggestionRepositoryInterface;
use App\Domains\Search\Repositories\Interfaces\SynonymSuggestionRepositoryInterface;
use App\Domains\Search\Repositories\Interfaces\UserBehaviorRepositoryInterface;
use App\Domains\Search\Services\AIQueryInterpreter;
use App\Domains\Search\Services\KeyboardLayoutFixer;
use App\Domains\Search\Services\SearchCacheService;
use App\Domains\Search\Support\EntityExtractor;
use App\Domains\Search\Support\IntentDetector;
use App\Domains\Search\Support\KeywordProcessor;
use App\Domains\Search\Support\SynonymExpander;
use App\Domains\Search\Support\SynonymProvider;
use App\Domains\Search\Support\UserPreferenceAnalyzer;
use Illuminate\Support\ServiceProvider;

class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {

        $this->app->bind(SearchIndexRepositoryInterface::class, EloquentSearchIndexRepository::class);
        $this->app->bind(SearchRepositoryInterface::class, EloquentSearchRepository::class);

        $this->app->singleton(SynonymProvider::class);
        $this->app->singleton(IntentDetector::class);

        // $this->app->singleton(
        //   KeywordProcessor::class,
        //   fn($app) => new KeywordProcessor(
        //     $app->make(SynonymProvider::class),
        //     $app->make(IntentDetector::class),
        //   )
        // );
        $this->app->bind(
            UserBehaviorRepositoryInterface::class,
            EloquentUserBehaviorRepository::class
        );

        $this->app->singleton(UserPreferenceAnalyzer::class);
        $this->app->bind(
            SuggestionRepositoryInterface::class,
            EloquentSuggestionRepository::class
        );
        $this->app->bind(
            PopularSearchRepositoryInterface::class,
            EloquentPopularSearchRepository::class
        );
        $this->app->bind(
            SynonymSuggestionRepositoryInterface::class,
            EloquentSynonymSuggestionRepository::class
        );

        $this->app->singleton(SynonymExpander::class);

        $this->app->singleton(
            KeywordProcessor::class,
            fn ($app) => new KeywordProcessor(
                $app->make(SynonymProvider::class),
                $app->make(IntentDetector::class),
                $app->make(SynonymExpander::class),  // ← إضافة
            )
        );
        $this->app->singleton(AIQueryInterpreter::class);
        $this->app->singleton(KeyboardLayoutFixer::class);
        $this->app->singleton(SearchCacheService::class);
        $this->app->singleton(EntityExtractor::class);
        $this->app->singleton(\App\Domains\Search\Services\AIQueryInterpreter::class);

    }
}
