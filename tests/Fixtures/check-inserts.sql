-- Executed only against the isolated CI database after the live HTTP fixture.
BEGIN;
CREATE FUNCTION pg_temp.reject_check(statement text, expected text) RETURNS void LANGUAGE plpgsql AS $$
DECLARE actual text;
BEGIN
    BEGIN
        EXECUTE statement;
    EXCEPTION WHEN check_violation THEN
        GET STACKED DIAGNOSTICS actual = CONSTRAINT_NAME;
        IF actual <> expected THEN RAISE EXCEPTION 'Unexpected CHECK identifier'; END IF;
        RETURN;
    END;
    RAISE EXCEPTION 'Expected CHECK rejection was absent';
END;
$$;
SELECT pg_temp.reject_check($sql$
    INSERT INTO library.book
    SELECT gen_random_uuid(), 'Invalid', 'Fixture', '0000000000102', id, 0, 0, NULL, NULL
    FROM library.category LIMIT 1
$sql$, 'ck_book_total_positive');
SELECT pg_temp.reject_check($sql$
    INSERT INTO library.book
    SELECT gen_random_uuid(), 'Invalid', 'Fixture', 'ABCDEFGHIJKLM', id, 1, 1, NULL, NULL
    FROM library.category LIMIT 1
$sql$, 'ck_book_isbn_digits');
SELECT pg_temp.reject_check($sql$
    INSERT INTO library.book
    SELECT gen_random_uuid(), E' \t\n ', 'Fixture', '0000000000102', id, 1, 1, NULL, NULL
    FROM library.category LIMIT 1
$sql$, 'ck_book_title_nonempty');
SELECT pg_temp.reject_check($sql$
    INSERT INTO library.book
    SELECT gen_random_uuid(), 'Invalid', 'Fixture', '0000000000102', id, 1, 1, '', NULL
    FROM library.category LIMIT 1
$sql$, 'ck_book_cover_key_nonempty');
SELECT pg_temp.reject_check($sql$
    INSERT INTO library."user"
    SELECT gen_random_uuid(), 'invalid-role', password_hash, 'reader' FROM library."user" LIMIT 1
$sql$, 'ck_user_role');
SELECT pg_temp.reject_check($sql$
    INSERT INTO library."user"
    SELECT gen_random_uuid(), ' UPPER ', password_hash, 'librarian' FROM library."user" LIMIT 1
$sql$, 'ck_user_username_normalized');
SELECT pg_temp.reject_check($sql$
    INSERT INTO library.member VALUES (gen_random_uuid(), 'Fixture', E' \t ')
$sql$, 'ck_member_document_nonempty');
SELECT pg_temp.reject_check($sql$
    INSERT INTO library.loan_item
    SELECT gen_random_uuid(), loan_id, book_id, book_title, 'ABCDEFGHIJKLM', category_name, due_at, NULL
    FROM library.loan_item LIMIT 1
$sql$, 'ck_loan_item_isbn_digits');
ROLLBACK;
