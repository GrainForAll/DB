<?php

namespace DB;

class DB {

  private $db;
  private $st;

  /**
   * Object constructor.
   * @param array $settings, containing:
   *   - host
   *   - name
   *   - user
   *   - pass
   *   - port
   */
  public function __construct(array $connection) {
    try {
      $this->db = new \PDO("mysql:{$connection['host_type']}={$connection['host']};port={$connection['port']};dbname={$connection['name']}", $connection['user'], $connection['pass']);
      $this->db->setAttribute(\PDO::ATTR_EMULATE_PREPARES, FALSE);
      $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    } catch (\PDOException $e) {
      throw new \Exception($e);
    }
  }

  /**
   * Call
   */
  public function call() {
    $this->next->call();
  }

  /**
   *
   */
  public function query($sql) {
    try {
      $this->st = $this->db->prepare($sql);
      return $this;
    } catch (\PDOException $e) {
      throw new \Exception($e);
    }
  }

  /**
   *
   */
  public function bind($pos, $val, $type = NULL) {
    if (is_null($type)) {
      switch(TRUE) {
        case is_int($val):
          $type = PDO::PARAM_INT;
          break;
        case is_null($val):
          $type = PDO::PARAM_NULL;
          break;
        default:
          $type = PDO::PARAM_STR;
          break;
      }
    }

    $this->st->bindValue($pos, $val, $type);
    return $this;
  }

  /**
   *
   */
  public function execute($params = NULL) {
    try {
      $this->st->execute($params);
      return $this;
    } catch (\PDOException $e) {
      throw new \Exception($e);
    }
  }

  /**
   *
   */
  public function update($table, $fields, $where = NULL) {
    $i = 0;
    $query = array();
    foreach ($fields as $key => $value) {
      $query[] = "{$key} = :param_{$i}";
      $i++;
    }

    if (!empty($query)) {
      // Construct the query with placeholders.
      $param_sql = implode(', ', $query);
      $this->query("UPDATE {$table} SET " . $param_sql . " " . $where);

      // Bind each placeholder.
      $i = 0;
      foreach ($fields as $key => $value) {
        $pos = ':param_' . $i;
        $this->bind($pos, $value);
        $i++;
      }
    }

    return $this;
  }

  /**
   * Fetch methods.
   */
  public function fetchColumn() {
    return $this->st->fetchColumn();
  }
  public function fetch() {
    return $this->st->fetch(\PDO::FETCH_OBJ);
  }
  public function fetchAssoc() {
    return $this->st->fetch(\PDO::FETCH_ASSOC);
  }
  public function fetchAll() {
    return $this->st->fetchAll(\PDO::FETCH_OBJ);
  }
  public function fetchAllAssoc() {
    return $this->st->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Retrieve the last insert ID.
   */
  public function lastInsertId() {
    return $this->db->lastInsertId();
  }

  /**
   * Debug function to log any bound params.
   */
  public function debugDumpParams() {
    return $this->st->debugDumpParams();
  }
}
