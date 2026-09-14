<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Support\Str;

final readonly class CustomerData
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}

    /** @param array<string, mixed> $attributes */
    public static function fromArray(array $attributes): self
    {
        return new self(
            name: (string) $attributes['name'],
            email: Str::lower(trim((string) $attributes['email'])),
        );
    }
}
