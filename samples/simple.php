<?php
/**
 * Created by PhpStorm.
 * User: Csaba_Hete
 * Date: 2017.06.23.
 * Time: 10:39
 */

use PdfWriter\PdfWriter;
use PdfWriter\Samples\Simple\Body;
use PdfWriter\Samples\Simple\Footer;
use PdfWriter\Samples\Simple\Header;

require '../vendor/autoload.php';
$pdf = new PdfWriter([]);
$header = new Header();
$header->setWatermark("     PREVIEW");
$pdf->setHeader($header);
$pdf->setBody(new Body());
$pdf->setFooter(new Footer());

$pdf->setShowGrid(true);
$pdf->SetAutoPageBreak(true, PdfWriter::BOTTOM_MARGIN);
$pdf->output();