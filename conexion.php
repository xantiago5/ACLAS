<?php
// Configuración de la base de datos
define('DB_ACLAS', 'localhost');
define('DB_NAME', 'sistema_asistencia');
define('DB_USER', 'root');
define('DB_PASS', '');

class Database {
    private $host = DB_ACLAS;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                )
            );
        } catch(PDOException $exception) {
            error_log("Error de conexión: " . $exception->getMessage());
            throw new Exception("Error de conexión a la base de datos");
        }
        return $this->conn;
    }

    public function closeConnection() {
        $this->conn = null;
    }
}

// Función para crear las tablas si no existen
function createTables() {
    try {
        $database = new Database();
        $db = $database->getConnection();

        // Tabla de estudiantes
        $query_students = "CREATE TABLE IF NOT EXISTS estudiantes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(20) UNIQUE NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            apellido VARCHAR(100) NOT NULL,
            documento VARCHAR(20) UNIQUE NOT NULL,
            fecha_nacimiento DATE NOT NULL,
            grado VARCHAR(10) NOT NULL,
            genero ENUM('M', 'F') NOT NULL,
            direccion TEXT NOT NULL,
            telefono VARCHAR(20) NOT NULL,
            email VARCHAR(100),
            foto_url TEXT,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            activo BOOLEAN DEFAULT TRUE,
            INDEX idx_codigo (codigo),
            INDEX idx_grado (grado),
            INDEX idx_documento (documento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        // Tabla de asistencia
        $query_attendance = "CREATE TABLE IF NOT EXISTS asistencia (
            id INT AUTO_INCREMENT PRIMARY KEY,
            estudiante_id INT NOT NULL,
            fecha DATE NOT NULL,
            hora TIME NOT NULL,
            estado ENUM('presente', 'ausente', 'tardanza') DEFAULT 'presente',
            observaciones TEXT,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE,
            UNIQUE KEY unique_attendance (estudiante_id, fecha),
            INDEX idx_fecha (fecha),
            INDEX idx_estudiante_fecha (estudiante_id, fecha)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        // Tabla de configuración
        $query_config = "CREATE TABLE IF NOT EXISTS configuracion (
            id INT AUTO_INCREMENT PRIMARY KEY,
            clave VARCHAR(50) UNIQUE NOT NULL,
            valor TEXT NOT NULL,
            descripcion TEXT,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        // Tabla de logs
        $query_logs = "CREATE TABLE IF NOT EXISTS logs_sistema (
            id INT AUTO_INCREMENT PRIMARY KEY,
            accion VARCHAR(100) NOT NULL,
            tabla_afectada VARCHAR(50),
            registro_id INT,
            datos_anteriores JSON,
            datos_nuevos JSON,
            usuario VARCHAR(50),
            ip_address VARCHAR(45),
            fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_fecha (fecha_hora),
            INDEX idx_accion (accion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $db->exec($query_students);
        $db->exec($query_attendance);
        $db->exec($query_config);
        $db->exec($query_logs);

        echo "Tablas creadas exitosamente o ya existen.";
    } catch (Exception $e) {
        error_log("Error al crear tablas: " . $e->getMessage());
        die("Error al crear tablas: " . $e->getMessage());
    }
}

// Llamar a la función para crear las tablas
// createTables(); // Comentar esta línea después de la primera ejecución

// Funciones adicionales de ejemplo del archivo original
function logSystemEvent($action, $affected_table = null, $record_id = null, $old_data = null, $new_data = null, $user = 'Sistema') {
    try {
        $database = new Database();
        $db = $database->getConnection();
        $query = "INSERT INTO logs_sistema (accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, usuario, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $stmt->execute([
            $action,
            $affected_table,
            $record_id,
            json_encode($old_data),
            json_encode($new_data),
            $user,
            $ip_address
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Error registrando log: " . $e->getMessage());
        return false;
    }
}

function getBasicStats() {
    try {
        $database = new Database();
        $db = $database->getConnection();
        $stats = [];
        $query = "SELECT COUNT(*) as total FROM estudiantes WHERE activo = TRUE";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats['total_estudiantes'] = $stmt->fetch()['total'];
        $query = "SELECT COUNT(*) as total FROM asistencia WHERE fecha = CURDATE()";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats['asistencias_hoy'] = $stmt->fetch()['total'];
        if ($stats['total_estudiantes'] > 0) {
            $stats['tasa_asistencia'] = round(($stats['asistencias_hoy'] / $stats['total_estudiantes']) * 100, 2);
        } else {
            $stats['tasa_asistencia'] = 0;
        }
        $query = "SELECT grado, COUNT(*) as total FROM estudiantes WHERE activo = TRUE GROUP BY grado ORDER BY grado";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats['por_grado'] = $stmt->fetchAll();
        return $stats;
    } catch (Exception $e) {
        error_log("Error obteniendo estadísticas: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al obtener estadísticas.'];
    }
}
?>