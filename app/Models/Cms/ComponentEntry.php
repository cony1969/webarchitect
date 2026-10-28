<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ComponentEntry extends Model
{
    use HasUuids;

    protected $table = 'cms_component_entries';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['source_payload' => 'array'];
    }

    public function component()
    {
        return $this->belongsTo(Component::class);
    }
}
