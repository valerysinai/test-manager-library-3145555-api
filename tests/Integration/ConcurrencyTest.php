<?php
declare(strict_types=1);
namespace Tests\Integration;
use Illuminate\Support\Facades\DB;
final class ConcurrencyTest extends ApiCase
{
    private function librarians(string $admin): array
    {
        $tokens=[];
        for($i=0;$i<2;$i++) {
            $password=bin2hex(random_bytes(20));
            $this->api('POST','/users',['username'=>'worker-'.$i,'password'=>$password,'role'=>'librarian'],$admin)->assertCreated();
            $tokens[]=$this->login('worker-'.$i,$password);
        }
        return $tokens;
    }
    private function pair(array $tasks,array $tokens): array
    {
        $directory=sys_get_temp_dir().'/library-race-'.bin2hex(random_bytes(10));
        mkdir($directory,0700); $processes=[]; $channels=[];
        try {
            foreach($tasks as $i=>$task) {
                $env=getenv();
                $env['APP_ENV']='testing'; $env['APP_DEBUG']='false'; $env['LOG_CHANNEL']='null';
                $env['JWT_SECRET']=$this->secret; $env['CLOCK_MODE']='fixed'; $env['CLOCK_FIXED_AT']=$this->fixed;
                $env['LIBRARY_BARRIER']=$directory; $env['LIBRARY_TOKEN']=$tokens[$i];
                $env['LIBRARY_WORK']=json_encode($task,JSON_THROW_ON_ERROR);
                $process=proc_open([PHP_BINARY,__DIR__.'/../Fixtures/concurrent-request.php'],
                    [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2),$env);
                self::assertIsResource($process); fclose($pipes[0]);
                $processes[]=$process; $channels[]=$pipes;
            }
            $deadline=microtime(true)+20;
            while(count(glob($directory.'/*.ready'))<2 && microtime(true)<$deadline) usleep(10000);
            self::assertCount(2,glob($directory.'/*.ready'),'Ambos workers deben estar listos antes de liberarlos.');
            file_put_contents($directory.'/go','');
            $results=[];
            foreach($processes as $i=>$process) {
                stream_set_blocking($channels[$i][1],false); $output=''; $until=microtime(true)+30;
                do {
                    $output.=stream_get_contents($channels[$i][1]);
                    $running=proc_get_status($process)['running'];
                    if(!$running) break;
                    usleep(10000);
                } while(microtime(true)<$until);
                self::assertFalse($running,'Worker fuera de tiempo.');
                $output.=stream_get_contents($channels[$i][1]);
                $result=json_decode($output,true);
                self::assertIsArray($result,'El worker debe producir JSON sin trazas.');
                self::assertArrayHasKey('status',$result,'El worker debe completar una petición.');
                $results[]=$result;
            }
            return $results;
        } finally {
            foreach($processes as $i=>$process) {
                if(proc_get_status($process)['running']) proc_terminate($process);
                foreach([1,2] as $pipe) if(is_resource($channels[$i][$pipe])) fclose($channels[$i][$pipe]);
                proc_close($process);
            }
            foreach(glob($directory.'/*') as $file) unlink($file);
            rmdir($directory);
        }
    }
    private function loanTask(string $member,string $book): array
    {
        return ['method'=>'POST','uri'=>'/api/v1/loans','body'=>['member_id'=>$member,'items'=>[['book_id'=>$book]]]];
    }
    public function test_two_librarians_competing_for_the_last_copy_produce_one_201_and_one_409(): void
    {
        $admin=$this->login(); $tokens=$this->librarians($admin);
        $book=$this->book($admin,1,1); $a=$this->member($admin,1); $b=$this->member($admin,2);
        $results=$this->pair([$this->loanTask($a['id'],$book['id']),$this->loanTask($b['id'],$book['id'])],$tokens);
        $statuses=array_column($results,'status'); sort($statuses); self::assertSame([201,409],$statuses);
        self::assertSame(1,DB::table('library.loan_item')->count());
        self::assertSame(0,(int)DB::table('library.book')->where('id',$book['id'])->value('available_copies'));
    }
    public function test_simultaneous_loans_of_different_books_cannot_exceed_the_member_limit(): void
    {
        $admin=$this->login(); $tokens=$this->librarians($admin); $member=$this->member($admin);
        $books=[]; for($i=1;$i<=4;$i++) $books[]=$this->book($admin,$i,1)['id'];
        $this->lend($admin,$member['id'],array_slice($books,0,2))->assertCreated();
        $results=$this->pair([$this->loanTask($member['id'],$books[2]),$this->loanTask($member['id'],$books[3])],$tokens);
        $statuses=array_column($results,'status'); sort($statuses); self::assertSame([201,422],$statuses);
        $codes=array_column(array_column($results,'body'),'code');
        self::assertContains('MEMBER_LIMIT_EXCEEDED',$codes);
        self::assertSame(3,DB::table('library.loan_item')->whereNull('returned_at')->count());
    }
    public function test_simultaneous_returns_increment_stock_once_even_with_other_copies_outstanding(): void
    {
        $admin=$this->login(); $tokens=$this->librarians($admin); $book=$this->book($admin,1,3);
        $a=$this->member($admin,1); $b=$this->member($admin,2);
        $loan=$this->lend($admin,$a['id'],[$book['id']])->assertCreated()->json();
        $this->lend($admin,$b['id'],[$book['id']])->assertCreated();
        $task=['method'=>'POST','uri'=>'/api/v1/loans/'.$loan['id'].'/items/'.$loan['items'][0]['id'].'/return','body'=>null];
        $results=$this->pair([$task,$task],$tokens);
        $statuses=array_column($results,'status'); sort($statuses); self::assertSame([200,409],$statuses);
        self::assertSame(2,(int)DB::table('library.book')->where('id',$book['id'])->value('available_copies'));
        self::assertSame(1,DB::table('library.loan_item')->whereNotNull('returned_at')->count());
        self::assertSame(1,DB::table('library.loan_item')->whereNull('returned_at')->count());
    }
}
