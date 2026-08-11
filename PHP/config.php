<?php
class Database {
    private $serverName = "(localdb)\\MSSQLLocalDB";
    private $database = "NexsyncDB";
    private $conn;
    
    public function getConnection() {
        if ($this->conn === null) {
            try {
                $this->conn = new PDO(
                    "sqlsrv:Server={$this->serverName};Database={$this->database}",
                    null,
                    null,
                    array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    )
                );
            } catch(PDOException $e) {
                error_log("Database Connection Error: " . $e->getMessage());
                return null;
            }
        }
        return $this->conn;
    }
    
    public function query($sql, $params = []) {
        $conn = $this->getConnection();
        if (!$conn) return false;
        
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Query Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }
    
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : false;
    }
    
    public function insert($sql, $params = []) {
        $conn = $this->getConnection();
        if (!$conn) return false;
        
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $conn->lastInsertId();
        } catch(PDOException $e) {
            error_log("Insert Error: " . $e->getMessage());
            return false;
        }
    }

    public function update($sql, $params = []) {
        $conn = $this->getConnection();
        if (!$conn) return false;
        
        try {
            $stmt = $conn->prepare($sql);
            return $stmt->execute($params);
        } catch(PDOException $e) {
            error_log("Update Error: " . $e->getMessage());
            return false;
        }
    }

    public function delete($sql, $params = []) {
        $conn = $this->getConnection();
        if (!$conn) return false;
        
        try {
            $stmt = $conn->prepare($sql);
            return $stmt->execute($params);
        } catch(PDOException $e) {
            error_log("Delete Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
