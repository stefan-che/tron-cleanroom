<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\User\BlockedIp;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BlockedIpService
{
    private const int MAX_LOGIN_ATTEMPTS = 3;
    private const int BLOCK_DURATION_MINUTES = 60 * 24 * 31;

    /**
     * Checks whether the IP address is blocked
     */
    public function isBlocked(string $ip): bool
    {
        $blockedIp = BlockedIp::where('ip_address', $ip)->first();

        if (!$blockedIp) {
            return false;
        }

        // Permanent block
        if ($blockedIp->is_permanently_blocked) {
            return true;
        }

        // Unblocked
        if ($blockedIp->unblocked_at) {
            return false;
        }

        // Block period has expired
        if ($blockedIp->blocked_until && $blockedIp->blocked_until->isPast()) {
            return false;
        }

        return $blockedIp->blocked_until !== null;
    }

    /**
     * Returns the remaining block time in minutes
     */
    public function getRemainingBlockTime(string $ip): ?int
    {
        $blockedIp = BlockedIp::where('ip_address', $ip)->first();

        if (!$blockedIp || $blockedIp->unblocked_at || $blockedIp->is_permanently_blocked) {
            return null;
        }

        if (!$blockedIp->blocked_until) {
            return null;
        }

        $remainingSeconds = Carbon::now()->diffInSeconds($blockedIp->blocked_until, false);

        return max(0, (int) ceil($remainingSeconds / 60));
    }

    /**
     * Increments the attempt counter for the IP
     */
    public function incrementAttempts(string $ip, ?string $userAgent = null): void
    {
        $blockedIp = BlockedIp::firstOrNew(['ip_address' => $ip]);

        $blockedIp->attempts = ($blockedIp->attempts ?? 0) + 1;
        $blockedIp->user_agent = $userAgent ?? $blockedIp->user_agent;

        if ($blockedIp->attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $blockedIp->blocked_until = Carbon::now()->addMinutes(self::BLOCK_DURATION_MINUTES);
            $blockedIp->reason = 'Too many failed login attempts';
            $blockedIp->is_permanently_blocked = false;
            $blockedIp->unblocked_at = null;
            $blockedIp->unblocked_by = null;

            Log::channel('security')->warning("IP address {$ip} blocked after " . self::MAX_LOGIN_ATTEMPTS . " failed login attempts");
        }

        $blockedIp->save();
    }

    /**
     * Resets the attempt counter for the IP
     */
    public function resetAttempts(string $ip): void
    {
        BlockedIp::where('ip_address', $ip)->delete();
    }

    /**
     * Blocks the IP permanently
     */
    public function blockPermanently(string $ip, string $reason, ?int $userId = null): BlockedIp
    {
        $blockedIp = BlockedIp::firstOrNew(['ip_address' => $ip]);

        $blockedIp->attempts = $blockedIp->attempts ?? 0;
        $blockedIp->blocked_until = null;
        $blockedIp->reason = $reason;
        $blockedIp->is_permanently_blocked = true;
        $blockedIp->unblocked_at = null;
        $blockedIp->unblocked_by = null;

        $blockedIp->save();

        Log::channel('security')->info("IP address {$ip} blocked permanently. Reason: {$reason}");

        return $blockedIp;
    }

    /**
     * Blocks the IP for a given time
     */
    public function blockTemporary(string $ip, int $minutes, string $reason, ?int $userId = null): BlockedIp
    {
        $blockedIp = BlockedIp::firstOrNew(['ip_address' => $ip]);

        $blockedIp->attempts = $blockedIp->attempts ?? 0;
        $blockedIp->blocked_until = Carbon::now()->addMinutes($minutes);
        $blockedIp->reason = $reason;
        $blockedIp->is_permanently_blocked = false;
        $blockedIp->unblocked_at = null;
        $blockedIp->unblocked_by = null;

        $blockedIp->save();

        Log::channel('security')->info("IP address {$ip} blocked for {$minutes} minutes. Reason: {$reason}");

        return $blockedIp;
    }

    /**
     * Unblocks the IP
     */
    public function unblock(string $ip, ?int $userId = null): bool
    {
        $blockedIp = BlockedIp::where('ip_address', $ip)->first();

        if (!$blockedIp) {
            return false;
        }

        $blockedIp->unblocked_at = Carbon::now();
        $blockedIp->unblocked_by = $userId;
        $blockedIp->save();

        Log::channel('security')->info("IP address {$ip} unblocked by user ID: {$userId}");

        return true;
    }

    /**
     * Returns block statistics
     */
    public function getStatistics(): array
    {
        $total = BlockedIp::count();
        $active = BlockedIp::active()->count();
        $permanent = BlockedIp::where('is_permanently_blocked', true)->count();
        $unblocked = BlockedIp::unblocked()->count();

        return [
            'total' => $total,
            'active' => $active,
            'permanent' => $permanent,
            'temporary' => $active - $permanent,
            'unblocked' => $unblocked,
        ];
    }

    /**
     * Checks whether the IP may be blocked (validation)
     */
    public function canBlock(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}
