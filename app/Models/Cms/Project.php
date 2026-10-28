<?php

namespace App\Models\Cms;

class Project extends Document
{
    protected $table = 'cms_projects';

    public function domains()
    {
        return $this->hasMany(Domain::class)->orderBy('position');
    }

    public function pages()
    {
        return $this->hasMany(Page::class)->orderBy('position');
    }
}
