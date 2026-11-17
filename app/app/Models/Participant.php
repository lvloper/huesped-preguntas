<?php

namespace App\Models;

use Database\Factories\ParticipantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Participant extends Model
{
    /** @use HasFactory<ParticipantFactory> */
    use HasFactory;

    protected $fillable = ['name', 'email'];

    protected static function booted(): void
    {
        static::creating(function (Participant $participant): void {
            $participant->uid ??= Str::random(40);
        });
    }
}
