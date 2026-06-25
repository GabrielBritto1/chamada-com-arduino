<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Escola extends Model
{
    use HasUuids;

    protected $fillable = ['name'];

    public function series(): HasMany
    {
        return $this->hasMany(Serie::class);
    }
}
