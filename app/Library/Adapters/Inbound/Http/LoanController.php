<?php
declare(strict_types=1);
namespace App\Library\Adapters\Inbound\Http;
use App\Library\Application\Services\{LendBooksService, ReturnItemService, GetLoanService, SearchLoansService, OverdueService, MostBorrowedReportService};
use App\Library\Application\LoanQuery;
use App\Library\Application\Ports\Clock;
use App\Library\Domain\Errors\ValidationError;
use Illuminate\Http\Request;
final class LoanController
{
    public function create(Request $r,LendBooksService $service,Clock $clock): mixed
    {
        $b=Inputs::body($r,['member_id','items']);
        if (!is_array($b['items']) || !array_is_list($b['items'])) { throw new ValidationError('items','Se requiere una lista de líneas.'); }
        $ids=[];
        foreach ($b['items'] as $item) {
            if (!$item instanceof \stdClass || array_keys(get_object_vars($item))!==['book_id'] || !is_string($item->book_id)) {
                throw new ValidationError('items','Cada línea admite únicamente book_id.');
            }
            $ids[]=$item->book_id;
        }
        return response()->json(Outputs::loan($service->execute($r->attributes->get('actor'),Inputs::text($b,'member_id'),$ids),$clock->now()),201);
    }
    public function show(string $id,GetLoanService $service,Clock $clock): mixed
    {
        return response()->json(Outputs::loan($service->execute($id),$clock->now()));
    }
    public function index(Request $r,SearchLoansService $service,Clock $clock): mixed
    {
        $q=Inputs::query($r,['from','to','status','member_id','page','size']);
        $p=$service->execute(new LoanQuery($q['from']??null,$q['to']??null,$q['status']??null,$q['member_id']??null,
            Inputs::page($q,'page',1),Inputs::page($q,'size',20)));
        $now=$clock->now();
        return response()->json(['items'=>array_map(static fn($l)=>Outputs::loan($l,$now),$p->items),'page'=>$p->page,'size'=>$p->size,'total'=>$p->total]);
    }
    public function returnItem(Request $r,string $loanId,string $itemId,ReturnItemService $service): mixed
    {
        Inputs::emptyBody($r); return response()->json(Outputs::item($service->execute($loanId,$itemId)));
    }
    public function overdue(Request $r,OverdueService $service): mixed
    {
        Inputs::query($r,[]); return response()->json(['items'=>$service->execute()]);
    }
    public function report(Request $r,MostBorrowedReportService $service): mixed
    {
        $q=Inputs::query($r,['from','to']);
        if (!isset($q['from'],$q['to'])) { throw new ValidationError('from/to','Ambas fechas son obligatorias.'); }
        return response()->json($service->execute($r->attributes->get('actor'),$q['from'],$q['to']));
    }
}
