<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Lexicon;

use App\Domains\Search\Support\Text\TextFolder;
use Illuminate\Support\Facades\Log;

/**
 * Lexicon — الموارد اللغوية لمجموعة scripts، مُجمَّعة عند الطلب.
 *
 * لماذا تُحمَّل حسب الـ script لا حسب اللغة؟
 *   لأن الاستعلام قد يخلط أكثر من script ("iphone سعر")، وعندها نحتاج
 *   الموردَين معاً. تحميلها حسب وسم اللغة الوارد من العميل كان يعني
 *   الاعتماد على قيمة لا يتحكم بها النظام وكثيراً ما تكون خاطئة.
 *
 * لماذا القوائم في resources لا في ثوابت الصنف؟
 *   لأن إضافة لغة يجب أن تكون إضافة ملف، لا تعديل كود مُختبَر. النسخة
 *   السابقة دفنت العربية والإنجليزية في ثوابت داخل خمسة أصناف مختلفة،
 *   فكانت إضافة التركية تعني تعديل خمسة ملفات وإعادة اختبارها كلها.
 *
 * التحميل كسول ومُخزَّن داخل النسخة: استعلام واحد يلمس ملفَّين على الأكثر.
 */
final class Lexicon
{
    private const RESOURCE_DIR = 'search/lexicon';

    private const SHARED = '_shared';

    /**
     * ملفات محمَّلة، بمفتاح الـ script.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $loaded = [];

    /**
     * موارد مُجمَّعة، بمفتاح توقيع مجموعة الـ scripts.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $merged = [];

    /**
     * الموارد المجمَّعة لمجموعة scripts.
     *
     * @param  string[]  $scripts  رموز ISO 15924 كما يعيدها UnicodeScript
     * @return array<string, mixed>
     */
    public function for(array $scripts): array
    {
        $key = $this->signature($scripts);

        return $this->merged[$key] ??= $this->build($scripts);
    }

    /**
     * كلمات الوقف كخريطة بحث O(1).
     *
     * @param  string[]  $scripts
     * @return array<string, true>
     */
    public function stopwords(array $scripts): array
    {
        return $this->flagMap($scripts, 'stopwords');
    }

    /**
     * @param  string[]  $scripts
     * @return array<string, true>
     */
    public function fillers(array $scripts): array
    {
        return $this->flagMap($scripts, 'fillers');
    }

    /**
     * دوالّ النفي مرتّبة من الأطول إلى الأقصر.
     *
     * الترتيب ليس تجميلاً: "ما بدي" و"بدي" كلاهما يطابق بداية النص نفسه،
     * فلو جُرِّب الأقصر أولاً لالتُقط "بدي" كحشو وضاع النفي كلياً.
     *
     * @param  string[]  $scripts
     * @return array<string, int> العبارة => عدد الكلمات المستهلَكة
     */
    public function negationCues(array $scripts): array
    {
        $cues = $this->section($scripts, 'negation_cues');

        uksort($cues, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $cues;
    }

    /**
     * @param  string[]  $scripts
     * @return string[]
     */
    public function temporalCues(array $scripts): array
    {
        return array_values($this->listSection($scripts, 'temporal_cues'));
    }

    /**
     * @param  string[]  $scripts
     * @return array<string, int> العبارة => إزاحة بالسنوات
     */
    public function relativeTime(array $scripts): array
    {
        $cues = $this->section($scripts, 'relative_time');

        uksort($cues, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $cues;
    }

    /**
     * @param  string[]  $scripts
     * @return array<string, string> العبارة => المُعامِل
     */
    public function rangeCues(array $scripts): array
    {
        $cues = $this->section($scripts, 'range_cues');

        uksort($cues, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $cues;
    }

    /**
     * السوابق التي تُجرَّد لتوليد صورة ثانية من الكلمة.
     *
     * ترتيبها من الأطول إلى الأقصر: "بال" يجب أن تُجرَّب قبل "ال"،
     * وإلا جُرِّدت "ال" من "بالايفون" فبقيت "بايفون" — كلمة لا وجود لها.
     *
     * @param  string[]  $scripts
     * @return string[]
     */
    public function strippablePrefixes(array $scripts): array
    {
        $prefixes = $this->listSection($scripts, 'strippable_prefixes');

        usort($prefixes, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $prefixes;
    }

    /**
     * @param  string[]  $scripts
     * @return array<string, string>
     */
    public function transliterations(array $scripts): array
    {
        return $this->section($scripts, 'transliterations');
    }

    /**
     * @param  string[]  $scripts
     * @return array<string, array<string, string>> الكلمة => [مفتاح السمة => قيمتها]
     */
    public function attributes(array $scripts): array
    {
        return $this->section($scripts, 'attributes');
    }

    /**
     * @param  string[]  $scripts
     * @return array<string, array{0:string,1:float}> الكلمة => [النية، الوزن]
     */
    public function intentSignals(array $scripts): array
    {
        return $this->section($scripts, 'intent_signals');
    }

    /**
     * @param  string[]  $scripts
     * @return array<string, array{key:string, factor:float}>
     */
    public function units(array $scripts): array
    {
        return $this->section($scripts, 'units');
    }

    /**
     * @param  string[]  $scripts
     * @return string[]
     */
    public function currencySymbols(array $scripts): array
    {
        return array_values($this->listSection($scripts, 'currency_symbols'));
    }

    /**
     * هل يوجد ملف موارد لهذا الـ script؟
     *
     * غيابه ليس خطأ: اللغة تبقى مدعومة بالتطبيع والتقسيم والترتيب،
     * وتفقد فقط الطبقة المعجمية.
     */
    public function hasResourcesFor(string $script): bool
    {
        return is_file($this->pathFor($script));
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * @param  string[]  $scripts
     * @return array<string, mixed>
     */
    private function build(array $scripts): array
    {
        $merged = $this->load(self::SHARED);

        foreach ($scripts as $script) {
            foreach ($this->load($script) as $section => $entries) {
                $merged[$section] = array_merge($merged[$section] ?? [], $entries);
            }
        }

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $script): array
    {
        $key = mb_strtolower($script, 'UTF-8');

        if (array_key_exists($key, $this->loaded)) {
            return $this->loaded[$key];
        }

        $path = $this->pathFor($key);

        if (! is_file($path)) {
            return $this->loaded[$key] = [];
        }

        $data = require $path;

        if (! is_array($data)) {
            Log::warning('Lexicon: resource file did not return an array', ['script' => $key]);

            return $this->loaded[$key] = [];
        }

        return $this->loaded[$key] = $data;
    }

    private function pathFor(string $script): string
    {
        return resource_path(self::RESOURCE_DIR.'/'.mb_strtolower($script, 'UTF-8').'.php');
    }

    /**
     * قسم مفاتيحه معنوية (خريطة).
     *
     * المفاتيح تمرّ بالتطبيع هنا لا في ملفات الموارد، حتى لا يقع محرِّر
     * الموارد في فخّ كتابة "قَهْوَة" بالتشكيل فلا تُطابَق أبداً.
     *
     * @param  string[]  $scripts
     * @return array<string, mixed>
     */
    private function section(array $scripts, string $section): array
    {
        $raw = $this->for($scripts)[$section] ?? [];
        $normalized = [];

        foreach ($raw as $key => $value) {
            $normalized[TextFolder::fold((string) $key)] = $value;
        }

        return $normalized;
    }

    /**
     * قسم قيمه معنوية (قائمة).
     *
     * @param  string[]  $scripts
     * @return string[]
     */
    private function listSection(array $scripts, string $section): array
    {
        $raw = $this->for($scripts)[$section] ?? [];

        return array_values(array_unique(array_map(
            static fn ($value): string => TextFolder::fold((string) $value),
            $raw
        )));
    }

    /**
     * قائمة → خريطة بحث سريعة.
     *
     * @param  string[]  $scripts
     * @return array<string, true>
     */
    private function flagMap(array $scripts, string $section): array
    {
        return array_fill_keys($this->listSection($scripts, $section), true);
    }

    /**
     * @param  string[]  $scripts
     */
    private function signature(array $scripts): string
    {
        $sorted = array_values(array_unique($scripts));
        sort($sorted);

        return implode(',', $sorted);
    }
}
