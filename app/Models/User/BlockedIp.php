<?php

namespace App\Models\User;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class BlockedIp extends Model
{
    use HasFactory, AsSource, Filterable;

    protected $fillable = [
        'ip_address',
        'attempts',
        'blocked_until',
        'reason',
        'user_agent',
        'is_permanently_blocked',
        'unblocked_at',
        'unblocked_by',
    ];

    protected $casts = [
        'blocked_until' => 'datetime',
        'unblocked_at' => 'datetime',
        'is_permanently_blocked' => 'boolean',
    ];

    /**
     * Relation to the user who unblocked the IP
     */
    public function unblockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unblocked_by');
    }

    /**
     * Scope for active blocks
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('is_permanently_blocked', true)
              ->orWhere(function ($sq) {
                  $sq->where('is_permanently_blocked', false)
                     ->where('blocked_until', '>', Carbon::now())
                     ->whereNull('unblocked_at');
              });
        });
    }

    /**
     * Scope for unblocked IPs
     */
    public function scopeUnblocked($query)
    {
        return $query->whereNotNull('unblocked_at');
    }

    /**
     * Scope for permanent blocks
     */
    public function scopePermanent($query)
    {
        return $query->where('is_permanently_blocked', true);
    }

    /**
     * Scope for temporary blocks
     */
    public function scopeTemporary($query)
    {
        return $query->where('is_permanently_blocked', false)
                    ->whereNull('unblocked_at');
    }

    /**
     * Checks whether the block is active
     */
    public function isActive(): bool
    {
        if ($this->unblocked_at) {
            return false;
        }

        if ($this->is_permanently_blocked) {
            return true;
        }

        return $this->blocked_until && $this->blocked_until->isFuture();
    }

    /**
     * Returns the block status in human-readable form
     */
    public function getStatusText(): string
    {
        if ($this->unblocked_at) {
            return 'Unblocked';
        }

        if ($this->is_permanently_blocked) {
            return 'Blocked permanently';
        }

        if ($this->blocked_until && $this->blocked_until->isFuture()) {
            return 'Blocked temporarily';
        }

        return 'Inactive';
    }

    /**
     * Returns the remaining block time in minutes
     */
    public function getRemainingMinutes(): ?int
    {
        if (!$this->isActive() || $this->is_permanently_blocked) {
            return null;
        }

        return Carbon::now()->diffInMinutes($this->blocked_until, false);
    }
}
