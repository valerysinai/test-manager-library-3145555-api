<?php

declare(strict_types=1);

namespace App\Library\Application\Ports;

use DateTimeImmutable;

interface TokenIssuer
{
    /** T-14: JWT HS256, sub/role e instantes UTC; sin nombre, contraseña ni hash. */
    public function issue(string $subject, string $role, DateTimeImmutable $issuedAt, DateTimeImmutable $expiresAt): string;
}
