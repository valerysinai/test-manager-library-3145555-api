<?php
declare(strict_types=1);
namespace Tests\Application;
use App\Library\Application\Services\{LendBooksService,ReturnItemService,MostBorrowedReportService,SearchLoansService,OverdueService};
use App\Library\Application\LoanQuery;
use App\Library\Domain\{Quantity,User};
use App\Library\Domain\Errors\{BookNotAvailable,ItemAlreadyReturned,MemberLimitExceeded,MemberHasOverdue};
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\{DomainFixtures as F,FakeClock,InMemoryCatalog,InMemoryMemberRepository,InMemoryLoans,LoanUnitOfWork,SequentialUuid};
final class LoanServicesTest extends TestCase
{
    private InMemoryCatalog $books; private InMemoryMemberRepository $members; private InMemoryLoans $loans;
    private LoanUnitOfWork $work; private FakeClock $clock; private User $actor; private LendBooksService $lend;
    protected function setUp(): void
    {
        $this->books=new InMemoryCatalog; $this->members=new InMemoryMemberRepository;
        $this->members->members[F::member()->id]=F::member(); $this->loans=new InMemoryLoans($this->members);
        $this->work=new LoanUnitOfWork($this->books,$this->loans); $this->clock=new FakeClock(new DateTimeImmutable('2026-10-01T12:00:00Z'));
        $this->actor=new User(F::id(900),'ana','opaque-fixture-value','admin');
        for($i=1;$i<=4;$i++) $this->books->books[F::id($i)]=F::book($i);
        $this->lend=new LendBooksService($this->books,$this->members,$this->loans,$this->work,$this->clock,new SequentialUuid);
    }
    private function returns(): ReturnItemService { return new ReturnItemService($this->books,$this->loans,$this->work,$this->clock); }
    public function test_lending_and_return_coordinate_persistence_snapshots_and_member_lock(): void
    {
        $loan=$this->lend->execute($this->actor,F::member()->id,[F::id(1),F::id(2)]);
        self::assertSame([F::member()->id],$this->loans->locks);
        self::assertSame($this->actor->id,$loan->loanedByUserId);
        self::assertSame(1,$this->books->books[F::id(1)]->availableCopies);
        self::assertSame('2026-10-15T12:00:00+00:00',$loan->items[0]->dueAt->format('c'));
        $this->returns()->execute($loan->id,$loan->items[0]->id);
        self::assertSame(2,$this->books->books[F::id(1)]->availableCopies);
        $this->expectException(ItemAlreadyReturned::class);
        $this->returns()->execute($loan->id,$loan->items[0]->id);
    }
    public function test_unavailable_second_book_rolls_back_all_state(): void
    {
        $this->books->books[F::id(2)]=F::book(2)->withdraw()->withdraw();
        try { $this->lend->execute($this->actor,F::member()->id,[F::id(1),F::id(2)]); self::fail('Debió rechazar existencias.'); }
        catch(BookNotAvailable) { self::assertSame(2,$this->books->books[F::id(1)]->availableCopies); self::assertSame([],$this->loans->loans); }
    }
    public function test_limit_and_overdue_are_checked_against_member_history(): void
    {
        $this->lend->execute($this->actor,F::member()->id,[F::id(1),F::id(2),F::id(3)]);
        try { $this->lend->execute($this->actor,F::member()->id,[F::id(4)]); self::fail('Debió rechazar límite.'); }
        catch(MemberLimitExceeded $e) { self::assertSame(3,$e->currentCount); }
        $this->clock->set(new DateTimeImmutable('2026-10-16T12:00:00Z'));
        $this->expectException(MemberHasOverdue::class);
        $this->lend->execute($this->actor,F::member()->id,[F::id(4)]);
    }
    public function test_report_uses_frozen_text_and_excludes_personal_dimensions(): void
    {
        $loan=$this->lend->execute($this->actor,F::member()->id,[F::id(1)]);
        $this->returns()->execute($loan->id,$loan->items[0]->id);
        $book=$this->books->books[F::id(1)];
        $this->books->books[$book->id]=$book->revise('Renombrado','Autor',$book->category,new Quantity(2));
        $this->lend->execute($this->actor,F::member()->id,[F::id(1)]);
        $service=new MostBorrowedReportService($this->loans);
        $report=$service->execute($this->actor,'2026-10-01','2026-10-01');
        self::assertCount(2,$report['rows']);
        self::assertSame(['book_id','book_title','category_name','times_borrowed'],array_keys($report['rows'][0]));
        self::assertSame([],$service->execute($this->actor,'2026-11-01','2026-11-02')['rows']);
    }
    public function test_listing_filters_dates_and_computed_status_and_overdue_lists_lines(): void
    {
        $this->lend->execute($this->actor,F::member()->id,[F::id(1)]);
        $this->clock->set(new DateTimeImmutable('2026-10-03T12:00:00Z'));
        $this->lend->execute($this->actor,F::member()->id,[F::id(2)]);
        $search=new SearchLoansService($this->loans,$this->clock);
        self::assertSame(1,$search->execute(new LoanQuery('2026-10-02','2026-10-03',null,null))->total);
        $this->clock->set(new DateTimeImmutable('2026-10-16T12:00:00Z'));
        self::assertSame(1,$search->execute(new LoanQuery(null,null,'overdue',null))->total);
        $late=(new OverdueService($this->loans,$this->clock))->execute();
        self::assertCount(1,$late); self::assertSame(1,$late[0]['days_late']);
        $empty=$search->execute(new LoanQuery(null,null,null,null,3,1));
        self::assertSame([],$empty->items); self::assertSame(2,$empty->total);
    }
}
