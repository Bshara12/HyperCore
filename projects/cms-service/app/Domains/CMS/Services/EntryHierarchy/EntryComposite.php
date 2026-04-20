<?php

namespace App\Domains\CMS\Services\EntryHierarchy;

class EntryComposite implements EntryComponent
{
  protected int $id;
  protected array $children = [];

  public function __construct(int $id)
  {
    $this->id = $id;
  }

  public function getId(): int
  {
    return $this->id;
  }

  public function getChildren(): array
  {
    return $this->children;
  }

  public function addChild(EntryComponent $component): void
  {
    $this->children[] = $component;
  }
  
  public function flatten(): array
  {
    $result = [$this];

    foreach ($this->children as $child) {
      $result = array_merge($result, $child->flatten());
    }

    return $result;
  }
}
