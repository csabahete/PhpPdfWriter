<?php
/**
 * Created by IntelliJ IDEA.
 * User: Csaba Hete
 * Date: 2017.01.07.
 * Time: 0:36
 */

namespace PdfWriter;

use Exception;

class PdfWriterTable implements PdfTable
{
    const BORDER_NONE = 0;
    const BORDER_ALL = 1;
    const BORDER_LEFT = 'L';
    const BORDER_TOP = 'T';
    const BORDER_RIGHT = 'R';
    const BORDER_BOTTOM = 'B';

    const ALIGN_LEFT = 'L';
    const ALIGN_CENTER = 'C';
    const ALIGN_RIGHT = 'R';

    const DEFAULT_CELL_ALIGN = self::ALIGN_CENTER;

    protected $width;
    protected $leftMargin;
    protected $lineHeight = PdfWriter::DEFAULT_LINE_HEIGHT;
    protected $border = self::BORDER_NONE;
    protected $headerFillColor = 255; //NONE

    protected $header = [];

    protected $rows = [];
    protected $headerAfterPageBreak = false;
    protected $headerAlign = null;

    /** @var */
    protected $numberOfColumns;
    /** @var array */
    protected $columnWidths;
    /** @var array */
    protected $cellAlignments;
    /** @var PdfWriter */
    protected $pdf;

    /**
     * PdfWriterTable constructor.
     * @param PdfWriter $pdf
     * @param float $width The width of the table if no column sizes or header with sizes was given
     * @param $numberOfColumns
     * @param array $columnWidths
     */
    public function __construct(PdfWriter $pdf, $width, $numberOfColumns = 0, array $columnWidths = [])
    {
        $this->pdf = $pdf;
        $this->width = $width;
        $this->setNumberOfColumns($numberOfColumns);
        $this->columnWidths = $columnWidths;
    }

    /**
     * @param mixed $numberOfColumns
     * @return PdfWriterTable
     * @throws Exception
     */
    public function setNumberOfColumns($numberOfColumns)
    {
        if ($numberOfColumns < 0) {
            throw new Exception("Value must be positive, $numberOfColumns given");
        }
        $this->numberOfColumns = $numberOfColumns;
        return $this;
    }

    /**
     * @param int $lineHeight
     * @return PdfWriterTable
     * @throws Exception
     */
    public function setLineHeight($lineHeight)
    {
        if ($lineHeight < 0) {
            throw new Exception("Value must be positive, $lineHeight given");
        }
        $this->lineHeight = $lineHeight;
        return $this;
    }

    /**
     * @param boolean $headerAfterPageBreak
     * @return PdfWriterTable
     */
    public function setHeaderAfterPageBreak($headerAfterPageBreak)
    {
        $this->headerAfterPageBreak = $headerAfterPageBreak;
        return $this;
    }

    /**
     * Sets the table header
     * @param array $columns If parameter array is associative, keys will be used as column width,
     * otherwise all columns going to be in same width.
     * @return PdfWriterTable
     * @throws Exception
     */
    public function setHeader(array $columns)
    {
        if ($this->array_depth($columns) > 1) {
            throw new Exception("Nested arrays as parameter does'nt allowed here!");
        }
        $this->header = array_values($columns);
        $this->setNumberOfColumns(count($columns));

        if ($this->isAssociativeArray($columns) && $this->arrayHasNumericKeysOnly($columns)) {
            $this->setColumnWidths(array_keys($columns));
        } else {
            $this->calculateColumnWidths();
        }
        return $this;
    }

    protected function array_depth($array)
    {
        $max_indentation = 1;

        $array_str = print_r($array, true);
        $lines = explode("\n", $array_str);

        foreach ($lines as $line) {
            $indentation = (strlen($line) - strlen(ltrim($line))) / 4;

            if ($indentation > $max_indentation) {
                $max_indentation = $indentation;
            }
        }

        return ceil(($max_indentation - 1) / 2) + 1;
    }

    protected function isAssociativeArray(array $arr)
    {
        if (array() === $arr) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    protected function arrayHasNumericKeysOnly(array $arr)
    {
        return !count(array_filter(array_keys($arr), 'is_string')) > 0;
    }

    /**
     * @param array $columnWidths
     * @return PdfWriterTable
     * @throws Exception
     */
    public function setColumnWidths($columnWidths)
    {
        if ($this->numberOfColumns <= 0) {
            throw new Exception("Number of columns or header must be set before this operation!");
        }
        if ($this->array_depth($columnWidths) > 1) {
            throw new Exception("Nested arrays as parameter doesn't allowed here!");
        }
        if (count($columnWidths) < $this->numberOfColumns) {
            $diff = $this->numberOfColumns - count($columnWidths);
            $givenWidth = array_reduce($columnWidths, function ($carry, $item) {
                $carry += $item;
                return $carry;
            });
            $otherColumnsWidth = ($this->pdf->getRowWidth() - $givenWidth) / $diff;
            for ($i = 0; $i < $diff; ++$i) {
                array_push($columnWidths, $otherColumnsWidth);
            }
        }
        $this->columnWidths = $columnWidths;
        $this->width = array_reduce($columnWidths, function ($carry, $item) {
            $carry += $item;
            return $carry;
        });
        return $this;
    }

    protected function calculateColumnWidths()
    {
        if ($this->width != 0) {
            $columnWidth = $this->width / $this->numberOfColumns;
            $this->columnWidths = array_fill(0, $this->numberOfColumns, $columnWidth);
        }
    }

    /**
     * @param int $border PdfWriterTable::BORDER_NONE | PdfWriterTable::BORDER_ALL | PdfWriterTable::BORDER_LEFT |
     *   PdfWriterTable::BORDER_TOP | PdfWriterTable::BORDER_RIGHT | PdfWriterTable::BORDER_BOTTOM
     * @return PdfWriterTable
     * @throws Exception
     */
    public function setBorder($border)
    {
        if (!in_array($border, [
            PdfWriterTable::BORDER_NONE,
            PdfWriterTable::BORDER_ALL,
            PdfWriterTable::BORDER_LEFT,
            PdfWriterTable::BORDER_TOP,
            PdfWriterTable::BORDER_RIGHT,
            PdfWriterTable::BORDER_BOTTOM
        ])
        ) {
            throw new Exception("Invalid argument");
        }
        $this->border = $border;
        return $this;
    }

    /**
     * @param array $cellAlignments
     * @return PdfWriterTable
     * @throws Exception
     */
    public function setCellAlignments(array $cellAlignments)
    {
        if ($this->numberOfColumns == 0) {
            throw new Exception("Number of columns not yet initialized! You must set header, numberOfColumns or cellWidth before this operation!");
        }
        if ($this->array_depth($cellAlignments) > 1) {
            throw new Exception("Nested arrays as parameter does'nt allowed here!");
        }
        if (count($cellAlignments) < $this->numberOfColumns) {
            $diff = $this->numberOfColumns - count($cellAlignments);
            for ($i = 0; $i < $diff; ++$i) {
                array_push($cellAlignments, self::DEFAULT_CELL_ALIGN);
            }
        }
        $this->cellAlignments = $cellAlignments;
        return $this;
    }

    /**
     * @param int $headerFillColor
     * @return PdfWriterTable
     * @throws Exception
     */
    public function setHeaderFillColor($headerFillColor)
    {
        if ($headerFillColor < 0 || $headerFillColor > 255) {
            throw new Exception("Value must be between 0 and 255 (inclusive)");
        }
        $this->headerFillColor = $headerFillColor;
        return $this;
    }

    /**
     * @param null $headerAlign
     * @return PdfWriterTable
     */
    public function setHeaderAlign($headerAlign)
    {
        //TODO: make it using the constants
        $this->headerAlign = $headerAlign;
        return $this;
    }

    /** Adds a row to the table's content
     * @param array $row
     * @throws Exception
     */
    public function addRow(array $row)
    {
        if ($this->numberOfColumns === 0) {
            throw new Exception("Number of columns not yet initialized");
        }
        if (count($row) < $this->numberOfColumns) {
            $diff = $this->numberOfColumns - count($row);
            for ($i = 0; $i < $diff; ++$i) {
                array_push($row, ' ');
            }
        }
        array_push($this->rows, $row);
    }

    /** Writes the table to the pdf
     */
    public function writeToPage()
    {
        $this->leftMargin = $this->pdf->GetX();
        $this->setWidth();
        $this->writeHeader($this->headerAlign);
        $this->writeContent();
    }

    protected function setWidth()
    {
        if (empty($this->header)) {
            if (count($this->columnWidths) === 0) {
                $this->width ?: $this->width = $this->pdf->getRowWidth();
                if ($this->width === 0) {
                    throw new Exception("No space on page or unknown error");
                }
                $this->calculateColumnWidths();
            }
        }
    }

    protected function writeHeader($rowAlign = null)
    {
        if ($this->header) {
            $this->pdf->SetFillColor($this->headerFillColor);
            $this->writeRow($this->header, $this->border, true, $rowAlign);
            $this->pdf->SetFillColor(255);
        }
    }

    /**
     * @param $row
     * @param $cellBorder
     * @param $fill
     */
    protected function writeRow($row, $cellBorder, $fill, $rowAlign = null)
    {
        for ($i = 0; $i < $this->numberOfColumns; ++$i) {
            if ($this->headerAfterPageBreak && $this->pdf->GetY() + $this->lineHeight >= $this->pdf->getBBorder()) {
                $this->pdf->AddPage();
                $this->writeHeader();
            }
            $lastCellInRow = $this->numberOfColumns - 1 == $i;
            $cellContent = $row[$i];
            $cellWidth = $this->columnWidths[$i];
            if (null === $rowAlign) {
                $cellAlign = empty($this->cellAlignments) ? self::DEFAULT_CELL_ALIGN : $this->cellAlignments[$i];
            } else {
                $cellAlign = $rowAlign;
            }
            $this->writeCell($cellWidth, $this->lineHeight, $cellContent, $cellBorder, $cellAlign, $fill,
                $lastCellInRow);
        }
    }

    /**
     * @param float $width
     * @param float $height
     * @param mixed $content
     * @param mixed $border 0 | 1 | L | T | R | B
     * @param string $align 'L' | 'C' | 'R'
     * @param boolean $fill
     * @param boolean $lastCellInRow
     */
    protected function writeCell($width, $height, $content, $border, $align, $fill, $lastCellInRow)
    {
        $cursorAfterWrite = $lastCellInRow ? 1 : 0;
        $this->pdf->fitCell($width, $height, $content, $border, $cursorAfterWrite, $align, $fill);
    }

    protected function writeContent()
    {
        foreach ($this->rows as $row) {
            $this->writeRow($row, $this->border, false);
        }
    }
}
