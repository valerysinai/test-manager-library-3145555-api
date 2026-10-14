<?php
declare(strict_types=1);
namespace App\Library\Adapters\Inbound\Http;
use App\Library\Application\Services\{CreateBookService, EditBookService, DeactivateBookService, GetBookService,
    SearchBooksService, UploadCoverService, RemoveCoverService, GetCoverService};
use App\Library\Application\Ports\{CategoryRepository, UuidGenerator};
use Illuminate\Http\Request;
final class BookController
{
    public function categories(Request $r, CategoryRepository $categories): mixed
    {
        Inputs::query($r,[]);
        return response()->json(['items'=>array_map(static fn($c)=>['id'=>$c->id,'name'=>$c->name],$categories->all())]);
    }
    public function index(Request $r, SearchBooksService $service): mixed
    {
        $q=Inputs::query($r,['q','category_id','page','size']);
        $p=$service->execute($q['q']??'',$q['category_id']??null,Inputs::page($q,'page',1),Inputs::page($q,'size',20));
        return response()->json(['items'=>array_map(Outputs::book(...),$p->items),'page'=>$p->page,'size'=>$p->size,'total'=>$p->total]);
    }
    public function show(string $id, GetBookService $service): mixed { return response()->json(Outputs::book($service->execute($id))); }
    public function create(Request $r, CreateBookService $service, UuidGenerator $ids): mixed
    {
        $b=Inputs::body($r,['title','author','isbn','category_id','total_copies']);
        return response()->json(Outputs::book($service->execute($r->attributes->get('actor'),$ids->next(),
            Inputs::text($b,'title'),Inputs::text($b,'author'),Inputs::text($b,'isbn'),Inputs::text($b,'category_id'),Inputs::integer($b,'total_copies'))),201);
    }
    public function edit(Request $r,string $id,EditBookService $service): mixed
    {
        $b=Inputs::body($r,['title','author','category_id','total_copies']);
        return response()->json(Outputs::book($service->execute($r->attributes->get('actor'),$id,
            Inputs::text($b,'title'),Inputs::text($b,'author'),Inputs::text($b,'category_id'),Inputs::integer($b,'total_copies'))));
    }
    public function deactivate(Request $r,string $id,DeactivateBookService $service): mixed
    {
        Inputs::emptyBody($r); $service->execute($r->attributes->get('actor'),$id); return response('',204);
    }
    public function upload(Request $r,string $id,UploadCoverService $service): mixed
    {
        return response()->json(Outputs::book($service->execute($r->attributes->get('actor'),$id,Inputs::upload($r))));
    }
    public function remove(Request $r,string $id,RemoveCoverService $service): mixed
    {
        Inputs::emptyBody($r); $service->execute($r->attributes->get('actor'),$id); return response('',204);
    }
    public function cover(string $id,GetCoverService $service): mixed
    {
        $cover=$service->execute($id);
        return response($cover->bytes,200,['Content-Type'=>$cover->mediaType,'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']);
    }
}
