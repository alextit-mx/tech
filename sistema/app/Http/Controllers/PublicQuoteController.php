<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Support\QuotePdf;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PublicQuoteController extends Controller
{
    public function show(string $token): View
    {
        $quote = Quote::with('items', 'customer')->where('public_token', $token)->firstOrFail();

        return view('site.quote', ['quote' => $quote]);
    }

    public function pdf(string $token): Response
    {
        return QuotePdf::download(Quote::where('public_token', $token)->firstOrFail());
    }
}
