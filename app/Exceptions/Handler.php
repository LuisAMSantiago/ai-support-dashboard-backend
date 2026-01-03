<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class Handler extends ExceptionHandler
{
    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        //
    }

    public function render($request, Throwable $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            $status = 500;
            $message = 'Server Error';
            $errors = null;

            if ($exception instanceof ValidationException) {
                $status = 422;
                $message = 'Validation Error';
                $errors = $exception->errors();
            } elseif ($exception instanceof AuthenticationException) {
                $status = 401;
                $message = 'Unauthenticated';
            } elseif ($exception instanceof AuthorizationException) {
                $status = 403;
                $message = 'Forbidden';
            } elseif ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
                $status = 404;
                $message = 'Not Found';
            } elseif ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
                $message = $exception->getMessage() ?: 'Error';
            } else {
                $status = 500;
                $message = $exception->getMessage() ?: 'Server Error';
            }

            $meta = [
                'success' => false,
                'message' => $message,
                'code' => $status,
            ];

            // Adiciona errors apenas para ValidationException
            if ($errors !== null) {
                $meta['errors'] = $errors;
            }

            if (config('app.debug')) {
                $meta['debug'] = [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'trace' => $exception->getTrace(),
                ];
            }

            return response()->json(['data' => null, 'meta' => $meta], $status);
        }

        return parent::render($request, $exception);
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            $meta = [
                'success' => false,
                'message' => 'Unauthenticated',
                'code' => 401,
            ];
            return response()->json(['data' => null, 'meta' => $meta], 401);
        }

        return redirect()->guest(route('login'));
    }
}
