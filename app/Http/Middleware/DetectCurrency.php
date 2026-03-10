<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\CurrencyResolver;

class DetectCurrency
{
    protected CurrencyResolver $resolver;

    public function __construct(CurrencyResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function handle(Request $request, Closure $next)
    {
        $data = $this->resolver->resolve($request);

        // Stock in request attributes
        $request->attributes->set('geo_ip', $data['ip']);
        $request->attributes->set('geo_country', $data['country']);
        $request->attributes->set('geo_currency', $data['currency']);
        $request->attributes->set('geo_source', $data['source']);

        // Store currency in session (strict IP, no override)
        if (!$request->session()->has('currency')) {
            $request->session()->put('currency', $data['currency']);
        }

        return $next($request);
    }
}
