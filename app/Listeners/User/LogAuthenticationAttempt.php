<?php

namespace App\Listeners\User;

use App\Helpers\Development;
use App\Models\User\AuthLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Request;

class LogAuthenticationAttempt
{
    public function handleLogin(Login $event): void
    {
        $this->logAttempt($event->user, AuthLog::STATUS_SUCCESS);
    }

    public function handleFailed(Failed $event): void
    {
        if (isset($event->user)) {
            $this->logAttempt($event->user, AuthLog::STATUS_FAILED);
        }
    }

    private function logAttempt($user, int $status, int $type = AuthLog::TYPE_LOGIN): void
    {
        $ip = Development::getRealIpUser();
        $userAgent = Request::userAgent();

        $user->update([
            'last_login_ip_address' => $ip,
        ]);

        AuthLog::create([
            'user_id' => $user->id,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'browser' => $this->getBrowserInfo($userAgent),
            'location' => $this->getLocationInfo($ip),
            'status' => $status,
            'type' => $type
        ]);
    }

    private function getBrowserInfo($userAgent): string
    {
        $browser = 'Unknown';

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
