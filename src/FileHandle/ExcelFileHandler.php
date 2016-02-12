<?php
namespace waterada\CsvFileIterator\FileHandle;

/**
 * ExcelFileHandler
 *
 * @property string $_filePath       : 読み込むファイルパス。
 * @property resource $_fh              : OPEN中のCSVファイル
 */
class ExcelFileHandler extends FileHandler
{
    protected $_data;
    protected $_position = 0;

    public function open($option)
    {
        $reader = \PHPExcel_IOFactory::createReader('Excel2007');
        $book = $reader->load($this->_filePath);
        $sheet = $book->getActiveSheet();
        //$this->_values = $sheet->toArray();
        $this->_data = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false); // Loop all cells, even if it is not set
            $values = [];
            foreach ($cellIterator as $cell) {
                /** @var \PHPExcel_Cell $cell */
                if (isset($cell)) {
                    //$values[] = $cell->getValue(); //入力値そのまま(数式のまま。数値は、1234.0)
                    $values[] = $cell->getFormattedValue(); //計算・書式適用後(数値は、1,234 のようなカンマ入るので注意)
                } else {
                    $values[] = "";
                }
            }
            $this->_data[] = $values;
        }

        //末尾の空行取る
        for ($i = count($this->_data) - 1; $i >= 0; $i--) {
            if (implode("", $this->_data[$i]) === "") {
                array_pop($this->_data);
            } else {
                break;
            }
        }

        //１行目をラベルとして獲得
        $columnNames = $this->_data[0];

        //ラベル末尾の空欄取る
        for ($i = count($columnNames) - 1; $i >= 0; $i--) {
            if ($columnNames[$i] === "") {
                array_pop($columnNames);
            } else {
                break;
            }
        }

        //値末尾の空欄取る
        array_walk($this->_data, function (&$row) use ($columnNames) {
            $row = array_slice($row, 0, count($columnNames));
        });

        return $columnNames;
    }

    public function fgetcsv()
    {
        if ($this->_position < count($this->_data)) {
            $row = $this->_data[$this->_position];
            $this->_position++;
        } else {
            $row = false;
        }

        return $row;
    }

    /**
     * これをしないとポインタを掴み続けて、 Permission denied になりファイルが消せない（たぶんgcのタイミングがあとにあるから？）
     */
    public function close()
    {
        unset($this->_data);
    }

    public function rewind()
    {
        $this->_position = 1; //カラム行(0)は飛ばす
    }
}