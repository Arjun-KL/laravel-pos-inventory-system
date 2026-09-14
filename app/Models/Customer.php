<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
    ];

    /**
     * Lower-cased on write so the plain unique index gives case-insensitive
     * uniqueness. Anything querying by email must lower-case it first.
     */
    protected function email(): Attribute
    {
        return Attribute::set(static fn (string $value): string => Str::lower(trim($value)));
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
