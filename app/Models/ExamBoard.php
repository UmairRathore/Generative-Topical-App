<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamBoard extends Model
{
    protected $fillable = ['name', 'slug'];

    public function qualifications(): HasMany
    {
        return $this->hasMany(Qualification::class);
    }
}
