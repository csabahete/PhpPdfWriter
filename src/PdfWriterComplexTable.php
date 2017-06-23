<?php
/**
 * Created by IntelliJ IDEA.
 * User: Csaba Hete
 * Date: 2017.01.07.
 * Time: 12:39
 */

namespace PdfWriter;

use Exception;

class PdfWriterComplexTable extends PdfWriterTable
{
    /** Constant array keys for rows and cells */
    const ROW_COMMENT = -4;

    const ROW_CONTENT = 0;
    const CELL_CONTENT = 0;
    const CELL_OPTIONS = -1;

    const FONT_COLOR = -2;
    const CELL_ALIGN = -3;
    const FONT_STYLE = -4;
    const FONT_SIZE = -11;
    const CELL_BORDER = -5;
    const CELL_TYPE = -6;
    const CELL_FILL = -10;

    /** Constant option values for rows and cells */
    // CELL_TYPE
    const FIT_CELL = -7;
    const MULTI_CELL = -8;

    //FONT_STYLE
    const BOLD_TEXT = 'B';
    const ITALIC_TEXT = 'I';
    const UNDERLINE_TEXT = 'U';

    /** Default settings*/
    const SEPARATOR_LINE_MARGIN = 1;
    const COMMENT_FONT_SIZE = 7;
    const COMMENT_TEXT_COLOR = 160;
    const DEFAULT_FONT_COLOR = 0;
    const DEFAULT_FONT_STYLE = '';
    const DEFAULT_BORDER = self::BORDER_NONE;
    const DEFAULT_CELL_TYPE = self::FIT_CELL;
    const DEFAULT_FONT_SIZE = PdfWriter::DEFAULT_FONT_SIZE;

    protected $rowSeparatorLines = false;
    protected $tableHeaderBottomMargin = 0;
    protected $writingHeader;

    /** Sets the table header
     * @param array $header Parameter must be in format array($array($headerRow), array($headerRow),...)  with max depth of 3.
     * If $headerRow contains a nested array, each nested value goes in a separate FittedCell and the
     * sum of these cells height will give the row height.
     * @return PdfWriterTable
     * @throws Exception
     */
    public function setHeader(array $header)
    {
        $numberOfColumns = $this->validateHeader($header);
        $h = [];
        foreach ($header as $row) {
            array_push($h, [
                self::ROW_CONTENT => $row
            ]);
        }
        $this->header = $h;
        $this->setNumberOfColumns($numberOfColumns);
        $this->calculateColumnWidths();
        return $this;
    }

    /**
     * @param array $header
     * @return int
     * @throws Exception
     */
    public function validateHeader(array $header)
    {
        if ($this->array_depth($header) < 2) {
            throw new Exception('Invalid header format! Array must be in format array(array($headerRow), array($headerRow),...)');
        }
        if ($this->array_depth($header) > 3) {
            throw new Exception("Max array depth of header exceeded!");
        }
        $numberOfColumns = count(array_values($header)[0]);
        foreach ($header as $row) {
            if ($numberOfColumns != count($row)) {
                throw new Exception("Each header row must be the same size!");
            }
        }
        return $numberOfColumns;
    }

    /** Adds a row to the table's content
     * @param array $row Array with max depth of 3. The nested array may have 0 === PdfWriterComplexTable::ROW_CONTENT or
     * PdfWriterComplexTable::ROW_COMMENT as key. Parameter must be in format
     * array(
     *      $array( 0 => $rowContent) |
     *      $array(PdfWriterComplexTable::ROW_CONTENT => $rowContent, PdfWriterComplexTable::ROW_COMMENT => $comment)
     * ) where $rowContent is an Array too.
     * An example of $rowContent:
     * $rowContent = [
     *     PdfWriterComplexTable::CELL_CONTENT => 'Summary: ',
     *     PdfWriterComplexTable::CELL_OPTIONS => [
     *         PdfWriterComplexTable::FONT_STYLE => PdfWriterComplexTable::BOLD_TEXT,
     *     ]
     * ].
     * If $rowContent contains a nested array, each nested value goes in a separate FittedCell and the sum of these cell's
     * height will give the row height.
     * $comment will be placed in a MultiCell
     *
     * @throws Exception
     */
    public function addRow(array $row)
    {
        $this->validateRow($row);
        $this->completeShortRows($row);
        array_push($this->rows, $row);
    }

    /**
     * @param array $row
     * @throws Exception
     */
    protected function validateRow(array &$row)
    {
        if ($this->numberOfColumns === 0) {
            throw new Exception("Number of columns not yet initialized");
        }
        if ($this->array_depth($row) > 5) {
            throw new Exception("Max array depth exceeded!");
        }
        if (array_key_exists(self::ROW_COMMENT, $row)) {
            if (is_array($row[self::ROW_COMMENT])) {
                throw new Exception("Row comment can be a string only!");
            }
            if (!$row[self::ROW_COMMENT]) {
                unset($row[self::ROW_COMMENT]);
            }
        }
        if (empty($row)) {
            $row = [array_fill(0, $this->numberOfColumns, null)];
        }
        if (!$this->rowContainsProperKeysOnly($row)) {
            throw new Exception("Array may contain only 0 === PdfWriterComplexTable::ROW_CONTENT or PdfWriterComplexTable::ROW_COMMENT as key !");
        }
    }

    /**
     * @param array $row
     * @return bool
     */
    protected function rowContainsProperKeysOnly(array $row)
    {
        $keys = array_keys($row);
        foreach ($keys as $key) {
            if (!in_array($key, [self::ROW_CONTENT, self::ROW_COMMENT])) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array $row
     */
    protected function completeShortRows(array &$row)
    {
        if (count($row[self::ROW_CONTENT]) < $this->numberOfColumns) {
            $diff = $this->numberOfColumns - count($row[self::ROW_CONTENT]);
            for ($i = 0; $i < $diff; ++$i) {
                array_push($row[self::ROW_CONTENT], ' ');
            }
        }
    }

    /**
     * @param boolean $rowSeparatorLines
     * @return PdfWriterComplexTable
     */
    public function drawRowSeparatorLines($rowSeparatorLines)
    {
        $this->rowSeparatorLines = $rowSeparatorLines;
        return $this;
    }

    /**
     * @param int $tableHeaderBottomMargin
     * @return PdfWriterComplexTable
     */
    public function setHeaderBottomMargin($tableHeaderBottomMargin)
    {
        $this->tableHeaderBottomMargin = $tableHeaderBottomMargin;
        return $this;
    }

    /** Writes the table to the pdf
     */
    public function writeToPage()
    {
        $this->leftMargin = $this->pdf->GetX();
        $this->setWidth();
        if (!$this->tableHeaderNotFitInPage()) {
            $this->pdf->AddPage();
        }
        $this->writeHeader($this->headerAlign);
        $this->writeContent();
    }

    protected function setWidth()
    {
        if (count($this->columnWidths) === 0) {
            $this->width ?: $this->width = $this->pdf->getRowWidth();
            if ($this->width === 0) {
                throw new Exception("No space on page or unknown error");
            }
            $this->calculateColumnWidths();
        }
    }

    protected function tableHeaderNotFitInPage()
    {
        $headerHeight = 0;
        foreach ($this->header as $row) {
            $headerHeight = +$this->getRowHeight($row, false);
        }
        $firstRowHeight = empty($this->rows) ? 0 : $this->getRowHeight($this->rows [0], true);
        return $this->pdf->GetY() + $headerHeight + $firstRowHeight <= $this->pdf->getBBorder();
    }

    /**
     * @param $row
     * @param bool $contentOnly
     * @return int
     */
    protected function getRowHeight($row, $contentOnly = true)
    {
        $numOfRows = 1;
        if (!$contentOnly) {
            if (array_key_exists(self::ROW_COMMENT, $row)) {
                $commentWidth = $this->width - $this->columnWidths[0];
                $numOfRows += $this->pdf->numberOfLines($commentWidth, $row[self::ROW_COMMENT]);
            }
        }
        $row = array_values($row[self::ROW_CONTENT]);
        for ($i = 0; $i < $this->numberOfColumns; ++$i) {
            $numOfRows = max($numOfRows, $this->countCellRows($row[$i], $this->columnWidths[$i]));
        }
        return $numOfRows * $this->lineHeight;
    }

    protected function countCellRows($cell, $cellWidth)
    {
        $cellRows = 1;
        if (is_array($cell)) {
            if ($this->array_depth($cell) < 2 || $this->array_depth($cell) == 2 && !array_key_exists(self::CELL_OPTIONS,
                    $cell)
            ) {
                return count($cell); // no cell options Ex.:['a','b','c','d','e','f','g'], ['a',['b'],['c'],'d','e','f','g']
            } elseif ($this->array_depth($cell) == 2) {
                if (array_key_exists(self::CELL_OPTIONS, $cell)) {
                    return $this->countCustomCellRows($cell, $cellWidth); // cell with options
                }
            } else { // multiple sub-cells with options
                $cellRows = 0;
                foreach ($cell as $subCell) {
                    $cellRows += $this->countCellRows($subCell, $cellWidth);
                }
            }
        }
        return $cellRows;
    }

    protected function countCustomCellRows(array $cell, $cellWidth)
    {
        if (!is_array($cell [self::CELL_OPTIONS])) {
            throw new Exception("Cell options must be an array!");
        }
        if (array_key_exists(self::CELL_TYPE, $cell [self::CELL_OPTIONS])
            && $cell [self::CELL_OPTIONS] [self::CELL_TYPE] == self::MULTI_CELL
        ) {
            $this->setFontStyle($cell);
            $numberOfLines = $this->pdf->numberOfLines($cellWidth, $cell[self::CELL_CONTENT]);
            $this->pdf->resetFont();
            return $numberOfLines;
        }
        return 1;
    }

    /**
     * @param array $cellContent
     * @throws Exception
     */
    protected function setFontStyle(array $cellContent)
    {
        $fontStyle = $this->getCellOptionOrDefault($cellContent[self::CELL_OPTIONS], self::FONT_STYLE);
        $fontSize = $this->getCellOptionOrDefault($cellContent[self::CELL_OPTIONS], self::FONT_SIZE);
        $this->pdf->SetFont(PdfWriter::DEFAULT_FONT_FAMILY, $fontStyle, $fontSize);
    }

    /**
     * @param array $cellOptions
     * @param $cellOptionKey
     * @return mixed
     */
    protected function getCellOptionOrDefault(array $cellOptions, $cellOptionKey)
    {
        if ($this->hasCellOption($cellOptions, $cellOptionKey)) {
            $this->validateCellOption($cellOptionKey, $cellOptions[$cellOptionKey]);
            return $cellOptions[$cellOptionKey];
        } else {
            return $this->getDefaultCellOption($cellOptionKey);
        }
    }

    /**
     * @param array $cellOptions
     * @param $cellOptionKey
     * @return bool
     */
    protected function hasCellOption(array $cellOptions, $cellOptionKey)
    {
        return array_key_exists($cellOptionKey, $cellOptions);
    }

    private function validateCellOption($cellOptionKey, $optionValue)
    {
        switch ($cellOptionKey) {
            case self::FONT_COLOR:
                if (!is_numeric($optionValue)) {
                    throw new Exception("Font color must be a value between 0(black) and 255(white)");
                }
                break;
            case self::CELL_ALIGN:
                if (!in_array($optionValue, [
                    self::ALIGN_CENTER,
                    self::ALIGN_LEFT,
                    self::ALIGN_RIGHT,
                ])
                ) {
                    throw new Exception("Cell align must be equal to PdfWriterComplexTable::ALIGN_CENTER == 'C' |
                         PdfWriterComplexTable::ALIGN_LEFT == 'L'|
                          PdfWriterComplexTable::ALIGN_RIGHT == 'R'");
                }
                break;
            case self::FONT_STYLE:
                if (!in_array($optionValue, [
                    self::BOLD_TEXT,
                    self::ITALIC_TEXT,
                    self::UNDERLINE_TEXT,
                ])
                ) {
                    throw new Exception("Font style must be equal to PdfWriterComplexTable::BOLD_TEXT == 'B' |
                         PdfWriterComplexTable::ITALIC_TEXT == 'I'|
                          PdfWriterComplexTable::UNDERLINE_TEXT == 'U'");
                }
                break;
            case self::CELL_BORDER:
                if (!in_array($optionValue, [
                    self::BORDER_NONE,
                    self::BORDER_ALL,
                    self::BORDER_LEFT,
                    self::BORDER_RIGHT,
                    self::BORDER_TOP,
                    self::BORDER_BOTTOM,
                ])
                ) {
                    throw new Exception("Border style must be equal to 
                            PdfWriterComplexTable::BORDER_NONE == 0 |
                            PdfWriterComplexTable::BORDER_ALL == 1|
                            PdfWriterComplexTable::BORDER_LEFT == 'L'|
                            PdfWriterComplexTable::BORDER_RIGHT == 'R'|
                            PdfWriterComplexTable::BORDER_TOP == 'T'|
                            PdfWriterComplexTable::BORDER_BOTTOM == 'B' ");
                }
                break;
            case self::CELL_TYPE:
                if (!in_array($optionValue, [
                    self::FIT_CELL,
                    self::MULTI_CELL,
                ])
                ) {
                    throw new Exception("Cell type must be equal to PdfWriterComplexTable::FIT_CELL |
                         PdfWriterComplexTable::MULTI_CELL ");
                }
                break;
            case self::CELL_FILL:
                if (!is_bool($optionValue)) {
                    throw new Exception("Fill style must be true or false");
                }
                break;
        }
    }

    private function getDefaultCellOption($cellOptionKey)
    {
        switch ($cellOptionKey) {
            case self::FONT_COLOR:
                return self::DEFAULT_FONT_COLOR;
                break;
            case self::CELL_ALIGN:
                return self::DEFAULT_CELL_ALIGN;
                break;
            case self::FONT_STYLE:
                return self::DEFAULT_FONT_STYLE;
                break;
            case self::FONT_SIZE:
                return self::DEFAULT_FONT_SIZE;
                break;
            case self::CELL_BORDER:
                return self::DEFAULT_BORDER;
                break;
            case self::CELL_TYPE:
                return self::DEFAULT_CELL_TYPE;
                break;
            case self::CELL_FILL:
                return false;
                break;
        }
    }

    protected function writeHeader($rowAlign = null)
    {
        if ($this->header) {
            $this->writingHeader = true;
            $this->pdf->SetFillColor($this->headerFillColor);
            $this->pdf->SetFont(PdfWriter::DEFAULT_FONT_FAMILY, self::BOLD_TEXT, PdfWriter::DEFAULT_FONT_SIZE);
            foreach ($this->header as $headerRow) {
                $this->writeRow($headerRow, $this->border, true, $rowAlign);
                $this->pdf->SetY($this->pdf->GetY() + $this->tableHeaderBottomMargin);
            }
            $this->pdf->SetFillColor(255);
            $this->pdf->resetFont();
            $this->writingHeader = false;
        }
    }

    /**
     * @param $row
     * @param $cellBorder
     * @param $fill
     * @param null $rowAlign
     */
    protected function writeRow($row, $cellBorder, $fill, $rowAlign = null)
    {
        $rowHeight = $this->getRowHeight($row);

        $commentHeight = 0;
        $rowHasComment = array_key_exists(self::ROW_COMMENT, $row);
        if ($rowHasComment) {
            $commentWidth = $this->width - $this->columnWidths[0];
            $this->pdf->SetFont(PdfWriter::DEFAULT_FONT_FAMILY, '', self::COMMENT_FONT_SIZE);
            $commentHeight = $this->pdf->numberOfLines($commentWidth, $row[self::ROW_COMMENT]) * $this->lineHeight;
            $this->pdf->resetFont();

            $rowContent = array_values($row[self::ROW_CONTENT]);
            $this->writeRowContent($rowContent, $cellBorder, $fill, $rowAlign, $rowHeight, $commentHeight);

            $initialX = $this->pdf->GetX();
            $this->pdf->SetX($this->leftMargin + $this->columnWidths[0]);
            $this->pdf->SetFont(PdfWriter::DEFAULT_FONT_FAMILY, '', self::COMMENT_FONT_SIZE);
            $this->pdf->SetTextColor(self::COMMENT_TEXT_COLOR);
            $this->pdf->MultiCell($commentWidth, $this->lineHeight, $row[self::ROW_COMMENT], $cellBorder,
                self::ALIGN_LEFT,
                $fill);
            $this->pdf->resetFont();
            $this->pdf->SetX($initialX);
        } else {
            $this->writeRowContent(array_values($row[self::ROW_CONTENT]), $cellBorder, $fill, $rowAlign, $rowHeight,
                $commentHeight);
        }
    }

    /**
     * @param $row
     * @param $cellBorder
     * @param $fill
     * @param $rowAlign
     * @param $rowHeight
     * @param $commentHeight
     */
    protected function writeRowContent($row, $cellBorder, $fill, $rowAlign, $rowHeight, $commentHeight)
    {
        for ($i = 0; $i < $this->numberOfColumns; ++$i) {
            $this->breakPageIfNeeded($rowHeight, $commentHeight);
            $lastCellInRow = $this->numberOfColumns - 1 == $i;
            $cellWidth = $this->columnWidths[$i];
            $cellContent = $row[$i];
            if (null === $rowAlign) {
                $cellAlign = empty($this->cellAlignments) ? self::DEFAULT_CELL_ALIGN : $this->cellAlignments[$i];
            } else {
                $cellAlign = $rowAlign;
            }
            if (is_array($cellContent)) {
                $this->writeMultiLinedCell($cellWidth, $rowHeight, $cellContent, $cellBorder, $cellAlign, $fill,
                    $lastCellInRow);
            } else {
                $this->writeCell($cellWidth, $rowHeight, $cellContent, $cellBorder, $cellAlign, $fill,
                    $lastCellInRow);
            }
        }
    }

    /**
     * @param $rowHeight
     * @param $commentHeight
     */
    protected function breakPageIfNeeded($rowHeight, $commentHeight)
    {
        if (!$this->writingHeader && $this->headerAfterPageBreak && $this->pdf->GetY() + $rowHeight + $commentHeight >= $this->pdf->getBBorder()) {
            $this->pdf->AddPage();
            $this->writeHeader();
        }
    }

    /**
     * @param $cellWidth
     * @param $rowHeight
     * @param array $cellContent
     * @param $cellBorder
     * @param $cellAlign
     * @param $fill
     * @param $lastCellInRow
     */
    protected function writeMultiLinedCell(
        $cellWidth,
        $rowHeight,
        array $cellContent,
        $cellBorder,
        $cellAlign,
        $fill,
        $lastCellInRow
    ) {
        $initialX = $this->pdf->GetX();
        $initialY = $this->pdf->GetY();

        if ($this->array_depth($cellContent) < 2) { // no cell options Ex.:['a','b','c','d','e','f','g']
            $cellHeight = $this->calculateCellHeight($cellWidth, $rowHeight, $cellContent);
            foreach ($cellContent as $subCellContent) {
                $this->pdf->fitCell($cellWidth, $cellHeight, $subCellContent, $cellBorder, 2,
                    $cellAlign, $fill);
            }
        } elseif ($this->array_depth($cellContent) == 2) {
            /* cell with options or no cell options Ex.:['a',['b'],[ PdfWriterComplexTable::CELL_CONTENT => 'c'],'d','e','f','g'] */
            $this->writeCustomCells($cellWidth, $rowHeight, $cellContent, $cellBorder, $cellAlign, $fill, $initialX);
        } else {
            /* multiple sub-cells with options */
            $cellHeight = $this->calculateCellHeight($cellWidth, $rowHeight, $cellContent);
            foreach ($cellContent as $subCell) {
                if (is_array($subCell)) {
                    $this->writeCustomCells($cellWidth, $cellHeight, $subCell, $cellBorder, $cellAlign, $fill,
                        $initialX);
                } else {
                    $this->pdf->fitCell($cellWidth, $cellHeight, $subCell, $cellBorder, 2,
                        $cellAlign, $fill);
                }
            }
        }

        if ($lastCellInRow) {
            $this->pdf->SetXY($this->leftMargin, $initialY + $rowHeight);
        } else {
            $this->pdf->SetXY($initialX + $cellWidth, $initialY);
        }
    }

    /**
     * @param $cellWidth
     * @param $rowHeight
     * @param array $cellContent
     * @return float|int
     */
    protected function calculateCellHeight($cellWidth, $rowHeight, array $cellContent)
    {
        $cellHeight = $rowHeight > ($this->countCellRows($cellContent, $cellWidth) * $this->lineHeight) ?
            $rowHeight / $this->countCellRows($cellContent, $cellWidth) : $this->lineHeight;
        return $cellHeight;
    }

    /**
     * @param $cellWidth
     * @param $rowHeight
     * @param array $cellContent
     * @param $cellBorder
     * @param $cellAlign
     * @param $fill
     * @param $initialX
     */
    protected function writeCustomCells(
        $cellWidth,
        $rowHeight,
        array $cellContent,
        $cellBorder,
        $cellAlign,
        $fill,
        $initialX
    ) {
        if (array_key_exists(self::CELL_OPTIONS, $cellContent)) {
            /* cell with options */

            $this->setTextColor($cellContent);
            $this->setCellAlign($cellContent, $cellAlign);
            $this->setFontStyle($cellContent);
            $this->setCellBorder($cellContent, $cellBorder);
            $this->setFillStyle($cellContent, $fill);
            $cellType = $this->getCellOptionOrDefault($cellContent [self::CELL_OPTIONS], self::CELL_TYPE);
            switch ($cellType) {
                case self::FIT_CELL:
                    $this->pdf->fitCell($cellWidth, $rowHeight, $cellContent [self::CELL_CONTENT], $cellBorder, 2,
                        $cellAlign, $fill);
                    break;
                case self::MULTI_CELL:
                    $this->pdf->MultiCell($cellWidth, $rowHeight, $cellContent [self::CELL_CONTENT], $cellBorder,
                        $cellAlign, $fill);
                    $this->pdf->SetX($initialX);
                    break;
            }
            $this->pdf->resetFont();
        } else {
            /* no cell options Ex.:['a',['b'],[ PdfWriterComplexTable::CELL_CONTENT => 'c'],'d','e','f','g'] */
            $cellHeight = $this->calculateCellHeight($cellWidth, $rowHeight, $cellContent);
            foreach ($cellContent as $subCellContent) {
                if (is_array($subCellContent)) {
                    $subCellContent = array_values($subCellContent)[0];
                }
                $this->pdf->fitCell($cellWidth, $cellHeight, $subCellContent, $cellBorder, 2,
                    $cellAlign, $fill);
            }
        }
    }

    /**
     * @param array $cellContent
     * @throws Exception
     */
    protected function setTextColor(array $cellContent)
    {
        $textColor = $this->getCellOptionOrDefault($cellContent [self::CELL_OPTIONS], self::FONT_COLOR);
        $this->pdf->SetTextColor($textColor);
    }

    /**
     * @param array $cellContent
     * @param $cellAlign
     * @throws Exception
     */
    protected function setCellAlign(array $cellContent, &$cellAlign)
    {
        if ($this->hasCellOption($cellContent[self::CELL_OPTIONS], self::CELL_ALIGN)) {
            $cellAlign = $this->getCellOptionOrDefault($cellContent[self::CELL_OPTIONS], self::CELL_ALIGN);
        }
    }

    /**
     * @param array $cellContent
     * @param $cellBorder
     * @throws Exception
     */
    protected function setCellBorder(array $cellContent, &$cellBorder)
    {
        if ($this->hasCellOption($cellContent[self::CELL_OPTIONS], self::CELL_BORDER)) {
            $cellBorder = $this->getCellOptionOrDefault($cellContent[self::CELL_OPTIONS], self::CELL_BORDER);
        }
    }

    /**
     * @param array $cellContent
     * @param $fill
     * @throws Exception
     */
    protected function setFillStyle(array $cellContent, &$fill)
    {
        if ($this->hasCellOption($cellContent[self::CELL_OPTIONS], self::CELL_FILL)) {
            $fill = $this->getCellOptionOrDefault($cellContent[self::CELL_OPTIONS], self::CELL_FILL);
        }
    }

    protected function writeContent()
    {
        for ($i = 0; $i < count($this->rows); ++$i) {
            $row = $this->rows [$i];
            $this->writeRow($row, $this->border, false);
            if ($this->rowSeparatorLines) {
                $this->pdf->drawSeparatorLineTo($this->leftMargin, $this->leftMargin + $this->width,
                    $this->pdf->GetY() + self::SEPARATOR_LINE_MARGIN, self::SEPARATOR_LINE_MARGIN);
            }
        }
    }
}
