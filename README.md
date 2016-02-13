CsvFileIterator
================

[![Build Status](https://travis-ci.org/waterada/php-csv-file-iterator.svg?branch=master)](https://travis-ci.org/waterada/php-csv-file-iterator)

概要(summary)
-------------

CSV/TST/Excelファイルを特に設定なくても自動認識して読みこむことができます。
詳細は使用例を見てください。


使用例(example):
----------------

```php
// ファイル冒頭で使用宣言
use \waterada\CsvFileIterator\CsvFileIterator;


// 普通に読む
//      CSV/TSV のファイルを Iterator として読み込みます(foreach でレコード単位でループできる)。
//      下記のフォーマットを自動判別します:
//        - CSV か TSV か (1行目に\tを含んでいるなら TSV)
//        - BOM の有無 (まずは冒頭の BOM の存在を疑う)
//        - 文字コード (ただしSJIS, UTF-8, UTF-16LE のみ対応) (2行目から20行読んで判別。BOMがあればそれを優先) 
//      1行目はラベル行として読み込まれ、各値にアクセスする際のキーとなります。1行目のラベル行には改行を含めることはできません。
$csv = new CsvFileIterator($path);
echo implode(",", $csv->getColumnMapper()->getColumns()); // 列1,列2,列3
foreach ($csv->iterate() as $rownum => $record) {
    echo $rownum . ":" . $record->get('列1'); // 1:あ
    echo $rownum . ":" . $record->get('列2'); // 2:い
    echo $rownum . ":" . $record->get('列3'); // 3:う
    echo implode(",", $record->toArray()); // あ,い,う
}


// 除外条件を設定可能
//      指定したものを foreach で読み飛ばします。
$csv = new CsvFileIterator($path);
// CSVの中身:  
//    ID
//    1
//    2
//    3
//    4
//    5
$csv->getColumnMapper()->setConditions(['ID' => ['NOT_IN', ['2', '3']]);
foreach ($csv->iterate() as $record) {
    echo $record->get('ID'); // 1 → 4 → 5 の順に出力
}


// 合致条件を設定可能
//     指定したもの以外を foreach で読み飛ばします。
$csv = new CsvFileIterator($path);
// CSVの中身:  
//    ID
//    1
//    2
//    3
//    4
//    5
$csv->getColumnMapper()->setConditions(['ID' => ['IN', ['2', '3']]);
foreach ($csv->iterate() as $record) {
    echo $record->get('ID'); // 2 → 3 の順に出力
}


// 出力カラム順指定 (toArray の順序を指定する)
$csv = new CsvFileIterator($path);
// CSVの中身:  
//    Q1,Q2,Q3,Q4,Q5
//    11,12,13,14,15
//    21,22,23,24,25
$csv->getColumnMapper()->setColumns(['Q5', 'Q3', 'Q1']);
echo implode(",", $csv->getColumns()); // Q5,Q3,Q1
foreach ($csv->iterate() as $record) {
    echo implode(",", $record->toArray()); // 15,13,11 → 25,23,21 の順に出力
}


// メソッドチェインも可能
$csv = new CsvFileIterator($path);
$columns = $csv->getColumnMapper()->setColumns(['Q3', 'Q1'])->setConditions([
    'Q2'     => ['NOT_IN', ['12']],
    'status' => ['IN', ['complete']],
])->getColumns();


// データの有無をチェック
$csv = new CsvFileIterator($path);
echo ($csv->isEmpty() ? 'true' : 'false'); // ラベル行だけか、すべての行が除外されたら、true


// エンコーディングを強制できる
$csv = new CsvFileIterator($path, 'SJIS');


// 区切り文字を強制できる
$csv = new CsvFileIterator($path, null, "\t");


// 拡張子が xlsx のファイル(Excelファイル)も自動認識して開けます（ただしCSV的にデータのみ読む）
$csv = new CsvFileIterator("aaa.xlsx");


// 大量データを読む際に複数リクエストに分割して % を算出しながら読むこともできます。
$csv = new CsvFileIterator($path);
$csv->setLimit(1000); //1000行ごとにforeachを中断する ※こうすることでRecordLimitExceptionが発生するようになります。
$position = $_SESSION['position']; //前回の "位置" を取得
try {
    foreach ($csv->iterate($position) as $record) { // iterate() に前回の "位置" を渡す。null なら頭からとなる
        //処理
    }
} catch (RecordLimitException $e) { //中断した場合の処理
    $_SESSION['position'] = $e->getPosition(); //セッションに保存
    $percent = intval($e->getCursor() / $csv->getMaxCursor()); //進捗率
}
```


Install
-------------

- composer.json に下記を記述してください。(Please write the following into your composer.json.)

  - `"repositories": []` のパートに下記を追加 (inside the `"repositories": []` part):

  ```
        {
            "type": "git",
            "url": "git@github.com:waterada/php-csv-file-iterator.git"
        }
  ```

  - `"require": {}` のパートに下記を追加 (inside the `"require": {}` part):

  ```
        "waterada/csv-file-iterator": "1.*"
  ```

- `comoposer update waterada/csv-file-iterator` を実行してください。(Please run `comoposer update waterada/csv-file-iterator`.)

以上で使えるようになります。(That's all.)
