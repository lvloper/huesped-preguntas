<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = ['name', 'email', 'question', 'selected', 'answered'];

    protected function casts(): array
    {
        return ['selected' => 'boolean', 'answered' => 'boolean'];
    }
}
