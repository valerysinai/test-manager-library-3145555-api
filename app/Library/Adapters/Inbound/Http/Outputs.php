<?php
declare(strict_types=1);
namespace App\Library\Adapters\Inbound\Http;
use App\Library\Domain\{Book, Loan, LoanItem, Member};
use DateTimeImmutable;
use DateTimeZone;
final class Outputs
{
    public static function date(DateTimeImmutable $at): string { return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'); }
    public static function book(Book $b): array
    {
        return ['id'=>$b->id,'title'=>$b->title,'author'=>$b->author,'isbn'=>$b->isbn->value,
            'category'=>['id'=>$b->category->id,'name'=>$b->category->name],
            'total_copies'=>$b->totalCopies->value,'available_copies'=>$b->availableCopies,'cover_key'=>$b->coverKey];
    }
    public static function member(Member $m): array { return ['id'=>$m->id,'full_name'=>$m->fullName,'document_number'=>$m->documentNumber->value]; }
    public static function item(LoanItem $i): array
    {
        return ['id'=>$i->id,'book_id'=>$i->bookId,'book_title'=>$i->bookTitle,'isbn'=>$i->isbn->value,
            'category_name'=>$i->categoryName,'due_at'=>self::date($i->dueAt),'returned_at'=>$i->returnedAt === null ? null : self::date($i->returnedAt)];
    }
    public static function loan(Loan $l, DateTimeImmutable $now): array
    {
        return ['id'=>$l->id,'member_id'=>$l->memberId,'loaned_at'=>self::date($l->loanedAt),
            'status'=>$l->status($now),'items'=>array_map(self::item(...),$l->items)];
    }
}
