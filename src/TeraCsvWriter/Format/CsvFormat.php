<?php
namespace waterada\TeraCsvWriter\Format;

class CsvFormat extends Format
{
    const ENC_UTF8 = 'UTF-8';
    const ENC_SJIS = 'SJIS-win';
    const ENC_UTF16LE = 'UTF-16LE';

    public $delim;
    public $encoding;
    public $bom;
    public $br;
    public $withBrAtEOF;

    private $__fh;
    private $__cache;
    private $__needsCache;

    public function __construct($delim = ",")
    {
        $this->delim = $delim;
        $this->__fh = null;
        $this->__cache = null;
    }

    /**
     * @throws \Exception
     */
    public function begin()
    {
        //ファイルハンドル返す
        $this->__fh = $this->output->getFileHandle();
        //エンコーディング
        $filterName = null;
        switch ($this->encoding) {
            case self::ENC_UTF8:
                if ($this->bom) {
                    fwrite($this->__fh, "\xef\xbb\xbf");
                }
                //UTF-8 ならフィルタは処理しない
                break;
            case self::ENC_SJIS:
                $filterName = 'convert.iconv.utf-8/cp932';
                break;
            case self::ENC_UTF16LE:
                fwrite($this->__fh, "\xff\xfe");
                $filterName = 'convert.iconv.utf-8/utf-16le';
                break;
            default:
                throw new \UnexpectedValueException("未対応のencoding:" . $this->encoding);
        }
        // 書き出すファイルの文字エンコードにあったフィルター（UTF-8から自動で変換しながら書く）を $fh に適用する。
        if (isset($filterName)) {
            $sh = stream_filter_prepend($this->__fh, $filterName, STREAM_FILTER_WRITE);
            if ($sh === false) {
                throw new \LogicException('Counld not apply stream filter.');
            }
        }
        // キャッシュが必要(改行コードの調整が必要)かを判断
        //  先頭改行にすることで、末尾改行なしを実現するプランはカラム行無いときに破綻。ちゃんとやるべき
        //  １行ずつキャッシュして、常に前の行を出力する
        $this->__needsCache = ($this->br !== "\n" || $this->withBrAtEOF == false);
        //カラム行
        if ($this->output->skipColumnsLine() == false) {
            $this->_outputColumnsLine();
        }
    }

    public static $case = 1;

    /**
     * @param array $data
     * @param bool $isHeader
     */
    public function outputLine($data, $isHeader = false)
    {
        if ($this->__needsCache) {
            //キャッシュしてるものがあれば出力
            if (isset($this->__cache)) {
                fwrite($this->__fh, $this->__cache);
            }
            //一旦CSV文字列を取 2:
            $fh_output = fopen("php://output", "w");
            ob_start();
            fputcsv($fh_output, $data, $this->delim, '"');
            $this->__cache = ob_get_contents();
            ob_end_clean();
            fclose($fh_output);
            if ($this->br !== "\n") {
                $this->__cache = substr_replace($this->__cache, $this->br, -1, 1);
            }
        } else {
            fputcsv($this->__fh, $data, $this->delim, '"');
        }
    }

    public function finish()
    {
        //キャッシュしてるものがあれば出力
        if (isset($this->__cache)) {
            if ($this->withBrAtEOF == false) {
                $size = strlen($this->br);
                $this->__cache = substr_replace($this->__cache, '', -$size, $size);
            }
            fwrite($this->__fh, $this->__cache);
        }
        fclose($this->__fh);
    }
}
