<?php

namespace App\Models\Cms;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class Document extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['source_payload', 'owner_key'];

    protected function casts(): array
    {
        return ['document' => 'array', 'settings' => 'array', 'source_payload' => 'array', 'revision' => 'integer', 'position' => 'integer'];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
