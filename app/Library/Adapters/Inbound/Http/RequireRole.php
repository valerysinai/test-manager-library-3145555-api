<?php
declare(strict_types=1);
namespace App\Library\Adapters\Inbound\Http;
use App\Library\Domain\Errors\ForbiddenRole;
use Closure;
use Illuminate\Http\Request;
final class RequireRole
{
    public function handle(Request $request, Closure $next, string $role): mixed
    {
        if ($request->attributes->get('actor')?->role !== $role) { throw new ForbiddenRole; }
        return $next($request);
    }
}
