<?php

namespace App\Console\Commands;

use App\DTOs\NotificationPayloadDTO;
use App\Services\Auth\AuthApiClient;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class ConsumeNotificationEventsCommand extends Command
{
    protected $signature   = 'notifications:consume';
    protected $description = 'يستمع لأحداث RabbitMQ من كل الخدمات';

    private const QUEUE_NAME   = 'notification-service-queue';
    private const EXCHANGE     = 'microservices';
    private const BINDING_KEYS = [
        // ─── Auth ──────────────────────────────────────────────────────────
        'auth.user.registered',
        'auth.otp.sent',
        'auth.otp.resent',

        // ─── Booking ───────────────────────────────────────────────────────
        'booking.booking.created',
        'booking.booking.cancelled',
        'booking.booking.rescheduled',

        // ─── E-Commerce: Orders ────────────────────────────────────────────
        'ecommerce.order.created',
        'ecommerce.order.paid',
        'ecommerce.order.shipped',
        'ecommerce.order.delivered',
        'ecommerce.order.cancelled',

        // ─── E-Commerce: Payments ──────────────────────────────────────────
        'ecommerce.payment.paid',
        'ecommerce.payment.failed',
        'ecommerce.payment.refunded',

        // ─── E-Commerce: Return Requests ───────────────────────────────────
        'ecommerce.return_request.created',
        'ecommerce.return_request.approved',
        'ecommerce.return_request.rejected',

        // ─── CMS: Subscriptions ────────────────────────────────────────────
        'cms.subscription.created',
        'cms.subscription.renewed',
        'cms.subscription.cancelled',
        'cms.subscription.grace_period',
        'cms.subscription.expired',

        // ─── CMS: Payments ─────────────────────────────────────────────────
        'cms.payment.paid',
        'cms.payment.failed',
        'cms.payment.refunded',

        // ─── CMS: Installments ─────────────────────────────────────────────
        'cms.installment.paid',
        'cms.installment.completed',
        'cms.installment.defaulted',

        // ─── CMS: Projects ─────────────────────────────────────────────────
        'cms.project.created',
    ];

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly AuthApiClient $authClient,
    ) {
        parent::__construct();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Entry Point
    // ═══════════════════════════════════════════════════════════════════════

    public function handle(): void
    {
        $this->info('[Consumer] بدأ الاستماع...');

        $maxRetries = 5;
        $attempt    = 0;

        while ($attempt < $maxRetries) {
            try {
                $this->startConsuming();
                break;
            } catch (\Exception $e) {
                $attempt++;
                $waitSeconds = $attempt * 5;
                $this->error("[Consumer] ❌ {$e->getMessage()}");
                if ($attempt < $maxRetries) {
                    $this->warn("[Consumer] إعادة المحاولة {$attempt}/{$maxRetries} بعد {$waitSeconds}s...");
                    sleep($waitSeconds);
                }
            }
        }

        $this->error('[Consumer] فشلت كل المحاولات.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RabbitMQ Connection
    // ═══════════════════════════════════════════════════════════════════════

    private function startConsuming(): void
    {
        $connection = new AMQPStreamConnection(
            host:     config('queue.connections.rabbitmq.host'),
            port:     config('queue.connections.rabbitmq.port'),
            user:     config('queue.connections.rabbitmq.login'),
            password: config('queue.connections.rabbitmq.password'),
            vhost:    config('queue.connections.rabbitmq.vhost'),
        );

        $channel = $connection->channel();
        $this->info('[Consumer] ✅ متصل بـ RabbitMQ');

        $channel->exchange_declare(
            exchange: self::EXCHANGE, type: 'topic',
            passive: false, durable: true, auto_delete: false,
        );

        $channel->queue_declare(
            queue: self::QUEUE_NAME, passive: false, durable: true,
            exclusive: false, auto_delete: false,
            arguments: new AMQPTable(['x-message-ttl' => 604800000]),
        );

        foreach (self::BINDING_KEYS as $key) {
            $channel->queue_bind(self::QUEUE_NAME, self::EXCHANGE, $key);
            $this->info("[Consumer] ✅ يستمع لـ: {$key}");
        }

        $channel->basic_qos(prefetch_size: 0, prefetch_count: 1, a_global: false);

        $channel->basic_consume(
            queue: self::QUEUE_NAME, no_ack: false,
            callback: fn (AMQPMessage $msg) => $this->processMessage($msg),
        );

        $this->info('[Consumer] 🎧 جاهز...');

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Message Router
    // ═══════════════════════════════════════════════════════════════════════

    private function processMessage(AMQPMessage $message): void
    {
        $routingKey = $message->getRoutingKey();

        try {
            $data = json_decode($message->getBody(), true, flags: JSON_THROW_ON_ERROR);

            $this->info("[Consumer] 📨 {$routingKey} | user: {$data['user_id']}");

            match ($routingKey) {
                // ─── Auth ─────────────────────────────────────────────────
                'auth.user.registered'              => $this->handleUserRegistered($data),
                'auth.otp.sent'                     => $this->handleOtpSent($data),
                'auth.otp.resent'                   => $this->handleOtpResent($data),

                // ─── Booking ──────────────────────────────────────────────
                'booking.booking.created'           => $this->handleBookingCreated($data),
                'booking.booking.cancelled'         => $this->handleBookingCancelled($data),
                'booking.booking.rescheduled'       => $this->handleBookingRescheduled($data),

                // ─── E-Commerce Orders ────────────────────────────────────
                'ecommerce.order.created'           => $this->handleOrderCreated($data),
                'ecommerce.order.paid'              => $this->handleOrderPaid($data),
                'ecommerce.order.shipped'           => $this->handleOrderShipped($data),
                'ecommerce.order.delivered'         => $this->handleOrderDelivered($data),
                'ecommerce.order.cancelled'         => $this->handleOrderCancelled($data),

                // ─── E-Commerce Payments ──────────────────────────────────
                'ecommerce.payment.paid'            => $this->handleEcommercePaymentPaid($data),
                'ecommerce.payment.failed'          => $this->handleEcommercePaymentFailed($data),
                'ecommerce.payment.refunded'        => $this->handleEcommercePaymentRefunded($data),

                // ─── E-Commerce Return Requests ───────────────────────────
                'ecommerce.return_request.created'  => $this->handleReturnCreated($data),
                'ecommerce.return_request.approved' => $this->handleReturnApproved($data),
                'ecommerce.return_request.rejected' => $this->handleReturnRejected($data),

                // ─── CMS Subscriptions ────────────────────────────────────
                'cms.subscription.created'          => $this->handleSubscriptionCreated($data),
                'cms.subscription.renewed'          => $this->handleSubscriptionRenewed($data),
                'cms.subscription.cancelled'        => $this->handleSubscriptionCancelled($data),
                'cms.subscription.grace_period'     => $this->handleSubscriptionGracePeriod($data),
                'cms.subscription.expired'          => $this->handleSubscriptionExpired($data),

                // ─── CMS Payments ─────────────────────────────────────────
                'cms.payment.paid'                  => $this->handleCmsPaymentPaid($data),
                'cms.payment.failed'                => $this->handleCmsPaymentFailed($data),
                'cms.payment.refunded'              => $this->handleCmsPaymentRefunded($data),

                // ─── CMS Installments ─────────────────────────────────────
                'cms.installment.paid'              => $this->handleInstallmentPaid($data),
                'cms.installment.completed'         => $this->handleInstallmentCompleted($data),
                'cms.installment.defaulted'         => $this->handleInstallmentDefaulted($data),

                // ─── CMS Projects ─────────────────────────────────────────
                'cms.project.created'               => $this->handleProjectCreated($data),

                default => $this->warn("[Consumer] ⚠️ حدث غير معروف: {$routingKey}"),
            };

            $message->ack();
            $this->info("[Consumer] ✅ {$routingKey}");

        } catch (\JsonException $e) {
            $message->nack(requeue: false);
            $this->error("[Consumer] ❌ JSON تالف: {$e->getMessage()}");
        } catch (\Exception $e) {
            $message->nack(requeue: true);
            $this->error("[Consumer] ❌ فشل: {$e->getMessage()}");
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Auth Handlers
    // ═══════════════════════════════════════════════════════════════════════

    private function handleUserRegistered(array $data): void
    {
        $service = ['name' => 'auth-service'];

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'مرحباً بك في منصتنا! 🎉',
                body: "أهلاً {$data['user_name']}، نحن سعداء بانضمامك إلينا.",
                userEmail: $data['user_email'],
                data: ['type' => 'welcome', 'action_url' => config('app.frontend_url') . '/dashboard'],
            ),
            $service, skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'مرحباً بك! 🎉',
                body: "أهلاً {$data['user_name']}، نحن سعداء بانضمامك إلينا.",
                data: ['type' => 'welcome'],
            ),
            $service, skipUserVerification: true,
        );

        if (!empty($data['otp_code'])) {
            $this->handleOtpSent($data);
        }
    }

    private function handleOtpSent(array $data): void
    {
        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'رمز التحقق الخاص بك',
                body: "رمز التحقق: {$data['otp_code']}\nصالح لمدة 10 دقائق.",
                userEmail: $data['user_email'],
                data: ['type' => 'otp', 'otp_code' => $data['otp_code']],
            ),
            ['name' => 'auth-service'], skipUserVerification: true,
        );
    }

    private function handleOtpResent(array $data): void
    {
        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'رمز التحقق الجديد',
                body: "رمز جديد: {$data['otp_code']}\nيلغي الرمز السابق.",
                userEmail: $data['user_email'],
                data: ['type' => 'otp_resend', 'otp_code' => $data['otp_code']],
            ),
            ['name' => 'auth-service'], skipUserVerification: true,
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Booking Handlers
    // ═══════════════════════════════════════════════════════════════════════

    private function handleBookingCreated(array $data): void
    {
        $service   = ['name' => 'booking-service'];
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تم استلام حجزك ✅',
                body: "تم إنشاء حجزك من {$data['start_at']} إلى {$data['end_at']}.",
                userEmail: $userEmail,
                data: ['type' => 'booking_created', 'booking_id' => $data['booking_id']],
            ),
            $service, skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم استلام حجزك ✅',
                body: "حجزك من {$data['start_at']} إلى {$data['end_at']} بانتظار التأكيد.",
                data: ['type' => 'booking_created', 'booking_id' => $data['booking_id']],
            ),
            $service, skipUserVerification: true,
        );
    }

    private function handleBookingCancelled(array $data): void
    {
        $service   = ['name' => 'booking-service'];
        $userEmail = $this->resolveUserEmail($data['user_id']);
        $refundText = ($data['refund_amount'] ?? 0) > 0
            ? "\n💰 سيتم استرداد: {$data['refund_amount']} {$data['currency']}" : '';

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تم إلغاء حجزك ❌',
                body: "تم إلغاء حجزك.{$refundText}",
                userEmail: $userEmail,
                data: ['type' => 'booking_cancelled', 'booking_id' => $data['booking_id']],
            ),
            $service, skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم إلغاء حجزك ❌',
                body: "تم إلغاء حجزك.{$refundText}",
                data: ['type' => 'booking_cancelled', 'booking_id' => $data['booking_id']],
            ),
            $service, skipUserVerification: true,
        );
    }

    private function handleBookingRescheduled(array $data): void
    {
        $service   = ['name' => 'booking-service'];
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تم تعديل موعد حجزك 🔄',
                body: "الموعد الجديد: من {$data['new_start_at']} إلى {$data['new_end_at']}.",
                userEmail: $userEmail,
                data: ['type' => 'booking_rescheduled', 'booking_id' => $data['booking_id']],
            ),
            $service, skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم تعديل موعد حجزك 🔄',
                body: "الموعد الجديد: من {$data['new_start_at']} إلى {$data['new_end_at']}.",
                data: ['type' => 'booking_rescheduled', 'booking_id' => $data['booking_id']],
            ),
            $service, skipUserVerification: true,
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // E-Commerce Order Handlers
    // ═══════════════════════════════════════════════════════════════════════

    private function handleOrderCreated(array $data): void
    {
        $service   = ['name' => 'ecommerce-service'];
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تم استلام طلبك 🛍️',
                body: "تم إنشاء طلبك رقم #{$data['order_id']}. الإجمالي: {$data['total_price']} {$data['currency']}",
                userEmail: $userEmail,
                data: ['type' => 'order_created', 'order_id' => $data['order_id']],
            ),
            $service, skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم استلام طلبك 🛍️',
                body: "طلبك رقم #{$data['order_id']} بانتظار الدفع.",
                data: ['type' => 'order_created', 'order_id' => $data['order_id']],
            ),
            $service, skipUserVerification: true,
        );
    }

    private function handleOrderPaid(array $data): void
    {
        $service   = ['name' => 'ecommerce-service'];
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تم تأكيد دفع طلبك ✅',
                body: "تم استلام دفعتك لطلب رقم #{$data['order_id']} وهو قيد المعالجة.",
                userEmail: $userEmail,
                data: ['type' => 'order_paid', 'order_id' => $data['order_id']],
            ),
            $service, skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم تأكيد دفع طلبك ✅',
                body: "طلبك رقم #{$data['order_id']} قيد المعالجة.",
                data: ['type' => 'order_paid', 'order_id' => $data['order_id']],
            ),
            $service, skipUserVerification: true,
        );
    }

    private function handleOrderShipped(array $data): void
    {
        $service   = ['name' => 'ecommerce-service'];
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تم شحن طلبك 🚚',
                body: "طلبك رقم #{$data['order_id']} في طريقه إليك الآن!",
                userEmail: $userEmail,
                data: ['type' => 'order_shipped', 'order_id' => $data['order_id']],
            ),
            $service, skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم شحن طلبك 🚚',
                body: "طلبك رقم #{$data['order_id']} في طريقه إليك!",
                data: ['type' => 'order_shipped', 'order_id' => $data['order_id']],
            ),
            $service, skipUserVerification: true,
        );
    }

    private function handleOrderDelivered(array $data): void
    {
        $service   = ['name' => 'ecommerce-service'];
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تم تسليم طلبك 🎉',
                body: "وصل طلبك رقم #{$data['order_id']} بنجاح. نأمل أن ينال إعجابك!",
                userEmail: $userEmail,
                data: ['type' => 'order_delivered', 'order_id' => $data['order_id']],
            ),
            $service, skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم تسليم طلبك 🎉',
                body: "وصل طلبك رقم #{$data['order_id']}!",
                data: ['type' => 'order_delivered', 'order_id' => $data['order_id']],
            ),
            $service, skipUserVerification: true,
        );
    }

    private function handleOrderCancelled(array $data): void
    {
        $service   = ['name' => 'ecommerce-service'];
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تم إلغاء طلبك ❌',
                body: "تم إلغاء طلبك رقم #{$data['order_id']}.",
                userEmail: $userEmail,
                data: ['type' => 'order_cancelled', 'order_id' => $data['order_id']],
            ),
            $service, skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم إلغاء طلبك ❌',
                body: "تم إلغاء طلبك رقم #{$data['order_id']}.",
                data: ['type' => 'order_cancelled', 'order_id' => $data['order_id']],
            ),
            $service, skipUserVerification: true,
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // E-Commerce Payment Handlers
    // ═══════════════════════════════════════════════════════════════════════

    private function handleEcommercePaymentPaid(array $data): void
    {
        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم استلام دفعتك ✅',
                body: "تم استلام {$data['amount']} {$data['currency']} عبر {$data['gateway']}.",
                data: ['type' => 'payment_paid', 'payment_id' => $data['payment_id']],
            ),
            ['name' => 'ecommerce-service'], skipUserVerification: true,
        );
    }

    private function handleEcommercePaymentFailed(array $data): void
    {
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'فشلت عملية الدفع ⚠️',
                body: "فشل دفع مبلغ {$data['amount']} {$data['currency']}. يرجى المحاولة مجدداً.",
                userEmail: $userEmail,
                data: ['type' => 'payment_failed', 'order_id' => $data['order_id']],
            ),
            ['name' => 'ecommerce-service'], skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'فشلت عملية الدفع ⚠️',
                body: "فشل دفع {$data['amount']} {$data['currency']}. يرجى المحاولة مجدداً.",
                data: ['type' => 'payment_failed', 'order_id' => $data['order_id']],
            ),
            ['name' => 'ecommerce-service'], skipUserVerification: true,
        );
    }

    private function handleEcommercePaymentRefunded(array $data): void
    {
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تم استرداد مبلغك 💰',
                body: "تم استرداد {$data['amount']} {$data['currency']} وسيظهر في حسابك خلال 3-5 أيام.",
                userEmail: $userEmail,
                data: ['type' => 'payment_refunded', 'payment_id' => $data['payment_id']],
            ),
            ['name' => 'ecommerce-service'], skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم استرداد مبلغك 💰',
                body: "تم استرداد {$data['amount']} {$data['currency']}.",
                data: ['type' => 'payment_refunded', 'amount' => $data['amount']],
            ),
            ['name' => 'ecommerce-service'], skipUserVerification: true,
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // E-Commerce Return Request Handlers
    // ═══════════════════════════════════════════════════════════════════════

    private function handleReturnCreated(array $data): void
    {
        $service   = ['name' => 'ecommerce-service'];
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تم استلام طلب الإرجاع 📦',
                body: "تم استلام طلب إرجاعك للطلب #{$data['order_id']}. سيتم مراجعته خلال 2-3 أيام.",
                userEmail: $userEmail,
                data: ['type' => 'return_created', 'return_id' => $data['return_id']],
            ),
            $service, skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم استلام طلب الإرجاع 📦',
                body: "طلب الإرجاع للطلب #{$data['order_id']} قيد المراجعة.",
                data: ['type' => 'return_created', 'return_id' => $data['return_id']],
            ),
            $service, skipUserVerification: true,
        );
    }

    private function handleReturnApproved(array $data): void
    {
        $service   = ['name' => 'ecommerce-service'];
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تم قبول طلب الإرجاع ✅',
                body: "تمت الموافقة على إرجاع الطلب #{$data['order_id']}. سيتم استرداد المبلغ خلال 3-5 أيام.",
                userEmail: $userEmail,
                data: ['type' => 'return_approved', 'return_id' => $data['return_id']],
            ),
            $service, skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم قبول طلب الإرجاع ✅',
                body: "تمت الموافقة على إرجاع الطلب #{$data['order_id']}.",
                data: ['type' => 'return_approved', 'return_id' => $data['return_id']],
            ),
            $service, skipUserVerification: true,
        );
    }

    private function handleReturnRejected(array $data): void
    {
        $service   = ['name' => 'ecommerce-service'];
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تم رفض طلب الإرجاع ❌',
                body: "نأسف، تم رفض طلب إرجاعك للطلب #{$data['order_id']}. للاستفسار تواصل مع الدعم.",
                userEmail: $userEmail,
                data: ['type' => 'return_rejected', 'return_id' => $data['return_id']],
            ),
            $service, skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم رفض طلب الإرجاع ❌',
                body: "تم رفض طلب الإرجاع للطلب #{$data['order_id']}.",
                data: ['type' => 'return_rejected', 'return_id' => $data['return_id']],
            ),
            $service, skipUserVerification: true,
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CMS Subscription Handlers
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * cms.subscription.created
     * المستخدم اشترك في خطة جديدة
     */
    private function handleSubscriptionCreated(array $data): void
    {
        $service   = ['name' => 'cms-service'];
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تم تفعيل اشتراكك 🎉',
                body: implode("\n", [
                    "تم تفعيل اشتراكك في خطة \"{$data['plan_name']}\" بنجاح.",
                    "💰 المبلغ: {$data['plan_price']} {$data['currency']}",
                    "📅 ينتهي في: {$data['ends_at']}",
                    $data['auto_renew'] ? "🔄 التجديد التلقائي: مفعّل" : '',
                ]),
                userEmail: $userEmail,
                data: [
                    'type'            => 'subscription_created',
                    'subscription_id' => $data['subscription_id'],
                    'plan_name'       => $data['plan_name'],
                    'ends_at'         => $data['ends_at'],
                    'action_url'      => config('app.frontend_url') . '/subscriptions',
                ],
            ),
            $service, skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم تفعيل اشتراكك 🎉',
                body: "اشتراكك في خطة \"{$data['plan_name']}\" فعّال حتى {$data['ends_at']}.",
                data: [
                    'type'            => 'subscription_created',
                    'subscription_id' => $data['subscription_id'],
                ],
            ),
            $service, skipUserVerification: true,
        );
    }

    /**
     * cms.subscription.renewed
     * تم تجديد الاشتراك (يدوياً أو تلقائياً)
     */
    private function handleSubscriptionRenewed(array $data): void
    {
        $service   = ['name' => 'cms-service'];
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $renewType = $data['is_auto_renewed']
            ? 'تم تجديد اشتراكك تلقائياً'
            : 'تم تجديد اشتراكك';

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: "{$renewType} 🔄",
                body: implode("\n", [
                    "{$renewType} في خطة \"{$data['plan_name']}\" بنجاح.",
                    "💰 المبلغ: {$data['plan_price']} {$data['currency']}",
                    "📅 ينتهي في: {$data['new_ends_at']}",
                ]),
                userEmail: $userEmail,
                data: [
                    'type'            => 'subscription_renewed',
                    'subscription_id' => $data['subscription_id'],
                    'plan_name'       => $data['plan_name'],
                    'new_ends_at'     => $data['new_ends_at'],
                    'is_auto_renewed' => $data['is_auto_renewed'],
                ],
            ),
            $service, skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: "{$renewType} 🔄",
                body: "اشتراكك في \"{$data['plan_name']}\" مجدَّد حتى {$data['new_ends_at']}.",
                data: [
                    'type'            => 'subscription_renewed',
                    'subscription_id' => $data['subscription_id'],
                ],
            ),
            $service, skipUserVerification: true,
        );
    }

    /**
     * cms.subscription.cancelled
     * المستخدم ألغى اشتراكه
     */
    private function handleSubscriptionCancelled(array $data): void
    {
        $service   = ['name' => 'cms-service'];
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $reasonText = !empty($data['cancel_reason'])
            ? "\n📝 السبب: {$data['cancel_reason']}" : '';

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تم إلغاء اشتراكك ❌',
                body: implode("\n", array_filter([
                    "تم إلغاء اشتراكك في خطة \"{$data['plan_name']}\".",
                    "📅 ستتمكن من الاستمرار حتى: {$data['ends_at']}",
                    $reasonText,
                ])),
                userEmail: $userEmail,
                data: [
                    'type'            => 'subscription_cancelled',
                    'subscription_id' => $data['subscription_id'],
                    'ends_at'         => $data['ends_at'],
                ],
            ),
            $service, skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم إلغاء اشتراكك ❌',
                body: "تم إلغاء اشتراكك في \"{$data['plan_name']}\". يبقى فعّالاً حتى {$data['ends_at']}.",
                data: [
                    'type'            => 'subscription_cancelled',
                    'subscription_id' => $data['subscription_id'],
                ],
            ),
            $service, skipUserVerification: true,
        );
    }

    /**
     * cms.subscription.grace_period
     * فشل التجديد التلقائي → دخل فترة السماح
     */
    private function handleSubscriptionGracePeriod(array $data): void
    {
        $service   = ['name' => 'cms-service'];
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تنبيه: فشل تجديد اشتراكك ⚠️',
                body: implode("\n", [
                    "فشل التجديد التلقائي لاشتراكك في خطة \"{$data['plan_name']}\".",
                    "أنت الآن في فترة السماح حتى {$data['ends_at']}.",
                    "يرجى تحديث بيانات الدفع لتجنب انقطاع الخدمة.",
                ]),
                userEmail: $userEmail,
                data: [
                    'type'            => 'subscription_grace_period',
                    'subscription_id' => $data['subscription_id'],
                    'ends_at'         => $data['ends_at'],
                    'action_url'      => config('app.frontend_url') . '/billing',
                ],
            ),
            $service, skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تنبيه: فشل تجديد اشتراكك ⚠️',
                body: "فشل التجديد التلقائي. أنت في فترة السماح حتى {$data['ends_at']}. يرجى تحديث بيانات الدفع.",
                data: [
                    'type'            => 'subscription_grace_period',
                    'subscription_id' => $data['subscription_id'],
                ],
            ),
            $service, skipUserVerification: true,
        );
    }

    /**
     * cms.subscription.expired
     * انتهى الاشتراك نهائياً
     */
    private function handleSubscriptionExpired(array $data): void
    {
        $service   = ['name' => 'cms-service'];
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'انتهى اشتراكك 😔',
                body: implode("\n", [
                    "انتهى اشتراكك في خطة \"{$data['plan_name']}\".",
                    "جدّد الآن للاستمرار في الاستفادة من جميع المزايا.",
                ]),
                userEmail: $userEmail,
                data: [
                    'type'            => 'subscription_expired',
                    'subscription_id' => $data['subscription_id'],
                    'action_url'      => config('app.frontend_url') . '/subscriptions/renew',
                ],
            ),
            $service, skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'انتهى اشتراكك 😔',
                body: "انتهى اشتراكك في \"{$data['plan_name']}\". جدّد الآن لاستعادة مزاياك.",
                data: [
                    'type'            => 'subscription_expired',
                    'subscription_id' => $data['subscription_id'],
                ],
            ),
            $service, skipUserVerification: true,
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CMS Payment Handlers
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * cms.payment.paid
     * دفع ناجح (خطة اشتراك، تقسيط...)
     */
    private function handleCmsPaymentPaid(array $data): void
    {
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $typeLabel = $data['payment_type'] === 'installment'
            ? 'قسط رقم ' . ($data['installment_number'] ?? '') : 'دفعة كاملة';

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تم استلام دفعتك ✅',
                body: "تم استلام {$typeLabel} بمبلغ {$data['amount']} {$data['currency']} عبر {$data['gateway']}.",
                userEmail: $userEmail,
                data: [
                    'type'         => 'cms_payment_paid',
                    'payment_id'   => $data['payment_id'],
                    'amount'       => $data['amount'],
                    'payment_type' => $data['payment_type'],
                ],
            ),
            ['name' => 'cms-service'], skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم استلام دفعتك ✅',
                body: "تم استلام {$data['amount']} {$data['currency']}.",
                data: ['type' => 'cms_payment_paid', 'payment_id' => $data['payment_id']],
            ),
            ['name' => 'cms-service'], skipUserVerification: true,
        );
    }

    /**
     * cms.payment.failed
     * فشل الدفع
     */
    private function handleCmsPaymentFailed(array $data): void
    {
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'فشلت عملية الدفع ⚠️',
                body: implode("\n", [
                    "فشلت عملية الدفع بمبلغ {$data['amount']} {$data['currency']}.",
                    "يرجى التحقق من بيانات الدفع والمحاولة مجدداً.",
                ]),
                userEmail: $userEmail,
                data: [
                    'type'       => 'cms_payment_failed',
                    'payment_id' => $data['payment_id'],
                    'action_url' => config('app.frontend_url') . '/billing',
                ],
            ),
            ['name' => 'cms-service'], skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'فشلت عملية الدفع ⚠️',
                body: "فشل دفع {$data['amount']} {$data['currency']}. يرجى المحاولة مجدداً.",
                data: ['type' => 'cms_payment_failed', 'payment_id' => $data['payment_id']],
            ),
            ['name' => 'cms-service'], skipUserVerification: true,
        );
    }

    /**
     * cms.payment.refunded
     * استرداد مبلغ
     */
    private function handleCmsPaymentRefunded(array $data): void
    {
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تم استرداد مبلغك 💰',
                body: "تم استرداد {$data['amount']} {$data['currency']} وسيظهر في حسابك خلال 3-5 أيام عمل.",
                userEmail: $userEmail,
                data: ['type' => 'cms_payment_refunded', 'payment_id' => $data['payment_id']],
            ),
            ['name' => 'cms-service'], skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم استرداد مبلغك 💰',
                body: "تم استرداد {$data['amount']} {$data['currency']}.",
                data: ['type' => 'cms_payment_refunded', 'amount' => $data['amount']],
            ),
            ['name' => 'cms-service'], skipUserVerification: true,
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CMS Installment Handlers
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * cms.installment.paid
     * تم دفع قسط واحد
     */
    private function handleInstallmentPaid(array $data): void
    {
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: "تم دفع القسط {$data['installment_number']} ✅",
                body: implode("\n", [
                    "تم استلام القسط رقم {$data['installment_number']} من أصل {$data['total_installments']}.",
                    "💰 مبلغ القسط: {$data['installment_amount']} {$data['currency']}",
                    "📊 الأقساط المتبقية: {$data['remaining_installments']}",
                    $data['next_due_date'] ? "📅 موعد القسط القادم: {$data['next_due_date']}" : '',
                ]),
                userEmail: $userEmail,
                data: [
                    'type'                   => 'installment_paid',
                    'payment_id'             => $data['payment_id'],
                    'installment_number'     => $data['installment_number'],
                    'remaining_installments' => $data['remaining_installments'],
                    'next_due_date'          => $data['next_due_date'],
                ],
            ),
            ['name' => 'cms-service'], skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: "تم دفع القسط {$data['installment_number']} ✅",
                body: "القسط {$data['installment_number']}/{$data['total_installments']} مدفوع. المتبقي: {$data['remaining_installments']} أقساط.",
                data: ['type' => 'installment_paid', 'payment_id' => $data['payment_id']],
            ),
            ['name' => 'cms-service'], skipUserVerification: true,
        );
    }

    /**
     * cms.installment.completed
     * تم سداد جميع الأقساط
     */
    private function handleInstallmentCompleted(array $data): void
    {
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تم سداد جميع الأقساط 🎉',
                body: implode("\n", [
                    "تهانينا! تم سداد جميع أقساطك ({$data['total_installments']} أقساط) بالكامل.",
                    "💰 إجمالي المبلغ: {$data['total_amount']} {$data['currency']}",
                ]),
                userEmail: $userEmail,
                data: [
                    'type'               => 'installment_completed',
                    'payment_id'         => $data['payment_id'],
                    'total_installments' => $data['total_installments'],
                ],
            ),
            ['name' => 'cms-service'], skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم سداد جميع الأقساط 🎉',
                body: "تهانينا! اكتمل سداد جميع الأقساط. الإجمالي: {$data['total_amount']} {$data['currency']}.",
                data: ['type' => 'installment_completed', 'payment_id' => $data['payment_id']],
            ),
            ['name' => 'cms-service'], skipUserVerification: true,
        );
    }

    /**
     * cms.installment.defaulted
     * تعثّر في سداد الأقساط
     */
    private function handleInstallmentDefaulted(array $data): void
    {
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تنبيه: تعثّر في سداد الأقساط ⚠️',
                body: implode("\n", [
                    "تم تسجيل تعثّر في سداد أقساطك.",
                    "📊 الأقساط المدفوعة: {$data['paid_installments']} من {$data['total_installments']}",
                    "💰 المبلغ المتبقي: {$data['remaining_amount']} {$data['currency']}",
                    "يرجى التواصل معنا لترتيب سداد المبالغ المتبقية.",
                ]),
                userEmail: $userEmail,
                data: [
                    'type'                   => 'installment_defaulted',
                    'payment_id'             => $data['payment_id'],
                    'remaining_installments' => $data['remaining_installments'],
                    'remaining_amount'       => $data['remaining_amount'],
                    'action_url'             => config('app.frontend_url') . '/billing',
                ],
            ),
            ['name' => 'cms-service'], skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تنبيه: تعثّر في سداد الأقساط ⚠️',
                body: "تعثّر في الأقساط. المتبقي: {$data['remaining_installments']} أقساط بمبلغ {$data['remaining_amount']} {$data['currency']}.",
                data: ['type' => 'installment_defaulted', 'payment_id' => $data['payment_id']],
            ),
            ['name' => 'cms-service'], skipUserVerification: true,
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CMS Project Handler
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * cms.project.created
     * مشروع جديد أُنشئ → نرحّب بصاحبه
     */
    private function handleProjectCreated(array $data): void
    {
        $service   = ['name' => 'cms-service'];
        $userEmail = $this->resolveUserEmail($data['user_id']);

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'email',
                title: 'تم إنشاء مشروعك بنجاح 🚀',
                body: implode("\n", [
                    "تم إنشاء مشروع \"{$data['name']}\" بنجاح.",
                    "ابدأ الآن بإضافة محتواك وإدارة مشروعك.",
                ]),
                userEmail: $userEmail,
                data: [
                    'type'       => 'project_created',
                    'project_id' => $data['project_id'],
                    'name'       => $data['name'],
                    'action_url' => config('app.frontend_url') . '/projects/' . $data['slug'],
                ],
            ),
            $service, skipUserVerification: true,
        );

        $this->notificationService->createFromConsumer(
            new NotificationPayloadDTO(
                userId: $data['user_id'], channel: 'in_app',
                title: 'تم إنشاء مشروعك 🚀',
                body: "مشروع \"{$data['name']}\" جاهز الآن. ابدأ بإضافة محتواك!",
                data: ['type' => 'project_created', 'project_id' => $data['project_id']],
            ),
            $service, skipUserVerification: true,
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Helper
    // ═══════════════════════════════════════════════════════════════════════

    private array $userEmailCache = [];

    private function resolveUserEmail(string $userId): ?string
    {
        if (isset($this->userEmailCache[$userId])) {
            return $this->userEmailCache[$userId];
        }

        try {
            $user  = $this->authClient->getUserById($userId);
            $email = $user['email'] ?? null;
            $this->userEmailCache[$userId] = $email;
            return $email;
        } catch (\Exception $e) {
            $this->warn("[Consumer] ⚠️ فشل جلب إيميل {$userId}: {$e->getMessage()}");
            return null;
        }
    }
}
