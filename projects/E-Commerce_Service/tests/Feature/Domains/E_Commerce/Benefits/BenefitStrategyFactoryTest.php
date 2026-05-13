<?php

namespace Tests\Feature\Domains\E_Commerce\Benefits;

use App\Domains\E_Commerce\Benefits\BenefitStrategyFactory;
use App\Domains\E_Commerce\Benefits\PercentageDiscountStrategy;
use App\Domains\E_Commerce\Benefits\FixedAmountDiscountStrategy;
use InvalidArgumentException;

beforeEach(function () {
  $this->factory = new BenefitStrategyFactory();
});

it('returns PercentageDiscountStrategy for percentage type', function () {
  $strategy = $this->factory->make('percentage');

  expect($strategy)->toBeInstanceOf(PercentageDiscountStrategy::class);
});

it('returns FixedAmountDiscountStrategy for fixed_amount type', function () {
  $strategy = $this->factory->make('fixed_amount');

  expect($strategy)->toBeInstanceOf(FixedAmountDiscountStrategy::class);
});

it('is case insensitive for benefit type', function () {
  $strategy = $this->factory->make('PERCENTAGE');

  expect($strategy)->toBeInstanceOf(PercentageDiscountStrategy::class);
});

it('throws an exception for unsupported benefit types', function () {
  expect(fn() => $this->factory->make('invalid_type'))
    ->toThrow(InvalidArgumentException::class, "Unsupported benefit_type: invalid_type");
});
