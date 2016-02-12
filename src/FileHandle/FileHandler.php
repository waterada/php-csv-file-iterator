<?php
namespace waterada\CsvFileIterator\FileHandle;

/**
 * FileHandler
 */
abstract class FileHandler
{
    public static function createFileHandle($filePath)
    {
        $fileInfo = new \SplFileInfo($filePath);
        switch ($fileInfo->getExtension()) {
            case 'xlsx':
                $fileHandle = new ExcelFileHandler($filePath);
                break;
            default:
                $fileHandle = new CsvFileHandler($filePath);
        }
        return $fileHandle;
    }

    /**
     * @var string ファイルのパス
     */
    protected $_filePath;

    public function __construct($filePath)
    {
        $this->_filePath = $filePath;
    }

    /**
     * ファイルを開く
     *
     * @param $option
     * @return array カラム名の配列。データの並び順に準じる
     */
    abstract public function open($option);

    /**
     * @return array|false １行分のデータ。終端なら false を返す
     */
    abstract public function fgetcsv();

    /**
     * これをしないとポインタを掴み続けて、 Permission denied になりファイルが消せない（たぶんgcのタイミングがあとにあるから？）
     */
    abstract public function close();

    /**
     * 先頭位置に戻す。先頭位置とはカラム行の次の行のこと。
     */
    abstract public function rewind();
}