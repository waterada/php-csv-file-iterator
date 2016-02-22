<?php
use waterada\CsvFileIterator\CsvFileIterator;
use waterada\CsvFileWriter\CsvFileWriterFlow;
use waterada\CsvFileWriter\EncodingFlow;
use waterada\CsvFileWriter\HeaderFlow;
use waterada\CsvFileWriter\LineBreakFlow;
use waterada\CsvFileWriter\OutputToFlow;

class CsvFileWriterTest extends PHPUnit_Framework_TestCase
{
    public $path;

    public function tearDown()
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }

    private function __tempfile($ext = null)
    {
        $this->path = tempnam('/tmp', 'test');
        unlink($this->path);
        if (isset($ext)) {
            $this->path .= $ext;
        }
        return $this->path;
    }

    const BOM_UTF8 = "\xef\xbb\xbf";

    public function cases_TSV_CSV()
    {
        $cases1 = [
            'CSV' => [
                function (CsvFileWriterFlow $flow) {
                    return $flow->CSV();
                },
                function ($expected) {
                    return $expected;
                },
            ],
            'TSV' => [
                function (CsvFileWriterFlow $flow) {
                    return $flow->TSV();
                },
                function ($expected) {
                    return preg_replace('/,/', "\t", $expected);
                },
            ],
        ];
        $cases2 = [
            'UTF8 (BOM無し)' => [
                function (EncodingFlow $flow) {
                    return $flow->UTF8()->withoutBOM();
                },
                function ($expected) {
                    return $expected;
                },
            ],
            'UTF8 (BOM有り)' => [
                function (EncodingFlow $flow) {
                    return $flow->UTF8()->BOM();
                },
                function ($expected) {
                    return self::BOM_UTF8 . $expected;
                },
            ],
            'SJIS' => [
                function (EncodingFlow $flow) {
                    return $flow->SJIS();
                },
                function ($expected) {
                    return mb_convert_encoding($expected, 'SJIS-win', 'UTF-8');
                },
            ],
            'UTF16LE' => [
                function (EncodingFlow $flow) {
                    return $flow->UTF16LE();
                },
                function ($expected) {
                    return "\xff\xfe" . mb_convert_encoding($expected, 'UTF-16LE', 'UTF-8');
                },
            ],
        ];
        $cases3 = [
            'ファイル出力できる' => [
                function (LineBreakFlow $flow) {
                    return $flow->LF()->WITH_BR_at_EOF()->columns(["あ", "い"]);
                },
                "あ,い\nか,き\nさ,し\n",
            ],
            '1行目のカラムを無しにできる' => [
                function (LineBreakFlow $flow) {
                    return $flow->LF()->WITH_BR_at_EOF()->noColumnsLine();
                },
                "か,き\nさ,し\n",
            ],
            'EOFの改行を無しにできる' => [
                function (LineBreakFlow $flow) {
                    return $flow->LF()->REMOVE_BR_at_EOF()->columns(["あ", "い"]);
                },
                "あ,い\nか,き\nさ,し",
            ],
            '改行コードをCRLFにできる' => [
                function (LineBreakFlow $flow) {
                    return $flow->CRLF()->WITH_BR_at_EOF()->columns(["あ", "い"]);
                },
                "あ,い\r\nか,き\r\nさ,し\r\n",
            ],
            '改行コードをCRLFにしたうえで、EOFの改行は無しにできる' => [
                function (LineBreakFlow $flow) {
                    return $flow->CRLF()->REMOVE_BR_at_EOF()->columns(["あ", "い"]);
                },
                "あ,い\r\nか,き\r\nさ,し",
            ],
        ];
        $cases = [];
        foreach ($cases1 as $key1 => list($flow1, $exp1)) {
            foreach ($cases2 as $key2 => list($flow2, $exp2)) {
                foreach ($cases3 as $key3 => list($flow3, $exp3)) {
                    $cases[sprintf('%s x %s x %s', $key1, $key2, $key3)] = [
                        $flow3($flow2($flow1(new CsvFileWriterFlow()))),
                        $exp2($exp1($exp3)),
                    ];
                }
            }
        }
        return $cases;
    }

    /**
     * @dataProvider cases_TSV_CSV
     * @param OutputToFlow $flow
     * @param string $expected
     */
    public function test_CSV_TSV($flow, $expected)
    {
        //[UTF8] あ e38182  い e38184  か e3818b  き e3818d  さ e38195  し e38197
        $path = $this->__tempfile();
        $out = $flow->toPath($path)->errorIfExists()->begin();
        $out->outputLine(["か", "き"]);
        $out->outputLine(["さ", "し"]);
        $out->finish();
        $actual = file_get_contents($path);
        $this->assertEquals($expected, $actual, sprintf("- %s\n+ %s", bin2hex($expected), bin2hex($actual)));
    }

    public function cases_XLSX_header()
    {
        return [
            [true],
            [false],
        ];
    }

    private function __getActualContents($path)
    {
        $csv = new CsvFileIterator($path);
        $actual = $csv->toArrayWithColumns();
        return $actual;
    }

    /**
     * @dataProvider cases_XLSX_header
     * @param bool $withHeader
     */
    public function test_XLSX($withHeader)
    {
        $path = $this->__tempfile(".xlsx");
        $flow = (new CsvFileWriterFlow());
        if ($withHeader) {
            $out = $flow->XLSX()->columns(["あ", "い"])->toPath($path)->errorIfExists()->begin();
        } else {
            $out = $flow->XLSX()->noColumnsLine()->toPath($path)->errorIfExists()->begin();
            $out->outputLine(["あ", "い"]);
        }
        $out->outputLine(["か", "き"]);
        $out->outputLine(["さ", "し"]);
        $out->finish();
        $actual = $this->__getActualContents($path);
        $this->assertEquals([["あ", "い"], ["か", "き"], ["さ", "し"]], $actual);
    }

    public function cases_csv_or_excel()
    {
        return [
            'CSV' => [(new CsvFileWriterFlow())->CSV()->UTF8()->withoutBOM()->LF()->WITH_BR_at_EOF(), null],
            'XLSX' => [(new CsvFileWriterFlow())->XLSX(), '.xlsx'],
        ];
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage FileAlreadyExists:
     * @dataProvider cases_csv_or_excel
     * @param HeaderFlow $flow
     */
    public function test_errorIfExists($flow)
    {
        $path = $this->__tempfile();
        touch($path);
        $flow->noColumnsLine()->toPath($path)->errorIfExists();
    }

    /**
     * @dataProvider cases_csv_or_excel
     * @param HeaderFlow $flow
     */
    public function test_overwriteIfExists($flow)
    {
        $path = $this->__tempfile();
        touch($path);
        $out = $flow->noColumnsLine()->toPath($path)->overwriteIfExists()->begin();
        $out->outputLine(["あ"]);
        $out->finish();
        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));
    }

    /**
     * @dataProvider cases_csv_or_excel
     * @param HeaderFlow $flow
     * @param string $ext
     */
    public function test_only_columns($flow, $ext)
    {
        $path = $this->__tempfile($ext);
        $out = $flow->columns(["あ", "い"])->toPath($path)->errorIfExists()->begin();
        $out->finish();
        $actual = $this->__getActualContents($path);
        $this->assertEquals([["あ", "い"]], $actual);
    }
//
//    /**
//     * @dataProvider cases_csv_or_excel
//     * @param HeaderFlow $flow
//     * @param string $ext
//     */
//    public function test_download_skip_header($flow, $ext)
//    {
//        ob_start();
//        $out = $flow->columns(["あ"])->toDownload()->skipDownloadHeader()->begin();
//        $out->finish();
//        $from_output = ob_get_contents();
//        ob_end_clean();
//        $this->assertNotEquals("", $from_output, "obで取得可能");
//
//        //一時的にファイルに吐いて確認
//        $path = $this->__tempfile($ext);
//        file_put_contents($path, $from_output);
//        $actual = $this->__getActualContents($path);
//        $this->assertEquals([["あ"]], $actual);
//    }
//
//    /**
//     * @dataProvider cases_csv_or_excel
//     * @param HeaderFlow $flow
//     * @param string $ext
//     */
//    public function test_download_with_header($flow, $ext)
//    {
//        ob_start();
//        $out = $flow->columns(["あ"])->toDownload()->outputDownloadHeader("ああ.dat")->begin();
//        $out->finish();
//        $from_output = ob_get_contents();
//        ob_end_clean();
//        $this->assertNotEquals("", $from_output, "obで取得できていること");
//
//        //一時的にファイルに吐いて確認
//        $path = $this->__tempfile($ext);
//        file_put_contents($path, $from_output);
//        $actual = $this->__getActualContents($path);
//        $this->assertEquals([["あ"]], $actual);
//    }

//todo: Excelで分割ダウンロードしようとしたら、前のファイルがある時点でエラーにする
}