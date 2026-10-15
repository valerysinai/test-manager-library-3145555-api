<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Persistence;
use App\Library\Application\Ports\UserRepository;
use App\Library\Domain\User;
use App\Library\Domain\Errors\UsernameTaken;
use PDOException;
final readonly class PgUserRepository implements UserRepository
{
    public function __construct(private PgSession $session) {}
    public function findByUsername(string $username): ?User
    {
        $r = $this->session->one('SELECT * FROM library."user" WHERE username=:username', ['username' => $username]);
        return $r === null ? null : Rows::user($r);
    }
    public function findById(string $id): ?User
    {
        $r = $this->session->one('SELECT * FROM library."user" WHERE id=:id', ['id' => $id]);
        return $r === null ? null : Rows::user($r);
    }
    public function lockBootstrap(): void { $this->session->lock('bootstrap-admin'); }
    public function add(User $user): void
    {
        try {
            $this->session->write('INSERT INTO library."user" (id,username,password_hash,role) VALUES (:id,:username,:hash,:role)',
                ['id' => $user->id, 'username' => $user->username, 'hash' => $user->passwordHash(), 'role' => $user->role]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23505' && str_contains($e->errorInfo[2] ?? '', 'uq_user_username')) { throw new UsernameTaken; }
            throw $e;
        }
    }
}
