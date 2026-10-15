<?php
declare(strict_types=1);
namespace App\Library\Application;
use App\Library\Domain\Guard;
final readonly class UserIdentity
{
    public string $id;
    public function __construct(string $id, public string $role)
    {
        $this->id = Guard::uuid($id);
        if (!in_array($role, ['admin', 'librarian'], true)) { throw new \InvalidArgumentException('Rol inválido.'); }
    }
}
