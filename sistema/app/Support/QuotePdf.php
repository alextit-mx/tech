<?php

namespace App\Support;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class QuotePdf
{
    public static function download(Quote $quote): Response
    {
        $quote->loadMissing('items', 'customer', 'user');

        return Pdf::loadView('pdf.quote', [
            'quote' => $quote,
            'logo' => public_path('img/ats-computer.png'),
        ])->setPaper('letter')->stream($quote->folio.'.pdf');
    }
}
