<?php
declare(strict_types=1);
namespace App\Library\Adapters\Inbound\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
final class SafeExceptionReporter
{
    public static function report(Throwable $error): bool
    {
        // Nunca registrar message/trace, SQL/bindings, petición, token o credenciales.
        if (!$error instanceof \DomainException && !$error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            Log::error('Error interno de API.', ['type'=>$error::class]);
        }
        return false;
    }
}
