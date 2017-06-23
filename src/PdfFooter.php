<?php
/**
 * Created by IntelliJ IDEA.
 * User: Csaba Hete
 * Date: 2017.01.04.
 * Time: 16:55
 */

namespace PdfWriter;

interface PdfFooter
{
    /**
     * @param PdfWriter $pdf
     * @param $printData
     */
    public function createFooter(PdfWriter $pdf, $printData);

    /**
     * @param PdfWriter $pdf
     * @param $printData
     * @return mixed
     */
    public function getHeight(PdfWriter $pdf, $printData);
}
