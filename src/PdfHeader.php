<?php
/**
 * Created by IntelliJ IDEA.
 * User: Csaba Hete
 * Date: 2017.01.04.
 * Time: 16:55
 */

namespace PdfWriter;

interface PdfHeader
{
    /**
     * @param PdfWriter $pdf
     * @param $printData
     * @param int $copy
     */
    public function createHeader(PdfWriter $pdf, $printData, $copy);

    /**
     * @param string $txt
     */
    public function setWatermark($txt);
}
