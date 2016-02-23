<?php
namespace waterada\TeraCsvWriter\Output;

abstract class Output
{
    abstract public function getPath();

    abstract public function getFileHandle();

    public function skipColumnsLine()
    {
        return false;
    }

    public function incrementPosition()
    {
    }

    /**
     * @param null|bool $isFinished
     */
    public function setFinished($isFinished) {
    }
}