<?php

namespace App\Models\Energy;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Orchid\Screen\AsSource;

class EnergyService extends BaseModel
{
    use HasFactory, AsSource;

    const string REFEE_CODE = 'refee';
    const string TR_ENERGY_CODE = 'tr.energy';
    const string NETTS_CODE = 'netts.io';

    protected $table = 'energy_services';

    protected $fillable = [
        'name',
        'code',
        'is_enabled',
        'priority',
        'description',
        'site_link',
        'api_doc_link',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'priority' => 'integer',
    ];
}
