<?php
/**
 * Created by IntelliJ IDEA.
 * User: Csaba Hete
 * Date: 2017.01.06.
 * Time: 23:34
 */

namespace PdfWriter;

interface PdfBody
{
    /**
     * @param PdfWriter $pdf
     * @param $printData
     */
    public function createBody(PdfWriter $pdf, $printData);
}
