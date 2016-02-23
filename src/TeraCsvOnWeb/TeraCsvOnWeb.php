<?php
namespace waterada\TeraCsvOnWeb;

class TeraCsvOnWeb
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
