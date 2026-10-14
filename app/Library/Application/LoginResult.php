<?php

declare(strict_types=1);

namespace App\Library\Application;

use JsonSerializable;
use SensitiveParameter;

final readonly class LoginResult implements JsonSerializable
{
    public const int LIFETIME_SECONDS = 28800;

    public function __construct(#[SensitiveParameter] private string $accessToken, private string $role)
    {
        if ($accessToken === '' || !in_array($role, ['admin', 'librarian'], true)) {
            throw new \InvalidArgumentException('El emisor debe devolver un token y un rol válidos.');
        }
    }

    public function jsonSerialize(): array
    {
        return ['access_token' => $this->accessToken, 'token_type' => 'Bearer',
            'expires_in' => self::LIFETIME_SECONDS, 'role' => $this->role];
    }

    public function __debugInfo(): array
    {
        return ['access_token' => '[REDACTED]', 'token_type' => 'Bearer',
            'expires_in' => self::LIFETIME_SECONDS, 'role' => $this->role];
    }
}
