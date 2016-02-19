<?php
namespace waterada\CsvFileWriter\Format;

use PHPExcel;
use PHPExcel_IOFactory;

class XlsxFormat extends Format
{
    private $data = [];

    public function outputLine($data, $isHeader = false)
    {
        $this->data[] = $data;
    }

    public function finish()
    {
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0)->fromArray($this->data);

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save($this->output->getPath());
    }
}