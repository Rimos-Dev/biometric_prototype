<?php
// Define el tipo de contenido de la respuesta como JSON
header('Content-Type: application/json');

// Configuración de errores para depuración y registro
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
// Ruta donde se guardarán los logs de PHP
ini_set('error_log', 'C:\\xampp\\htdocs\\biometric_prototype\\backend\\php_error.log');

// Inicia el buffer de salida para evitar que se envíe contenido antes de tiempo
ob_start();

// Incluye los archivos de configuración de la base de datos y el servicio de usuario
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/services/UserService.php';

// Estructura inicial de la respuesta en caso de error
$response = [
    "status" => "error",
    "message" => "Ocurrió un error inesperado."
];

// Lee el cuerpo de la solicitud POST (JSON)
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

// Verifica si hubo un error al decodificar el JSON de entrada
if (json_last_error() !== JSON_ERROR_NONE) {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "Error al decodificar JSON de entrada: " . json_last_error_msg() . " | Raw Input: " . substr($rawInput, 0, 200) . "..."]);
    exit();
}

// Obtiene los datos de la imagen y el nombre de usuario de la solicitud
$imageData = $data['image_data'] ?? null;
$username = $data['user_id'] ?? null;

// Valida que los datos necesarios no estén vacíos
if (!$imageData || !$username) {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "Datos incompletos: se requiere image_data y username."]);
    exit();
}

// Define la ruta para la carpeta de imágenes temporales
$tempImagesDir = dirname(__DIR__) . '/temp_images/';
// Crea la carpeta si no existe
if (!is_dir($tempImagesDir)) {
    mkdir($tempImagesDir, 0777, true);
}

// Genera un nombre único para el archivo de imagen temporal
$imageFileName = uniqid('face_') . '.png';
$imagePath = $tempImagesDir . $imageFileName;

// Decodifica la imagen Base64 y la guarda temporalmente
$decodedImage = base64_decode($imageData);
if ($decodedImage === false) {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "Error al decodificar la imagen Base64."]);
    exit();
}

if (file_put_contents($imagePath, $decodedImage) === false) {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "Error al guardar la imagen temporal en el servidor."]);
    exit();
}

// Define las rutas absolutas al ejecutable de Python y al script biométrico
$pythonExecutable = 'C:\\Users\\ASUS\\AppData\\Local\\Programs\\Python\\Python39\\python.exe';
$pythonScriptPath = 'C:\\xampp\\htdocs\\biometric_prototype\\python_scripts\\core\\biometric_processor.py';

// Escapa las rutas para que sean seguras en el comando de shell
$escapedImagePath = escapeshellarg($imagePath);

$storedVectorJson = null;
// Intenta inicializar el servicio de usuario para interactuar con la DB
try {
    $userService = new UserService();
} catch (Exception $e) {
    ob_end_clean();
    error_log("Error al inicializar UserService en authenticate.php: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Error interno del servidor: Fallo al inicializar servicio de usuario para autenticación."]);
    exit();
}

// Busca el usuario por nombre de usuario en la base de datos
$user = $userService->getUserByUsername($username);
if (!$user) {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "Usuario '{$username}' no encontrado en la base de datos."]);
    exit();
}
// Obtiene el ID numérico del usuario
$userId = $user['id'];

// Obtiene las plantillas biométricas del usuario desde la base de datos
$templates = $userService->getBiometricTemplatesByUserId($userId);

// Verifica si se encontraron plantillas biométricas
if (!empty($templates)) {
    // Maneja el caso si template_data es un recurso de la base de datos o un string
    if (is_resource($templates[0])) {
        $storedVectorJson = stream_get_contents($templates[0]);
    } else {
        $storedVectorJson = $templates[0];
    }
} else {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "Usuario '{$username}' encontrado, pero no tiene datos biométricos registrados para autenticación."]);
    exit();
}

// Genera un nombre único para el archivo JSON temporal del vector biométrico
$tempBiometricFileName = 'temp_biometric_' . uniqid() . '.json';
$tempBiometricFilePath = $tempImagesDir . $tempBiometricFileName;

// Guarda el vector biométrico almacenado en un archivo JSON temporal
if (file_put_contents($tempBiometricFilePath, $storedVectorJson) === false) {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "Error al guardar la plantilla biométrica temporal."]);
    exit();
}
// Escapa la ruta del archivo biométrico temporal
$escapedBiometricFilePath = escapeshellarg($tempBiometricFilePath);

// Escapa el ID de usuario para pasarlo a Python
$escapedUserIdForPython = escapeshellarg($userId);

// Configura variables de entorno para Python y construye el comando completo
$envVars = "SET OMP_NUM_THREADS=1 & SET KMP_AFFINITY=noverbose & SET ONNX_WARNINGS_SUPPRESS=1 & ";
// Construye el comando para ejecutar el script Python con todos los argumentos
$command = "{$envVars}\"{$pythonExecutable}\" \"{$pythonScriptPath}\" \"{$escapedImagePath}\" \"{$escapedUserIdForPython}\" \"{$escapedBiometricFilePath}\"";

// Registra el comando en el log de PHP
error_log("Comando Python a ejecutar (authenticate.php): " . $command);
// Ejecuta el comando Python y captura su salida
$pythonOutput = shell_exec($command);

// Asegura que $pythonOutput sea una cadena para evitar errores si shell_exec devuelve null
if ($pythonOutput === null) {
    $pythonOutput = '';
    error_log("shell_exec devolvió NULL para el comando Python en authenticate.php.");
}
// Registra la salida bruta de Python en el log de PHP
error_log("Salida bruta de Python (authenticate.php): " . $pythonOutput);

// Elimina los archivos temporales generados
if (file_exists($imagePath)) {
    unlink($imagePath);
}
if (file_exists($tempBiometricFilePath)) {
    unlink($tempBiometricFilePath);
}

// Verifica si la salida de Python está vacía
if (empty($pythonOutput)) {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "El comando Python no devolvió ninguna salida válida o la salida está vacía. Verifique la ejecución del script Python."]);
    exit();
}

// Encuentra y extrae la cadena JSON de la salida de Python
$json_start_pos = strpos($pythonOutput, '{');
$json_end_pos = strrpos($pythonOutput, '}');

$json_string = '';
if ($json_start_pos !== false && $json_end_pos !== false && $json_end_pos > $json_start_pos) {
    $json_string = substr($pythonOutput, $json_start_pos, $json_end_pos - $json_start_pos + 1);
    $json_string = trim($json_string);
}

// Decodifica la cadena JSON de Python
$pythonResponse = json_decode($json_string, true);

// Verifica si hubo un error al decodificar el JSON de Python
if (json_last_error() !== JSON_ERROR_NONE) {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "Error al decodificar la salida JSON de Python: " . json_last_error_msg() . " | Salida Python cruda: " . $pythonOutput . " | JSON extraído: " . $json_string]);
    exit();
}

// Procesa la respuesta recibida del script Python
if ($pythonResponse['status'] === 'success') {
    // Si la autenticación biométrica fue exitosa
    if ($pythonResponse['match_result'] === 'match') {
        try {
            // Intenta obtener la conexión a la base de datos
            $pdo = getDbConnection();
            if ($pdo) {
                // Actualiza la fecha de último login del usuario en la base de datos
                $stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :user_id");
                $stmt->execute([':user_id' => $userId]);
                $message = "Autenticación exitosa. Similitud: " . round($pythonResponse['similarity_score'], 4);
                $authStatus = "authenticated";
            } else {
                $message = "Autenticación exitosa, pero no se pudo actualizar el login. Similitud: " . round($pythonResponse['similarity_score'], 4);
                $authStatus = "authenticated";
            }
        } catch (PDOException $e) {
            // Registra cualquier error al actualizar el login
            error_log("Error al actualizar last_login en authenticate.php: " . $e->getMessage());
            $message = "Autenticación exitosa, pero error al actualizar el registro de login. Similitud: " . round($pythonResponse['similarity_score'], 4);
            $authStatus = "authenticated";
        }
    } else {
        // Si no hubo coincidencia biométrica
        $message = "Autenticación fallida o resultado inesperado. Similitud: " . round($pythonResponse['similarity_score'], 4);
        $authStatus = "failed";
    }

    // Prepara la respuesta final para el frontend
    $response = [
        "status" => "success",
        "message" => $message,
        "user_id" => $username,
        "auth_status" => $authStatus,
        "similarity_score" => $pythonResponse['similarity_score'] ?? 0.0
    ];
} else {
    // Si el script Python reportó un error, pasa su respuesta directamente
    $response = $pythonResponse;
}

// Limpia el buffer de salida y envía la respuesta JSON
ob_end_clean();
echo json_encode($response);
exit();
