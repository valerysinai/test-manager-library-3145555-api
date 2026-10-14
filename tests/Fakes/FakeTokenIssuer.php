<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Library\Application\Ports\TokenIssuer;
use DateTimeImmutable;

/** No firma JWT: captura únicamente identidad, rol y fechas del contrato del puerto. */
final class FakeTokenIssuer implements TokenIssuer
{
    public array $issued = [];
    public bool $failOnIssue = false;
    public bool $returnEmpty = false;

    public function issue(string $subject, string $role, DateTimeImmutable $issuedAt, DateTimeImmutable $expiresAt): string
    {
        if ($this->failOnIssue) {
            throw new \RuntimeException('No se pudo emitir el token.');
        }
        $this->issued[] = compact('subject', 'role', 'issuedAt', 'expiresAt');

        return $this->returnEmpty ? '' : 'fake-token-'.count($this->issued);
    }
}
