<?php
declare(strict_types=1);
namespace App\Library\Adapters\Inbound\Http;
use App\Library\Application\Ports\{TokenVerifier, UserRepository};
use App\Library\Domain\Errors\Unauthenticated;
use Closure;
use Illuminate\Http\Request;
final readonly class AuthenticateToken
{
    public function __construct(private TokenVerifier $tokens, private UserRepository $users) {}
    public function handle(Request $request, Closure $next): mixed
    {
        $header = $request->header('Authorization', '');
        if (preg_match('/\ABearer ([A-Za-z0-9_.-]+)\z/i', $header, $match) !== 1) { throw new Unauthenticated; }
        $identity = $this->tokens->verify($match[1]);
        $user = $this->users->findById($identity->id);
        if ($user === null || $user->role !== $identity->role) { throw new Unauthenticated; }
        $request->attributes->set('actor', $user);
        return $next($request);
    }
}
