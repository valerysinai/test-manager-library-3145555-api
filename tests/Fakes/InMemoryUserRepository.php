<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Library\Application\Ports\UserRepository;
use App\Library\Domain\Errors\UsernameTaken;
use App\Library\Domain\User;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    public array $users = [];
    public int $lookupCount = 0;
    public bool $conflictOnAdd = false;
    public function findById(string $id): ?User { return $this->users[$id] ?? null; }
    public function lockBootstrap(): void {}

    public function findByUsername(string $username): ?User
    {
        $this->lookupCount++;
        foreach ($this->users as $user) {
            if ($user->username === $username) {
                return $user;
            }
        }

        return null;
    }

    public function add(User $user): void
    {
        if ($this->conflictOnAdd) {
            throw new UsernameTaken;
        }
        foreach ($this->users as $existing) {
            if ($existing->username === $user->username) {
                throw new UsernameTaken;
            }
        }
        if (isset($this->users[$user->id])) {
            throw new \LogicException('Identidad de usuario repetida.');
        }
        $this->users[$user->id] = $user;
    }
}
