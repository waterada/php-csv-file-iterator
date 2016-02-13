<?php
namespace waterada\CsvFileIterator\FileHandler;

use waterada\CsvFileIterator\Position;

/**
 * FileHandler
 *
 * ファイル操作の基本部分を担うクラス。複雑なロジックは持たない。CSVやExcelなどの差分を吸収するためのもの。
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

    /**
     * @var Position 現在位置を指し示す
     */
    protected $_position;

    public function __construct($filePath)
    {
        $this->_filePath = $filePath;
        $this->_position = new Position();
        $this->_limit = null;
    }

    /**
     * ファイルを開く
     *
     * @param $option
     * @return array カラム名の配列。データの並び順に準じる
     */
    abstract public function open($option);

    /**
     * 1行分のデータを読む。このパフォーマンスが全体のパフォーマンスに大きくかかわるので重たいロジックは載せないこと。
     *
     * @return array|false １行分のデータ。終端なら false を返す
     */
    abstract public function fgetcsv();

    /**
     * これをしないとポインタを掴み続けて、 Permission denied になりファイルが消せない（たぶんgcのタイミングがあとにあるから？）
     */
    abstract public function close();

    /**
     * 先頭位置に戻す。先頭位置とはカラム行の次の行のこと。
     *
     * @param null|Position $position
     */
    abstract public function rewind($position = null);

    /**
     * 一時停止させ、復元に必要な情報を返す
     *
     * @return Position
     */
    abstract public function suspend();

    /**
     * 現在の行番号を返す
     *
     * @return int
     */
    public function getRownum()
    {
        return $this->_position->rownum;
    }

    /**
     * 現在の行番号を+1する
     *
     * @return int
     */
    public function incrementRownum()
    {
        return $this->_position->rownum++;
    }

    /**
     * 末尾の位置を返す
     *
     * @return int
     */
    abstract public function getMaxCursor();
}