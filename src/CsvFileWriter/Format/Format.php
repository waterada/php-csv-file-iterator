<?php
namespace waterada\CsvFileWriter\Format;

use waterada\CsvFileWriter\Output\Output;

abstract class Format
{
    public $columns = null;

    /** @var Output */
    public $output = null;

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
