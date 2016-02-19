<?php
use waterada\CsvFileIterator\CsvFileIterator;
use waterada\CsvFileIterator\RecordLimitException;
use waterada\CsvFileIterator\WritingPosition;
use waterada\CsvFileWriter\CsvFileOnWeb;
use waterada\CsvFileWriter\CsvFileWriterFlow;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/../src/CsvFileWriter/CsvFileWriter.php';
require_once __DIR__ . '/../src/CsvFileWriter/CsvFileWriterFlow.php';
require_once __DIR__ . '/../src/CsvFileWriter/Output.php';
require_once __DIR__ . '/../src/CsvFileWriter/Format/Format.php';
require_once __DIR__ . '/../src/CsvFileWriter/Format/CsvFormat.php';
require_once __DIR__ . '/../src/CsvFileWriter/Format/XlsxFormat.php';
require_once __DIR__ . '/../src/CsvFileWriter/WritingPosition.php';
require_once __DIR__ . '/../src/CsvFileOnWeb/CsvFileOnWeb.php';

/**
 * @property PHPUnit_Framework_MockObject_MockObject|CsvFileOnWeb $onWeb
 * @property array $session
 * @property array $data
 * @property array $actual
 * @property array $expected
 */
class CsvWriterControllerTest extends PHPUnit_Framework_TestCase
{
    const RECORD_LIMIT = 3;

    public function setUp()
    {
        $this->onWeb = null;
        $this->session = [];
        $this->data = []; //todo: ひつようか
        $this->actual = [];
        $this->expected = [];
    }

    private function __mock_OnWeb()
    {
        $this->onWeb = $this->getMockBuilder('waterada\CsvFileWriter\CsvFileOnWeb')->setMethods([
            'ajaxHeader',
            'ajaxBody',
            'downloadHeader',
            //'downloadBody',
        ])->getMock();
    }

    //--------------------

    public function test_read()
    {
        $path = FileFabricate::fromString("ID\n1\n2\n3\n4\n5\n6\n7\n8\n")->getPath();
        $this->actual = [];

        //初回アクセス
        $this->__mock_OnWeb();
        $this->onWeb->expects($this->once())->method('ajaxHeader');
        $this->onWeb->expects($this->once())->method('ajaxBody')->with([
            'isFinished' => false,
            'cur' => strlen("ID\n1\n2\n3\n"),
            'max' => filesize($path),
        ]);
        $this->read($path);
        $this->assertEquals(["1", "2", "3"], $this->actual);

        //２回目アクセス
        $this->__mock_OnWeb();
        $this->onWeb->expects($this->once())->method('ajaxHeader');
        $this->onWeb->expects($this->once())->method('ajaxBody')->with([
            'isFinished' => false,
            'cur' => strlen("ID\n1\n2\n3\n4\n5\n6\n"),
            'max' => filesize($path),
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

    public function test_download_streaming()
    {
        $filename = "abc.csv";

        $this->__mock_OnWeb();
        $this->onWeb->expects($this->once())->method('downloadHeader')->with($filename);

        ob_start();
        $this->download_streaming($filename, ["NUM", "ABC"], [["1", "a"], ["2", "b"], ["3", "c"]]);
        $actual = ob_get_contents();
        ob_end_clean();
        $this->assertEquals("NUM,ABC\n1,a\n2,b\n3,c\n", $actual);
    }

    private function download_streaming($filename, $columns, $data)
    {
        $this->onWeb->downloadHeader($filename); //ここでヘッダ送出（ただしファイルサイズ不明）
        $out = (new CsvFileWriterFlow())
            ->CSV()
            ->UTF8()
            ->withoutBOM()
            ->LF()
            ->WITH_BR_at_EOF()
            ->columns($columns)
            ->toDownloadStreming()
            ->begin();
        $this->__selectFromTable(
            $data,
            function ($line) use ($out) {
                $out->outputLine($line);
            }
        );
        $out->finish();
    }

    //--------------------

    public function test_download_after_making()
    {
        $path = FileFabricate::fromString("ID")->changeFileNameTo("abc.csv")->getPath();
        unlink($path); //ファイルパスだけ取得したら、消しておく

        $columns = ["NUM", "ABC"];
        $data = [["1", "a"], ["2", "b"], ["3", "c"], ["4", "d"], ["5", "e"], ["6", "f"], ["7", "g"], ["8", "h"]];
        $filename = "abc.csv";

        //初回アクセス
        $this->__mock_OnWeb();
        $this->onWeb->expects($this->once())->method('ajaxHeader');
        $this->onWeb->expects($this->once())->method('ajaxBody')->with([
            'isFinished' => false,
            'current' => 3,
        ]);
        $this->download_after_making($path, $columns, $data, $filename);
        $this->assertEquals("NUM,ABC\n1,a\n2,b\n3,c\n", file_get_contents($path));

        //2回目アクセス
        $this->__mock_OnWeb();
        $this->onWeb->expects($this->once())->method('ajaxHeader');
        $this->onWeb->expects($this->once())->method('ajaxBody')->with([
            'isFinished' => false,
            'current' => 6,
        ]);
        $this->download_after_making($path, $columns, $data, $filename);
        $this->assertEquals("NUM,ABC\n1,a\n2,b\n3,c\n4,d\n5,e\n6,f\n", file_get_contents($path));

        //3回目アクセス
        $this->__mock_OnWeb();
        $this->onWeb->expects($this->once())->method('ajaxHeader');
        $this->onWeb->expects($this->once())->method('ajaxBody')->with([
            'isFinished' => true,
            'current' => 8,
        ]);
        $this->download_after_making($path, $columns, $data, $filename);
        $this->assertEquals("NUM,ABC\n1,a\n2,b\n3,c\n4,d\n5,e\n6,f\n7,g\n8,h\n", file_get_contents($path));

        //4回目アクセス
        $this->__mock_OnWeb();
        $this->onWeb->expects($this->never())->method('ajaxHeader');
        $this->onWeb->expects($this->once())->method('downloadHeader')->with($path, $filename);
        ob_start();
        $this->download_after_making($path, $columns, $data, $filename);
        $actual = ob_get_contents();
        ob_end_clean();
        $this->assertEquals("NUM,ABC\n1,a\n2,b\n3,c\n4,d\n5,e\n6,f\n7,g\n8,h\n", $actual);
    }

    public function download_after_making($path, $columns, $data, $filename)
    {
        //ファイルゴミが残る可能性あるので注意
        /** @var WritingPosition $position */
        $position = $this->__session_get("position");
        if (!isset($position)) {
            $position = new WritingPosition($path);
        }
        if ($position->isMaking()) {
            $out = (new CsvFileWriterFlow())
                ->CSV()
                ->SJIS()
                ->LF()
                ->WITH_BR_at_EOF()
                ->columns($columns)
                ->toDownloadAfterMaking($position)
                ->begin();
            $isFinished = $this->__selectFromTable(
                $data,
                function ($line) use ($out) {
                    $out->outputLine($line);
                },
                $position->getNextRownum() - 1,
                self::RECORD_LIMIT
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
}