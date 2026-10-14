<?php
declare(strict_types=1);
namespace Tests\Integration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\{Artisan,DB};
final class FinalConstraintsTest extends ApiCase
{
    public function test_all_pending_checks_reject_direct_invalid_sql_writes_and_can_be_reapplied(): void
    {
        $token=$this->login(); $book=$this->book($token); $member=$this->member($token);
        $loan=$this->lend($token,$member['id'],[$book['id']])->assertCreated()->json();
        $cases=[
            ['UPDATE library.book SET total_copies=0,available_copies=0 WHERE id=?',[$book['id']],'ck_book_total_positive'],
            ['UPDATE library.book SET isbn=? WHERE id=?',['ABC1234567890',$book['id']],'ck_book_isbn_digits'],
            ['UPDATE library.book SET title=? WHERE id=?',[" \t\n ",$book['id']],'ck_book_title_nonempty'],
            ['UPDATE library.book SET cover_key=? WHERE id=?',['',$book['id']],'ck_book_cover_key_nonempty'],
            ['UPDATE library."user" SET role=? WHERE username=?',['reader','initial-admin'],'ck_user_role'],
            ['UPDATE library."user" SET username=? WHERE username=?',[' ANA ','initial-admin'],'ck_user_username_normalized'],
            ['UPDATE library.member SET document_number=? WHERE id=?',["\t ",$member['id']],'ck_member_document_nonempty'],
            ['UPDATE library.loan_item SET isbn=? WHERE id=?',['ABCDEFGHIJKLM',$loan['items'][0]['id']],'ck_loan_item_isbn_digits'],
        ];
        foreach($cases as [$sql,$params,$name]) {
            try { DB::statement($sql,$params); self::fail('El motor aceptó una regla inválida: '.$name); }
            catch(QueryException $e) { self::assertSame('23514',$e->errorInfo[0]); self::assertStringContainsString($name,$e->errorInfo[2]); }
        }
        self::assertCount(9,DB::select("SELECT c.conname FROM pg_constraint c JOIN pg_namespace n ON n.oid=c.connamespace WHERE n.nspname='library' AND c.contype='c'"));
        self::assertSame(0,Artisan::call('migrate:rollback',['--force'=>true,'--step'=>1]));
        self::assertCount(1,DB::select("SELECT c.conname FROM pg_constraint c JOIN pg_namespace n ON n.oid=c.connamespace WHERE n.nspname='library' AND c.contype='c'"));
        self::assertSame(0,Artisan::call('migrate',['--force'=>true]));
        self::assertCount(9,DB::select("SELECT c.conname FROM pg_constraint c JOIN pg_namespace n ON n.oid=c.connamespace WHERE n.nspname='library' AND c.contype='c'"));
    }
}
