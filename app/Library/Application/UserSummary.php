<?php

declare(strict_types=1);

namespace App\Library\Application;

use App\Library\Domain\User;
use JsonSerializable;

final readonly class UserSummary implements JsonSerializable
{
    private function __construct(public string $id, public string $username, public string $role)
    {
    }

    public static function fromUser(User $user): self
    {
        return new self($user->id, $user->username, $user->role);
    }

    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'username' => $this->username, 'role' => $this->role];
    }
}
