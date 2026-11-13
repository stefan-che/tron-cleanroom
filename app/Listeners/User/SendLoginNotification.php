<?php

namespace App\Listeners\User;

use App\Helpers\Development;
use App\Jobs\User\TelegramSendJob;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;

class SendLoginNotification
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (!$user->telegram_id) {
            return;
        }

        $ip = Development::getRealIpUser();
        $userAgent = Request::userAgent();
        $browser = $this->getBrowserInfo($userAgent);
        $location = $this->getLocationInfo($ip);

        $message = __('telegram.notifications.login', [
            'time' => now()->format('Y-m-d H:i:s'),
            'ip' => $ip,
            'browser' => $browser,
            'location' => $location
        ]);

        TelegramSendJob::dispatch($user->telegram_id, $message);
    }

    private function getBrowserInfo($userAgent): string
    {
        $browser = "Unknown";

        if (strpos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($userAgent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            $browser = 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            $browser = 'Edge';
        } elseif (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident/7') !== false) {
            $browser = 'Internet Explorer';
        }

        return $browser;
    }

    private function getLocationInfo($ip): string
    {
        return $ip === '127.0.0.1' ? 'localhost' : 'Unknown';
    }
}
