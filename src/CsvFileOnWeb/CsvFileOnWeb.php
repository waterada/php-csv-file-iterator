<?php
namespace waterada\CsvFileWriter;

use waterada\CsvFileIterator\CsvFileIterator;
use waterada\CsvFileIterator\RecordLimitException;
use waterada\CsvFileIterator\WritingPosition;

class CsvFileOnWeb
{
    public function ajaxHeader()
    {
        header("Content-Type: application/json; charset=utf-8");
        header('Access-Control-Allow-Origin: *');
    }

    public function ajaxBody($array)
    {
        return json_encode($array, JSON_UNESCAPED_UNICODE);
    }

    public function downloadHeader($filename, $path = null)
    {
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Type: application/octet-stream');
        header('Content-Transfer-Encoding: binary');
        if (isset($path)) {
            header('Content-Length: ' . filesize($path));
        }
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0
    }

    public function downloadBody($path, $chunksize = 4096) {
        //巨大ファイルでも out of memory エラーが出ないようにバッファリングを無効
        //while (ob_get_level() > 0) {
        //    ob_end_clean();
        //}
        ob_start();

        // ファイル出力(ファイルが大きい場合に備えてメモリを使い過ぎないように少しずつ返す)
        if ($file = fopen($path, 'rb')) {
            while (!feof($file) and (connection_status() == 0)) {
                echo fread($file, $chunksize); //指定したバイト数ずつ出力
                ob_flush();
                flush();
            }
            ob_flush();
            flush();
            fclose($file);
        }
        ob_end_clean();
        return "";
    }
}

class CsvFileOnWebController
{
    public function __construct()
    {
        $this->onWeb = new CsvFileOnWeb();
        $this->session = new \MockSession();
    }

    public function download_after_making()
    {
        //ファイルゴミが残る可能性あるので注意
        /** @var WritingPosition $position */
        $position = $this->session->get("position");
        if (!isset($position)) {
            $path = "";
            $position = new WritingPosition($path);
        }
        if ($position->isMaking()) {
            $out = (new CsvFileWriterFlow())
                ->CSV()
                ->SJIS()
                ->LF()
                ->WITH_BR_at_EOF()
                ->noColumnsLine()
                ->toDownloadAfterMaking($position)
                ->begin();
            $isFinished = $this->_selectFromTable(
                function ($data) use ($out) {
                    $out->outputLine($data);
                },
                $position->getNextRownum(),
                1000
            );
            $out->finish($isFinished); //完了していたら$positionにフラグ立て
            $this->session->set("position", $position);
            $this->onWeb->ajaxHeader();
            return $this->onWeb->ajaxBody([ //ダウンロードファイル作成中
                'isFinished' => $isFinished,
                'current' => $position->getRownum(), //現在の出力行
            ]);
        }
        //ダウンロード
        $this->session->remove("position");
        $filename = "";
        $this->onWeb->downloadHeader($position->getPath(), $filename);
        return $this->onWeb->downloadBody($position->getPath()); //完了(一度にメモリにロードしないように配慮)
    }
}