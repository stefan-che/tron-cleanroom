<?php

namespace App\Models\Ai;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property ?string $model
 * @property ?string $api_key
 * @property ?string $admin_api_key
 * @property ?float $balance_topup
 * @property ?\Carbon\Carbon $balance_topup_at
 * @property ?float $balance_threshold
 * @property bool $is_enabled
 * @property int $priority
 * @property ?string $prompt
 * @property ?string $description
 * @property ?string $site_link
 * @property ?string $api_doc_link
 *
 * @method static openAi()
 */
class AiService extends BaseModel
{
    use HasFactory, AsSource, Filterable;

    const string OPENAI_CODE = 'openai';
    const string CLAUDE_CODE = 'claude';

    protected $table = 'ai_services';

    protected $fillable = [
        'name',
        'code',
        'model',
        'api_key',
        'admin_api_key',
        'balance_topup',
        'balance_topup_at',
        'balance_threshold',
        'is_enabled',
        'priority',
        'prompt',
        'description',
        'site_link',
        'api_doc_link',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'priority' => 'integer',
        'balance_topup' => 'float',
        'balance_topup_at' => 'datetime',
        'balance_threshold' => 'float',
    ];

    public function isEnabled(): bool
    {
        return $this->is_enabled;
    }

    public function scopeOpenAi(Builder $query): Builder
    {
        return $query->where('code', self::OPENAI_CODE);
    }

    public function scopeClaude(Builder $query): Builder
    {
        return $query->where('code', self::CLAUDE_CODE);
    }
}
