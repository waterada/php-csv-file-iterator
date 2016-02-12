<?php
namespace waterada\CsvFileIterator\FileHandle;

/**
 * Class FormatDetector
 * ファイルのフォーマット（文字エンコード、区切り文字、カラム名など）を（先頭部分を試し読みして）自動判別するクラス。
 */
class CsvFormatDetector
{
    const BOM_UTF8 = "\xef\xbb\xbf";
    const BOM_UTF16LE = "\xff\xfe";

    protected $_columnNames;
    protected $_filter;
    protected $_delim;

    /**
     * 先頭部分を試し読みしてファイルのフォーマット（文字エンコード、区切り文字、カラム名など）を判別する。
     *
     * @param $fh
     * @param string|null $encoding - nullなら自動判別
     * @param string|null $delimiter - nullなら自動判別
     */
    public function detect($fh, $encoding = null, $delimiter = null)
    {
        $line1 = fgets($fh);
        $line1 = self::__defval($line1, '');

        //冒頭にBOMがついていたら撤去
        $bom = null;
        foreach ([self::BOM_UTF8, self::BOM_UTF16LE] as $_bom) {
            $bom_len = strlen($_bom);
            if (substr($line1, 0, $bom_len) == $_bom) {
                $bom = $_bom;
                $line1 = substr($line1, $bom_len);
            }
        }

        //文字コードを変換するフィルターを自動判別
        $this->_filter = null;
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
            $lines = [];
            for ($i = 0; $i < 20; $i++) {
                $line = fgets($fh);
                if ($line === false) {
                    break;
                }
                $lines[] = self::__defval($line, '');
            }
            //文字コード推測
            $filterEncoding = mb_detect_encoding(implode($lines), "UTF-8, SJIS-win", true);
        }

        if (isset($filterEncoding) && $filterEncoding !== 'UTF-8') { //UTF-8 以外もしくは、推測できない場合は
            $this->setEncoding($filterEncoding);
            $line1 = mb_convert_encoding($line1, 'UTF-8', $filterEncoding);
        }

        //区切り文字
        if (isset($delimiter)) {
            $this->_delim = $delimiter;
        } elseif (strpos($line1, "\t") !== false) {
            $this->_delim = "\t";
        } else {
            $this->_delim = ",";
        }

        //カラム名
        $this->_columnNames = str_getcsv($line1, $this->_delim, '"');

        //ポインタを初期位置に戻しておく
        rewind($fh);
    }

    /**
     * @return array カラム名の配列を返す(エンコード済み)
     */
    public function getColumnNames()
    {
        return $this->_columnNames;
    }

    /**
     * @return string 区切り文字
     */
    public function getDelimiter()
    {
        return $this->_delim;
    }

    /**
     * 自動ではなく、文字コードを明示したい場合に使用する。
     *
     * @param $encoding
     */
    public function setEncoding($encoding)
    {
        $this->_filter = null;
        if ($encoding === "UTF-8") { //UTF-8 なら何もしない
            return;
        }
        //変換をセット
        $filter = null;
        switch ($encoding) {
            case 'SJIS-win':
                $filter = 'convert.iconv.cp932/utf-8';
                break;
            case 'UTF-16LE':
                $filter = 'convert.iconv.utf-16le/utf-8';
                break;
            default:
                throw new \UnexpectedValueException("未対応のencoding:" . $encoding);
        }
        //フィルターセット
        if ($filter !== null) {
            $this->_filter = function ($fh) use ($filter) {
                $sh = stream_filter_prepend($fh, $filter, STREAM_FILTER_READ);
                if ($sh === false) {
                    // @codeCoverageIgnoreStart
                    throw new \LogicException('Counld not apply stream filter.');
                    // @codeCoverageIgnoreEnd
                }
            };
        }
    }

    /**
     * 読み込むファイルの文字エンコードにあったフィルター（UTF-8に自動で変換しながら読む）を $fh に適用する。
     *
     * @param $fh
     */
    public function applyReadingFilter($fh)
    {
        if ($this->_filter !== null) {
            call_user_func($this->_filter, $fh);
        }
    }

    /**
     * デフォルト値を返す。
     * $val 自体は変更しない。
     *
     * @param mixed $val 評価する値
     * @param mixed $def デフォルト値
     * @return mixed 空(empty($val))なら$defを、そうでなければ$valを返す。
     */
    private static function __defval(&$val, $def = '')
    {
        if (empty($val)) {
            return $def;
        } else {
            return $val;
        }
    }
}