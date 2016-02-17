<?php
namespace waterada\CsvFileIterator;

use waterada\CsvFileIterator\ColumnMapper\ColumnMapper;
use waterada\CsvFileIterator\ColumnMapper\IndexedColumnMapper;
use waterada\CsvFileIterator\FileHandler\FileHandler;

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
     * @var FileHandler ファイルを具体的に操作するハンドラ
     */
    protected $_fileHandle;

    /**
     * @var ColumnMapper 列名を列Indexに読み替えるマップ
     */
    protected $_columnMapper;

    /**
     * @var null|int $limit 一度のリクエストで読む最大レコード数。これを過ぎたら強制的に foreach が終了する。null なら制限しない。
     */
    protected $_limit;

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
     * @param null|Position $position
     * @return Record[]
     */
    public function iterate($position = null)
    {
        //開始位置をセット
        $this->_fileHandle->rewind($position);

        $count = 0;
        while (($values = $this->__readLine($count)) !== false) {
            $this->_fileHandle->incrementRownum();
            $count++;
            if ($this->_columnMapper->meetsCondition($values) == false) {
                continue;
            }
            yield $this->_fileHandle->getRownum() => new Record($this->_columnMapper, $values);
        }
    }

    /**
     * @param int $count
     * @return array|false
     * @throws RecordLimitException
     */
    private function __readLine($count)
    {
        if (isset($this->_limit) && $this->_limit < $count + 1) { //制限設定があるなら次(+1)の行が制限を超えるなら終了とする
            $e = new RecordLimitException();
            $position = $this->_fileHandle->suspend();
            $e->setPosition($position);
            throw $e;
        }
        return $this->_fileHandle->fgetcsv();
    }

    /**
     * @param null|int $limit 一度のリクエストで読む最大レコード数。これを過ぎたら強制的に foreach が終了する。null なら制限しない。
     */
    public function setLimit($limit)
    {
        $this->_limit = $limit;
    }

    /**
     * @return int 最大
     */
    public function getMaxCursor()
    {
        return $this->_fileHandle->getMaxCursor();
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