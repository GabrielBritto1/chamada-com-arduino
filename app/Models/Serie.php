<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Serie extends Model
{
    use HasUuids;

    protected $fillable = ['escola_id', 'name'];

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class);
    }

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }
}
