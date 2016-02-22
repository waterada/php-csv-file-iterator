<?php
use waterada\CsvFileIterator\CsvFileIterator;
use waterada\CsvFileIterator\RecordLimitException;
use waterada\CsvFileWriter\WritingPosition;
use waterada\CsvFileOnWeb\CsvFileOnWeb;
use waterada\CsvFileWriter\CsvFileWriterFlow;

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * @property PHPUnit_Framework_MockObject_MockObject|CsvFileOnWeb $onWeb
 * @property array $session
 * @property array $actual
 * @property string[] paths
 */
class CsvWriterControllerTest extends PHPUnit_Framework_TestCase
{
    const RECORD_LIMIT = 3;

    private $paths = [];

    public function setUp()
    {
        $this->onWeb = null;
        $this->session = [];
        $this->actual = [];
        $this->paths = [];
    }

    public function tearDown()
    {
        foreach ($this->paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function __destruct()
    {
        $this->tearDown();
    }

    private function __mock_OnWeb()
    {
        $this->onWeb = $this->getMockBuilder('waterada\CsvFileOnWeb\CsvFileOnWeb')->setMethods([
            'ajaxHeader',
            'ajaxBody',
            'downloadHeader',
            //'downloadBody',
        ])->getMock();
    }

    //--------------------

    public function cases_read_CSV_XLSX()
    {
        return [
            [
                FileFabricate::fromString("ID\n1\n2\n3\n4\n5\n6\n7\n8\n")->getPath(),
                strlen("ID\n1\n2\n3\n4\n5\n6\n7\n8\n"),
                strlen("ID\n1\n2\n3\n"),
                strlen("ID\n1\n2\n3\n4\n5\n6\n"),
            ],
            [
                realpath('test_suspend_8.xlsx'),
                count(['ID']) + count(range(1, 8)),
                count(['ID']) + count(range(1, 3)),
                count(['ID']) + count(range(1, 6)),
            ],
        ];
    }

    /**
     * @dataProvider cases_read_CSV_XLSX
     * @param string $path
     * @param int $max
     * @param int $cur1
     * @param int $cur2
     */
    public function test_read($path, $max, $cur1, $cur2)
    {
        $this->actual = [];

        //初回アクセス
        $this->__mock_OnWeb();
        $this->onWeb->expects($this->once())->method('ajaxHeader');
        $this->onWeb->expects($this->once())->method('ajaxBody')->with([
            'isFinished' => false,
            'cur' => $cur1,
            'max' => $max,
        ]);
        $this->read($path);
        $this->assertEquals(["1", "2", "3"], $this->actual);

        //２回目アクセス
        $this->__mock_OnWeb();
        $this->onWeb->expects($this->once())->method('ajaxHeader');
        $this->onWeb->expects($this->once())->method('ajaxBody')->with([
            'isFinished' => false,
            'cur' => $cur2,
            'max' => $max,
        ]);
        $this->read($path);
        $this->assertEquals(["1", "2", "3", "4", "5", "6"], $this->actual);

        //３回目アクセス
        $this->__mock_OnWeb();
        $this->onWeb->expects($this->once())->method('ajaxHeader');
        $this->onWeb->expects($this->once())->method('ajaxBody')->with([
            'isFinished' => true,
        ]);
        $this->read($path);
        $this->assertEquals(["1", "2", "3", "4", "5", "6", "7", "8"], $this->actual);
    }

    public function read($path)
    {
        $position = $this->__session_get("position");
        $iterator = new CsvFileIterator($path);
        try {
            foreach ($iterator->iterate($position, self::RECORD_LIMIT) as $rownum => $record) {
                $this->actual[] = $record->get("ID"); //行ごとの処理
            }
        } catch (RecordLimitException $e) {
            $this->__session_set("position", $e->getPosition());
            $this->onWeb->ajaxHeader();
            return $this->onWeb->ajaxBody([
                'isFinished' => false,
                'cur' => $e->getCursor(), //現在のバイト数
                'max' => $iterator->getMaxCursor(), //最大バイト数
            ]);
        }
        $this->__session_remove("position");
        $this->onWeb->ajaxHeader();
        return $this->onWeb->ajaxBody([
            'isFinished' => true,
        ]);
    }

    //--------------------

    public function cases_download_streaming_CSV_XLSX()
    {
        return [
            [true, "abc.csv"],
            [false, "abc.xlsx"],
        ];
    }

    /**
     * @dataProvider cases_download_streaming_CSV_XLSX
     * @param bool $iSCsv
     * @param string $filename
     */
    public function test_download_streaming($iSCsv, $filename)
    {
        $this->__mock_OnWeb();
        $this->onWeb->expects($this->once())->method('downloadHeader')->with($filename);

        ob_start();
        $this->download_streaming($iSCsv, $filename, ["NUM", "ABC"], [["1", "a"], ["2", "b"], ["3", "c"]]);
        $actual = ob_get_contents();
        ob_end_clean();

        if ($iSCsv) {
            $this->assertEquals("NUM,ABC\n1,a\n2,b\n3,c\n", $actual);
        } else {
            $actual = $this->__extractDataFromXlsxBinary($actual);
            $this->assertEquals([["NUM", "ABC"], ["1", "a"], ["2", "b"], ["3", "c"]], $actual);
        }
    }

    private function download_streaming($iSCsv, $filename, $columns, $data)
    {
        $this->onWeb->downloadHeader($filename); //ここでヘッダ送出（ただしファイルサイズ不明）
        if ($iSCsv) {
            $out = (new CsvFileWriterFlow())
                ->CSV()
                ->UTF8()
                ->withoutBOM()
                ->LF()
                ->WITH_BR_at_EOF()
                ->columns($columns)
                ->toDownloadStreming()
                ->begin();
        } else {
            $out = (new CsvFileWriterFlow())
                ->XLSX()
                ->columns($columns)
                ->toDownloadStreming()
                ->begin();
        }
        $this->__selectFromTable(
            $data,
            function ($line) use ($out) {
                $out->outputLine($line);
            }
        );
        $out->finish();
    }

    //--------------------

    public function test_download_after_making_CSV()
    {
        $path = $this->__testPath('.csv');
        $filename = "abc.csv";
        $columns = ["NUM", "ABC"];
        $data = [["1", "a"], ["2", "b"], ["3", "c"], ["4", "d"], ["5", "e"], ["6", "f"], ["7", "g"], ["8", "h"]];

        //初回アクセス
        $this->__mock_OnWeb();
        $this->onWeb->expects($this->once())->method('ajaxHeader');
        $this->onWeb->expects($this->once())->method('ajaxBody')->with([
            'isFinished' => false,
            'current' => 3,
        ]);
        $this->download_after_making('CSV', $path, $columns, $data, $filename);
        $this->assertEquals("NUM,ABC\n1,a\n2,b\n3,c\n", file_get_contents($path));

        //2回目アクセス
        $this->__mock_OnWeb();
        $this->onWeb->expects($this->once())->method('ajaxHeader');
        $this->onWeb->expects($this->once())->method('ajaxBody')->with([
            'isFinished' => false,
            'current' => 6,
        ]);
        $this->download_after_making('CSV', $path, $columns, $data, $filename);
        $this->assertEquals("NUM,ABC\n1,a\n2,b\n3,c\n4,d\n5,e\n6,f\n", file_get_contents($path));

        //3回目アクセス
        $this->__mock_OnWeb();
        $this->onWeb->expects($this->once())->method('ajaxHeader');
        $this->onWeb->expects($this->once())->method('ajaxBody')->with([
            'isFinished' => true,
            'current' => 8,
        ]);
        $this->download_after_making('CSV', $path, $columns, $data, $filename);
        $this->assertEquals("NUM,ABC\n1,a\n2,b\n3,c\n4,d\n5,e\n6,f\n7,g\n8,h\n", file_get_contents($path));

        //4回目アクセス
        $this->__mock_OnWeb();
        $this->onWeb->expects($this->never())->method('ajaxHeader');
        $this->onWeb->expects($this->once())->method('downloadHeader')->with($path, $filename);
        ob_start();
        $this->download_after_making('CSV', $path, $columns, $data, $filename);
        $actual = ob_get_contents();
        ob_end_clean();
        $this->assertEquals("NUM,ABC\n1,a\n2,b\n3,c\n4,d\n5,e\n6,f\n7,g\n8,h\n", $actual);
    }

    public function test_download_after_making_XLSX()
    {
        $path = $this->__testPath('.xlsx');
        $filename = "abc.xlsx";
        $columns = ["NUM", "ABC"];
        $data = [["1", "a"], ["2", "b"], ["3", "c"], ["4", "d"], ["5", "e"], ["6", "f"], ["7", "g"], ["8", "h"]];

        //初回アクセス
        $this->__mock_OnWeb();
        $this->onWeb->expects($this->once())->method('ajaxHeader');
        $this->onWeb->expects($this->once())->method('ajaxBody')->with([
            'isFinished' => true,
            'current' => 8,
        ]);
        $this->download_after_making('XLSX', $path, $columns, $data, $filename);
        $this->assertStringEndsWith(".xlsx", $path);
        $this->assertFileExists($path);
        $this->assertNotEquals(0, filesize($path));
        $actual = (new CsvFileIterator($path))->toArrayWithColumns();
        $this->assertEquals(array_merge([$columns], $data), $actual, "Excelファイルの内容が正しいこと");
        $xlsxBinary = file_get_contents($path);

        //2回目アクセス
        $this->__mock_OnWeb();
        $this->onWeb->expects($this->never())->method('ajaxHeader');
        $this->onWeb->expects($this->once())->method('downloadHeader')->with($path, $filename);
        ob_start();
        $this->download_after_making('XLSX', $path, $columns, $data, $filename);
        $actual = ob_get_contents();
        ob_end_clean();
        $this->assertEquals($xlsxBinary, $actual, sprintf("- %s\n+ %s", bin2hex($xlsxBinary), bin2hex($actual)));
    }

    public function download_after_making($csv_or_xlsx, $path, $columns, $data, $filename)
    {
        //ファイルゴミが残る可能性あるので注意
        /** @var WritingPosition $position */
        $position = $this->__session_get("position");
        if (!isset($position)) {
            $position = new WritingPosition($path);
        }
        if ($position->isMaking()) {
            if ($csv_or_xlsx === 'CSV') {
                $out = (new CsvFileWriterFlow())
                    ->CSV()
                    ->SJIS()
                    ->LF()
                    ->WITH_BR_at_EOF()
                    ->columns($columns)
                    ->toDownloadAfterMaking($position)
                    ->begin();
                $limit = self::RECORD_LIMIT;
            } else {
                $out = (new CsvFileWriterFlow())
                    ->XLSX()
                    ->columns($columns)
                    ->toDownloadAfterMaking($position)
                    ->begin();
                $limit = null; //XLSX ではファイル追記はできないので分割しない（１度に全部吐く）
            }
            $isFinished = $this->__selectFromTable(
                $data,
                function ($line) use ($out) {
                    $out->outputLine($line);
                },
                $position->getNextRownum() - 1,
                $limit
            );
            $out->finish($isFinished); //完了していたら$positionにフラグ立て
            $this->__session_set("position", $position);
            $this->onWeb->ajaxHeader();
            return $this->onWeb->ajaxBody([ //ダウンロードファイル作成中
                'isFinished' => $isFinished,
                'current' => $position->getRownum(), //現在の出力行
            ]);
        }
        //ダウンロード
        $this->__session_remove("position");
        $this->onWeb->downloadHeader($position->getPath(), $filename);
        return $this->onWeb->downloadBody($position->getPath()); //完了(一度にメモリにロードしないように配慮)
    }

    //--------------------

    private function __session_get($key)
    {
        return (isset($this->session[$key]) ? $this->session[$key] : null);
    }

    private function __session_set($key, $value)
    {
        $this->session[$key] = $value;
    }

    private function __session_remove($key)
    {
        unset($this->session[$key]);
    }

    /**
     * @param array $data
     * @param callable $onRead
     * @param null|int $start
     * @param null|int $limit
     * @return array
     */
    private function __selectFromTable($data, $onRead, $start = 0, $limit = null)
    {
        $isFinished = false;
        foreach ($data as $index => $line) {
            if ($index < $start) {
                continue;
            }
            if (isset($limit) && $start + $limit - 1 < $index) {
                continue;
            }
            if ($index == count($data) - 1) {
                $isFinished = true;
            }
            $onRead($line); //CsvWriter に渡す
        }
        return $isFinished;
    }

    private function __testPath($ext = "")
    {
        $path = tempnam('/tmp', 'test');
        unlink($path);
        $path = $path . $ext;
        $this->paths[] = $path;
        return $path;
    }

    private function __extractDataFromXlsxBinary($binary)
    {
        $path = $this->__testPath('.xlsx');
        file_put_contents($path, $binary);
        $data = (new CsvFileIterator($path))->toArrayWithColumns();
        return $data;
    }
}