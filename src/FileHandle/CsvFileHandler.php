<?php
namespace waterada\CsvFileIterator\FileHandle;

/**
 * CsvFileHandler
 */
class CsvFileHandler extends FileHandler
{
    const BOM_UTF8 = "\xef\xbb\xbf";
    const BOM_UTF16LE = "\xff\xfe";

    /**
     * @var resource OPEN中のCSVファイル
     */
    protected $_fh;

    /**
     * @var null|string 区切り文字
     */
    protected $_delim = null;

    /**
     * @inheritdoc
     */
    public function open($option)
    {
        $this->_fh = fopen($this->_filePath, 'r');
        if ($this->_fh === false) {
            throw new \LogicException("No such file or directory: " . $this->_filePath);
        }

        //ファイルの形式を（先頭部分を試し読みして）自動判別する
        list($this->_delim, $columnNames) = $this->_detectFormat($this->_fh, $option['encoding'], $option['delimiter']);
        return $columnNames;
    }

    /**
     * @inheritdoc
     */
    public function fgetcsv()
    {
        return fgetcsv($this->_fh, null, $this->_delim);
    }

    /**
     * @inheritdoc
     */
    public function close()
    {
        if ($this->_fh !== null) {
            fclose($this->_fh);
            $this->_fh = null;
        }
    }

    /**
     * @inheritdoc
     */
    public function rewind()
    {
        rewind($this->_fh);
        $this->fgetcsv(); //カラム行を飛ばす
    }

    /**
     * 先頭部分を試し読みしてファイルのフォーマット（文字エンコード、区切り文字、カラム名など）を判別する。
     *
     * @param $fh
     * @param string|null $encoding - nullなら自動判別
     * @param string|null $delimiter - nullなら自動判別
     * @return array
     */
    protected function _detectFormat($fh, $encoding = null, $delimiter = null)
    {
        $line1 = fgets($fh);
        $line1 = ($line1 === false ? '' : $line1);

        //冒頭にBOMがついていたら撤去
        $bom = null;
        foreach ([self::BOM_UTF8, self::BOM_UTF16LE] as $_bom) {
            $bom_len = strlen($_bom);
            if (substr($line1, 0, $bom_len) == $_bom) {
                $bom = $_bom;
                $line1 = substr($line1, $bom_len);
            }
        }

        //文字コードを変換フィルターに使うエンコードを自動判別
        $filterEncoding = null;
        if ($bom === self::BOM_UTF8) {
            //UTF-8ならencodeしない
            $filterEncoding = 'UTF-8'; //BOMがあったら優先
        } elseif ($bom === self::BOM_UTF16LE) {
            $filterEncoding = 'UTF-16LE'; //BOMがあったら優先
        } elseif (isset($encoding)) {
            $filterEncoding = $encoding; //指定があればそれで決定
        } else {
            //２０行読んで自動判別に使う
            $lines = [$line1];
            for ($i = 0; $i < 20; $i++) {
                $line = fgets($fh);
                if ($line === false) {
                    break;
                }
                $lines[] = $line;
            }
            //文字コード推測
            $filterEncoding = mb_detect_encoding(implode($lines), "UTF-8, SJIS-win", true);
        }

        //ポインタを初期位置に戻しておく
        rewind($fh);

        if (isset($filterEncoding) && $filterEncoding !== 'UTF-8') { //UTF-8 以外もしくは、推測できない場合は
            $this->__setEncodingFilter($fh, $filterEncoding);
            $line1 = mb_convert_encoding($line1, 'UTF-8', $filterEncoding);
        }

        //区切り文字
        if (isset($delimiter)) {
            $delim = $delimiter;
        } elseif (strpos($line1, "\t") !== false) {
            $delim = "\t";
        } else {
            $delim = ",";
        }

        //カラム名
        $columnNames = str_getcsv($line1, $delim, '"');

        return [$delim, $columnNames];
    }

    /**
     * 自動ではなく、文字コードを明示したい場合に使用する。
     *
     * @param $fh
     * @param $encoding
     */
    private function __setEncodingFilter($fh, $encoding)
    {
        if ($encoding === "UTF-8") { //UTF-8 なら何もしない
            return;
        }
        //フィルタ名を特定
        $filterName = null;
        switch ($encoding) {
            case 'SJIS-win':
                $filterName = 'convert.iconv.cp932/utf-8';
                break;
            case 'UTF-16LE':
                $filterName = 'convert.iconv.utf-16le/utf-8';
                break;
            default:
                throw new \UnexpectedValueException("未対応のencoding:" . $encoding);
        }
        // 読み込むファイルの文字エンコードにあったフィルター（UTF-8に自動で変換しながら読む）を $fh に適用する。
        if ($filterName !== null) {
            $sh = stream_filter_prepend($fh, $filterName, STREAM_FILTER_READ);
            if ($sh === false) {
                throw new \LogicException('Counld not apply stream filter.');
            }
        }
    }
}