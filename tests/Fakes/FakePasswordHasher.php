<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Library\Application\Ports\PasswordHasher;
use SensitiveParameter;

/** Doble de prueba: no es Argon2id ni un adaptador de producción. No guarda contraseñas. */
final class FakePasswordHasher implements PasswordHasher
{
    private array $digests = [];
    public int $hashCount = 0;
    public int $verifyCount = 0;
    public int $missingHashCount = 0;
    public bool $failOnHash = false;

    public function hash(#[SensitiveParameter] string $password): string
    {
        $this->hashCount++;
        if ($this->failOnHash) {
            throw new \RuntimeException('No se pudo generar el hash.');
        }
        $opaque = bin2hex(random_bytes(24));
        $this->digests[$opaque] = hash('sha256', $password);

        return $opaque;
    }

    public function verify(#[SensitiveParameter] string $password, #[SensitiveParameter] ?string $hash): bool
    {
        $this->verifyCount++;
        if ($hash === null) {
            $this->missingHashCount++;
        }
        $digest = hash('sha256', $password);

        return $hash !== null && isset($this->digests[$hash]) && hash_equals($this->digests[$hash], $digest);
    }

    public function __debugInfo(): array
    {
        return ['hash_count' => $this->hashCount, 'verify_count' => $this->verifyCount];
    }
}
