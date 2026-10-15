<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Storage;
use App\Library\Application\CoverContent;
use App\Library\Application\Ports\{CoverStorage, UuidGenerator};
final readonly class DiskCoverStorage implements CoverStorage
{
    private string $root;
    public function __construct(string $directory, private UuidGenerator $ids, string $publicDirectory)
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('No se pudo preparar el directorio privado de portadas.');
        }
        $root = realpath($directory);
        $public = realpath($publicDirectory);
        if ($root === false || ($public !== false && ($root === $public || str_starts_with($root, $public.DIRECTORY_SEPARATOR)))) {
            throw new \RuntimeException('Las portadas requieren un directorio privado fuera de public.');
        }
        $this->root = $root;
    }
    public function mediaType(string $bytes): ?string
    {
        $type = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!in_array($type, ['image/jpeg', 'image/png'], true)) { return null; }
        $size = @getimagesizefromstring($bytes);
        return is_array($size) && ($size['mime'] ?? null) === $type ? $type : null;
    }
    private function path(string $key): string
    {
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.(?:jpg|png)\z/', $key) !== 1) {
            throw new \UnexpectedValueException('Clave de portada inválida.');
        }
        $path = $this->root.DIRECTORY_SEPARATOR.$key;
        if (is_link($path)) { throw new \RuntimeException('No se admiten enlaces de portada.'); }
        return $path;
    }
    public function put(CoverContent $cover): string
    {
        if ($this->mediaType($cover->bytes) !== $cover->mediaType) { throw new \InvalidArgumentException('El contenido no coincide con la imagen.'); }
        $key = $this->ids->next().($cover->mediaType === 'image/png' ? '.png' : '.jpg');
        $path = $this->path($key);
        $file = fopen($path, 'xb');
        if ($file === false) { throw new \RuntimeException('No se pudo guardar la portada.'); }
        try {
            chmod($path, 0600);
            $offset = 0;
            while ($offset < strlen($cover->bytes)) {
                $written = fwrite($file, substr($cover->bytes, $offset));
                if ($written === false || $written === 0) { throw new \RuntimeException('Escritura de portada incompleta.'); }
                $offset += $written;
            }
        } catch (\Throwable $error) { fclose($file); @unlink($path); throw $error; }
        fclose($file);
        return $key;
    }
    public function read(string $key): ?CoverContent
    {
        $path = $this->path($key);
        if (!file_exists($path)) { return null; }
        if (!is_file($path) || filesize($path) > CoverContent::MAX_BYTES) { throw new \RuntimeException('Archivo de portada inválido.'); }
        $bytes = file_get_contents($path);
        if ($bytes === false) { throw new \RuntimeException('No se pudo leer la portada.'); }
        $type = $this->mediaType($bytes);
        if ($type === null) { throw new \RuntimeException('Contenido de portada inválido.'); }
        return new CoverContent($bytes, $type);
    }
    public function delete(string $key): void
    {
        $path = $this->path($key);
        if (file_exists($path) && !unlink($path)) { throw new \RuntimeException('No se pudo borrar la portada.'); }
    }
}
