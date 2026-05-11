<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ProcessScheduledOffersAction
{
    public function __construct(
        protected OfferRepositoryInterface $repository
    ) {}

    public function execute(): array
    {
        $now = Carbon::now();

        $activatedOffers = $this->repository->getAndActivateDueOffers($now);
        $deactivatedOffers = $this->repository->getAndDeactivateExpiredOffers($now);

        // ✅ هذا الـ Action يشتغل كـ Cron Job ويغير حالة offers كثيرة
        // نمسح كل الـ Cache المتعلق بالـ offers عن طريق tag
        // لأننا ما نعرف أي project تأثر بالضبط
        Cache::tags(['offers'])->flush();

        return [
            'activated' => $activatedOffers,
            'deactivated' => $deactivatedOffers,
        ];
    }
}
