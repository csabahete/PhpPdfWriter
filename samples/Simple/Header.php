<?php
/**
 * Created by PhpStorm.
 * User: Csaba_Hete
 * Date: 2017.06.23.
 * Time: 10:40
 */

namespace PdfWriter\Samples\Simple;

use PdfWriter\AbstractPdfHeader;
use PdfWriter\PdfWriter;

class Header extends AbstractPdfHeader
{

    /**
     * @param PdfWriter $pdf
     * @param $printData
     * @param int $copy
     */
    public function createHeader(PdfWriter $pdf, $printData, $copy)
    {
        $this->putWatermark($pdf);

        $pdf->fitCell(
            $pdf->getRowWidth(),
            PdfWriter::DEFAULT_LINE_HEIGHT,
            'This is a simple header',
            1,
            2
            );
    }
}