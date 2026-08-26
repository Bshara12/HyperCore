<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Rescue;

use App\Domains\Search\Support\Text\UnicodeScript;

/**
 * KeyboardLayoutMapper — عكس الكتابة بلوحة مفاتيح خاطئة.
 *
 * ─── الحالة ─────────────────────────────────────────────────────────
 *
 * يريد المستخدم "iphone" ولسان الإدخال عربي، فيخرج "هحاخىث". النصّ
 * الناتج ليس خطأ إملائياً ولا كلمة في أي لغة — هو الكلمة الصحيحة
 * بحروف موضعها نفسه على لوحة أخرى.
 *
 * ولهذا لا يعالجه تصحيح الأخطاء الإملائية: المسافة التحريرية بين
 * "هحاخىث" و"iphone" تساوي طول الكلمتين معاً، فلا قرابة بينهما على
 * مستوى المحارف إطلاقاً. القرابة في موضع المفتاح لا في شكل الحرف.
 *
 * ─── لماذا الاتجاهان ────────────────────────────────────────────────
 *
 * الخطأ متناظر: عربي يكتب إنجليزية بلوحة عربية، وعربي يكتب عربية
 * بلوحة إنجليزية ("hgshum" يقصد "الساعة"). كلاهما شائع، وكلفة دعم
 * الاتجاه الثاني عكس الخريطة نفسها.
 *
 * ─── الحدّ ──────────────────────────────────────────────────────────
 *
 * لا يقرّر هذا الصنف صحّة شيء، بل يقترح فقط. القبول يعود إلى المستدعي
 * وشرطه واحد: هل وجد النصّ المعكوس نتائج فعلاً؟ فمهما بدا العكس
 * معقولاً، نصٌّ لا يطابق شيئاً في الفهرس لا قيمة له.
 */
final class KeyboardLayoutMapper
{
    private const RESOURCE_DIR = 'search/layouts';

    /**
     * خرائط محمَّلة: script => [لاتيني => نظير]
     *
     * @var array<string, array<string, string>>|null
     */
    private ?array $layouts = null;

    /**
     * كل الصور الممكنة للنصّ بعد عكس التخطيط.
     *
     * ─── المُدخَل نصّ خام لا مطبَّع ──────────────────────────────────
     *
     * وهذا خلاف بقيّة النظام، وله سبب واحد حاسم: التطبيع يدمج حروفاً
     * تحتلّ مفاتيح مختلفة على اللوحة.
     *
     *     ى  ←  مفتاح n        ي  ←  مفتاح d
     *
     * وكلاهما يُطبَّع إلى "ي". فلو عُكس النصّ بعد التطبيع لصار المفتاحان
     * مصدراً واحداً، ولأنتج "iphone" ← "ipho_de" بدل "iphone" — أي أن
     * التطبيع نفسه يمحو المعلومة التي يقوم عليها العكس.
     *
     * النصّ الخام يحتفظ بالتمييز، والتطبيع يقع بعد العكس في المحلّل.
     *
     * @return string[]
     */
    public function candidates(string $rawQuery): array
    {
        if (trim($rawQuery) === '') {
            return [];
        }

        $candidates = [];

        foreach ($this->layouts() as $map) {
            foreach ([
                $this->toLatin($rawQuery, $map),
                $this->fromLatin($rawQuery, $map),
            ] as $candidate) {
                if ($this->isUsable($candidate, $rawQuery)) {
                    $candidates[$candidate] = true;
                }
            }
        }

        return array_keys($candidates);
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * نصّ غير لاتيني → لاتيني. "هحاخىث" → "iphone"
     *
     * @param  array<string, string>  $map
     */
    private function toLatin(string $text, array $map): string
    {
        /*
         | العكس بالأطول أولاً.
         |
         | المفتاح b يُنتج "لا" — محرفين. لو استُبدل حرف "ل" وحده أولاً
         | لانكسر الزوج ولخرج محرفان لاتينيان مكان مفتاح واحد. الترتيب
         | التنازلي بالطول يضمن مطابقة "لا" قبل "ل".
         */
        $reverse = array_flip($map);

        uksort($reverse, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return strtr($text, $reverse);
    }

    /**
     * نصّ لاتيني → غير لاتيني. "hgshum" → "الساعة"
     *
     * @param  array<string, string>  $map
     */
    private function fromLatin(string $text, array $map): string
    {
        return strtr($text, $map);
    }

    /**
     * هل يستحقّ المرشَّح المحاولة؟
     *
     * يُرفَض إذا لم يتغيّر شيء، أو إذا بقي بعد العكس محارف من الـ script
     * الأصلي — وذلك يعني أن النصّ لم يكن مكتوباً بلوحة خاطئة أصلاً بل
     * كان مزيجاً مقصوداً.
     */
    private function isUsable(string $candidate, string $original): bool
    {
        if ($candidate === '' || $candidate === $original) {
            return false;
        }

        $before = UnicodeScript::profile($original);
        $after = UnicodeScript::profile($candidate);

        // العكس الناجح يبدّل الـ script المهيمن؛ وإلا فلم يُعكَس شيء ذو بال.
        return $before !== [] && $after !== []
            && array_key_first($before) !== array_key_first($after);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function layouts(): array
    {
        if ($this->layouts !== null) {
            return $this->layouts;
        }

        $directory = resource_path(self::RESOURCE_DIR);
        $loaded = [];

        foreach (glob($directory.'/*.php') ?: [] as $path) {
            $map = require $path;

            if (! is_array($map) || $map === []) {
                continue;
            }

            /*
             | القيم تُترك بصورتها الخام بلا تطبيع.
             |
             | التطبيع هنا يدمج ى (مفتاح n) مع ي (مفتاح d) في رمز واحد،
             | فيصير للمفتاحين مصدر واحد ويستحيل عكسهما. الخريطة الخام
             | وحدها تحفظ التناظر بين المفتاح وحرفه.
             */
            $loaded[basename($path, '.php')] = array_map('strval', $map);
        }

        return $this->layouts = $loaded;
    }
}
