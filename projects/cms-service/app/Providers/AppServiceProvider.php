<?php

namespace App\Providers;

use App\Domains\AI\Providers\AIProviderChain;
use App\Domains\AI\Providers\GeminiProvider;
use App\Domains\AI\Providers\OllamaProvider;
use App\Domains\AI\Providers\OpenRouterProvider;
use App\Domains\AI\Repositories\Eloquent\EloquentAiConversationRepository;
use App\Domains\AI\Repositories\Interface\AiConversationRepositoryInterface;
use App\Domains\Auth\Repository\Elequment\ProjectUserRepository;
use App\Domains\Auth\Repository\Interface\ProjectUserRepositoryInterface;
use App\Domains\CMS\Analytics\Repositories\AnalyticsRepositoryInterface;
use App\Domains\CMS\Analytics\Repositories\EloquentCmsAnalyticsRepository;
use App\Domains\CMS\Read\Repositories\EntryProjectReadRepository;
use App\Domains\CMS\Read\Repositories\EntryProjectReadRepositoryInterface;
use App\Domains\CMS\Read\Repositories\EntryReadRepository;
use App\Domains\CMS\Read\Repositories\EntryReadRepositoryInterface;
use App\Domains\CMS\Read\Repositories\EntryVersionReadRepository;
use App\Domains\CMS\Read\Repositories\EntryVersionReadRepositoryInterface;
use App\Domains\CMS\Repositories\Eloquent\DataCollectionRepositoryEloquent;
use App\Domains\CMS\Repositories\Interface\DataEntryVersionRepository;
use App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use App\Domains\CMS\Repositories\Eloquent\EloquentDataEntryRepository;
use App\Domains\CMS\Repositories\Eloquent\EloquentDataEntryValueRepository;
use App\Domains\CMS\Repositories\Eloquent\EloquentDataEntryVersionRepository;
use App\Domains\CMS\Repositories\Eloquent\EloquentProjectRepository;
use App\Domains\CMS\Repositories\Eloquent\EloquentSeoEntryRepository;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;
use App\Domains\CMS\Repositories\Eloquent\DataTypeRepositoryEloquent;
use App\Domains\CMS\Repositories\Eloquent\EloquentDataEntryRelationRepository;
use App\Domains\CMS\Repositories\Eloquent\FieldRepositoryEloquent;
use App\Domains\CMS\Repositories\Eloquent\RatingRepository;
use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\DataEntryRelationRepository;
use App\Domains\CMS\Repositories\Interface\FieldRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\RatingRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\SeoEntryRepository;
use App\Domains\Payment\Repositories\EloquentPaymentRepository;
use App\Domains\Payment\Repositories\PaymentRepositoryInterface;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    $this->app->bind(DataTypeRepositoryInterface::class, DataTypeRepositoryEloquent::class);
    $this->app->bind(FieldRepositoryInterface::class, FieldRepositoryEloquent::class);
    $this->app->bind(DataEntryRepositoryInterface::class, EloquentDataEntryRepository::class);
    $this->app->bind(DataCollectionRepositoryInterface::class, DataCollectionRepositoryEloquent::class);
    $this->app->bind(PaymentRepositoryInterface::class, EloquentPaymentRepository::class);
    $this->app->bind(AnalyticsRepositoryInterface::class, EloquentCmsAnalyticsRepository::class);
    $this->app->bind(AiConversationRepositoryInterface::class, EloquentAiConversationRepository::class);
    $this->app->bind(
      ProjectRepositoryInterface::class,
      EloquentProjectRepository::class
    );
    $this->app->bind(
      DataEntryRepositoryInterface::class,
      EloquentDataEntryRepository::class
    );

    $this->app->bind(
      DataEntryValueRepository::class,
      EloquentDataEntryValueRepository::class
    );

    $this->app->bind(
      SeoEntryRepository::class,
      EloquentSeoEntryRepository::class
    );
    $this->app->bind(
      DataEntryRepositoryInterface::class,
      EloquentDataEntryRepository::class
    );

    $this->app->bind(
      DataEntryVersionRepository::class,
      EloquentDataEntryVersionRepository::class
    );
    $this->app->bind(ProjectRepositoryInterface::class, EloquentProjectRepository::class);
    $this->app->bind(
      DataEntryRelationRepository::class,
      EloquentDataEntryRelationRepository::class
    );
    $this->app->bind(
      EntryReadRepositoryInterface::class,
      EntryReadRepository::class
    );

    $this->app->bind(
      EntryVersionReadRepositoryInterface::class,
      EntryVersionReadRepository::class
    );
    $this->app->bind(
      ProjectUserRepositoryInterface::class,
      ProjectUserRepository::class
    );
    $this->app->bind(
      EntryProjectReadRepositoryInterface::class,
      EntryProjectReadRepository::class
    );
    $this->app->bind(
      RatingRepositoryInterface::class,
      RatingRepository::class
    );

    $this->app->singleton(AIProviderChain::class, function ($app) {
      return new AIProviderChain(
        openRouter: $app->make(OpenRouterProvider::class)
      );
    });
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    //
    Relation::enforceMorphMap([
      'project' => \App\Models\Project::class,
      'data' => \App\Models\DataEntry::class,
    ]);
  }
}
