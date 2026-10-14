<?php

declare(strict_types=1);

namespace App\Library\Domain;

use App\Library\Domain\Errors\ValidationError;
use SensitiveParameter;

final readonly class User
{
    public string $id;
    public string $username;

    public function __construct(
        string $id,
        string $username,
        #[SensitiveParameter] private string $passwordHash,
        public string $role,
    ) {
        $this->id = Guard::uuid($id);
        $this->username = self::normalizeUsername($username);
        if (!in_array($role, ['admin', 'librarian'], true)) {
            throw new ValidationError('role', 'El rol no es válido.');
        }
        if ($passwordHash === '' || strlen($passwordHash) > 512) {
            throw new ValidationError('password_hash', 'El hash debe existir y respetar el almacenamiento definido.');
        }
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public static function normalizeUsername(string $username): string
    {
        return Guard::text('username', mb_strtolower(trim($username), 'UTF-8'), 120);
    }

    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'username' => $this->username, 'role' => $this->role];
    }
}
