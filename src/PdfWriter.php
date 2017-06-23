<?php
/**
 * Created by IntelliJ IDEA.
 * User: Csaba Hete
 * Date: 2016.12.18.
 * Time: 21:19
 */

namespace PdfWriter;

use App\Modules\Core\Exceptions\InvalidArgumentException;
use FPDF;

class PdfWriter extends FPDF
{
    const BOTTOM_MARGIN = 10;

    const DEFAULT_LINE_HEIGHT = 4;
    const DEFAULT_FONT_FAMILY = 'Arial';
    const DEFAULT_FONT_SIZE = 8;

    const AUTO_PAGE_BREAK_MARGIN = 10;
    const SEPARATOR_LINE_MARGIN = 2;

    const SEPARATOR_LINE_COLOR = 120;
    const WATERMARK_SIZE = 110;


    const WATERMARK_COLOR = 230;
    const DEFAULT_TEXT_COLOR = 0;
    protected $angle = 0;

    /** @var  PdfHeader */
    protected $header;

    /** @var  PdfFooter */
    protected $footer;

    /** @var  PdfBody */
    protected $body;
    protected $watermark;
    protected $numberOfCopies = 1;
    protected $copy;
    protected $showGrid = false;

    protected $NewPageGroup;
    protected $PageGroups;
    protected $CurrPageGroup;
    private $printData;

    /**
     * PdfDocumentWriter constructor.
     * @param array $printData
     * @param string $orientation Default page orientation. Possible values are (case insensitive):
     *                            P or Portrait,
     *                            L or Landscape.
     *                            Default value is P.
     * @param string $unit User unit. Possible values are:
     *                         pt: point,
     *                         mm: millimeter,
     *                         cm: centimeter,
     *                         in: inch.
     *                     A point equals 1/72 of inch, that is to say about 0.35 mm (an inch being 2.54 cm).
     *                     This is a very common unit in typography; font sizes are expressed in that unit.
     *                     Default value is mm.
     * @param string $size The size used for pages. It can be either one of the following values (case insensitive):
     *                     A3, A4, A5, Letter, Legal, or an array containing the width and the height
     *                     (expressed in the unit given by unit). Default value is A4.
     */
    public function __construct($printData, $orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size);
        $this->fontpath = dirname(__FILE__) . '/fonts' . DIRECTORY_SEPARATOR;
        $this->AddFont(self::DEFAULT_FONT_FAMILY, '', 'arial.php');
        $this->AddFont(self::DEFAULT_FONT_FAMILY, 'B', 'arialbd.php');
        $this->resetFont();
        $this->resetTextColor();
        $this->SetAutoPageBreak(true, self::AUTO_PAGE_BREAK_MARGIN);

        $this->printData = $printData;
    }

    /**
     * @param bool $showGrid
     */
    public function setShowGrid($showGrid)
    {
        $this->showGrid = $showGrid;
    }

    public function resetFont()
    {
        $this->SetFont(self::DEFAULT_FONT_FAMILY, '', self::DEFAULT_FONT_SIZE);
        $this->SetTextColor(self::DEFAULT_TEXT_COLOR);
    }

    protected function resetTextColor()
    {
        $this->SetTextColor(self::DEFAULT_TEXT_COLOR);
    }

    /**
     * @param PdfHeader $header
     * @return PdfWriter
     */
    public function setHeader($header)
    {
        $this->header = $header;
        return $this;
    }

    /**
     * @param PdfFooter $footer
     * @return PdfWriter
     */
    public function setFooter($footer)
    {
        $this->footer = $footer;
        $this->SetAutoPageBreak(true, self::BOTTOM_MARGIN + $footer->getHeight($this, $this->printData));
        return $this;
    }

    /**
     * @param PdfBody $body
     * @return PdfWriter
     */
    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     * @param int $numberOfCopies
     * @return PdfWriter
     */
    public function setNumberOfCopies($numberOfCopies)
    {
        $this->numberOfCopies = $numberOfCopies;
        return $this;
    }

    public function Header()
    {
        if (!empty($this->header)) {
            $this->header->setWatermark($this->watermark);
            $this->header->createHeader($this, $this->printData, $this->copy);
        }
        /** GRID */
        if ($this->showGrid) {
            $this->drawGrid();
        }
    }

    protected function drawGrid($step = self::DEFAULT_LINE_HEIGHT)
    {
        $initialX = $this->GetX();
        $initialY = $this->GetY();

        $this->showMargins();
        $this->createGrid($step);
        $this->setXY($initialX, $initialY);
    }

    protected function showMargins()
    {
        $this->SetLineWidth(0.5);
        $this->SetDrawColor(255, 0, 0);
        $this->Line(0, $this->tMargin, $this->GetPageWidth(), $this->tMargin);
        $this->Line(0, $this->getBBorder(), $this->GetPageWidth(), $this->getBBorder());
        $this->Line($this->lMargin, 0, $this->lMargin, $this->GetPageHeight());
        $this->Line($this->getRBorder(), 0, $this->getRBorder(), $this->GetPageHeight());
        $this->resetLineWidth();
        $this->SetDrawColor(0);
    }

    public function getBBorder()
    {
        return $this->GetPageHeight() - $this->bMargin;
    }

    public function getRBorder()
    {
        return $this->GetPageWidth() - $this->rMargin;
    }

    public function resetLineWidth()
    {
        $this->SetLineWidth(0.2);
    }

    protected function createGrid($step)
    {
        $this->SetDrawColor(120);
        $this->SetTextColor(150);
        $this->SetFont(self::DEFAULT_FONT_FAMILY, '', self::DEFAULT_FONT_SIZE);

        for ($i = $this->tMargin; $i + $step < $this->getBBorder(); $i += $step) {
            $this->Line($this->lMargin, $i, $this->getRBorder(), $i);
            $x = 0;
            $y = (int)$i;
            $this->SetXY($this->lMargin, $y);
            $this->Cell($this->lMargin, $step, "($x, $y)", 0, "L", 2);
        }

        $this->SetDrawColor(0);
        $this->SetTextColor(0);
    }

    public function Footer()
    {
        if (!empty($this->footer)) {
            $this->SetXY($this->getLMargin(), $this->getBBorder());
            $this->footer->createFooter($this, $this->printData);
        }
    }

    /**
     * Creates a cell and writes the given text into it. If the text doesn't fits, then an ellipsis effect will be applied
     *
     * @param int $w Cell width. If 0, the cell extends up to the right margin.
     * @param int $h Cell height. Default value: 0
     * @param string $txt String to print. Default value: empty string.
     * @param int $border Indicates if borders must be drawn around the cell. The value can be either a number:
     *                      0: no border,
     *                      1: frame,
     *                    or a string containing some or all of the following characters in any order:
     *                      L: left,
     *                      T: top,
     *                      R: right,
     *                      B: bottom.
     *                    Default value: 0.
     * @param int $ln Indicates where the current position should go after the call. Possible values are:
     *                0: to the right,
     *                1: to the beginning of the next line,
     *                2: below.
     *                Putting 1 is equivalent to putting 0 and calling Ln() just after. Default value: 0.
     * @param string $align Allows to center or align the text. Possible values are:
     *                      L or empty string: left align (default value),
     *                      C: center,
     *                      R: right align
     * @param bool $fill Indicates if the cell background must be painted (true) or transparent (false).
     *                   Default value: false.
     * @param string $link URL or identifier returned by AddLink().
     */
    public function fitCell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        $h = $h?:self::DEFAULT_LINE_HEIGHT;

        $txt = $this->encodeString($txt);
        if (!empty($txt) && $txt != "") {
            $txt = $this->ellipsis($w, $txt);
        }
        $this->Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);
    }

    /**
     * @param $txt
     * @return string
     */
    protected function encodeString($txt)
    {
        return iconv('UTF-8', 'ISO-8859-2//IGNORE', $txt);
    }

    protected function ellipsis($w, $txt)
    {
        $txt = trim($txt);
        $txtWidth = $this->GetStringWidth($txt);

        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }

        if ($w < $txtWidth) {
            $avgCharWidth = $txtWidth / strlen($txt);
            $targetCharAmount = (int)$w / $avgCharWidth;
            return substr($txt, 0, $targetCharAmount - 4) . " ...";
        }
        return $txt;
    }

    /**
     * Creates a cell and writes the given text into it. If the text doesn't fits, then line breaks will be applied at
     * the end of the row (or at the given width).
     *
     * @param int $w Cell width. If 0, the cell extends up to the right margin.
     * @param int $h Cell height. Default value: 0
     * @param string $txt String to print. Default value: empty string.
     * @param int $border Indicates if borders must be drawn around the cell. The value can be either a number:
     *                      0: no border,
     *                      1: frame,
     *                    or a string containing some or all of the following characters in any order:
     *                      L: left,
     *                      T: top,
     *                      R: right,
     *                      B: bottom.
     *                    Default value: 0.
     * @param string $align Allows to center or align the text. Possible values are:
     *                      L or empty string: left align (default value),
     *                      C: center,
     *                      R: right align,
     *                      J: justification (default value)
     * @param bool $fill Indicates if the cell background must be painted (true) or transparent (false).
     *                   Default value: false.
     */
    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false)
    {
        $txt = $this->encodeString($txt);
        parent::MultiCell($w, $h, $txt, $border, $align, $fill);
    }

    public function setWatermark($watermark)
    {
        $this->watermark = $watermark;
    }

    public function getTMargin()
    {
        return $this->tMargin;
    }

    public function getBMargin()
    {
        return $this->bMargin;
    }

    public function getRowWidth()
    {
        return $this->GetPageWidth() - $this->getLMargin() - $this->getRMargin();
    }

    public function getLMargin()
    {
        return $this->lMargin;
    }

    public function getRMargin()
    {
        return $this->rMargin;
    }

    public function withinPageBorder($dY)
    {
        return $this->GetY() + $dY <= $this->getBBorder();
    }

    /** Draws a horizontal line between page margins to current X position
     * @param $y
     * @param int $lineBottomMargin
     */
    public function drawSeparatorLine($y, $lineBottomMargin = self::SEPARATOR_LINE_MARGIN)
    {
        $this->SetDrawColor(self::SEPARATOR_LINE_COLOR);
        $this->Line($this->getLMargin(), $y, $this->getRBorder(), $y);
        $this->SetXY($this->getLMargin(), $y + $lineBottomMargin);
        $this->SetDrawColor(0);
    }

    /** Draws a horizontal line to given position
     * @param $x1
     * @param $x2
     * @param $y
     * @param int $lineBottomMargin
     */
    public function drawSeparatorLineTo($x1, $x2, $y, $lineBottomMargin = self::SEPARATOR_LINE_MARGIN)
    {
        $this->SetDrawColor(self::SEPARATOR_LINE_COLOR);
        $this->Line($x1, $y, $x2, $y);
        $this->SetXY($this->getLMargin(), $y + $lineBottomMargin);
        $this->SetDrawColor(0);
    }

    public function watermarkCurrentPage($watermark)
    {
        $watermark = $this->encodeString($watermark);
        $this->SetFont(self::DEFAULT_FONT_FAMILY, 'B', self::WATERMARK_SIZE);
        $this->SetTextColor(self::WATERMARK_COLOR);
        $x = $this->CurOrientation == 'P' ? 45 : 30;
        $y = $this->CurOrientation == 'P' ? 280 : 190;
        $angle = $this->CurOrientation == 'P' ? 60 : 32;
        $this->rotatedText($x, $y, $watermark, $angle);
        $this->resetFont();
        $this->resetTextColor();
    }

    public function rotatedText($x, $y, $txt, $angle)
    {
        // rotate by angle, center of rotation: x, y
        $this->Rotate($angle, $x, $y);
        $this->Text($x, $y, $txt);
        $this->Rotate(0);
    }

    /**
     * Page number groups
     */

    // Written by Larry Stanbery - 20 May 2004
    // "freeware" -- same license as FPDF
    // creates "page groups" -- groups of pages with page numbering
    // total page numbers are represented by aliases of the form {nbX}
    protected function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1) {
            $x = $this->x;
        }
        if ($y == -1) {
            $y = $this->y;
        }
        if ($this->angle != 0) {
            $this->_out('Q');
        }
        $this->angle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c,
                $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }   // variable indicating whether a new group was requested

    public function rotatedImage($file, $x, $y, $w, $h, $angle)
    {
        // center of rotation: upper left corner
        $this->Rotate($angle, $x, $y);
        $this->Image($file, $x, $y, $w, $h);
        $this->Rotate(0);
    }     // variable containing the number of pages of the groups

    public function Close()
    {
        if ($this->body) {
            for ($i = 0; $i < $this->numberOfCopies; ++$i) {
                $this->copy = $i;
                $this->startPageGroup();
                $this->AddPage();
                $this->body->createBody($this, $this->printData);
            }
            $this->pageGroupAlias();
        }
        parent::Close();
    }  // variable containing the alias of the current page group

    // create a new page group; call this before calling AddPage()

    public function startPageGroup()
    {
        $this->NewPageGroup = true;
    }

    // current page in the group

    public function pageGroupAlias()
    {
        return $this->CurrPageGroup;
    }

    // alias of the current page group -- will be replaced by the total number of pages in this group

    public function groupPageNo()
    {
        return $this->PageGroups[$this->CurrPageGroup];
    }

    public function _beginpage($orientation = 'P', $size = 'A4', $rotation = 0)
    {
        parent::_beginpage($orientation, $size, $rotation);
        if ($this->NewPageGroup) {
            // start a new group
            $n = sizeof($this->PageGroups) + 1;
            $alias = "{nb$n}";
            $this->PageGroups[$alias] = 1;
            $this->CurrPageGroup = $alias;
            $this->NewPageGroup = false;
        } else {
            if ($this->CurrPageGroup) {
                $this->PageGroups[$this->CurrPageGroup]++;
            }
        }
    }

    public function _putpages()
    {
        $nb = $this->page;
        if (!empty($this->PageGroups)) {
            // do page number replacement
            foreach ($this->PageGroups as $k => $v) {
                for ($n = 1; $n <= $nb; $n++) {
                    $this->pages[$n] = str_replace($k, $v, $this->pages[$n]);
                }
            }
        }
        parent::_putpages();
    }

    /** Number of lines if text written in MultiCell
     * @param $w
     * @param $txt
     * @return int
     */
    public function numberOfLines($w, $txt)
    {
        $txt = trim($txt);
        $cw =& $this->CurrentFont['cw'];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 and $s[$nb - 1] == "\n") {
            $nb--;
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ') {
                $sep = $i;
            }
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }
}
