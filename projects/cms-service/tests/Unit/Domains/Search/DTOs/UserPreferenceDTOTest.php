<?php

use App\Domains\Search\DTOs\UserPreferenceDTO;

test('it initializes with provided values correctly', function () {
    $affinities = [10 => 0.8, 20 => 0.2];
    $termAffinities = ['laptop' => 0.9, 'phone' => 0.4];

    $dto = new UserPreferenceDTO(
        affinities: $affinities,
        termAffinities: $termAffinities,
        totalClicks: 50,
        hasHistory: true
    );

    expect($dto->affinities)->toBe($affinities)
        ->and($dto->termAffinities)->toBe($termAffinities)
        ->and($dto->totalClicks)->toBe(50)
        ->and($dto->hasHistory)->toBeTrue();
});

test('it creates a default noHistory instance correctly', function () {
    $dto = UserPreferenceDTO::noHistory();

    expect($dto->affinities)->toBeEmpty()
        ->and($dto->termAffinities)->toBeEmpty()
        ->and($dto->totalClicks)->toBe(0)
        ->and($dto->hasHistory)->toBeFalse();
});

test('affinityFor returns the data type affinity or zero when absent', function () {
    $dto = new UserPreferenceDTO(
        affinities: [10 => 0.75],
        termAffinities: [],
        totalClicks: 5,
        hasHistory: true
    );

    expect($dto->affinityFor(10))->toBe(0.75)
        ->and($dto->affinityFor(999))->toBe(0.0);
});

test('termAffinityFor returns the term affinity or zero when absent', function () {
    $dto = new UserPreferenceDTO(
        affinities: [],
        termAffinities: ['laptop' => 0.6],
        totalClicks: 5,
        hasHistory: true
    );

    expect($dto->termAffinityFor('laptop'))->toBe(0.6)
        ->and($dto->termAffinityFor('missing'))->toBe(0.0);
});

test('topAffinities returns the highest affinities sorted descending and honours the limit', function () {
    $dto = new UserPreferenceDTO(
        affinities: [10 => 0.2, 20 => 0.9, 30 => 0.5, 40 => 0.7],
        termAffinities: [],
        totalClicks: 12,
        hasHistory: true
    );

    // الترتيب تنازلي مع الحفاظ على المفاتيح (data_type_id)
    expect($dto->topAffinities(3))->toBe([20 => 0.9, 40 => 0.7, 30 => 0.5])
        ->and($dto->topAffinities())->toHaveCount(3); // الحد الافتراضي 3
});

test('topAffinities returns an empty array when there are no affinities', function () {
    expect(UserPreferenceDTO::noHistory()->topAffinities())->toBe([]);
});

test('topTerms returns the highest term affinities sorted descending and honours the limit', function () {
    $dto = new UserPreferenceDTO(
        affinities: [],
        termAffinities: [
            'a' => 0.1,
            'b' => 0.9,
            'c' => 0.5,
            'd' => 0.7,
            'e' => 0.3,
            'f' => 0.8,
        ],
        totalClicks: 30,
        hasHistory: true
    );

    expect($dto->topTerms(2))->toBe(['b' => 0.9, 'f' => 0.8])
        ->and($dto->topTerms())->toHaveCount(5); // الحد الافتراضي 5
});

test('topTerms returns an empty array when there are no term affinities', function () {
    expect(UserPreferenceDTO::noHistory()->topTerms())->toBe([]);
});
