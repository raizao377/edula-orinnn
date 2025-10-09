<?php
require_once __DIR__ . "/../config/database.php";

class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $nome;
    public $matricula;
    public $password;
    public $role;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Verificar login
    public function login($matricula, $password) {
        $query = "SELECT id, nome, matricula, role FROM " . $this->table_name . " WHERE matricula = :matricula AND password = :password LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":matricula", $matricula);
        $stmt->bindParam(":password", $password);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            $this->id = $row["id"];
            $this->nome = $row["nome"];
            $this->matricula = $row["matricula"];
            $this->role = $row["role"];
            return true;
        }
        
        return false;
    }

    // Criar novo usuário
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (nome, matricula, password, role) VALUES (:nome, :matricula, :password, :role)";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar dados
        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->matricula = htmlspecialchars(strip_tags($this->matricula));
        $this->password = htmlspecialchars(strip_tags($this->password));
        $this->role = htmlspecialchars(strip_tags($this->role));
        
        // Bind dos parâmetros
        $stmt->bindParam(":nome", $this->nome);
        $stmt->bindParam(":matricula", $this->matricula);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":role", $this->role);
        
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }

    // Verificar se matrícula já existe
    public function matriculaExists($matricula) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE matricula = :matricula LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":matricula", $matricula);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            return true;
        }
        
        return false;
    }
}
?>

