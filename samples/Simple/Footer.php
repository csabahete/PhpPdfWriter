<?php
/**
 * Created by PhpStorm.
 * User: Csaba_Hete
 * Date: 2017.06.23.
 * Time: 10:59
 */

namespace PdfWriter\Samples\Simple;


use PdfWriter\PdfFooter;
use PdfWriter\PdfWriter;

class Footer implements PdfFooter
{

    /**
     * @param PdfWriter $pdf
     * @param $printData
     */
    public function createFooter(PdfWriter $pdf, $printData)
    {
        $pdf->fitCell(
            $pdf->getRowWidth(),
            PdfWriter::DEFAULT_LINE_HEIGHT,
            "Simple footer"
        );
    }

    /**
     * @param PdfWriter $pdf
     * @param $printData
     * @return mixed
     */
    public function getHeight(PdfWriter $pdf, $printData)
    {
        return PdfWriter::DEFAULT_LINE_HEIGHT * 1;
    }
}