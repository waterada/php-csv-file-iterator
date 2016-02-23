<?php
namespace waterada\TeraCsvWriter\Output;

class DownloadStreamingOutput extends Output
{
    public function __construct()
    {
    }

    public function getPath()
    {
        return "php://output";
    }

    public function getFileHandle()
    {
        return fopen($this->getPath(), 'w');
    }
}