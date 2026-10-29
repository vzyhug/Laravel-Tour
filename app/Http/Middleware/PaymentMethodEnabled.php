<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentMethodEnabled
{
    public function handle(Request $request, Closure $next, string ...$methods): Response
    {
        abort_unless(array_intersect($methods, config('payments.checkout_methods', ['sepay', 'cash', 'card', 'vietqr_demo'])), 404);

        return $next($request);
    }
}
