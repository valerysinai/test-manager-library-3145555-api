<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Library\Application\Ports\{BookRepository,UnitOfWork};
use App\Library\Application\Services\LendBooksService;
use App\Library\Adapters\Outbound\Persistence\{PgSession,PgBookRepository,PgUnitOfWork};
use App\Library\Domain\Errors\ConcurrentModification;
use App\Library\Domain\User;
use Illuminate\Support\Facades\{Artisan,DB,Log};
use Illuminate\Http\UploadedFile;
use Tests\Fakes\DomainFixtures as F;
use Tests\Feature\ProductionAdaptersTest;
final class ApiContractTest extends ApiCase
{
    public function test_auth_bootstrap_users_and_all_protected_routes_enforce_authentication_before_input(): void
    {
        $this->api('GET','/health')->assertExactJson(['status'=>'ok']);
        foreach([
            ['GET','/categories'],['GET','/books'],['GET','/books/invalid'],['POST','/books'],['PUT','/books/invalid'],
            ['DELETE','/books/invalid'],['GET','/books/invalid/cover'],['PUT','/books/invalid/cover'],['DELETE','/books/invalid/cover'],
            ['GET','/members'],['POST','/members'],['GET','/members/invalid'],['POST','/users'],['GET','/loans'],
            ['POST','/loans'],['GET','/loans/invalid'],['GET','/loans/overdue'],['POST','/loans/invalid/items/invalid/return'],
            ['GET','/reports/most-borrowed']
        ] as [$method,$uri]) $this->error($this->api($method,$uri),401,'UNAUTHENTICATED');
        $this->error($this->api('GET','/books',null,'not.a.token'),401,'UNAUTHENTICATED');
        $this->error($this->api('POST','/auth/login',['username'=>'initial-admin','password'=>bin2hex(random_bytes(20))]),401,'INVALID_CREDENTIALS');
        $this->error($this->api('POST','/auth/login',['username'=>'missing','password'=>bin2hex(random_bytes(20))]),401,'INVALID_CREDENTIALS');
        $admin=$this->login();
        $password=' '.bin2hex(random_bytes(20)).' ';
        $created=$this->api('POST','/users',['username'=>' ANA ','password'=>$password,'role'=>'librarian'],$admin)->assertCreated()->json();
        self::assertSame(['id','username','role'],array_keys($created)); self::assertSame('ana',$created['username']);
        $lib=$this->login('ana',$password);
        foreach([['POST','/users'],['POST','/books'],['PUT','/books/invalid'],['DELETE','/books/invalid'],
            ['PUT','/books/invalid/cover'],['DELETE','/books/invalid/cover'],['GET','/reports/most-borrowed']] as [$method,$uri]) {
            $this->error($this->api($method,$uri),401,'UNAUTHENTICATED');
            $this->error($this->api($method,$uri,null,$lib),403,'FORBIDDEN_ROLE');
        }
        $this->error($this->api('POST','/users',['username'=>'ana','password'=>$password,'role'=>'librarian'],$admin),409,'USERNAME_TAKEN');
        $this->error($this->api('POST','/users',['username'=>'other','password'=>$password,'role'=>'admin'],$admin),422,'VALIDATION_ERROR');
        $before=DB::table('library.user')->where('username','initial-admin')->first();
        self::assertSame(0,Artisan::call('library:bootstrap-admin'));
        self::assertSame($before->password_hash,DB::table('library.user')->where('username','initial-admin')->value('password_hash'));
        config(['library.admin_username'=>'ana']);
        self::assertSame(1,Artisan::call('library:bootstrap-admin'));
        self::assertSame('librarian',DB::table('library.user')->where('username','ana')->value('role'));
    }
    public function test_catalog_members_covers_validation_and_soft_deletion_follow_the_contract(): void
    {
        $admin=$this->login(); $categories=$this->api('GET','/categories',null,$admin)->assertOk()->json('items');
        self::assertCount(5,$categories);
        $book=$this->book($admin); $member=$this->member($admin);
        $this->error($this->api('POST','/members',['full_name'=>'Duplicado','document_number'=>'DOC-1'],$admin),409,'DOCUMENT_TAKEN');
        $this->error($this->api('POST','/books',['title'=>'Otro','author'=>'Autor','isbn'=>$book['isbn'],
            'category_id'=>$book['category']['id'],'total_copies'=>1],$admin),409,'ISBN_TAKEN');
        $this->api('GET','/members/'.$member['id'],null,$admin)->assertJsonPath('active_loans',0);
        $this->api('GET','/members?q=DOC&page=2&size=1',null,$admin)->assertJsonPath('items',[])->assertJsonPath('total',1);
        foreach([['GET','/books?size=51',null],['GET','/books/invalid',null],['GET','/books?category_id=invalid',null],
            ['POST','/members',['full_name'=>'Socio','document_number'=>'other','id'=>F::id(900)]],
            ['PUT','/books/'.$book['id'],['title'=>'X','author'=>'Y','category_id'=>$book['category']['id'],'total_copies'=>1.0]],
        ] as [$method,$uri,$body]) $this->error($this->api($method,$uri,$body,$admin),422,'VALIDATION_ERROR');
        $this->error($this->api('GET','/books/'.F::id(999),null,$admin),404,'NOT_FOUND');
        $this->api('GET','/books?q=Libro',null,$admin)->assertJsonPath('total',1);
        $file=tempnam(sys_get_temp_dir(),'cover'); file_put_contents($file,ProductionAdaptersTest::png());
        try {
            $response=$this->call('PUT','/api/v1/books/'.$book['id'].'/cover',[],[],[
                'file'=>new UploadedFile($file,'misleading.txt','text/plain',null,true)],
                ['HTTP_AUTHORIZATION'=>'Bearer '.$admin,'CONTENT_TYPE'=>'multipart/form-data']);
            $response->assertOk();
            $this->api('GET','/books/'.$book['id'].'/cover',null,$admin)->assertOk()->assertHeader('Content-Type','image/png');
            $this->api('DELETE','/books/'.$book['id'].'/cover',null,$admin)->assertNoContent();
            $this->error($this->api('GET','/books/'.$book['id'].'/cover',null,$admin),404,'NOT_FOUND');
        } finally { @unlink($file); }
        $this->api('PUT','/books/'.$book['id'],['title'=>'Nuevo','author'=>'Autor','category_id'=>$book['category']['id'],'total_copies'=>3],$admin)
            ->assertOk()->assertJsonPath('available_copies',3)->assertJsonPath('isbn',$book['isbn']);
        $this->api('DELETE','/books/'.$book['id'],null,$admin)->assertNoContent();
        $this->error($this->api('GET','/books/'.$book['id'],null,$admin),404,'NOT_FOUND');
        $this->api('GET','/books',null,$admin)->assertJsonPath('total',0);
    }
    public function test_loans_returns_limits_and_report_snapshots_are_persisted_atomically(): void
    {
        $admin=$this->login(); $member=$this->member($admin); $book=$this->book($admin,1,3);
        $this->error($this->lend($admin,$member['id'],[$book['id'],$book['id']]),422,'DUPLICATE_BOOK_IN_LOAN');
        $loan=$this->lend($admin,$member['id'],[$book['id']])->assertCreated()->json();
        self::assertSame('2026-10-28T12:00:00Z',$loan['items'][0]['due_at']);
        $this->api('GET','/loans/'.$loan['id'],null,$admin)->assertJsonPath('status','open');
        $this->api('GET','/members/'.$member['id'],null,$admin)->assertJsonPath('active_loans',1);
        $this->error($this->api('DELETE','/books/'.$book['id'],null,$admin),409,'BOOK_HAS_OPEN_LOANS');
        $this->api('PUT','/books/'.$book['id'],['title'=>'Renombrado','author'=>'Autor',
            'category_id'=>'22222222-2222-4222-8222-222222222222','total_copies'=>3],$admin)->assertOk();
        $report=$this->api('GET','/reports/most-borrowed?from=2026-10-14&to=2026-10-14',null,$admin)->assertOk()->json('rows');
        self::assertSame('Libro 1',$report[0]['book_title']); self::assertSame('Novela',$report[0]['category_name']);
        self::assertSame(['book_id','book_title','category_name','times_borrowed'],array_keys($report[0]));
        $this->error($this->api('POST','/loans/'.$loan['id'].'/items/'.F::id(999).'/return',null,$admin),404,'NOT_FOUND');
        $return='/loans/'.$loan['id'].'/items/'.$loan['items'][0]['id'].'/return';
        $this->api('POST',$return,null,$admin)->assertOk()->assertJsonPath('returned_at','2026-10-14T12:00:00Z');
        $this->error($this->api('POST',$return,null,$admin),409,'ITEM_ALREADY_RETURNED');
        $this->api('GET','/loans?status=closed&from=2026-10-14&to=2026-10-14',null,$admin)->assertJsonPath('total',1);
        $this->api('GET','/loans?page=2&size=1',null,$admin)->assertJsonPath('items',[])->assertJsonPath('total',1);
        $this->error($this->api('GET','/reports/most-borrowed?from=2026-10-15&to=2026-10-14',null,$admin),422,'INVALID_DATE_RANGE');
        $this->api('GET','/reports/most-borrowed?from=2026-01-01&to=2026-01-02',null,$admin)->assertJsonPath('rows',[]);
        $ids=[];
        for($i=2;$i<=4;$i++) $ids[]=$this->book($admin,$i,1)['id'];
        $this->lend($admin,$member['id'],$ids)->assertCreated();
        $extra=$this->book($admin,5,1);
        $this->error($this->lend($admin,$member['id'],[$extra['id']]),422,'MEMBER_LIMIT_EXCEEDED');
        $other=$this->member($admin,2);
        $this->error($this->lend($admin,$other['id'],[$ids[0]]),409,'BOOK_NOT_AVAILABLE');
        self::assertSame(2,DB::table('library.loan')->count());
    }
    public function test_overdue_uses_current_clock_and_jwt_expiration_with_fresh_login(): void
    {
        $this->clockAt('2026-09-29T12:00:00Z'); $token=$this->login();
        $member=$this->member($token); $book=$this->book($token);
        $this->lend($token,$member['id'],[$book['id']])->assertCreated();
        $this->clockAt('2026-10-14T12:00:00Z');
        $this->error($this->api('GET','/books',null,$token),401,'UNAUTHENTICATED');
        $token=$this->login();
        $late=$this->api('GET','/loans/overdue',null,$token)->assertOk()->json('items');
        self::assertCount(1,$late); self::assertSame(1,$late[0]['days_late']);
        $this->api('GET','/loans?status=overdue',null,$token)->assertJsonPath('total',1);
        $this->error($this->lend($token,$member['id'],[$book['id']]),409,'MEMBER_HAS_OVERDUE');
    }
    public function test_real_optimistic_witness_rejects_a_stale_write(): void
    {
        $token=$this->login(); $book=$this->book($token);
        $a=$this->app->make(PgSession::class); $repo=$this->app->make(BookRepository::class);
        config(['database.connections.concurrent'=>config('database.connections.pgsql')]);
        $b=new PgSession(static fn()=>DB::connection('concurrent')->getPdo());
        $other=new PgBookRepository($b); $work=new PgUnitOfWork($b);
        $a->pdo()->beginTransaction();
        try {
            $stale=$repo->findActive($book['id']);
            $work->run(fn()=> $other->save($other->findActive($book['id'])->withdraw()));
            try { $repo->save($stale->withdraw()); self::fail('Debió rechazar versión vieja.'); }
            catch(ConcurrentModification) { self::assertTrue($a->pdo()->inTransaction()); }
        } finally { $a->pdo()->rollBack(); }
        self::assertSame(1,(int)DB::table('library.book')->where('id',$book['id'])->value('available_copies'));
    }
    public function test_failure_after_stock_writes_rolls_back_the_whole_loan(): void
    {
        $token=$this->login(); $book=$this->book($token); $member=$this->member($token);
        $actor=new User(F::id(999),'missing-operator','opaque-fixture-value','librarian');
        try {
            $this->app->make(LendBooksService::class)->execute($actor,$member['id'],[$book['id']]);
            self::fail('Debió rechazar la FK del operador.');
        } catch(\PDOException $failure) { self::assertSame('23503',$failure->getCode()); }
        self::assertSame(2,(int)DB::table('library.book')->where('id',$book['id'])->value('available_copies'));
        self::assertSame(0,DB::table('library.loan')->count());
    }
    public function test_malformed_json_unknown_fields_and_cors_keep_errors_generic_and_logs_redacted(): void
    {
        $token=$this->login();
        $bad=$this->call('POST','/api/v1/members',[],[],[],[
            'HTTP_AUTHORIZATION'=>'Bearer '.$token,'CONTENT_TYPE'=>'application/json'],'{"');
        $this->error($bad,422,'VALIDATION_ERROR');
        $this->withHeaders(['Origin'=>'https://ui.example.test'])->get('/api/v1/health')->assertHeader('Access-Control-Allow-Origin','https://ui.example.test');
        $this->withHeaders(['Origin'=>'https://foreign.example.test'])->get('/api/v1/health')->assertHeaderMissing('Access-Control-Allow-Origin');
        $messages=[];
        Log::swap(new class($messages) {
            public function __construct(public array &$messages) {}
            public function error($message,$context=[]) { $this->messages[]=[$message,$context]; }
        });
        \App\Library\Adapters\Inbound\Http\SafeExceptionReporter::report(new \RuntimeException($this->password.' '.$token));
        self::assertStringNotContainsString($this->password,json_encode($messages,JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($token,json_encode($messages,JSON_THROW_ON_ERROR));
    }
}
