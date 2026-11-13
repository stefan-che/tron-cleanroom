<?php

declare(strict_types=1);

namespace App\Http\Controllers\Orchid;

use App\Models\User;
use App\Services\Security\BlockedIpService;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Orchid\Platform\Http\Controllers\LoginController as OLoginController;

class LoginController extends OLoginController
{
    private BlockedIpService $blockedIpService;

    public function __construct(
        \Illuminate\Contracts\Auth\Factory $auth,
        BlockedIpService $blockedIpService
    )
    {
        parent::__construct($auth);

        $this->blockedIpService = $blockedIpService;
    }

    public function login(Request $request, CookieJar $cookieJar)
    {
        $ip = $request->ip();

        if ($this->blockedIpService->isBlocked($ip)) {
            $remainingTime = $this->blockedIpService->getRemainingBlockTime($ip);
            $message = $remainingTime ? "IP address is blocked. Try again in {$remainingTime} minutes."
                : "IP address is blocked permanently.";

            throw ValidationException::withMessages([
                'email' => $message,
            ]);
        }

        $credentials = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
            'code' => 'nullable|string',
        ]);

        if (Auth::validate([
                'email' => $credentials['email'],
                'password' => $credentials['password'],
            ]) === false) {
            $this->blockedIpService->incrementAttempts($ip, $request->userAgent());
            throw ValidationException::withMessages([
                'email' => __('The details you entered did not match our records. Please double-check and try again.'),
            ]);
        }

        $this->blockedIpService->resetAttempts($ip);

        /**
         * @var User $user
         */
        $user = User::where('email', $credentials['email'])->first();

        if ($user->is_two_factor_enabled) {

            if (empty($credentials['code'])) {
                throw ValidationException::withMessages([
                    'code' => 'Enter the two-factor authentication code',
                ]);
            }

            $google2fa = app('pragmarx.google2fa');

            $valid = $google2fa->verifyKey(
                $user->two_factor_secret, $credentials['code']
            );

            if (!$valid) {
                throw ValidationException::withMessages([
                    'code' => 'Invalid two-factor authentication code'
                ]);
            }
        }

        Auth::login($user);

        if ($request->boolean('remember')) {
            $user = $cookieJar->forever($this->nameForLock(), $this->guard->id());
            $cookieJar->queue($user);
        }

        return $this->sendLoginResponse($request);
    }


    /**
     * Get a unique identifier for the auth session value.
     *
     * @return string
     */
    private function nameForLock(): string
    {
        return sprintf('%s_%s', $this->guard->getName(), '_orchid_lock');
    }
}
