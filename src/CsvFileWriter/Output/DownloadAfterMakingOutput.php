<?php
namespace waterada\CsvFileWriter\Output;

use waterada\CsvFileWriter\WritingPosition;

class DownloadAfterMakingOutput extends Output
{
    /** @var WritingPosition */
    private $position;

    public function __construct($position)
    {
        $this->position = $position;
    }

    public function getPath()
    {
        return $this->position->getPath();
    }

    public function getFileHandle()
    {
        if ($this->position->isMaking()) {
            return fopen($this->getPath(), 'a');
        } else {
            return fopen($this->getPath(), 'w');
        }
    }

    public function skipColumnsLine()
    {
        return ($this->position->getRownum() > 0);
    }

    public function incrementPosition()
    {
        $this->position->rownum++;
    }

    /**
     * @param null|bool $isFinished
     */
    public function setFinished($isFinished) {
        $this->position->isFinished = $isFinished;
    }
}