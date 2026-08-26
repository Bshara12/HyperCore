<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Rescue;

/**
 * EditDistance — مسافة دامیراو-ليفنشتاين على مستوى المحرف.
 *
 * ─── لماذا لا levenshtein() المدمجة ────────────────────────────────
 *
 * دالة PHP المدمجة تعمل على البايتات لا على المحارف. والحرف العربي
 * بايتان في UTF-8، فتراه الدالة رمزين:
 *
 *     levenshtein('هاتف', 'هتف')  =  2   ← والصحيح 1
 *
 * أي أن كل كلمة عربية أو صينية تبدو ضعف بُعدها الحقيقي، فتسقط خارج
 * أي عتبة تصحيح معقولة. النتيجة أن تصحيح الأخطاء يعمل للإنجليزية
 * وحدها ويصمت عن بقية اللغات.
 *
 * ─── لماذا دامیراو لا ليفنشتاين ────────────────────────────────────
 *
 * أشيع خطأ مطبعي هو تبادل حرفين متجاورين: "iphoen" و"teh" و"هاتف"→"هتاف".
 * ليفنشتاين تعدّه خطأين (حذف + إدراج)، فيسقط خارج عتبة الخطأ الواحد
 * رغم أنه أقرب الأخطاء إلى الصواب. دامیراو تعدّه عملية واحدة.
 */
final class EditDistance
{
    /**
     * المسافة بين نصّين، بالمحارف.
     *
     * @param  int|null  $ceiling  حدّ أعلى للإنهاء المبكر
     */
    public static function between(string $a, string $b, ?int $ceiling = null): int
    {
        if ($a === $b) {
            return 0;
        }

        $left = mb_str_split($a, 1, 'UTF-8');
        $right = mb_str_split($b, 1, 'UTF-8');

        $lengthA = count($left);
        $lengthB = count($right);

        if ($lengthA === 0) {
            return $lengthB;
        }

        if ($lengthB === 0) {
            return $lengthA;
        }

        /*
         | فارق الطول حدّ أدنى للمسافة: لا يمكن لكلمتين يفرق طولهما
         | ثلاثة محارف أن تفصلهما عمليتان. الفحص يوفّر بناء المصفوفة
         | كاملةً للمرشَّحين المستحيلين — وهم الأغلبية الساحقة.
         */
        if ($ceiling !== null && abs($lengthA - $lengthB) > $ceiling) {
            return $ceiling + 1;
        }

        $previous = range(0, $lengthB);
        $current = [];

        for ($i = 1; $i <= $lengthA; $i++) {
            $current = [$i];
            $rowMinimum = $i;

            for ($j = 1; $j <= $lengthB; $j++) {
                $cost = $left[$i - 1] === $right[$j - 1] ? 0 : 1;

                $value = min(
                    $previous[$j] + 1,        // حذف
                    $current[$j - 1] + 1,     // إدراج
                    $previous[$j - 1] + $cost // استبدال
                );

                /*
                 | التبادل: "ab" ↔ "ba" عملية واحدة لا اثنتان.
                 |
                 | يحتاج صفَّين سابقين لا صفّاً واحداً، ولهذا يُحفظ
                 | beforePrevious بدل الاكتفاء بـ previous.
                 */
                if (
                    $i > 1 && $j > 1
                    && $left[$i - 1] === $right[$j - 2]
                    && $left[$i - 2] === $right[$j - 1]
                ) {
                    $value = min($value, ($beforePrevious[$j - 2] ?? PHP_INT_MAX - 1) + 1);
                }

                $current[$j] = $value;
                $rowMinimum = min($rowMinimum, $value);
            }

            // كل الصفّ تجاوز السقف: لا يمكن للصفوف التالية أن تنزل تحته.
            if ($ceiling !== null && $rowMinimum > $ceiling) {
                return $ceiling + 1;
            }

            $beforePrevious = $previous;
            $previous = $current;
        }

        return $current[$lengthB];
    }

    /**
     * أقصى مسافة مقبولة لكلمة بهذا الطول.
     *
     * العتبة تتناسب مع الطول لأن الخطأ الواحد في كلمة من ثلاثة محارف
     * يغيّر ثلثها — وكلمتان بهذا القرب غالباً كلمتان مختلفتان لا خطأ
     * مطبعي. أمّا في كلمة من عشرة محارف فالخطآن انزلاق أصابع معتاد.
     */
    public static function toleranceFor(int $length): int
    {
        return match (true) {
            $length <= 3 => 0,
            $length <= 6 => 1,
            default => 2,
        };
    }
}
