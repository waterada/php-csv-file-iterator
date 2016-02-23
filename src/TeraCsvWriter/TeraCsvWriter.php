<?php
namespace waterada\TeraCsvWriter;

use waterada\TeraCsvWriter\Format\CsvFormat;
use waterada\TeraCsvWriter\Format\Format;
use waterada\TeraCsvWriter\Format\XlsxFormat;
use waterada\TeraCsvWriter\Output\DownloadAfterMakingOutput;
use waterada\TeraCsvWriter\Output\DownloadStreamingOutput;
use waterada\TeraCsvWriter\Output\PathOutput;

/**
 * Class TeraCsvWriter
 * CSVもしくはTSVのファイルを１行すつ生成し、レスポンスへと流すためのクラス。
 * BOMの有無や文字コード(ただしSJIS, UTF-8, UTF-16LE のみ)、区切り文字を指定可能。
 * 1行目はラベル行となる。
 */
class TeraCsvWriter
{
    /** @var Format */
    public $format;

    const FORMAT_CSV = "csv";
    const FORMAT_TSV = "tsv";
    const FORMAT_XLSX = "xlsx";
    
    public function setFormat($format)
    {
        switch ($format) {
            case self::FORMAT_CSV:
                $this->format = new CsvFormat(",");
                break;
            case self::FORMAT_TSV:
                $this->format = new CsvFormat("\t");
                break;
            case self::FORMAT_XLSX:
                $this->format = new XlsxFormat();
                break;
        }
    }

    const ENC_UTF8 = 'UTF-8';
    const ENC_SJIS = 'SJIS-win';
    const ENC_UTF16LE = 'UTF-16LE';

    public function setEncoding($encoding)
    {
        if ($this->format instanceof CsvFormat) {
            $this->format->encoding = $encoding;
        } else {
            throw new \LogicException("Encoding cannot be set on XLSX.");
        }
    }

    public function setBom($needs = true)
    {
        if ($this->format instanceof CsvFormat) {
            $this->format->bom = $needs;
        } else {
            throw new \LogicException("Bom cannot be set on XLSX.");
        }
    }

    public function setLineBreak($br = "\n")
    {
        if ($this->format instanceof CsvFormat) {
            $this->format->br = $br;
        } else {
            throw new \LogicException("LineBreak cannot be set on XLSX.");
        }
    }

    public function needLineBreakAtEof($needs = true)
    {
        if ($this->format instanceof CsvFormat) {
            $this->format->withBrAtEOF = $needs;
        } else {
            throw new \LogicException("LineBreak at EOF cannot be set on XLSX.");
        }
    }

    public function setColumns($columns = null)
    {
        $this->format->columns = $columns;
    }

    const EXISTING_ERROR = 'e';
    const EXISTING_OVERWRITE = 'w';
    //const EXISTING_APPEND = 'a';

    public function setOutputFilePath($path, $existingStrategy = self::EXISTING_ERROR)
    {
        $this->format->output = new PathOutput($path, $existingStrategy);

        //既存ファイルの扱い
        if (file_exists($path)) {
            switch ($existingStrategy) {
                case self::EXISTING_OVERWRITE:
                    //削除する。作成後ダウンロードの場合、a で開くので。
                    unlink($path);
                    break;
                default:
                    throw new \Exception('FileAlreadyExists:' . $path);
            }
        }
    }

    public function setOutputDownloadStreaming()
    {
        $this->format->output = new DownloadStreamingOutput();
    }

    /**
     * @param WritingPosition $position
     */
    public function setOutputDownloadAfterMaking($position)
    {
        $this->format->output = new DownloadAfterMakingOutput($position);
    }

    public function begin()
    {
        $this->format->begin();
    }

    public function outputLine($data)
    {
        $this->format->output->incrementPosition();
        $this->format->outputLine($data);
    }

    /**
     * @param bool|null $isFinished
     */
    public function finish($isFinished = null)
    {
        $this->format->output->setFinished($isFinished);
        $this->format->finish();
    }

    public function flow()
    {
        return new FormatFlow($this);
    }

}
