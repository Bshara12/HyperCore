<?php

declare(strict_types=1);

namespace App\Domains\Search\Support;

/**
 * SqlFragment — immutable value object
 *
 * يُدمج SQL string مع bindings كـ unit واحدة لا تنفصل.
 * الهدف: منع الفصل بين SQL construction وbindings construction.
 *
 * لماذا هذا النهج؟
 *   - PDO named params لا تعمل مع MySQL FULLTEXT AGAINST()
 *   - Query Builder لا يدعم FULLTEXT scoring بشكل نظيف
 *   - هذا الـ VO بسيط، لا magic، لا reflection
 *   - impossible to forget a binding — SQL و binding يُضافان معاً دائماً
 *
 * Usage:
 *   $fragment = SqlFragment::create('si.project_id = ?', [$dto->projectId])
 *       ->and('si.language = ?', [$dto->language])
 *       ->and('si.status = ?', ['published'])
 *       ->andIf($dto->dataTypeSlug !== null, 'si.data_type_slug = ?', [$dto->dataTypeSlug]);
 *
 *   $fragment->sql      // "si.project_id = ? AND si.language = ? AND ..."
 *   $fragment->bindings // [1, 'en', 'published', ...]
 */
final class SqlFragment
{
  private function __construct(
    public readonly string $sql,
    public readonly array  $bindings,
  ) {}

  // ─────────────────────────────────────────────────────────────────

  public static function create(string $sql = '', array $bindings = []): self
  {
    return new self($sql, $bindings);
  }

  /**
   * يُضيف condition بـ AND
   * SQL و binding يُضافان معاً — لا يمكن الفصل بينهما
   */
  public function and(string $sql, array $bindings = []): self
  {
    $separator = empty($this->sql) ? '' : ' AND ';

    return new self(
      $this->sql . $separator . $sql,
      array_merge($this->bindings, $bindings),
    );
  }

  /**
   * يُضيف condition مشروط — إذا $condition=false لا يُضيف شيئاً
   */
  public function andIf(bool $condition, string $sql, array $bindings = []): self
  {
    if (! $condition) {
      return $this;
    }

    return $this->and($sql, $bindings);
  }

  /**
   * يُضيف OR condition
   */
  public function or(string $sql, array $bindings = []): self
  {
    $separator = empty($this->sql) ? '' : ' OR ';

    return new self(
      $this->sql . $separator . $sql,
      array_merge($this->bindings, $bindings),
    );
  }

  /**
   * يُضيف IN condition مع placeholder generation تلقائي
   *
   * andIn('si.data_type_slug', ['product', 'article'])
   * → "si.data_type_slug IN (?, ?)"  bindings: ['product', 'article']
   */
  public function andIn(string $column, array $values): self
  {
    if (empty($values)) {
      return $this;
    }

    $placeholders = implode(', ', array_fill(0, count($values), '?'));

    return $this->and("{$column} IN ({$placeholders})", $values);
  }

  /**
   * يُضيف multiple NOT LIKE conditions
   * كل term يُضاف مع binding معاً — impossible to forget
   */
  public function andNotLikeAll(string $expression, array $terms): self
  {
    $fragment = $this;

    foreach ($terms as $term) {
      $term = trim((string) $term);
      if ($term === '') continue;

      $fragment = $fragment->and(
        "{$expression} NOT LIKE ?",
        ['%' . $term . '%']
      );
    }

    return $fragment;
  }

  /**
   * يُعيد SQL مُغلَّف بـ parentheses (مفيد للـ subqueries)
   */
  public function wrap(): self
  {
    return new self(
      '(' . $this->sql . ')',
      $this->bindings,
    );
  }

  public function isEmpty(): bool
  {
    return empty($this->sql);
  }

  public function __toString(): string
  {
    return $this->sql;
  }
}
