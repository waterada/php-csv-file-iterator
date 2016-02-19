<?php
namespace waterada\CsvFileWriter\Format;

use waterada\CsvFileWriter\CsvFileWriter;
use waterada\CsvFileWriter\Output;

abstract class Format
{
    public $columns = null;

    /** @var Output */
    public $output = null;

    public $existingStrategy = CsvFileWriter::EXISTING_ERROR;

    public $filename = null;

    public function begin()
    {
        $this->_outputColumnsLine();
    }

    protected function _outputColumnsLine()
    {
        if (isset($this->columns)) {
            $this->outputLine($this->columns, true);
        }
    }

    abstract public function outputLine($data, $isHeader = false);

    abstract public function finish();
}
