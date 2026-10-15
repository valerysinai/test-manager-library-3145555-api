<?php
use App\Library\Adapters\Inbound\Http\{HealthController, AuthController, BookController, MemberController, LoanController, AuthenticateToken, RequireRole};
use Illuminate\Support\Facades\Route;
Route::get('/health',HealthController::class);
Route::post('/auth/login',[AuthController::class,'login']);
Route::middleware(AuthenticateToken::class)->group(function(): void {
    Route::get('/categories',[BookController::class,'categories']);
    Route::get('/books',[BookController::class,'index']);
    Route::get('/books/{id}',[BookController::class,'show']);
    Route::get('/books/{id}/cover',[BookController::class,'cover']);
    Route::get('/members',[MemberController::class,'index']);
    Route::post('/members',[MemberController::class,'create']);
    Route::get('/members/{id}',[MemberController::class,'show']);
    Route::get('/loans/overdue',[LoanController::class,'overdue']);
    Route::get('/loans',[LoanController::class,'index']);
    Route::post('/loans',[LoanController::class,'create']);
    Route::get('/loans/{id}',[LoanController::class,'show']);
    Route::post('/loans/{loanId}/items/{itemId}/return',[LoanController::class,'returnItem']);
    Route::middleware(RequireRole::class.':admin')->group(function(): void {
        Route::post('/users',[AuthController::class,'create']);
        Route::post('/books',[BookController::class,'create']);
        Route::put('/books/{id}',[BookController::class,'edit']);
        Route::delete('/books/{id}',[BookController::class,'deactivate']);
        Route::put('/books/{id}/cover',[BookController::class,'upload']);
        Route::delete('/books/{id}/cover',[BookController::class,'remove']);
        Route::get('/reports/most-borrowed',[LoanController::class,'report']);
    });
});
