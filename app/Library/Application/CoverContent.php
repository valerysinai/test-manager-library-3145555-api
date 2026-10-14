<?php
declare(strict_types=1);
namespace App\Library\Application;
final readonly class CoverContent
{
    public const int MAX_BYTES = 2000000;
    public function __construct(public string $bytes, public string $mediaType)
    {
        if (!in_array($mediaType, ['image/jpeg', 'image/png'], true)
            || strlen($bytes) < 1 || strlen($bytes) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('La portada no cumple el contrato del almacenamiento.');
        }
    }
}
