<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Symfony\Component\HttpFoundation\Response;

class Pdf
{
    /** PDF tamaño carta con el logo disponible como $logo en la vista y "Página X de Y" abajo a la derecha. */
    public static function stream(string $view, array $data, string $filename): Response
    {
        $pdf = DomPdf::loadView($view, $data + ['logo' => Branding::pdfLogoPath()])
            ->setPaper('letter')
            ->setOption('isFontSubsettingEnabled', true);
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans');
        $width = $metrics->getTextWidth('Página 9 de 9', $font, 5);
        $canvas->page_text($canvas->get_width() - 34 - $width, $canvas->get_height() - 24.5, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 5, [0.53, 0.53, 0.53]);

        return $pdf->stream($filename);
    }
}
