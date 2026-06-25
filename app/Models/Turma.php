<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turma extends Model
{
    use HasUuids;

    protected $fillable = ['serie_id', 'name'];

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
