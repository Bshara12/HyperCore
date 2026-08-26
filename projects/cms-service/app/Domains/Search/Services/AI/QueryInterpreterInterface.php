<?php

declare(strict_types=1);

namespace App\Domains\Search\Services\AI;

/**
 * تفسير استعلام أخفق محلّياً، في صورة قابلة للتحقّق.
 *
 * الشكل المُعاد متعمَّد الضيق: مصطلحات تُطلَب، ومصطلحات تُستثنى، ودرجة
 * ثقة. لا نصّ استعلام ولا شروط بنيوية ولا معاملات ترتيب — أي أن أوسع
 * ما يستطيعه مزوّد خارجي هو اقتراح كلمات، ولا سبيل له إلى تغيير دلالة
 * الاستعلام ولا إلى إخفاء محتوى.
 */
interface QueryInterpreterInterface
{
    /**
     * @return array{include: string[], exclude: string[], confidence: float, source: string}|null
     *                                                                                             null حين يتعذّر التفسير
     */
    public function interpret(string $query, string $language): ?array;
}
