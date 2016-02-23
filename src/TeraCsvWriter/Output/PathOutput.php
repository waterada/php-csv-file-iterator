<?php
namespace waterada\TeraCsvWriter\Output;

class PathOutput extends Output
{
    private $path = null;
    private $existingStrategy = null;

    public function __construct($path, $existingStrategy)
    {
        $this->path = $path;
        $this->existingStrategy = $existingStrategy;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getFileHandle()
    {
        return fopen($this->getPath(), 'w');
    }
}