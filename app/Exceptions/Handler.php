<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use App\Http\Utils\Errors;



class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    protected $msg = '';

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        if ($exception instanceof CommonException) {
            return $exception->report($exception);   
        }
        if ($exception instanceof ValidaterException) {
            return $exception->report($exception);
        }
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        if ($exception instanceof CommonException) {
            $code =  $exception->render();
            $errorHandle = new Errors();
            return $errorHandle->error($code);
        }
        if ($exception instanceof ValidaterException) {
            $errData = $exception->render();
            $errorHandle = new Errors();
            return $errorHandle->validaterError($errData);
        }
        
        return parent::render($request, $exception);
    }
}
