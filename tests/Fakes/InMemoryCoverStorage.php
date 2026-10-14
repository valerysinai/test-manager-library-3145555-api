<?php
declare(strict_types=1);
namespace Tests\Fakes;
use App\Library\Application\CoverContent;
use App\Library\Application\Ports\CoverStorage;
/** Clasificación sintética para probar puertos; no sustituye detección MIME real de T-15. */
final class InMemoryCoverStorage implements CoverStorage
{
    public array $files = [];
    public bool $failDelete = false;
    public bool $failPut = false;
    private int $sequence = 0;
    public function __construct(private InMemoryCatalog $catalog) {}
    public function mediaType(string $bytes): ?string
    {
        $this->assertOutsideTransaction();
        return match (true) {
            str_starts_with($bytes, 'PNG:') => 'image/png',
            str_starts_with($bytes, 'JPEG:') => 'image/jpeg',
            default => null,
        };
    }
    public function put(CoverContent $cover): string
    {
        $this->assertOutsideTransaction();
        if ($this->failPut) { throw new \RuntimeException('Fallo simulado al guardar portada.'); }
        $key = 'cover-'.++$this->sequence;
        $this->files[$key] = $cover;
        $this->catalog->events[] = 'put:'.$key;
        return $key;
    }
    public function read(string $key): ?CoverContent
    {
        $this->assertOutsideTransaction();
        return $this->files[$key] ?? null;
    }
    public function delete(string $key): void
    {
        $this->assertOutsideTransaction();
        $this->catalog->events[] = 'delete:'.$key;
        if ($this->failDelete) { throw new \RuntimeException('Fallo simulado al borrar portada.'); }
        unset($this->files[$key]);
    }
    private function assertOutsideTransaction(): void
    {
        if ($this->catalog->inTransaction) { throw new \LogicException('IO externo dentro de transacción.'); }
    }
}
