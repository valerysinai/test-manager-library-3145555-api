<?php

declare(strict_types=1);

namespace App\Library\Application\Services;

use App\Library\Application\LoginResult;
use App\Library\Application\Ports\Clock;
use App\Library\Application\Ports\PasswordHasher;
use App\Library\Application\Ports\TokenIssuer;
use App\Library\Application\Ports\UserRepository;
use App\Library\Domain\Errors\InvalidCredentials;
use App\Library\Domain\Errors\ValidationError;
use App\Library\Domain\Guard;
use App\Library\Domain\User;
use DateInterval;
use SensitiveParameter;

final readonly class LoginService
{
    public function __construct(private UserRepository $users, private PasswordHasher $passwords,
        private TokenIssuer $tokens, private Clock $clock)
    {
    }

    public function execute(string $username, #[SensitiveParameter] string $password): LoginResult
    {
        $username = User::normalizeUsername($username);
        if ($password === '') {
            throw new ValidationError('password', 'La contraseña es obligatoria.');
        }
        $user = $this->users->findByUsername($username);
        $verified = $this->passwords->verify($password, $user?->passwordHash());
        if ($user === null || !$verified) {
            throw new InvalidCredentials;
        }
        $issuedAt = Guard::utc($this->clock->now());
        $expiresAt = $issuedAt->add(new DateInterval('PT'.LoginResult::LIFETIME_SECONDS.'S'));

        return new LoginResult($this->tokens->issue($user->id, $user->role, $issuedAt, $expiresAt), $user->role);
    }
}
