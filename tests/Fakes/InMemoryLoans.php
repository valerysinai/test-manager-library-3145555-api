<?php
declare(strict_types=1);
namespace Tests\Fakes;
use App\Library\Application\{LoanPage,LoanQuery};
use App\Library\Application\Ports\{LoanRepository,ReportReader};
use App\Library\Domain\{DateRange,Loan,LoanItem};
use App\Library\Domain\Errors\ItemAlreadyReturned;
use DateTimeImmutable;
final class InMemoryLoans implements LoanRepository,ReportReader
{
    public array $loans=[]; public array $locks=[];
    public function __construct(private InMemoryMemberRepository $members) {}
    public function lockMember(string $memberId): void { $this->locks[]=$memberId; }
    public function memberId(string $loanId): ?string { return $this->loans[$loanId]->memberId??null; }
    public function find(string $id): ?Loan { return $this->loans[$id]??null; }
    public function forMember(string $memberId): array { return array_values(array_filter($this->loans,static fn($l)=>$l->memberId===$memberId)); }
    public function add(Loan $loan): void { $this->loans[$loan->id]=$loan; }
    public function markReturned(LoanItem $item): void
    {
        $loan=$this->loans[$item->loanId]; $items=$loan->items;
        foreach($items as $i=>$old) {
            if($old->id===$item->id) {
                if($old->returnedAt!==null) { throw new ItemAlreadyReturned('La línea ya fue devuelta.'); }
                $items[$i]=$item;
            }
        }
        $this->loans[$loan->id]=Loan::restore($loan->id,$loan->memberId,$loan->loanedByUserId,$loan->loanedAt,$items);
    }
    public function search(LoanQuery $query,DateTimeImmutable $now): LoanPage
    {
        $rows=array_values(array_filter($this->loans,static fn($l)=>
            ($query->from===null||$l->loanedAt >= $query->from)
            &&($query->toExclusive===null||$l->loanedAt < $query->toExclusive)
            &&($query->status===null||$l->status($now)===$query->status)
            &&($query->memberId===null||$l->memberId===$query->memberId)));
        usort($rows,static fn($a,$b)=>[$b->loanedAt,$a->id]<=>[$a->loanedAt,$b->id]);
        return new LoanPage(array_slice($rows,($query->page-1)*$query->size,$query->size),$query->page,$query->size,count($rows));
    }
    public function overdue(DateTimeImmutable $now): array
    {
        $rows=[];
        foreach($this->loans as $loan) foreach($loan->items as $item) {
            if($item->isOverdue($now)) $rows[]=['loan_id'=>$loan->id,'item_id'=>$item->id,'book_title'=>$item->bookTitle,
                'member_name'=>$this->members->members[$loan->memberId]->fullName,'due_at'=>$item->dueAt->format('Y-m-d\TH:i:s\Z'),'days_late'=>$item->daysLate($now)];
        }
        usort($rows,static fn($a,$b)=>[-$a['days_late'],$a['due_at'],$a['item_id']]<=>[-$b['days_late'],$b['due_at'],$b['item_id']]);
        return $rows;
    }
    public function mostBorrowed(DateRange $range): array
    {
        $rows=[];
        foreach($this->loans as $loan) if($range->contains($loan->loanedAt)) foreach($loan->items as $item) {
            $key=json_encode([$item->bookId,$item->bookTitle,$item->categoryName],JSON_THROW_ON_ERROR);
            $rows[$key]??=['book_id'=>$item->bookId,'book_title'=>$item->bookTitle,'category_name'=>$item->categoryName,'times_borrowed'=>0];
            $rows[$key]['times_borrowed']++;
        }
        $rows=array_values($rows);
        usort($rows,static fn($a,$b)=>[-$a['times_borrowed'],$a['book_title']]<=>[-$b['times_borrowed'],$b['book_title']]);
        return $rows;
    }
}
