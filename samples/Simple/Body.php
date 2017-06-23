<?php
/**
 * Created by PhpStorm.
 * User: Csaba_Hete
 * Date: 2017.06.23.
 * Time: 10:58
 */

namespace PdfWriter\Samples\Simple;


use PdfWriter\PdfBody;
use PdfWriter\PdfWriter;

class Body implements PdfBody
{

    /**
     * @param PdfWriter $pdf
     * @param $printData
     */
    public function createBody(PdfWriter $pdf, $printData)
    {
        $pdf->fitCell(
            $pdf->getRowWidth(),
            PdfWriter::DEFAULT_LINE_HEIGHT,
            "Simple body",
            0,
            2
        );
    }
}