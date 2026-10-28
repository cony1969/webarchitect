<?php

namespace App\Models\Cms;

class Page extends Document
{
    protected $table = 'cms_pages';

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }
}
