<?php

namespace App\Services\Messaging;

use App\Models\PlatformSetting;
use Throwable;

/** Applies admin-configured mail settings at runtime (overrides .env). Safe before migrations. */
class MailConfigurator
{
    public static function apply(): void
    {
        try {
            $mailer = PlatformSetting::getValue('mail_mailer');
        } catch (Throwable) {
            return; // DB not ready (fresh install / migrations)
        }

        if ($from = PlatformSetting::getValue('mail_from_address')) {
            config(['mail.from.address' => $from]);
        }
        if ($name = PlatformSetting::getValue('mail_from_name')) {
            config(['mail.from.name' => $name]);
        }
        if (! $mailer) {
            return;
        }

        config(['mail.default' => $mailer]);

        if ($mailer === 'smtp') {
            $enc = PlatformSetting::getValue('mail_encryption', 'ssl');
            config([
                'mail.mailers.smtp.scheme' => $enc === 'ssl' ? 'smtps' : 'smtp',
                'mail.mailers.smtp.host' => PlatformSetting::getValue('mail_host'),
                'mail.mailers.smtp.port' => (int) PlatformSetting::getValue('mail_port', $enc === 'ssl' ? 465 : 587),
                'mail.mailers.smtp.username' => PlatformSetting::getValue('mail_username'),
                'mail.mailers.smtp.password' => PlatformSetting::getValue('mail_password'),
            ]);
        }
    }
}
