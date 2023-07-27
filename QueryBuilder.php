<?php

namespace App\Libraries;

class QueryBuilder {

    protected $db;
    protected $table;
    protected $selectColumns = [];
    protected $joins = [];
    protected $whereClauses = [];
    protected $orders = [];
    protected $parameters = [];
    protected $insertValues = [];
    protected $updateValues = [];
    protected $groupBy = [];
    protected $havingClauses = [];
    protected $limit;
    protected $offset;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setTable($table) {
        $this->table = $table;
        return $this;
    }

    public function getTable() {
        return $this->table;
    }

    public function select($columns = '*') {
        $this->selectColumns = explode(', ', $columns);
        return $this;
    }

    public function join($table, $condition, $type = 'INNER') {
        $this->joins[] = "$type JOIN $table ON $condition";
        return $this;
    }
    public function leftJoin($table, $condition) {
        $this->joins[] = "LEFT JOIN $table ON $condition";
        return $this;
    }

    public function rightJoin($table, $condition) {
        $this->joins[] = "RIGHT JOIN $table ON $condition";
        return $this;
    }

    public function where($column, $value, $operator = '=', $logicalOperator = 'AND') {
        $this->whereClauses[] = [
            'clause' => "$column $operator ?",
            'param' => $value,
            'logicalOperator' => $logicalOperator
        ];
        // Aggiungi il parametro all'array dei parametri
        $this->parameters[] = $value;

        return $this;
    }

    public function whereIn($column, array $values, $logicalOperator = 'AND') {
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->whereClauses[] = [
            'clause' => "$column IN ($placeholders)",
            'params' => $values,
            'logicalOperator' => $logicalOperator
        ];

        // Aggiungi i parametri all'array dei parametri
        foreach ($values as $value) {
            $this->parameters[] = $value;
        }
        return $this;
    }

    // Metodo per iniziare un gruppo di clausole con parentesi
    public function beginWhereGroup($logicalOperator = 'AND') {
        $this->whereClauses[] = [
            'clause' => '(',
            'logicalOperator' => $logicalOperator
        ];
        return $this;
    }

    // Metodo per terminare un gruppo di clausole con parentesi
    public function endWhereGroup() {
        $this->whereClauses[] = [
            'clause' => ')',
            'logicalOperator' => null
        ];
        return $this;
    }

    public function groupBy($column) {
        $this->groupBy[] = $column;
        return $this;
    }

    public function having($column, $value, $operator = '=') {
        $this->havingClauses[] = "$column $operator ?";
        $this->parameters[] = $value;
        return $this;
    }

    protected function buildGroupBy() {
        if (empty($this->groupBy)) {
            return '';
        }
        return ' GROUP BY ' . implode(', ', $this->groupBy);
    }

    protected function buildHaving() {
        if (empty($this->havingClauses)) {
            return '';
        }
        return ' HAVING ' . implode(' AND ', $this->havingClauses);
    }

    public function orderBy($column, $direction = 'ASC') {
        $this->orders[] = "$column $direction";
        return $this;
    }

    protected function buildSelect() {
        return 'SELECT ' . implode(', ', $this->selectColumns);
    }

    protected function buildJoins() {
        return implode(' ', $this->joins);
    }

    /*
    protected function buildWhere() {
        if (empty($this->whereClauses)) {
            return '';
        }

        $whereSql = '';
        $firstClause = true;


        foreach ($this->whereClauses as $whereClause) {
            if (!$firstClause) {
                $whereSql .= " " . $whereClause['logicalOperator'];
            } else {
                $firstClause = false;
            }
            $whereSql .= " " . $whereClause['clause'];
            if (!empty($whereClause['params'])) {
                foreach ($whereClause['params'] as $param) {
                    $this->parameters[] = $param;
                }
            }
            $first = false;
        }
        return ' WHERE ' . $whereSql;
    }*/

    public function buildWhere() {
        $whereString = '';
        if (!empty($this->whereClauses)) {
            $whereString .= ' WHERE ';
            $firstClause = true;
            foreach ($this->whereClauses as $index => $whereClause) {
                if ($whereClause['clause'] !== '(') {
                    if (!$firstClause && $this->whereClauses[$index - 1]['clause'] !== '(') {
                        $whereString .= ' ' . $whereClause['logicalOperator'];
                    }
                } else {
                    $whereString .= ' ' . $whereClause['logicalOperator'];
                }
                $whereString .= ' ' . $whereClause['clause'];
                $firstClause = false;
            }
        }
        return $whereString;
    }

    public function executeQuery() {
        $sql = $this->toSql();
        $this->logQuery($sql);
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($this->getParameters());
            $this->reset();
            return $stmt;
        } catch (\PDOException $e) {
            // Qui potresti scegliere di rilanciare l'eccezione o gestirla in altro modo
            throw $e;
        }
    }

    protected function buildOrderBy() {
        if (empty($this->orders)) {
            return '';
        }
        return ' ORDER BY ' . implode(', ', $this->orders);
    }

    public function limit($limit) {
        $this->limit = (int)$limit;
        return $this;
    }

    public function offset($offset) {
        $this->offset = (int)$offset;
        return $this;
    }

    protected function buildLimit() {
        if (is_null($this->limit)) {
            return '';
        }
        $sql = ' LIMIT ' . $this->limit;
        if (!is_null($this->offset)) {
            $sql .= ' OFFSET ' . $this->offset;
        }
        return $sql;
    }

    public function insert($values) {
        $columns = implode(", ", array_keys($values));
        $placeholders = implode(", ", array_fill(0, count($values), "?"));
        $sql = "INSERT INTO $this->table ($columns) VALUES ($placeholders)";
        $this->parameters = array_values($values);
        return $this->execute($sql);
    }

    /*public function update($values) {
        $setClauses = [];
        foreach ($values as $column => $value) {
            $setClauses[] = "$column = ?";
            $this->parameters[] = $value;
        }
        $setClause = implode(", ", $setClauses);
        $sql = "UPDATE $this->table SET $setClause" . $this->buildWhere();
        return $this->execute($sql);
    }*/

    public function update($values, $conditions = []) {
        $setClauses = [];
        foreach ($values as $column => $value) {
            $setClauses[] = "$column = ?";
            $this->parameters[] = $value;
        }
        $setClause = implode(", ", $setClauses);

        // Generate WHERE clauses if provided
        if (!empty($conditions)) {
            foreach ($conditions as $column => $value) {
                $this->where($column, $value);
            }
        }

        $sql = "UPDATE $this->table SET $setClause" . $this->buildWhere();

        // Aggiungi i parametri per la clausola WHERE
        foreach ($this->whereClauses as $whereClause) {
            if (isset($whereClause['param'])) {
                $this->parameters[] = $whereClause['param'];
            }
            if (isset($whereClause['params'])) {
                foreach ($whereClause['params'] as $param) {
                    $this->parameters[] = $param;
                }
            }
        }

        return $this->execute($sql);
    }



    public function delete() {
        $sql = "DELETE FROM $this->table" . $this->buildWhere();
        return $this->execute($sql);
    }

    public function beginTransaction() {
        return $this->db->beginTransaction();
    }

    public function commit() {
        return $this->db->commit();
    }

    public function rollback() {
        return $this->db->rollback();
    }

    public function logQuery($sql) {
        // Qui dovrai implementare il logging in base alle tue esigenze
        // Ad esempio, potresti scrivere il log su file o su database
        // Oppure potresti usare Monolog
    }



    public function count() {
        $sql = "SELECT COUNT(*) FROM $this->table" . $this->buildWhere();
        $stmt = $this->execute($sql);
        return $stmt->fetchColumn();
    }

    public function toSql() {
        $sql = $this->buildSelect();
        $sql .= " FROM $this->table ";
        $sql .= $this->buildJoins();
        $sql .= $this->buildWhere();
        $sql .= $this->buildGroupBy();
        $sql .= $this->buildHaving();
        $sql .= $this->buildOrderBy();
        $sql .= $this->buildLimit();
        return $sql;
    }

    public function reset() {
        $this->selectColumns = [];
        $this->joins = [];
        $this->whereClauses = [];
        $this->orders = [];
        $this->parameters = [];
        $this->insertValues = [];
        $this->updateValues = [];
        $this->groupBy = [];
        $this->havingClauses = [];
        $this->limit = null;
        $this->offset = null;
        return $this;
    }
    public function getParameters() {
        return $this->parameters;
    }

    public function get()
    {
        $sql = $this->toSql();
        $stmt = $this->db->prepare($sql);
        $params = $this->getParameters();
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function execute($sql) {
        $stmt = $this->db->prepare($sql);

        for ($i = 0; $i < count($this->parameters); $i++) {
            // Nota: PDOStatement::bindParam è uno-based, non zero-based.
            $stmt->bindParam($i + 1, $this->parameters[$i]);
        }

        $stmt->execute();
        $this->parameters = [];
        return $stmt;
    }

    public function or()
    {
        $this->beginWhereGroup('OR');
    }

    public function and()
    {
        $this->beginWhereGroup('AND');
    }

    public function getColumns()
    {
        $sql = "SHOW COLUMNS FROM $this->table";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $array = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $columns = [];
        foreach ($array as $column) {
            $columns[] = $column['Field'];
        }
        return $columns;
    }

}
