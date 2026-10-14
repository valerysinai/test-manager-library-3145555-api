<?php

declare(strict_types=1);

namespace App\Library\Application\Ports;

use App\Library\Domain\User;

interface UserRepository
{
    /** Recibe el nombre ya normalizado. */
    public function findByUsername(string $username): ?User;

    /** El índice UNIQUE arbitra altas concurrentes: traducir únicamente ese conflicto a UsernameTaken. */
    public function add(User $user): void;
}
