<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\Ports\{BookRepository, Clock, LoanRepository, MemberRepository, UnitOfWork, UuidGenerator};
use App\Library\Domain\{Guard, Loan, User};
use App\Library\Domain\Errors\{BookNotAvailable, ConcurrentModification, ResourceNotFound, ValidationError};
final readonly class LendBooksService
{
    public function __construct(private BookRepository $books, private MemberRepository $members,
        private LoanRepository $loans, private UnitOfWork $work, private Clock $clock, private UuidGenerator $ids) {}
    public function execute(User $actor, string $memberId, array $bookIds): Loan
    {
        $memberId = Guard::uuid($memberId, 'member_id');
        if (!array_is_list($bookIds) || count($bookIds) < 1 || count($bookIds) > 3) {
            throw new ValidationError('items', 'El préstamo debe contener entre 1 y 3 libros.');
        }
        $normalized = [];
        foreach ($bookIds as $id) {
            if (!is_string($id)) { throw new ValidationError('items', 'Cada libro debe tener un UUID.'); }
            $normalized[] = Guard::uuid($id, 'book_id');
        }
        try {
            return $this->work->run(function () use ($actor, $memberId, $normalized): Loan {
                $this->loans->lockMember($memberId);
                $now = $this->clock->now();
                $member = $this->members->find($memberId) ?? throw new ResourceNotFound('No se encontró el socio.');
                $catalog = $this->books->findActiveMany(array_values(array_unique($normalized)));
                $books = [];
                $itemIds = [];
                foreach ($normalized as $id) {
                    $books[] = $catalog[$id] ?? throw new ResourceNotFound('No se encontró el libro.');
                    $itemIds[] = $this->ids->next();
                }
                $result = Loan::lend($this->ids->next(), $member, $actor->id, $now,
                    $books, $itemIds, $this->loans->forMember($memberId));
                $updated = $result->books;
                usort($updated, static fn ($a, $b): int => $a->id <=> $b->id);
                foreach ($updated as $book) { $this->books->save($book); }
                $this->loans->add($result->loan);
                return $result->loan;
            });
        } catch (ConcurrentModification) { throw new BookNotAvailable('No fue posible reservar los ejemplares solicitados.'); }
    }
}
