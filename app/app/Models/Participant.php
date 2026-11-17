<?php

namespace App\Models;

use Database\Factories\ParticipantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    public const UID_RULES = 'string|max:40|regex:/\A[A-Za-z0-9_-]+\z/';

    /** @use HasFactory<ParticipantFactory> */
    use HasFactory;

    protected $fillable = ['uid', 'name', 'email'];
}
