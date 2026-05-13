<?php

namespace Tests\Unit\Domains\Booking\Actions\Client;

use App\Domains\Booking\Actions\Client\ValidateCancelableAction;
use Carbon\Carbon;

test('it throws an exception if the booking is already cancelled', function () {
    $booking = (object) ['status' => 'cancelled'];
    $action = new ValidateCancelableAction();

    expect(fn() => $action->execute($booking))
        ->toThrow(\Exception::class, 'Already cancelled');
});

test('it throws an exception if the booking is already completed', function () {
    $booking = (object) ['status' => 'completed'];
    $action = new ValidateCancelableAction();

    expect(fn() => $action->execute($booking))
        ->toThrow(\Exception::class, 'Already completed');
});

test('it throws an exception if the booking start time is in the past', function () {
    // تثبيت الوقت الحالي لنضمن دقة الاختبار
    Carbon::setTestNow(Carbon::create(2026, 5, 9, 12, 0, 0));

    // حجز بدأ قبل ساعة من الآن
    $booking = (object) [
        'status' => 'confirmed',
        'start_at' => '2026-05-09 11:00:00'
    ];
    
    $action = new ValidateCancelableAction();

    expect(fn() => $action->execute($booking))
        ->toThrow(\Exception::class, 'Cannot cancel past booking');

    Carbon::setTestNow(); // إعادة ضبط الوقت
});

test('it passes if the booking is confirmed and in the future', function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 9, 12, 0, 0));

    // حجز سيبدأ غداً
    $booking = (object) [
        'status' => 'confirmed',
        'start_at' => '2026-05-10 10:00:00'
    ];
    
    $action = new ValidateCancelableAction();

    // لا نتوقع رمي أي Exception
    $action->execute($booking);
    
    expect(true)->toBeTrue();

    Carbon::setTestNow();
});