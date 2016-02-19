<?php
namespace waterada\CsvFileWriter;

class Flow
{
    /** @var CsvFileWriter */
    protected $_x;

    protected $_args;

    /**
     * Flow constructor.
     *
     * @param CsvFileWriter $x
     */
    public function __construct(CsvFileWriter $x)
    {
        $this->_x = $x;
        $this->_args = func_get_args();
        array_shift($this->_args);
    }
}

class CsvFileWriterFlow extends FormatFlow
{
    public function __construct()
    {
        parent::__construct(new CsvFileWriter());
    }
}

class FormatFlow extends Flow
{
    public function CSV()
    {
        $this->_x->setFormat(CsvFileWriter::FORMAT_CSV);
        return new EncodingFlow($this->_x);
    }

    public function TSV()
    {
        $this->_x->setFormat(CsvFileWriter::FORMAT_TSV);
        return new EncodingFlow($this->_x);
    }

    public function XLSX()
    {
        $this->_x->setFormat(CsvFileWriter::FORMAT_XLSX);
        return new HeaderFlow($this->_x);
    }
}

class EncodingFlow extends Flow
{
    public function UTF8()
    {
        $this->_x->setEncoding(CsvFileWriter::ENC_UTF8);
        return new Utf8BomFlow($this->_x);
    }

    public function UTF16LE()
    {
        $this->_x->setEncoding(CsvFileWriter::ENC_UTF16LE);
        return new LineBreakFlow($this->_x);
    }

    public function SJIS()
    {
        $this->_x->setEncoding(CsvFileWriter::ENC_SJIS);
        return new LineBreakFlow($this->_x);
    }
}

class Utf8BomFlow extends Flow
{
    public function BOM()
    {
        $this->_x->setBom();
        return new LineBreakFlow($this->_x);
    }

    public function withoutBOM()
    {
        $this->_x->setBom(false);
        return new LineBreakFlow($this->_x);
    }
}

class LineBreakFlow extends Flow
{
    public function CRLF()
    {
        $this->_x->setLineBreak("\r\n");
        return new LineBreakAtEndFlow($this->_x);
    }

    public function LF()
    {
        $this->_x->setLineBreak("\n");
        return new LineBreakAtEndFlow($this->_x);
    }
}

class LineBreakAtEndFlow extends Flow
{
    public function REMOVE_BR_at_EOF()
    {
        $this->_x->needLineBreakAtEof(false);
        return new HeaderFlow($this->_x);
    }

    public function WITH_BR_at_EOF()
    {
        $this->_x->needLineBreakAtEof(true);
        return new HeaderFlow($this->_x);
    }
}

class HeaderFlow extends Flow
{
    public function noColumnsLine()
    {
        $this->_x->setColumns(null);
        return new OutputToFlow($this->_x);
    }

    public function columns($columns)
    {
        $this->_x->setColumns($columns);
        return new OutputToFlow($this->_x);
    }
}

class OutputToFlow extends Flow
{
    public function toPath($path)
    {
        return new ExistingFileFlow($this->_x, $path);
    }

    public function toDownloadStreming()
    {
        $this->_x->setOutputDownloadStreaming();
        return new BeginFlow($this->_x);
    }

    /**
     * @param WritingPosition $position
     * @return BeginFlow
     */
    public function toDownloadAfterMaking($position)
    {
        $this->_x->setOutputDownloadAfterMaking($position);
        return new BeginFlow($this->_x);
    }
}

class ExistingFileFlow extends Flow
{
    public function errorIfExists()
    {
        $this->_x->setOutputFilePath($this->_args[0], CsvFileWriter::EXISTING_ERROR);
        return new BeginFlow($this->_x);
    }

    public function overwriteIfExists()
    {
        $this->_x->setOutputFilePath($this->_args[0], CsvFileWriter::EXISTING_OVERWRITE);
        return new BeginFlow($this->_x);
    }

//追記は、CSV/TSV x File でないと意味なし。BOMとヘッダーは初回のみ必要。この辺の実行が必要
//    public function appendIfExists()
//    {
//        $this->_x->outputTo->existingStrategy = OutputTo::EXISTING_APPEND;
//        return new Outputable($this->_x);
//    }
}

class BeginFlow extends Flow
{
    public function begin()
    {
        $this->_x->begin();
        return new Outputable($this->_x);
    }
}

class Outputable extends Flow //型をどこかに記載することを考慮して末尾のFlowはつけない
{
    public function outputLine($data)
    {
        $this->_x->outputLine($data);
    }

    /**
     * @param bool|null $isFinished
     */
    public function finish($isFinished = null)
    {
        $this->_x->finish($isFinished);
    }
}

//$csv = new StartFlow(new CsvFileWriter());
//$output = $csv
//    ->CSV()
//    ->UTF16LE()
//    ->LF()->WITH_BR_at_EOF()
//    ->header($columns)
//    ->toPath($path)->errorIfExists()
//    ->begin();
//$output = (new CsvFileWriter())->flow()
//    ->CSV()
//    ->UTF8()->withoutBOM()
//    ->LF()->WITH_BR_at_EOF()
//    ->header($columns)
//    ->toPath($path)->errorIfExists()
//    ->begin();
//foreach ($wholeData as $data) {
//    $output->outputLine($data);
//}
//$output->finish();
