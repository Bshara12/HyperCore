<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Actions\Client\CalculateRefundAction;
use App\Domains\Booking\Actions\Client\CheckAvailabilityAction;
use App\Domains\Booking\Actions\Client\CheckBookingConflictAction;
use App\Domains\Booking\Actions\Client\CreateBookingAction;
use App\Domains\Booking\Actions\Client\CreateBookingRecordAction;
use App\Domains\Booking\Actions\Client\GetBookingAction;
use App\Domains\Booking\Actions\Client\ProcessBookingPaymentAction;
use App\Domains\Booking\Actions\Client\ProcessRefundAction;
use App\Domains\Booking\Actions\Client\UpdateBookingStatusAction;
use App\Domains\Booking\Actions\Client\UpdateBookingTimeAction;
use App\Domains\Booking\Actions\Client\ValidateBookingTimeAction;
use App\Domains\Booking\Actions\Client\ValidateCancelableAction;
use App\Domains\Booking\DTOs\Client\CancelBookingDTO;
use App\Domains\Booking\DTOs\Client\CreateBookingDTO;
use App\Domains\Booking\DTOs\Client\RescheduleBookingDTO;
use App\Domains\Booking\Read\Actions\GetResourceBookingsAction;
use App\Domains\Booking\Read\DTOs\GetResourceBookingsDTO;
use App\Domains\Booking\Read\DTOs\GetResourceSlotsDTO;
use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BookingService
{
  public function __construct(
    private readonly SlotGeneratorService        $slotGenerator,
    private readonly ResourceRepositoryInterface $resourceRepository,
    private readonly GetResourceBookingsAction   $getResourceBookingsAction,


    private readonly ValidateBookingTimeAction $validateTime,
    private readonly CheckAvailabilityAction $checkAvailability,
    private readonly CheckBookingConflictAction $checkConflict,
    private readonly CreateBookingRecordAction $createRecord,
    private readonly ProcessBookingPaymentAction $processPayment,
    private readonly GetBookingAction $getBookingAction,
    private readonly ValidateCancelableAction $validateCancelable,
    private readonly CalculateRefundAction $calculateRefund,
    private readonly ProcessRefundAction $processRefund,
    private readonly UpdateBookingStatusAction $updateBookingStatus,
    private readonly UpdateBookingTimeAction $updateBookingTime,
  ) {}

  public function getAvailableSlots(GetResourceSlotsDTO $dto): array
  {
    $resource = $this->resourceRepository->findById($dto->resourceId);

    throw_if(! $resource,           \Exception::class, 'Resource not found.');
    throw_if(! $resource->isActive(), \Exception::class, 'Resource is not active.');

    $carbon = Carbon::parse($dto->date);

    throw_if(
      $carbon->isPast() && ! $carbon->isToday(),
      \Exception::class,
      'Cannot view slots for past dates.'
    );

    return [
      'resource_id' => $dto->resourceId,
      'date'        => $carbon->format('Y-m-d'),
      'day'         => $carbon->format('l'),
      'slots'       => $this->slotGenerator->generate($resource, $carbon),
    ];
  }

  public function getResourceBookings(GetResourceBookingsDTO $dto): Collection
  {
    return $this->getResourceBookingsAction->execute($dto);
  }

  // client

  public function create(CreateBookingDTO $dto)
  {
    return DB::transaction(function () use ($dto) {

      // $resource = $this->resourceRepository->findById($dto->resourceId);
      $resource = $this->resourceRepository->findById($dto->resourceId);

      throw_if(! $resource, \Exception::class, 'Resource not found');
      throw_if(! $resource->isActive(), \Exception::class, 'Resource inactive');

      // 🔥 تحقق السعر
      if ($resource->payment_type === 'paid') {
        if ((float)$dto->amount !== (float)$resource->price) {
          throw new \Exception('Invalid booking amount');
        }
      } else {
        // 🔥 مجاني → اجبر السعر = 0
        $dto->amount = 0;
      }
      
      throw_if(! $resource, \Exception::class, 'Resource not found');
      throw_if(! $resource->isActive(), \Exception::class, 'Resource inactive');

      $start = Carbon::parse($dto->startAt);
      $end   = Carbon::parse($dto->endAt);

      // 1. validate time
      $this->validateTime->execute($start, $end);

      // 2. availability
      $this->checkAvailability->execute($dto->resourceId, $start, $end);

      // 3. conflict
      $this->checkConflict->execute(
        $dto->resourceId,
        $dto->startAt,
        $dto->endAt,
        $resource->capacity,
        null
      );

      // 4. create booking
      $booking = $this->createRecord->execute($dto);

      // 5. payment
      return $this->processPayment->execute($booking, $dto);
    });
  }

  public function cancel(CancelBookingDTO $dto)
  {
    return DB::transaction(function () use ($dto) {

      $booking = $this->getBookingAction->execute($dto->bookingId);

      // 1. ownership
      if ($booking->user_id !== $dto->userId) {
        throw new \Exception('Unauthorized');
      }

      // 2. validate
      $this->validateCancelable->execute($booking);

      // 3. refund calculation
      $refundAmount = $this->calculateRefund->execute($booking);

      // 4. refund via CMS
      if ($refundAmount > 0 && $booking->payment_id) {
        $this->processRefund->execute($booking, $refundAmount);
      }

      // 5. update
      return $this->updateBookingStatus->execute(
        $booking,
        $refundAmount
      );
    });
  }

  public function reschedule(RescheduleBookingDTO $dto)
  {
    return DB::transaction(function () use ($dto) {

      $booking = $this->getBookingAction->execute($dto->bookingId);

      // ownership
      if ($booking->user_id !== $dto->userId) {
        throw new \Exception('Unauthorized');
      }

      // status check
      if ($booking->status !== 'confirmed') {
        throw new \Exception('Only confirmed bookings can be rescheduled');
      }

      $start = Carbon::parse($dto->startAt);
      $end   = Carbon::parse($dto->endAt);

      // ✅ reuse
      $this->validateTime->execute($start, $end);

      $this->checkAvailability->execute(
        $booking->resource_id,
        $start,
        $end
      );

      $this->checkConflict->execute(
        $booking->resource_id,
        $dto->startAt,
        $dto->endAt,
        $booking->resource->capacity,
        $booking->id // 🔥 ignore itself
      );

      // update
      return $this->updateBookingTime->execute(
        $booking,
        $dto->startAt,
        $dto->endAt
      );
    });
  }
}
