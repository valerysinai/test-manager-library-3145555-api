<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Persistence;
use App\Library\Domain\{Book, Category, DocumentNumber, Isbn, Loan, LoanItem, Member, Quantity, User};
final class Rows
{
    private static function checked(\Closure $factory): mixed
    {
        try { return $factory(); }
        catch (\DomainException $failure) { throw new \UnexpectedValueException('Datos persistidos inválidos.', 0, $failure); }
    }
    public static function category(array $r): Category { return self::checked(static fn () => new Category($r['id'], $r['name'])); }
    public static function member(array $r): Member { return self::checked(static fn () => new Member($r['id'], $r['full_name'], new DocumentNumber($r['document_number']))); }
    public static function user(array $r): User { return self::checked(static fn () => new User($r['id'], $r['username'], $r['password_hash'], $r['role'])); }
    public static function book(array $r): Book
    {
        return self::checked(static fn () => new Book($r['id'], $r['title'], $r['author'], new Isbn($r['isbn']),
            new Category($r['category_id'], $r['category_name']), new Quantity((int) $r['total_copies']),
            (int) $r['available_copies'], $r['cover_key'], $r['deleted_at'] === null ? null : new \DateTimeImmutable($r['deleted_at'])));
    }
    public static function item(array $r): LoanItem
    {
        return self::checked(static fn () => new LoanItem($r['id'], $r['loan_id'], $r['book_id'], $r['book_title'],
            new Isbn($r['isbn']), $r['category_name'], new \DateTimeImmutable($r['due_at']),
            $r['returned_at'] === null ? null : new \DateTimeImmutable($r['returned_at'])));
    }
    public static function loan(array $r, array $items): Loan
    {
        return self::checked(static fn () => Loan::restore($r['id'], $r['member_id'], $r['loaned_by_user_id'],
            new \DateTimeImmutable($r['loaned_at']), array_map(self::item(...), $items)));
    }
}
