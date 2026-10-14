<?php

declare(strict_types=1);

namespace App\Library\Application\Services;

use App\Library\Application\Ports\PasswordHasher;
use App\Library\Application\Ports\UserRepository;
use App\Library\Application\UserSummary;
use App\Library\Domain\Errors\ForbiddenRole;
use App\Library\Domain\Errors\UsernameTaken;
use App\Library\Domain\Errors\ValidationError;
use App\Library\Domain\Guard;
use App\Library\Domain\User;
use SensitiveParameter;

final readonly class CreateLibrarianService
{
    public function __construct(private UserRepository $users, private PasswordHasher $passwords)
    {
    }

    /** El actor viene de la autenticación y el id del servidor; ninguno se admite como entrada HTTP. */
    public function execute(User $actor, string $id, string $username,
        #[SensitiveParameter] string $password, string $role): UserSummary
    {
        if ($actor->role !== 'admin') {
            throw new ForbiddenRole;
        }
        if ($role !== 'librarian') {
            throw new ValidationError('role', 'Solo se permite crear bibliotecarios.');
        }
        $id = Guard::uuid($id);
        $username = User::normalizeUsername($username);
        if ($password === '') {
            throw new ValidationError('password', 'La contraseña es obligatoria.');
        }
        if ($this->users->findByUsername($username) !== null) {
            throw new UsernameTaken;
        }
        $user = new User($id, $username, $this->passwords->hash($password), 'librarian');
        $this->users->add($user);

        return UserSummary::fromUser($user);
    }
}
