<?php
/**
 * Created by IntelliJ IDEA.
 * User: Csaba Hete
 * Date: 2017.01.07.
 * Time: 0:24
 */

namespace PdfWriter;

interface PdfTable
{
    /**
     * Sets the table header
     * @param array $columns
     */
    public function setHeader(array $columns);

    /**
     * Adds a row to the table's content
     * @param array $row
     */
    public function addRow(array $row);

    /**
     * Writes the table to the pdf
     */
    public function writeToPage();
}
