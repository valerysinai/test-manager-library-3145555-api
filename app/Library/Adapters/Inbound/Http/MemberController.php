<?php
declare(strict_types=1);
namespace App\Library\Adapters\Inbound\Http;
use App\Library\Application\Services\{CreateMemberService, SearchMembersService, GetMemberService};
use App\Library\Application\Ports\UuidGenerator;
use Illuminate\Http\Request;
final class MemberController
{
    public function index(Request $r,SearchMembersService $service): mixed
    {
        $q=Inputs::query($r,['q','page','size']);
        $p=$service->execute($q['q']??'',Inputs::page($q,'page',1),Inputs::page($q,'size',20));
        return response()->json(['items'=>array_map(Outputs::member(...),$p->items),'page'=>$p->page,'size'=>$p->size,'total'=>$p->total]);
    }
    public function show(string $id,GetMemberService $service): mixed
    {
        $details=$service->execute($id);
        return response()->json(Outputs::member($details->member)+['active_loans'=>$details->activeLoans]);
    }
    public function create(Request $r,CreateMemberService $service,UuidGenerator $ids): mixed
    {
        $b=Inputs::body($r,['full_name','document_number']);
        return response()->json(Outputs::member($service->execute($ids->next(),Inputs::text($b,'full_name'),Inputs::text($b,'document_number'))),201);
    }
}
