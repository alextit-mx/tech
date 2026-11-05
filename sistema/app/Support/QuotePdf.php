<?php

namespace App\Support;

use App\Models\Quote;
use Symfony\Component\HttpFoundation\Response;

class QuotePdf
{
    public static function download(Quote $quote): Response
    {
        $quote->loadMissing('items', 'customer', 'user');

        return Pdf::stream('pdf.quote', ['quote' => $quote], $quote->folio.'.pdf');
    }
}
