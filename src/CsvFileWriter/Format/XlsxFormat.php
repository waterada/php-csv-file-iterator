<?php
namespace waterada\CsvFileWriter\Format;

use PHPExcel;
use PHPExcel_IOFactory;
use waterada\CsvFileWriter\Output;

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

        switch ($this->output->mode) {
//            case Format::OUTPUTMODE_DOWNLOAD:
//                if ($this->downloadHeaderFilename !== CsvFileWriter::SKIP_DOWNLOAD_HEADER) {

//                }
//                $objWriter->save('php://output');
//                break;
            case Output::MODE_PATH:
                $objWriter->save($this->path);
                break;
        }
    }
}