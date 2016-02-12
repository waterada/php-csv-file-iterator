<?php
namespace waterada\CsvFileIterator\ColumnMapper;

/**
 * 指定された名前のカラムが、配列の何列目なのかを管理するクラス。
 *
 * @property array $_columnNames     : 列名の配列
 * @property array $_columnToIndex   : 列名を列Indexに読み替えるマップ
 * @property array $_wantIndexes     : 指定されていたら、toArray() で出力対象にしたい列とその順序 { 列Index => true }
 * @property array $_conditions      : 指定されていたら、この列のいずれかで有効値が入っている行はスキップする { 列Index => [ アクション => [値] }   ※アクションは "IN" or "NOT_IN"
 */
class IndexedColumnMapper implements ColumnMapper
{
    protected $_columnNames;
    protected $_columnToIndex;
    protected $_wantIndexes;
    protected $_conditions;

    /**
     * @param $columnNames
     */
    public function __construct($columnNames)
    {
        $this->_columnNames = $columnNames;
        $this->_columnToIndex = array_flip($columnNames);
        $this->_wantIndexes = [];
        $this->_conditions = [];
    }

    /**
     * @param string $columnName
     * @param bool $strict
     * @return string
     */
    public function getIndex($columnName, $strict = true)
    {
        if (isset($this->_columnToIndex[$columnName])) {
            $idx = $this->_columnToIndex[$columnName];
            return $idx;
        } elseif ($strict) {
            // @codeCoverageIgnoreStart
            throw new \LogicException("存在しないカラム " . $columnName . " にアクセスしました！");
            // @codeCoverageIgnoreEnd
        } else {
            return '';
        }
    }

    /**
     * @param array $wantColumns 取得を許可する列とその順序。指定形式: [ 列名 ] 。指定しなければ全列。存在しないカラムも指定できる。存在しないカラムは常に空欄となる。
     * @return ColumnMapper $this
     */
    public function setColumns($wantColumns)
    {
        $this->_columnNames = $wantColumns;
        $this->_wantIndexes = [];
        foreach ($wantColumns as $columnName) {
            $idx = $this->getIndex($columnName, false);
            $this->_wantIndexes[] = $idx;
        }
        return $this;
    }

    /**
     * @return array 取得される列とその順序。指定形式: [ 列名 ] 。
     */
    public function getColumns()
    {
        return $this->_columnNames;
    }

    /**
     * @param array $conditions
     * @return ColumnMapper $this
     */
    public function setConditions($conditions)
    {
        if (!is_array($conditions)) {
            // @codeCoverageIgnoreStart
            throw new \LogicException('$conditions には配列以外が指定されました！ ' . var_dump($conditions));
            // @codeCoverageIgnoreEnd
        }
        foreach ($conditions as $column => $cond) {
            if (is_callable($cond)) {
                $map = $cond;
            } else {
                list($operator, $values) = $cond;
                if (!is_array($values)) {
                    // @codeCoverageIgnoreStart
                    throw new \LogicException('$values に配列以外が指定されました！ $values:' . var_export($values,
                            true) . ' / $column:' . var_export($column, true) . ' / $cond:' . var_export($cond,
                            true) . ' / $conditions:' . var_export($conditions, true));
                    // @codeCoverageIgnoreEnd
                }
                //値をマップ化(有無を高速で判定するため)
                $map = [];
                foreach ($values as $v) {
                    $map[$v] = true;
                }
                //あるべきか、ないべきか
                switch ($operator) {
                    case 'IN':
                        $map['__exclude'] = false;
                        break;
                    case 'NOT_IN':
                        $map['__exclude'] = true;
                        break;
                    // @codeCoverageIgnoreStart
                    default:
                        throw new \LogicException('未知の operator [' . $operator . '] が指定されました！');
                }
                // @codeCoverageIgnoreEnd
            }
            $idx = $this->getIndex($column);
            $this->_conditions[$idx] = $map;
        }
        return $this;
    }

    /**
     * @param array $values
     * @return bool - false if should skip
     */
    public function meetsCondition($values)
    {
        if (!empty($this->_conditions)) {
            foreach ($this->_conditions as $index => $cond) {
                if (is_callable($cond)) {
                    if (call_user_func($cond, $this, $values) == false) {
                        return true;
                    }
                } elseif (isset($cond[$values[$index]]) == $cond['__exclude']) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Record::get() から参照される。カラム名で指定された列の値を選んで返す。
     *
     * @param array $values CSV１行分の配列
     * @param string $columnName カラム名
     * @return string
     */
    public function selectValue($values, $columnName)
    {
        $idx = $this->getIndex($columnName);
        return $values[$idx];
    }

    /**
     * Record::toArray() から参照される。必要な列のみに縮める。
     *
     * @param array $values CSV１行分の配列
     * @return array
     */
    public function selectValues($values)
    {
        if (empty($this->_wantIndexes)) {
            return $values;
        } else {
            $newValues = [];
            $indexes = $this->_wantIndexes;
            foreach ($indexes as $index) {
                if ($index === '') {
                    $newValues[] = '';
                } else {
                    $newValues[] = $values[$index];
                }
            }
            return $newValues;
        }
    }
}