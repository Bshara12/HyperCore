<?php

namespace App\Domains\CMS\Services\EntryHierarchy;

interface EntryComponent
{
  public function getId(): int;

  public function getChildren(): array;

  public function addChild(EntryComponent $component): void;

  public function flatten(): array;
}