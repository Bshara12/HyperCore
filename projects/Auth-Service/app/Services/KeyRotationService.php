<?php

namespace App\Services;

use App\Events\SystemLogEvent;
use Exception;
use Illuminate\Support\Facades\Log;

class KeyRotationService
{
    /**
     * يولّد زوج مفاتيح RSA جديد (2048-bit، نفس إعداد الأمر الأصلي
     * openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048)
     * وينسخ احتياطياً القديم قبل الاستبدال لأغراض تدقيق/رجوع عند الحاجة.
     *
     * أي توكن موقّع بالمفتاح القديم يصبح غير صالح فوراً بعدها
     * (فشل التحقق من التوقيع) — وهذا هو الهدف بالضبط في حال تسريب المفتاح.
     */
    public function rotate(int $actingUserId): void
    {
        $privatePath = config('jwt.private_key');
        $publicPath = config('jwt.public_key');

        $this->backupExisting($privatePath, $publicPath);

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            $error = openssl_error_string() ?: 'Unknown OpenSSL error';
            throw new Exception("Failed to generate new RSA key pair: {$error}");
        }

        openssl_pkey_export($resource, $privateKeyPem);
        $details = openssl_pkey_get_details($resource);
        $publicKeyPem = $details['key'];

        file_put_contents($privatePath, $privateKeyPem);
        chmod($privatePath, 0600);

        file_put_contents($publicPath, $publicKeyPem);
        chmod($publicPath, 0644);

        Log::warning('[KeyRotationService] RSA key pair rotated', [
            'triggered_by_user_id' => $actingUserId,
        ]);

        event(new SystemLogEvent(
            module: 'security',
            eventType: 'key_rotation',
            userId: $actingUserId,
        ));
    }

    private function backupExisting(string $privatePath, string $publicPath): void
    {
        if (! file_exists($privatePath) && ! file_exists($publicPath)) {
            return;
        }

        $backupDir = dirname($privatePath).'/backup';

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0700, true);
        }

        $timestamp = now()->format('Y-m-d_His');

        if (file_exists($privatePath)) {
            copy($privatePath, "{$backupDir}/private_{$timestamp}.key");
        }

        if (file_exists($publicPath)) {
            copy($publicPath, "{$backupDir}/public_{$timestamp}.key");
        }
    }
}
