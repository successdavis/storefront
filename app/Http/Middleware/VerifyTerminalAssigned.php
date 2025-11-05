<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTerminalAssigned
{
    public function handle($request, Closure $next)
    {
        if (!session()->has('pos_terminal_id')) {
            return redirect()->route('pos.index')
                ->withErrors(['terminal' => 'You must select a POS terminal first.']);
        }

        return $next($request);
    }
}
