<?php

namespace DB;

class SessionHandler {

    protected $db;

    /**
     * Constructor
     * @param $db   \DB\DB instance
     */
    public function __construct(\DB\DB $db) {
        $this->db = $db;

        session_cache_limiter(''); // Disable cache headers from being sent by PHP

        session_set_save_handler(
          array($this, 'open'),
          array($this, 'close'),
          array($this, 'read'),
          array($this, 'write'),
          array($this, 'destroy'),
          array($this, 'gc')
        );

        register_shutdown_function('session_write_close');

        session_start();
    }

    /**
     * Singleton factory method.
     */
    public static function init(\DB\DB $db) {
      static $instance = NULL;
      if (!($instance instanceof SessionHandler)) {
        $instance = new SessionHandler($db);
      }
      return $instance;
    }

    /**
     * Open a new session.
     * If we needed to do something special to open a session, like creating
     * a tmp file to store data, we'd do it here. Since we read/write directly
     * from/to the database, all we need to do is return TRUE.
     * @return (bool) TRUE
     */
    public function open($path, $name) {
      return TRUE;
    }

    /**
     * Close the session.
     * Similiar to the open() method, we don't need to do anything special here
     * and can just return TRUE.
     * @return (bool) TRUE
     */
    public function close() {
      return TRUE;
    }

    /**
     * Destroy the session.
     * This will be called if the session_destroy() function is called.
     * @param $id   session ID
     * @return (bool) TRUE
     */
    public function destroy($id) {
      // Delete the record associated with this ID.
      try {
        $this->db->query("DELETE FROM session WHERE sess_id = :id")
          ->execute(array(':id' => $id));
      }
      catch (\PDOException $e) {
        throw new \RuntimeException($e->getMessage());
      }

      return TRUE;
    }

    /**
     * Garbage collection.
     * This will automatically be called when garbage collection runs to
     * expunge old session records.
     * @param $lifetime   cutoff time to expunge records
     * @return (bool) TRUE
     */
    public function gc($lifetime) {
      // Delete the session records that have expired.
      try {
        $this->db->query("DELETE FROM session WHERE sess_time < :time")
          ->execute(array(':time' => time() - $lifetime));
      }
      catch (\PDOException $e) {
        throw new \RuntimeException($e->getMessage());
      }

      return TRUE;
    }

    /**
     * Read the current session data and return it.
     * @param $id   session ID
     * @return (string) serialized session data
     */
    public function read($id) {
      try {
        $session_data = $this->db->query("SELECT sess_data FROM session WHERE sess_id = :id")
          ->execute(array(':id' => $id))
          ->fetch();

        if ($session_data) {
          return base64_decode($session_data->sess_data);
        } else {
          $this->createNewSession($id);
          return '';
        }
      }
      catch (\PDOException $e) {
        throw new \RuntimeException($e->getMessage());
      }
    }

    /**
     * Write the current session data.
     * @param $id   session ID
     * @param $data serialized session data
     * @return (bool) TRUE
     */
    public function write($id, $data) {
      try {
        $this->db->query("INSERT INTO session (sess_id, sess_data, sess_time) VALUES (:id, :data, :time)
          ON DUPLICATE KEY UPDATE sess_data = VALUES(sess_data), sess_time = VALUES(sess_time)")
          ->execute(array(':id' => $id, ':data' => base64_encode($data), ':time' => time()));
      }
      catch (\PDOException $e) {
        throw new \RuntimeException($e->getMessage());
      }

      return TRUE;
    }

    /**
     * Creates a new session with the given session ID
     * @param $id   session ID
     * @return (bool) TRUE
     */
    private function createNewSession($id) {
      try {
        $this->db->query("INSERT INTO session (sess_id, sess_data, sess_time) VALUES (:id, :data, :time)")
          ->execute(array(':id' => $id, ':data' => '', ':time' => time()));
      }
      catch (\PDOException $e) {
        throw new \RuntimeException($e->getMessage());
      }

      return TRUE;
    }
}
