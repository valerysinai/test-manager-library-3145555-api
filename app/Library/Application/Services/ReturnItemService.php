<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{BookRepository, Clock, LoanRepository, UnitOfWork};
use App\Library\Domain\{Guard, LoanItem};
use App\Library\Domain\Errors\{ItemAlreadyReturned, ResourceNotFound};
final readonly class ReturnItemService
{
    public function __construct(private BookRepository $books, private LoanRepository $loans,
        private UnitOfWork $work, private Clock $clock) {}
    public function execute(string $loanId, string $itemId): LoanItem
    {
        $loanId = Guard::uuid($loanId, 'loan_id');
        $itemId = Guard::uuid($itemId, 'item_id');
        return $this->work->run(function () use ($loanId, $itemId): LoanItem {
            $memberId = $this->loans->memberId($loanId) ?? throw new ResourceNotFound('No se encontró el préstamo.');
            $this->loans->lockMember($memberId);
            $now = $this->clock->now();
            $loan = $this->loans->find($loanId) ?? throw new ResourceNotFound('No se encontró el préstamo.');
            $line = null;
            foreach ($loan->items as $item) { if ($item->id === $itemId) { $line = $item; break; } }
            if ($line === null) { throw new ResourceNotFound('No se encontró la línea en el préstamo.'); }
            if ($line->returnedAt !== null) { throw new ItemAlreadyReturned('La línea ya fue devuelta.'); }
            $book = $this->books->findActive($line->bookId) ?? throw new ResourceNotFound('No se encontró el libro.');
            $result = $loan->returnItem($itemId, $book, $now);
            $this->loans->markReturned($result->item);
            $this->books->save($result->book);
            return $result->item;
        });
    }
}
