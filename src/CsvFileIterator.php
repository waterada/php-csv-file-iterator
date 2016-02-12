<?php
namespace waterada\CsvFileIterator;

use waterada\CsvFileIterator\ColumnMapper\ColumnMapper;
use waterada\CsvFileIterator\ColumnMapper\IndexedColumnMapper;
use waterada\CsvFileIterator\FileHandle\FileHandler;

/**
 * Class CsvFileIterator
 * CSVもしくはTSVのファイルをIteratorとして読み込むためのクラス。
 * BOMや文字コード(ただしSJIS, UTF-8, UTF-16LE のみ)、区切り文字は(1行目に\tを含んでいるかどうかで)自動判別する。
 * 1行目のラベル行は改行を含まないことが前提。
 * 文字コードはラベルの次の行から20行読んで判別する。
 */
class CsvFileIterator
{
    /**
     * ファイルを具体的に操作するハンドラ
     *
     * @var FileHandler
     */
    protected $_fileHandle;

    /**
     * 列名を列Indexに読み替えるマップ
     *
     * @var ColumnMapper
     */
    protected $_columnMapper;

    /**
     * @param string $filePath
     * @param string|null $encoding - nullなら自動判別
     * @param string|null $delimiter - nullなら自動判別
     */
    public function __construct($filePath, $encoding = null, $delimiter = null)
    {
        $this->_fileHandle = FileHandler::createFileHandle($filePath);
        $columnNames = $this->_fileHandle->open(['encoding' => $encoding, 'delimiter' => $delimiter]);

        //カラムマッパー(カラム名と値の位置の紐付け)を注入
        $this->_columnMapper = new IndexedColumnMapper($columnNames);
    }

    public function __destruct()
    {
        $this->close();
    }

    public function getColumnMapper()
    {
        return $this->_columnMapper;
    }

    /**
     * @return Record[]
     */
    public function iterate()
    {
        //現在処理中の行番号(0～) ※0 ならまだ未処理。1はラベル行。データは2～。除外するレコードも行としてカウントされる。
        $rowNum = 0;

        $this->_fileHandle->rewind();
        while (($values = $this->_fileHandle->fgetcsv()) !== false) {
            $rowNum++;
            if ($this->_columnMapper->meetsCondition($values) == false) {
                yield $rowNum => new Record($this->_columnMapper, $values);
            }
        }
    }

    /**
     * これをしないとポインタを掴み続けて、 Permission denied になりファイルが消せない（たぶんgcのタイミングがあとにあるから？）
     */
    public function close()
    {
        $this->_fileHandle->close();
    }

    /**
     * ローデータ中にデータが存在する場合 True を返す
     *
     * @return bool
     */
    public function isEmpty()
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        foreach ($this->iterate() as $record) {
            return false;
        }
        return true;
    }
}