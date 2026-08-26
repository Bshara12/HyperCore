<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Search\Repositories\Eloquent\EloquentPopularSearchRepository;
use App\Domains\Search\Repositories\Eloquent\EloquentSearchIndexQueryRepository;
use App\Domains\Search\Repositories\Eloquent\EloquentSearchIndexRepository;
use App\Domains\Search\Repositories\Eloquent\EloquentSuggestionRepository;
use App\Domains\Search\Repositories\Eloquent\EloquentSynonymSuggestionRepository;
use App\Domains\Search\Repositories\Eloquent\EloquentUserBehaviorRepository;
use App\Domains\Search\Repositories\Interfaces\PopularSearchRepositoryInterface;
use App\Domains\Search\Repositories\Interfaces\SearchIndexQueryRepositoryInterface;
use App\Domains\Search\Repositories\Interfaces\SearchIndexRepositoryInterface;
use App\Domains\Search\Repositories\Interfaces\SuggestionRepositoryInterface;
use App\Domains\Search\Repositories\Interfaces\SynonymSuggestionRepositoryInterface;
use App\Domains\Search\Repositories\Interfaces\UserBehaviorRepositoryInterface;
use App\Domains\Search\Services\AI\GeminiProvider;
use App\Domains\Search\Services\AI\OpenRouterProvider;
use App\Domains\Search\Services\AI\ProviderQueryInterpreter;
use App\Domains\Search\Services\AI\QueryInterpreterInterface;
use App\Domains\Search\Support\Indexing\AttributeNormalizer;
use App\Domains\Search\Support\Indexing\SearchDocumentBuilder;
use App\Domains\Search\Support\Lexicon\Lexicon;
use App\Domains\Search\Support\Lexicon\ProjectSynonyms;
use App\Domains\Search\Support\Query\QueryAnalyzer;
use App\Domains\Search\Support\Ranking\Bm25fScorer;
use App\Domains\Search\Support\Ranking\PersonalizationScorer;
use App\Domains\Search\Support\Ranking\ResultRanker;
use App\Domains\Search\Support\Ranking\SignalScorer;
use App\Domains\Search\Support\Rescue\KeyboardLayoutMapper;
use App\Domains\Search\Support\Rescue\VocabularyMatcher;
use App\Domains\Search\Support\Retrieval\BooleanQueryBuilder;
use App\Domains\Search\Support\UserPreferenceAnalyzer;
use Illuminate\Support\ServiceProvider;

/**
 * تركيب نطاق البحث.
 *
 * ─── لماذا معظمها singleton ────────────────────────────────────────
 *
 * أصناف الفهم والترتيب عديمة الحالة عدا ذاكرة داخلية للموارد: المعجم
 * يحمّل ملفاته مرّة، والمُسجِّلون يقرؤون الضبط في الباني. إنشاؤها لكل
 * استدعاء يعني إعادة قراءة الضبط وتحميل الموارد في كل بحث.
 *
 * والذاكرة الداخلية آمنة هنا لأنها لا تعتمد على الطلب: موارد المعجم
 * لا تختلف بين مستخدم وآخر. الأصناف التي تحمل حالة خاصّة بالطلب —
 * كتفضيلات المستخدم — تعتمد على Cache لا على خصائص النسخة.
 */
class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerRepositories();
        $this->registerUnderstanding();
        $this->registerRanking();
        $this->registerIndexing();
        $this->registerAi();
    }

    // ─────────────────────────────────────────────────────────────────

    private function registerRepositories(): void
    {
        $this->app->bind(SearchIndexRepositoryInterface::class, EloquentSearchIndexRepository::class);
        $this->app->bind(SearchIndexQueryRepositoryInterface::class, EloquentSearchIndexQueryRepository::class);
        $this->app->bind(UserBehaviorRepositoryInterface::class, EloquentUserBehaviorRepository::class);
        $this->app->bind(SuggestionRepositoryInterface::class, EloquentSuggestionRepository::class);
        $this->app->bind(PopularSearchRepositoryInterface::class, EloquentPopularSearchRepository::class);
        $this->app->bind(SynonymSuggestionRepositoryInterface::class, EloquentSynonymSuggestionRepository::class);
    }

    private function registerUnderstanding(): void
    {
        $this->app->singleton(Lexicon::class);
        $this->app->singleton(ProjectSynonyms::class);
        $this->app->singleton(QueryAnalyzer::class);
        $this->app->singleton(BooleanQueryBuilder::class);
        $this->app->singleton(KeyboardLayoutMapper::class);
        $this->app->singleton(VocabularyMatcher::class);
        $this->app->singleton(UserPreferenceAnalyzer::class);
    }

    private function registerRanking(): void
    {
        $this->app->singleton(Bm25fScorer::class);
        $this->app->singleton(SignalScorer::class);
        $this->app->singleton(PersonalizationScorer::class);
        $this->app->singleton(ResultRanker::class);
    }

    private function registerIndexing(): void
    {
        $this->app->singleton(AttributeNormalizer::class);
        $this->app->singleton(SearchDocumentBuilder::class);
    }

    /**
     * سلسلة المزوّدين، مرتّبة حسب الأفضلية.
     *
     * لا يُسجَّل مزوّد بلا مفتاح: تسجيله يعني محاولةً محكومة بالفشل
     * في كل استدعاء، تستهلك من مهلة السلسلة وترفع عدّاد قاطع الدارة
     * فتُغلقه على المزوّد السليم بسبب عطب غيره.
     */
    private function registerAi(): void
    {
        $this->app->singleton(QueryInterpreterInterface::class, function (): ProviderQueryInterpreter {
            $providers = [];

            if (! empty(config('services.gemini.api_key'))) {
                $providers[] = $this->app->make(GeminiProvider::class);
            }

            if (! empty(config('services.openrouter.api_key'))) {
                $providers[] = $this->app->make(OpenRouterProvider::class);
            }

            return new ProviderQueryInterpreter($providers);
        });
    }
}
