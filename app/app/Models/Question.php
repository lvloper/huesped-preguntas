<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use SoftDeletes;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['name', 'email', 'question', 'selected', 'answered', 'participant_id'];

    protected function casts(): array
    {
        return ['selected' => 'boolean', 'answered' => 'boolean'];
    }
}
