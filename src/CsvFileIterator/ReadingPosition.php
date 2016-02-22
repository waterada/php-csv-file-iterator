<?php
namespace waterada\CsvFileIterator;

/**
 * Class ReadingPosition
 *
 * 読み込みを一旦中断して、再開したい場合に、中断位置をセッションなどに保存する際の単位となるクラス。
 */
class ReadingPosition
{
    /** @var int 現在処理中の行番号(0～) ※0 ならまだ未処理。1はラベル行。データは2～。除外するレコードも行としてカウントされる。 */
    public $rownum;

    /** @var int */
    public $cursor;

    public function __construct()
    {
        $this->rownum = 0;
        $this->cursor = 0;
    }
}