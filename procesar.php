<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'conexion.php';

class AttendanceSystem {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    // Registrar nuevo estudiante
    public function registerStudent($data) {
        try {
            // Validar datos requeridos
            $required_fields = ['nombre', 'apellido', 'documento', 'fecha_nacimiento', 'grado', 'genero', 'direccion', 'telefono'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("El campo {$field} es requerido");
                }
            }
            // Generar código único
            $codigo = $this->generateUniqueCode();
            $query = "INSERT INTO estudiantes (codigo, nombre, apellido, documento, fecha_nacimiento, grado, genero, direccion, telefono, email, foto_url)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                $codigo,
                trim($data['nombre']),
                trim($data['apellido']),
                trim($data['documento']),
                $data['fecha_nacimiento'],
                $data['grado'],
                $data['genero'],
                trim($data['direccion']),
                trim($data['telefono']),
                trim($data['email']),
                $data['foto_url'] ?? null
            ]);
            if ($result) {
                logSystemEvent('Registro de estudiante', 'estudiantes', $this->db->lastInsertId(), null, $data, 'API');
                return ['success' => true, 'message' => 'Estudiante registrado con éxito', 'codigo' => $codigo];
            }
            throw new Exception("Error al registrar el estudiante.");
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return ['success' => false, 'message' => 'Error: El documento o código ya existe.'];
            }
            error_log("Error PDO en registerStudent: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error de la base de datos: ' . $e->getMessage()];
        } catch (Exception $e) {
            error_log("Error en registerStudent: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Registrar asistencia
    public function registerAttendance($data) {
        try {
            $required_fields = ['codigo'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("El campo {$field} es requerido");
                }
            }
            $query = "SELECT id, activo FROM estudiantes WHERE codigo = ? LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$data['codigo']]);
            $student = $stmt->fetch();
            if (!$student) {
                throw new Exception("Estudiante no encontrado con el código proporcionado.");
            }
            if (!$student['activo']) {
                throw new Exception("El estudiante está inactivo.");
            }
            $estudiante_id = $student['id'];
            $fecha = date('Y-m-d');
            $hora = date('H:i:s');
            $query_check = "SELECT id FROM asistencia WHERE estudiante_id = ? AND fecha = ?";
            $stmt_check = $this->db->prepare($query_check);
            $stmt_check->execute([$estudiante_id, $fecha]);
            if ($stmt_check->fetch()) {
                throw new Exception("La asistencia para este estudiante ya ha sido registrada hoy.");
            }
            $query_insert = "INSERT INTO asistencia (estudiante_id, fecha, hora, estado, observaciones) VALUES (?, ?, ?, ?, ?)";
            $stmt_insert = $this->db->prepare($query_insert);
            $estado = $data['estado'] ?? 'presente';
            $observaciones = $data['observaciones'] ?? '';
            $result = $stmt_insert->execute([$estudiante_id, $fecha, $hora, $estado, $observaciones]);
            if ($result) {
                logSystemEvent('Registro de asistencia', 'asistencia', $this->db->lastInsertId(), null, $data, 'API');
                return ['success' => true, 'message' => 'Asistencia registrada con éxito.'];
            }
            throw new Exception("Error al registrar la asistencia.");
        } catch (Exception $e) {
            error_log("Error en registerAttendance: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Otras funciones (getStudents, getAttendanceRecords, getStatistics, etc.)
    public function getStudents($filters) {
        // Implementación de la función
        // ...
    }

    public function getAttendanceRecords($filters) {
        // Implementación de la función
        // ...
    }
    
    public function getStatistics($period) {
        // Implementación de la función
        // ...
    }

    private function generateUniqueCode() {
        // Implementación de la función para generar un código único
        // ...
    }
}

// Iniciar el sistema
try {
    $system = new AttendanceSystem();
    $method = $_SERVER['REQUEST_METHOD'];
    $response = ['success' => false, 'message' => 'Solicitud no reconocida'];

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'register_student':
                    $response = $system->registerStudent($input['data']);
                    break;
                case 'register_attendance':
                    $response = $system->registerAttendance($input['data']);
                    break;
                default:
                    $response = ['success' => false, 'message' => 'Acción POST no reconocida'];
            }
        }
    } elseif ($method === 'GET') {
        // Manejar solicitudes GET
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'get_students':
                    $filters = [
                        'grado' => $_GET['grado'] ?? '',
                        'genero' => $_GET['genero'] ?? '',
                        'search' => $_GET['search'] ?? ''
                    ];
                    $response = $system->getStudents($filters);
                    break;
                case 'get_attendance':
                    $filters = [
                        'fecha' => $_GET['fecha'] ?? '',
                        'grado' => $_GET['grado'] ?? '',
                        'estado' => $_GET['estado'] ?? ''
                    ];
                    $response = $system->getAttendanceRecords($filters);
                    break;
                case 'get_statistics':
                    $period = $_GET['period'] ?? 'today';
                    $response = $system->getStatistics($period);
                    break;
                default:
                    $response = ['success' => false, 'message' => 'Acción GET no reconocida'];
            }
        }
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>