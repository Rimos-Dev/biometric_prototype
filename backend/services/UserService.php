<?php
// Incluye el archivo de conexión a la base de datos.
require_once __DIR__ . '/../config/db.php';

// Clase para gestionar operaciones relacionadas con usuarios y plantillas biométricas.
class UserService
{
    private $pdo; // Instancia de la conexión PDO a la base de datos.

    /**
     * Constructor: Establece la conexión a la base de datos.
     * @throws Exception Si la conexión a la DB falla.
     */
    public function __construct()
    {
        $this->pdo = getDbConnection(); // Intenta conectar a la DB.
        if (!$this->pdo) {
            throw new Exception("No se pudo establecer conexión con la base de datos en UserService.");
        }
    }

    /**
     * Busca un usuario por su nombre de usuario.
     * @param string $username Nombre de usuario a buscar.
     * @return array|null Datos del usuario (id, username, email) o null si no se encuentra.
     */
    public function getUserByUsername($username)
    {
        $stmt = $this->pdo->prepare("SELECT id, username, email FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Registra un nuevo usuario en la base de datos.
     * @param string $username Nombre de usuario.
     * @param string $email Correo electrónico.
     * @return int|false ID del nuevo usuario o false en caso de error.
     */
    public function registerUser($username, $email)
    {
        try {
            $this->pdo->beginTransaction(); // Inicia una transacción para atomicidad.

            $stmt = $this->pdo->prepare("INSERT INTO users (username, email) VALUES (:username, :email) RETURNING id");
            $stmt->execute(['username' => $username, 'email' => $email]);
            $userId = $stmt->fetchColumn(); // Obtiene el ID generado.

            $this->pdo->commit(); // Confirma la transacción.
            return $userId;
        } catch (PDOException $e) {
            $this->pdo->rollBack(); // Revierte la transacción si hay un error.
            error_log("Error al registrar usuario: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Guarda una plantilla biométrica para un usuario.
     * Elimina plantillas anteriores del mismo usuario para mantener una única plantilla activa.
     * @param int $userId ID del usuario.
     * @param string $templateData Datos de la plantilla biométrica (JSON string).
     * @return bool True si se guarda correctamente, false en caso de error.
     */
    public function saveBiometricTemplate($userId, $templateData)
    {
        try {
            // Elimina plantillas existentes para este usuario.
            $deleteStmt = $this->pdo->prepare("DELETE FROM biometric_templates WHERE user_id = :user_id");
            $deleteStmt->execute(['user_id' => $userId]);

            // Inserta la nueva plantilla biométrica.
            $stmt = $this->pdo->prepare("INSERT INTO biometric_templates (user_id, template_data) VALUES (:user_id, :template_data)");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            // PDO::PARAM_LOB es adecuado para datos grandes o binarios como JSON de vectores.
            $stmt->bindParam(':template_data', $templateData, PDO::PARAM_LOB);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            error_log("Error al guardar plantilla biométrica: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene las plantillas biométricas de un usuario específico.
     * @param int $userId ID del usuario.
     * @return array Array de plantillas (cada una como un string).
     */
    public function getBiometricTemplatesByUserId($userId)
    {
        $stmt = $this->pdo->prepare("SELECT template_data FROM biometric_templates WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        // Retorna solo los valores de la columna 'template_data'.
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * Obtiene todas las plantillas biométricas y sus IDs de usuario.
     * Útil para autenticación 1:N (buscar un rostro entre todos los registrados).
     * @return array Array de arrays asociativos con 'user_id' y 'template_data'.
     */
    public function getAllBiometricTemplates()
    {
        $stmt = $this->pdo->query("SELECT user_id, template_data FROM biometric_templates");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
