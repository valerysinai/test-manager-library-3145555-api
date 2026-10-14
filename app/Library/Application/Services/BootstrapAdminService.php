<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{PasswordHasher, UnitOfWork, UserRepository, UuidGenerator};
use App\Library\Application\UserSummary;
use App\Library\Domain\User;
use App\Library\Domain\Errors\{UsernameTaken, ValidationError};
use SensitiveParameter;
final readonly class BootstrapAdminService
{
    public function __construct(private UserRepository $users, private PasswordHasher $passwords,
        private UnitOfWork $work, private UuidGenerator $ids) {}
    public function execute(string $username, #[SensitiveParameter] string $password): UserSummary
    {
        $username = User::normalizeUsername($username);
        if ($password === '') { throw new ValidationError('password', 'La contraseña inicial es obligatoria.'); }
        return $this->work->run(function () use ($username, $password): UserSummary {
            $this->users->lockBootstrap();
            $user = $this->users->findByUsername($username);
            if ($user !== null) {
                if ($user->role !== 'admin') { throw new UsernameTaken; }
                return UserSummary::fromUser($user);
            }
            $user = new User($this->ids->next(), $username, $this->passwords->hash($password), 'admin');
            $this->users->add($user);
            return UserSummary::fromUser($user);
        });
    }
}
