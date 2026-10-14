<?php
declare(strict_types=1);
namespace App\Library\Adapters\Inbound\Http;
use Closure;
use Illuminate\Http\Request;
final class ApiCors
{
    public function handle(Request $request, Closure $next): mixed
    {
        $origin = $request->headers->get('Origin');
        $configured = config('library.cors_origin');
        if (!is_string($configured) || $configured === '*' || $configured === '') {
            throw new \RuntimeException('CORS_ORIGIN debe identificar un origen concreto.');
        }
        if ($request->isMethod('OPTIONS') && $origin === $configured) {
            return response('', 204, ['Access-Control-Allow-Origin'=>$configured, 'Vary'=>'Origin',
                'Access-Control-Allow-Methods'=>'GET,POST,PUT,DELETE,OPTIONS',
                'Access-Control-Allow-Headers'=>'Authorization,Content-Type', 'Access-Control-Max-Age'=>'600']);
        }
        $response = $next($request);
        if ($origin === $configured) {
            $response->headers->set('Access-Control-Allow-Origin', $configured);
            $response->headers->set('Vary', 'Origin');
        }
        return $response;
    }
}
