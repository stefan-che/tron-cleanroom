<?php

namespace App\Models\Tron;

use App\Models\BaseModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $type
 * @property string $full_node
 * @property ?string $solidity_node
 * @property ?string $event_server
 * @property ?string $api_key
 * @property bool $is_enabled
 * @property bool $is_available
 * @property int $priority
 * @property ?int $last_block
 * @property ?Carbon $last_check_at
 * @property ?Carbon $last_block_changed_at
 * @property ?Carbon $unavailable_at
 * @property ?string $last_error
 * @property ?string $description
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Builder enabled()
 * @method static Builder available()
 * @method static Builder own()
 * @method static Builder tronGrid()
 * @method static Builder byPriority()
 */
class TronNode extends BaseModel
{
    use HasFactory, AsSource, Filterable;

    /**
     * Our own node
     */
    const string TYPE_OWN = 'own';

    /**
     * Public trongrid.io API (fallback and block reference for alerts)
     */
    const string TYPE_TRONGRID = 'trongrid';

    const string CODE_NODE_1 = 'node_1';
    const string CODE_NODE_2 = 'node_2';
    const string CODE_TRONGRID = 'trongrid';

    protected $table = 'tron_nodes';

    protected $fillable = [
        'name',
        'code',
        'type',
        'full_node',
        'solidity_node',
        'event_server',
        'api_key',
        'is_enabled',
        'is_available',
        'priority',
        'last_block',
        'last_check_at',
        'last_block_changed_at',
        'unavailable_at',
        'last_error',
        'description',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_available' => 'boolean',
        'priority' => 'integer',
        'last_block' => 'integer',
        'last_check_at' => 'datetime',
        'last_block_changed_at' => 'datetime',
        'unavailable_at' => 'datetime',
    ];

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    public function scopeOwn(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_OWN);
    }

    public function scopeTronGrid(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_TRONGRID);
    }

    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderBy('id');
    }

    public function isEnabled(): bool
    {
        return $this->is_enabled;
    }

    /**
     * The node can be used for operations
     */
    public function isReady(): bool
    {
        return $this->is_enabled && $this->is_available;
    }

    public function isOur(): bool
    {
        return $this->type === self::TYPE_OWN;
    }

    public function isTronGrid(): bool
    {
        return $this->type === self::TYPE_TRONGRID;
    }

    /**
     * Mark the node available (called by alerts after a successful check)
     */
    public function markAvailable(?int $lastBlock = null): bool
    {
        return $this->update(array_merge($this->blockAttributes($lastBlock), [
            'is_available' => true,
            'unavailable_at' => null,
            'last_error' => null,
        ]));
    }

    /**
     * Mark the node unavailable so operations move to the next one by priority
     */
    public function markUnavailable(string $reason, ?int $lastBlock = null): bool
    {
        return $this->update(array_merge($this->blockAttributes($lastBlock), [
            'is_available' => false,
            'unavailable_at' => $this->unavailable_at ?: now(),
            'last_error' => mb_substr($reason, 0, 1000),
        ]));
    }

    /**
     * Remember the block without touching the node status
     */
    public function updateLastBlock(int $lastBlock): bool
    {
        return $this->update($this->blockAttributes($lastBlock));
    }

    /**
     * The time of the last block change is needed by the sync-stall alert
     *
     * @return array<string, mixed>
     */
    private function blockAttributes(?int $lastBlock): array
    {
        $attributes = [
            'last_block' => $lastBlock ?? $this->last_block,
            'last_check_at' => now(),
        ];

        if ($lastBlock !== null && $lastBlock !== $this->last_block) {
            $attributes['last_block_changed_at'] = now();
        }

        return $attributes;
    }

    public function getStatusLabel(): string
    {
        if (!$this->is_enabled) {
            return 'Disabled';
        }

        return $this->is_available ? 'Available' : 'Unavailable';
    }
}
