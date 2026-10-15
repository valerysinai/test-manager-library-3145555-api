<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Clock;
use App\Library\Application\Ports\Clock;
use DateTimeImmutable;
use DateTimeZone;
final readonly class ConfiguredClock implements Clock
{
    private ?DateTimeImmutable $fixed;
    public function __construct(string $mode, ?string $fixedAt, string $profile)
    {
        if (!in_array($mode, ['system', 'fixed'], true)) { throw new \RuntimeException('Configuración de reloj inválida.'); }
        $fixed = null;
        if ($mode === 'fixed') {
            if (!in_array($profile, ['testing', 'demo'], true) || $fixedAt === null
                || preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|\+00:00)\z/', $fixedAt) !== 1) {
                throw new \RuntimeException('El reloj fijo requiere demo/testing e instante UTC válido.');
            }
            try {
                $date = new DateTimeImmutable($fixedAt);
                $errors = DateTimeImmutable::getLastErrors();
                if ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) { throw new \RuntimeException; }
            } catch (\Throwable) { throw new \RuntimeException('Instante fijo inválido.'); }
            $fixed = $date->setTimezone(new DateTimeZone('UTC'));
        }
        $this->fixed = $fixed;
    }
    public function now(): DateTimeImmutable { return $this->fixed ?? new DateTimeImmutable('now', new DateTimeZone('UTC')); }
}
