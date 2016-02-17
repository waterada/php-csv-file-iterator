<?php
namespace waterada\CsvFileIterator\ColumnMapper;

/**
 * カラム名から値をアクセスする際の戦略を管理する。
 */
interface ColumnMapper {
    /**
     * @param array $wantColumns 取得を許可する列とその順序。指定形式: [ 列名 ] 。指定しなければ全列。
     * @return ColumnMapper $this
     */
    public function setColumns($wantColumns);

    /**
     * @return array 取得される列とその順序。指定形式: [ 列名 ] 。
     */
    public function getColumns();

    /**
     * @param array $conditions
     * @return ColumnMapper $this
     */
    public function setConditions($conditions);

    /**
     * @param array $values
     * @return bool - false if should skip
     */
    public function meetsCondition($values);

    /**
     * Record::get() から参照される。カラム名で指定された列の値を選んで返す。
     *
     * @param array  $values CSV１行分の配列
     * @param string $columnName カラム名
     * @return string
     */
    public function selectValue($values, $columnName);

    /**
     * Record::toArray() から参照される。必要な列のみに縮める。
     *
     * @param array $values CSV１行分の配列
     * @return array
     */
    public function selectValues($values);
}