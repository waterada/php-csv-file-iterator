<?php
namespace waterada\CsvFileIterator\FileHandle;

/**
 * CsvFileHandler
 */
class CsvFileHandler extends FileHandler
{
    /**
     * @var resource OPEN中のCSVファイル
     */
    protected $_fh;

    /**
     * @var null|string 区切り文字
     */
    protected $_delim = null;

    /**
     * @inheritdoc
     */
    public function open($option)
    {
        $this->_fh = fopen($this->_filePath, 'r');
        if ($this->_fh === false) {
            throw new \LogicException("No such file or directory: " . $this->_filePath);
        }

        //ファイルの形式を（先頭部分を試し読みして）自動判別する
        $formatDetector = new CsvFormatDetector();
        $formatDetector->detect($this->_fh, $option['encoding'], $option['delimiter']);
        $formatDetector->applyReadingFilter($this->_fh);
        $this->_delim = $formatDetector->getDelimiter();
        $columnNames = $formatDetector->getColumnNames();
        return $columnNames;
    }

    /**
     * @inheritdoc
     */
    public function fgetcsv()
    {
        return fgetcsv($this->_fh, null, $this->_delim);
    }

    /**
     * @inheritdoc
     */
    public function close()
    {
        if ($this->_fh !== null) {
            fclose($this->_fh);
            $this->_fh = null;
        }
    }

    /**
     * @inheritdoc
     */
    public function rewind()
    {
        rewind($this->_fh);
        $this->fgetcsv(); //カラム行を飛ばす
    }
}