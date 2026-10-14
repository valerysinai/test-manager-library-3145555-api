<?php

declare(strict_types=1);

namespace App\Library\Application\Ports;

use SensitiveParameter;

interface PasswordHasher
{
    public function hash(#[SensitiveParameter] string $password): string;

    /** Hash nulo: hacer una verificación ficticia de coste equivalente y devolver false (T-14). */
    public function verify(#[SensitiveParameter] string $password, #[SensitiveParameter] ?string $hash): bool;
}
