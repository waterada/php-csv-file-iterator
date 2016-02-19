<?php
namespace waterada\CsvFileWriter;

use waterada\CsvFileIterator\WritingPosition;

class Output
{
    const MODE_PATH = "path";
    const MODE_DOWNLOAD_STREAMING = "download_streaming";
    const MODE_DOWNLOAD_AFTER_MAKING = "download_after_making";

    public $mode = null;
    public $path = null;
    public $existingStrategy = null;
    public $filename = null;

    /** @var null|WritingPosition */
    public $position = null;

    public function __construct($mode)
    {
        $this->mode = $mode;
    }

    /**
     * @param null|bool $isFinished
     */
    public function setFinished($isFinished) {
        if (isset($isFinished) && isset($this->position)) {
            $this->position->isFinished = $isFinished;
        }
    }

    public function incrementPosition()
    {
        if (isset($this->position)) {
            $this->position->rownum++;
        }
    }

    public function getPath()
    {
        if (isset($this->position)) {
            return $this->position->getPath();
        } else {
            return $this->path;
        }
    }
}