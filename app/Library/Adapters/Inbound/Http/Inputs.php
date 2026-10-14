<?php
declare(strict_types=1);
namespace App\Library\Adapters\Inbound\Http;
use App\Library\Domain\Errors\ValidationError;
use Illuminate\Http\Request;
final class Inputs
{
    public static function body(Request $request, array $fields): array
    {
        if (!$request->isJson()) { throw new ValidationError('body', 'Se requiere un objeto JSON.'); }
        try { $value = json_decode($request->getContent(), false, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new ValidationError('body', 'El JSON no es válido.'); }
        if (!$value instanceof \stdClass) { throw new ValidationError('body', 'Se requiere un objeto JSON.'); }
        $body = get_object_vars($value);
        if (array_diff(array_keys($body), $fields) !== [] || array_diff($fields, array_keys($body)) !== []) {
            throw new ValidationError('body', 'Los campos no coinciden con la operación.');
        }
        return $body;
    }
    public static function text(array $body, string $field): string
    {
        if (!is_string($body[$field] ?? null)) { throw new ValidationError($field, 'El campo debe ser una cadena.'); }
        return $body[$field];
    }
    public static function integer(array $body, string $field): int
    {
        if (!is_int($body[$field] ?? null)) { throw new ValidationError($field, 'El campo debe ser un entero.'); }
        return $body[$field];
    }
    public static function query(Request $request, array $fields): array
    {
        $query = $request->query->all();
        if (array_diff(array_keys($query), $fields) !== []) { throw new ValidationError('query', 'Parámetros no admitidos.'); }
        foreach ($query as $key => $value) {
            if ($value === null && $key === 'q') { $query[$key] = ''; continue; }
            if (!is_string($value)) { throw new ValidationError($key, 'El parámetro debe ser una cadena.'); }
        }
        return $query;
    }
    public static function page(array $query, string $key, int $default): int
    {
        if (!array_key_exists($key, $query)) { return $default; }
        $value = $query[$key];
        if (preg_match('/\A[1-9][0-9]*\z/', $value) !== 1
            || ($number = filter_var($value, FILTER_VALIDATE_INT)) === false || ($key === 'size' && $number > 50)) {
            throw new ValidationError($key, 'La paginación debe ser positiva y el tamaño no puede superar 50.');
        }
        return $number;
    }
    public static function emptyBody(Request $request): void
    {
        if (trim($request->getContent()) !== '') { throw new ValidationError('body', 'La operación no admite cuerpo.'); }
    }
    public static function upload(Request $request): string
    {
        if ($request->isMethod('PUT') && $request->allFiles()===[] && PHP_SAPI!=='cli'
            && str_starts_with(strtolower($request->header('Content-Type','')),'multipart/form-data')) {
            try {
                [$fields,$files]=request_parse_body();
                $request->request->replace($fields);
                $request->files->replace($files);
                // allFiles() caches converted files; use a fresh request after native PUT parsing.
                $request=Request::createFromBase($request);
            } catch (\Throwable) { throw new ValidationError('file','El multipart no es válido.'); }
        }
        if (!str_starts_with(strtolower($request->header('Content-Type', '')), 'multipart/form-data')
            || $request->request->all() !== [] || array_keys($request->allFiles()) !== ['file']) {
            throw new ValidationError('file', 'Se requiere únicamente el archivo file en multipart.');
        }
        $file = $request->file('file');
        if (!$file instanceof \Illuminate\Http\UploadedFile || !$file->isValid()
            || $file->getSize() < 1 || $file->getSize() > \App\Library\Application\CoverContent::MAX_BYTES) {
            throw new ValidationError('file', 'El archivo debe ser válido y no superar 2.000.000 bytes.');
        }
        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false) { throw new \RuntimeException('No se pudo leer el archivo recibido.'); }
        return $bytes;
    }
}
