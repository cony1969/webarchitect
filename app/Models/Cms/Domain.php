<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Domain extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'cms_domains';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['settings' => 'array', 'source_payload' => 'array', 'revision' => 'integer', 'position' => 'integer'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function pages()
    {
        return $this->hasMany(Page::class)->orderBy('position');
    }
}
