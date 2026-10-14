<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Security;
use App\Library\Application\Ports\PasswordHasher;
use SensitiveParameter;
final readonly class ArgonPasswordHasher implements PasswordHasher
{
    private string $dummy;
    public function __construct()
    {
        if (!in_array('argon2id', password_algos(), true)) { throw new \RuntimeException('Argon2id no está disponible.'); }
        $this->dummy = $this->hash(bin2hex(random_bytes(32)));
    }
    public function hash(#[SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    public function verify(#[SensitiveParameter] string $password, #[SensitiveParameter] ?string $hash): bool
    {
        $validHash = $hash !== null && (password_get_info($hash)['algoName'] ?? '') === 'argon2id';
        $matched = password_verify($password, $validHash ? $hash : $this->dummy);
        return $validHash && $matched;
    }
    public function __debugInfo(): array { return ['algorithm' => 'argon2id']; }
}
