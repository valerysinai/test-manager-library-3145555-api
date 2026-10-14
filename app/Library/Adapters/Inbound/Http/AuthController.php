<?php
declare(strict_types=1);
namespace App\Library\Adapters\Inbound\Http;
use App\Library\Application\Services\{LoginService, CreateLibrarianService};
use App\Library\Application\Ports\UuidGenerator;
use Illuminate\Http\Request;
final class AuthController
{
    public function login(Request $request, LoginService $service): mixed
    {
        $b = Inputs::body($request, ['username','password']);
        return response()->json($service->execute(Inputs::text($b,'username'),Inputs::text($b,'password')));
    }
    public function create(Request $request, CreateLibrarianService $service, UuidGenerator $ids): mixed
    {
        $b = Inputs::body($request,['username','password','role']);
        return response()->json($service->execute($request->attributes->get('actor'),$ids->next(),
            Inputs::text($b,'username'),Inputs::text($b,'password'),Inputs::text($b,'role')),201);
    }
}
