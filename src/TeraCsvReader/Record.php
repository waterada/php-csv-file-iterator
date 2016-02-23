<?php
namespace waterada\TeraCsvReader;

use waterada\TeraCsvReader\ColumnMapper\ColumnMapper;

/**
 * Class Record
 * 1レコード分のデータを保持するクラス
 *
 * @property ColumnMapper $_columnMapper
 * @property array   $_values
 */
class Record {
    protected $_columnMapper;
    protected $_values;

    /**
     * @param ColumnMapper $columnMapper
     * @param array   $values
     */
    public function __construct($columnMapper, $values) {
        $this->_columnMapper = $columnMapper;
        $this->_values = $values;
    }

    /**
     * @param $columnName - 値を取得したい列の名前
     * @return string
     */
    public function get($columnName) {
        return $this->_columnMapper->selectValue($this->_values, $columnName);
    }

    /**
     * @return array - １行分のデータ
     */
    public function toArray() {
        return $this->_columnMapper->selectValues($this->_values);
    }
}