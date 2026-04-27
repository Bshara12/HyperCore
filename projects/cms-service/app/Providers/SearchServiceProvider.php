<?php

namespace App\Providers;

use App\Domains\Search\Repositories\Eloquent\EloquentPopularSearchRepository;
use App\Domains\Search\Repositories\Eloquent\EloquentSearchIndexRepository;
use App\Domains\Search\Repositories\Eloquent\EloquentSearchRepository;
use App\Domains\Search\Repositories\Eloquent\EloquentSuggestionRepository;
use App\Domains\Search\Repositories\Eloquent\EloquentUserBehaviorRepository;
use App\Domains\Search\Repositories\Interfaces\PopularSearchRepositoryInterface;
use App\Domains\Search\Repositories\Interfaces\SearchIndexRepositoryInterface;
use App\Domains\Search\Repositories\Interfaces\SearchRepositoryInterface;
use App\Domains\Search\Repositories\Interfaces\SuggestionRepositoryInterface;
use App\Domains\Search\Repositories\Interfaces\UserBehaviorRepositoryInterface;
use App\Domains\Search\Support\IntentDetector;
use App\Domains\Search\Support\KeywordProcessor;
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

    $this->app->singleton(
      KeywordProcessor::class,
      fn($app) => new KeywordProcessor(
        $app->make(SynonymProvider::class),
        $app->make(IntentDetector::class),
      )
    );
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
  }
}
