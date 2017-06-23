<?php
/**
 * Created by IntelliJ IDEA.
 * User: Csaba Hete
 * Date: 2017.01.16.
 * Time: 15:59
 */

namespace PdfWriter;

abstract class AbstractPdfHeader implements PdfHeader
{

    protected $watermark = '';

    /** Sets the page watermark only once
     * @param $txt
     */
    public function setWatermark($txt)
    {
        if ($this->watermark == '' && $txt) {
            $this->watermark = $txt;
        }
    }

    protected function putWatermark(PdfWriter $pdf)
    {
        if ($this->watermark != '') {
            $initialX = $pdf->GetX();
            $initialY = $pdf->GetY();
            $pdf->watermarkCurrentPage($this->watermark);
            $pdf->SetXY($initialX, $initialY);
        }
    }
}
