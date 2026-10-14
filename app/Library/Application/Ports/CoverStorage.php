<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
use App\Library\Application\CoverContent;
interface CoverStorage
{
    /** Detecta por contenido, no por nombre/extensión ni cabecera del cliente. */
    public function mediaType(string $bytes): ?string;
    /** Clave nueva, opaca y no vacía; nunca ruta ni URL. Escritura completa o excepción. */
    public function put(CoverContent $cover): string;
    public function read(string $key): ?CoverContent;
    /** Idempotente si el binario ya no existe; falla explícitamente ante error de almacenamiento. */
    public function delete(string $key): void;
}
