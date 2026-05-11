<?php

namespace App\Domains\Search\Services;

/**
 * KeyboardLayoutFixer — مع إضافة looksLikeArabicKeyboardLayout()
 *
 * التغيير الوحيد: إضافة method واحدة جديدة في نهاية الـ class
 * لا شيء آخر يتغير في هذا الملف
 */
class KeyboardLayoutFixer
{
  /*
     * خريطة التحويل: المفتاح الإنجليزي → المقابل العربي
     */
  private const EN_TO_AR = [
    'q' => 'ض',
    'w' => 'ص',
    'e' => 'ث',
    'r' => 'ق',
    't' => 'ف',
    'y' => 'غ',
    'u' => 'ع',
    'i' => 'ه',
    'o' => 'خ',
    'p' => 'ح',
    'a' => 'ش',
    's' => 'س',
    'd' => 'ي',
    'f' => 'ب',
    'g' => 'ل',
    'h' => 'ا',
    'j' => 'ت',
    'k' => 'ن',
    'l' => 'م',
    ';' => 'ك',
    'z' => 'ئ',
    'x' => 'ء',
    'c' => 'ؤ',
    'v' => 'ر',
    'b' => 'لا',
    'n' => 'ى',
    'm' => 'ة',
    ',' => 'و',
    '.' => 'ز',
    'Q' => 'َ',
    'W' => 'ً',
    'E' => 'ُ',
    'R' => 'ٌ',
    'T' => 'لإ',
    'Y' => 'إ',
    'U' => '`',
    'I' => '÷',
    'O' => '×',
    'P' => '؛',
    'A' => 'ِ',
    'S' => 'ٍ',
    'D' => ']',
    'F' => '[',
    'G' => 'لأ',
    'H' => 'أ',
    'J' => 'ـ',
    'K' => '،',
    'L' => '/',
    'Z' => '~',
    'X' => 'ْ',
    'C' => '}',
    'V' => '{',
    'B' => 'لآ',
    'N' => 'آ',
    'M' => "'",
  ];

  /*
     * الاتجاه المعاكس: العربي → الإنجليزي
     * يُبنى تلقائياً من EN_TO_AR
     */
  private array $arToEn = [];

  private const EN_VOWELS = ['a', 'e', 'i', 'o', 'u'];

  // ─────────────────────────────────────────────────────────────────

  public function __construct()
  {
    foreach (self::EN_TO_AR as $en => $ar) {
      if (mb_strlen($ar, 'UTF-8') === 1) {
        $this->arToEn[$ar] = (string) $en;
      }
    }
  }

  // ─────────────────────────────────────────────────────────────────
  // الدالة الرئيسية (لم تتغير)
  // ─────────────────────────────────────────────────────────────────

  public function fix(string $query): array
  {
    $query = trim($query);
    if (empty($query)) {
      return $this->buildResult($query, null, 0.0, null);
    }

    $analysis = $this->analyzeCharacters($query);

    if ($analysis['mixed']) {
      return $this->buildResult($query, null, 0.0, null);
    }

    if ($analysis['dominantType'] === 'arabic') {
      return $this->tryArToEn($query, $analysis);
    }

    if ($analysis['dominantType'] === 'english') {
      $vowelRatio = $this->calculateVowelRatio($query);
      // if ($vowelRatio >= 0.20) {
      //   return $this->buildResult($query, null, 0.0, null);
      // }
      return $this->tryEnToAr($query, $analysis);
    }

    return $this->buildResult($query, null, 0.0, null);
  }

    // ─────────────────────────────────────────────────────────────────
    // ✅ NEW METHOD: كشف Arabic keyboard layout مكتوب بـ Arabic chars
    // ─────────────────────────────────────────────────────────────────

  /**
   * يكشف إذا كان النص العربي هو في الواقع نص إنجليزي
   * مكتوب بـ Arabic keyboard layout بالغلط.
   *
   * مثال:
   *   "هحاخىث" → i=ه, p=ح, h=ا, o=خ, n=ى, e=ث → "iphone"
   *
   * الخوارزمية:
   *   1. حوّل كل حرف عربي إلى مقابله الإنجليزي (AR→EN map)
   *   2. إذا الناتج إنجليزي بـ vowel ratio معقول → keyboard mismatch
   *   3. أرجع الناتج المحوَّل مع confidence
   *
   * @return array{
   *   isKeyboardMismatch: bool,
   *   convertedQuery: string|null,
   *   confidence: float
   * }
   */
  public function detectArabicKeyboardMismatch(string $query): array
  {
    $empty = ['isKeyboardMismatch' => false, 'convertedQuery' => null, 'confidence' => 0.0];

    if (empty(trim($query))) {
      return $empty;
    }

    // تأكد أن النص عربي بالكامل تقريباً
    $analysis = $this->analyzeCharacters($query);
    if ($analysis['dominantType'] !== 'arabic') {
      return $empty;
    }

    // جرّب التحويل AR → EN
    $fixResult = $this->tryArToEn($query, $analysis);

    if ($fixResult['fixed'] === null || $fixResult['confidence'] < 0.35) {
      return $empty;
    }

    return [
      'isKeyboardMismatch' => true,
      'convertedQuery'     => $fixResult['fixed'],
      'confidence'         => $fixResult['confidence'],
    ];
  }

  // ─────────────────────────────────────────────────────────────────
  // باقي الـ methods (لم تتغير)
  // ─────────────────────────────────────────────────────────────────

  private function calculateVowelRatio(string $text): float
  {
    $letters = preg_replace('/[^a-z]/i', '', mb_strtolower($text, 'UTF-8'));
    $len     = strlen($letters);
    if ($len === 0) return 0.0;
    $vowels = preg_replace('/[^aeiou]/i', '', $letters);
    return strlen($vowels) / $len;
  }

  private function analyzeCharacters(string $text): array
  {
    $chars   = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $total   = 0;
    $arabic  = 0;
    $english = 0;

    foreach ($chars as $char) {
      if ($char === ' ' || is_numeric($char)) continue;
      $total++;
      if ($this->isArabicChar($char))  $arabic++;
      elseif ($this->isEnglishChar($char)) $english++;
    }

    if ($total === 0) {
      return [
        'arabicRatio' => 0.0,
        'englishRatio' => 0.0,
        'dominantType' => 'unknown',
        'mixed' => false,
        'totalChars' => 0
      ];
    }

    $arabicRatio  = $arabic  / $total;
    $englishRatio = $english / $total;
    $mixed        = $arabicRatio > 0.2 && $englishRatio > 0.2;

    $dominantType = match (true) {
      $arabicRatio  >= 0.7 => 'arabic',
      $englishRatio >= 0.7 => 'english',
      default              => 'mixed',
    };

    return [
      'arabicRatio'  => round($arabicRatio,  4),
      'englishRatio' => round($englishRatio, 4),
      'dominantType' => $dominantType,
      'mixed'        => $mixed,
      'totalChars'   => $total,
    ];
  }

  private function tryArToEn(string $query, array $analysis): array
  {
    $converted = '';
    $chars     = preg_split('//u', $query, -1, PREG_SPLIT_NO_EMPTY);

    foreach ($chars as $char) {
      if ($char === ' ') {
        $converted .= ' ';
        continue;
      }
      $converted .= $this->arToEn[$char] ?? $char;
    }

    $converted  = trim($converted);
    $confidence = $this->scoreEnglishOutput($converted, $analysis);

    if ($confidence < 0.4) {
      return $this->buildResult($query, null, 0.0, null);
    }

    return $this->buildResult($query, $converted, $confidence, 'ar_to_en');
  }

  private function tryEnToAr(string $query, array $analysis): array
  {
    $converted  = '';
    $lowerQuery = mb_strtolower($query, 'UTF-8');
    $chars      = preg_split('//u', $lowerQuery, -1, PREG_SPLIT_NO_EMPTY);

    foreach ($chars as $char) {
      if ($char === ' ') {
        $converted .= ' ';
        continue;
      }
      $converted .= self::EN_TO_AR[$char] ?? $char;
    }

    $converted        = trim($converted);
    $convertedAnalysis = $this->analyzeCharacters($converted);
    $confidence        = $convertedAnalysis['arabicRatio'];

    if ($confidence < 0.6) {
      return $this->buildResult($query, null, 0.0, null);
    }

    return $this->buildResult($query, $converted, round($confidence, 4), 'en_to_ar');
  }

  private function scoreEnglishOutput(string $output, array $originalAnalysis): float
  {
    if (empty(trim($output))) return 0.0;

    $outputAnalysis = $this->analyzeCharacters($output);
    if ($outputAnalysis['englishRatio'] < 0.7) return 0.0;

    $score = 0.0;
    $score += $outputAnalysis['englishRatio'] * 0.4;

    $lowerOutput  = mb_strtolower($output, 'UTF-8');
    $outputChars  = preg_split('//u', $lowerOutput, -1, PREG_SPLIT_NO_EMPTY);
    $vowelCount   = count(array_filter($outputChars, fn($c) => in_array($c, self::EN_VOWELS, true)));
    $totalLetters = count(array_filter($outputChars, fn($c) => ctype_alpha($c)));

    if ($totalLetters > 0) {
      $vowelRatio  = $vowelCount / $totalLetters;
      $score      += ($vowelRatio >= 0.15 && $vowelRatio <= 0.6) ? 0.3 : 0.1;
    }

    if ($outputAnalysis['arabicRatio'] === 0.0) $score += 0.2;

    $wordLengths = array_map('mb_strlen', explode(' ', trim($output)));
    $avgLength   = count($wordLengths) > 0 ? array_sum($wordLengths) / count($wordLengths) : 0;
    if ($avgLength >= 2 && $avgLength <= 15) $score += 0.1;

    return round(min(1.0, $score), 4);
  }

  private function isArabicChar(string $char): bool
  {
    $code = mb_ord($char, 'UTF-8');
    return ($code >= 0x0600 && $code <= 0x06FF)
      || ($code >= 0xFB50 && $code <= 0xFDFF)
      || ($code >= 0xFE70 && $code <= 0xFEFF);
  }

  private function isEnglishChar(string $char): bool
  {
    return ctype_alpha($char) && mb_strlen($char, 'UTF-8') === 1 && ord($char) < 128;
  }

  private function buildResult(string $original, ?string $fixed, float $confidence, ?string $direction): array
  {
    
    return [
      'original'   => $original,
      'fixed'      => $fixed,
      'confidence' => $confidence,
      'direction'  => $direction,
    ];
  }
}
