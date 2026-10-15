<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE library.book
                ADD CONSTRAINT ck_book_total_positive CHECK (total_copies >= 1),
                ADD CONSTRAINT ck_book_isbn_digits CHECK (isbn ~ '^[0-9]{13}$'),
                ADD CONSTRAINT ck_book_title_nonempty CHECK (btrim(title,E' \t\n\r\013') <> ''),
                ADD CONSTRAINT ck_book_cover_key_nonempty CHECK (cover_key IS NULL OR btrim(cover_key,E' \t\n\r\013') <> '');
            ALTER TABLE library."user"
                ADD CONSTRAINT ck_user_role CHECK (role IN ('admin','librarian')),
                ADD CONSTRAINT ck_user_username_normalized CHECK (username <> '' AND username=btrim(username,E' \t\n\r\013') AND username=lower(username));
            ALTER TABLE library.member
                ADD CONSTRAINT ck_member_document_nonempty CHECK (btrim(document_number,E' \t\n\r\013') <> '');
            ALTER TABLE library.loan_item
                ADD CONSTRAINT ck_loan_item_isbn_digits CHECK (isbn ~ '^[0-9]{13}$');
            SQL);
    }
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE library.loan_item DROP CONSTRAINT ck_loan_item_isbn_digits;
            ALTER TABLE library.member DROP CONSTRAINT ck_member_document_nonempty;
            ALTER TABLE library."user" DROP CONSTRAINT ck_user_username_normalized, DROP CONSTRAINT ck_user_role;
            ALTER TABLE library.book DROP CONSTRAINT ck_book_cover_key_nonempty, DROP CONSTRAINT ck_book_title_nonempty,
                DROP CONSTRAINT ck_book_isbn_digits, DROP CONSTRAINT ck_book_total_positive;
            SQL);
    }
};
