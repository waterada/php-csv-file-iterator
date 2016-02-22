<?php
namespace waterada\CsvFileIterator;

/**
 * Class RecordLimitException
 *
 * レコードの上限に達した場合に発生する
 */
class RecordLimitException extends \Exception
{
    /** @var ReadingPosition */
    protected $_position;

    public function setPosition(ReadingPosition $position)
    {
        $this->_position = $position;
    }

    public function getPosition()
    {
        return $this->_position;
    }

    public function getCursor()
    {
        return $this->_position->cursor;
    }

    public function getRownum()
    {
        return $this->_position->rownum;
    }
}