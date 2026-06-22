<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Psr\Log\LogLevel;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<Throwable>, LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        AppointmentDomainException::class,
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (AppointmentDomainException $exception, $request) {
            if (! $request->expectsJson()) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['appointment' => $exception->getMessage()]);
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => $exception->errorCode,
                'errors' => (object) $exception->errors,
            ], $exception->httpStatus);
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
