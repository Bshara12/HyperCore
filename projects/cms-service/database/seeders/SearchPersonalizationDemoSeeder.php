<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Search\Support\Text\Segmenter;
use App\Domains\Search\Support\Text\TextFolder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * بيانات تجريبية لإثبات أثر التخصيص.
 *
 * ─── ما تُظهره ──────────────────────────────────────────────────────
 *
 * مستخدمان يبحثان بالكلمة نفسها فيريان ترتيبين مختلفين، لأن لكلٍّ
 * تاريخ نقر مختلفاً. وهذا هو الاختبار الحاسم للتخصيص: لا يكفي أن
 * يعمل، بل يجب أن يُنتج فرقاً ملحوظاً وقابلاً للتفسير.
 *
 * ─── لماذا اهتمامان متعاكسان ────────────────────────────────────────
 *
 * لو كان للمستخدمَين ذوق متقارب لما ظهر الفرق ولما دلّ الاختبار على
 * شيء. التعاكس يجعل الأثر — إن وُجد — لا لبس فيه.
 *
 * ─── حدود ما تُظهره ─────────────────────────────────────────────────
 *
 * التخصيص مضاعِف محدود بسقف (25% افتراضاً)، فهو يقلب ترتيب نتيجتين
 * متقاربتَي الصلة ولا يرفع نتيجة بعيدة الصلة فوق قريبتها. أي أن
 * الفرق المتوقَّع تبديلُ مواضع لا قلبُ القائمة — وهذا هو السلوك
 * المقصود لا قصورٌ فيه.
 */
class SearchPersonalizationDemoSeeder extends Seeder
{
    /** المستخدم الأول: يميل إلى أجهزة Apple. */
    private const USER_PHONES = 9001;

    /** المستخدم الثاني: يميل إلى الرياضة واللياقة. */
    private const USER_KITCHEN = 9002;

    private const CLICKS_PER_ENTRY = 4;

    public function run(): void
    {
        $projectId = (int) (DB::table('search_indices')
            ->select('project_id', DB::raw('COUNT(*) as c'))
            ->groupBy('project_id')
            ->orderByDesc('c')
            ->value('project_id') ?? 0);

        if ($projectId === 0) {
            $this->command->warn('لا يوجد فهرس بعد — شغّل search:reindex أولاً.');

            return;
        }

        $this->reset($projectId);

        /*
         | المجموعتان مختارتان لتتنافسا على كلمات مشتركة.
         |
         | لو كانت مفرداتهما منفصلة تماماً — هواتف مقابل مطبخ — لما ظهر
         | أثر التخصيص في أي استعلام: كل استعلام يخصّ مجموعة واحدة،
         | وترتيبها محسوم بالصلة النصّية قبل أن يتدخّل التخصيص أصلاً.
         |
         | هنا تشترك المجموعتان في "pro" و"air" و"max" و"15"، فيتنافس
         | صنفان على الصدارة ويرجّح كلُّ مستخدم صنفه — وهذا بالضبط ما
         | يُفترض بالتخصيص أن يفعله.
         */
        $apple = $this->entriesMatching($projectId, [
            'iphone 15 pro max', 'macbook pro', 'ipad pro', 'macbook air', 'apple watch',
        ]);

        $fitness = $this->entriesMatching($projectId, [
            'treadmill pro', 'ninja professional', 'dumbbells set', 'yoga mat', 'nike air max',
        ]);

        if ($apple === [] || $fitness === []) {
            $this->command->warn('لم أجد محتوى كافياً للمجموعتين في المشروع '.$projectId);

            return;
        }

        $this->buildHistory(self::USER_PHONES, $projectId, $apple, ['iphone pro', 'macbook pro', 'ipad']);
        /*
         | كلمات بحث المستخدم الثاني تتجنّب "pro" عمداً.
         |
         | صدى البحث الأخير إشارة قوية بحقّ، فلو بحث هذا المستخدم عن
         | "treadmill pro" لطابقت "pro" كلَّ نتيجة في استعلام العرض،
         | فبلغ المضاعِف حدَّه عند الاثنين وبدا التخصيص بلا أثر —
         | بينما السبب بيانات العرض لا الخوارزمية.
         */
        $this->buildHistory(self::USER_KITCHEN, $projectId, $fitness, ['treadmill folding', 'yoga mat', 'dumbbells set']);

        /*
         | تفضيلات المستخدم مُخزَّنة في الكاش لخمس عشرة دقيقة.
         |
         | بلا إبطالها هنا يبحث المشغّل فور التهيئة فيرى ترتيباً واحداً
         | للمستخدمَين، فيستنتج أن التخصيص لا يعمل — بينما السبب أن
         | الكاش يحمل ملفاً فارغاً حُسب قبل وجود أي نقرة.
         */
        foreach ([self::USER_PHONES, self::USER_KITCHEN] as $userId) {
            Cache::forget("user_preference:{$projectId}:{$userId}");
        }

        $this->report($projectId, $apple, $fitness);
    }

    // ─────────────────────────────────────────────────────────────────

    private function reset(int $projectId): void
    {
        $users = [self::USER_PHONES, self::USER_KITCHEN];

        DB::table('user_click_logs')->where('project_id', $projectId)->whereIn('user_id', $users)->delete();
        DB::table('user_search_logs')->where('project_id', $projectId)->whereIn('user_id', $users)->delete();
    }

    /**
     * مداخل الفهرس التي يطابق عنوانها أيّاً من هذه الكلمات.
     *
     * @param  string[]  $keywords
     * @return array<int, object>
     */
    private function entriesMatching(int $projectId, array $keywords): array
    {
        $query = DB::table('search_indices')
            ->select('entry_id', 'data_type_id', 'title', 'language')
            ->where('project_id', $projectId)
            ->where('status', 'published');

        $query->where(function ($group) use ($keywords) {
            foreach ($keywords as $keyword) {
                $group->orWhere('title_fold', 'like', TextFolder::fold($keyword).'%');
            }
        });

        return $query->limit(6)->get()->all();
    }

    /**
     * سجلّ بحث ونقر لمستخدم واحد.
     *
     * @param  array<int, object>  $entries
     * @param  string[]  $queries
     */
    private function buildHistory(int $userId, int $projectId, array $entries, array $queries): void
    {
        $searchIds = [];

        foreach ($queries as $offset => $keyword) {
            $searchIds[] = DB::table('user_search_logs')->insertGetId([
                'user_id' => $userId,
                'project_id' => $projectId,
                'keyword' => $keyword,
                'language' => 'en',
                'detected_intent' => 'general',
                'intent_confidence' => 0,
                'results_count' => count($entries),
                'session_id' => 'demo-'.$userId,

                /*
                 | أعمار متدرّجة خلال الأسبوع الماضي.
                 |
                 | تاريخ كلّه في لحظة واحدة لا يختبر الاضمحلال الزمني —
                 | وهو بالضبط الموضع الذي كان مقلوباً فيه قبل الإصلاح.
                 */
                'searched_at' => now()->subDays($offset + 1),
            ]);
        }

        foreach ($entries as $position => $entry) {
            for ($repeat = 0; $repeat < self::CLICKS_PER_ENTRY; $repeat++) {
                DB::table('user_click_logs')->insert([
                    'user_id' => $userId,
                    'project_id' => $projectId,
                    'search_log_id' => $searchIds[$repeat % count($searchIds)],
                    'entry_id' => $entry->entry_id,
                    'data_type_id' => $entry->data_type_id,
                    'result_position' => $position + 1,
                    'session_id' => 'demo-'.$userId,
                    'clicked_at' => now()->subDays($repeat),
                ]);
            }
        }
    }

    /**
     * @param  array<int, object>  $apple
     * @param  array<int, object>  $fitness
     */
    private function report(int $projectId, array $apple, array $fitness): void
    {
        $vocabulary = static fn (array $entries): string => implode(', ', array_slice(
            array_unique(array_merge(...array_map(
                static fn (object $e): array => Segmenter::tokenize(TextFolder::fold((string) $e->title)),
                $entries
            ))),
            0,
            6
        ));

        $this->command->info('');
        $this->command->info('تهيئة التخصيص — المشروع '.$projectId);
        $this->command->table(
            ['user_id', 'الميل', 'مداخل نُقر عليها', 'مفردات مكتسَبة'],
            [
                [self::USER_PHONES, 'أجهزة Apple', count($apple), $vocabulary($apple)],
                [self::USER_KITCHEN, 'رياضة ولياقة', count($fitness), $vocabulary($fitness)],
            ]
        );

        $this->command->info('جرّب الاستعلام نفسه بالمستخدمَين وقارن الترتيب:');
        $this->command->line('  GET /api/search?q=pro&lang=en   (X-Project-Id: '.$projectId.')');
        $this->command->line('  GET /api/search?q=air&lang=en');
        $this->command->line('  GET /api/search?q=max&lang=en');
        $this->command->info('');
    }
}
