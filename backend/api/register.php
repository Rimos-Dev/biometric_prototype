<?php
// Configuración de errores para depuración y registro
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
// Ruta donde se guardarán los logs de PHP
ini_set('error_log', 'C:\\xampp\\htdocs\\biometric_prototype\\backend\\php_error.log');

// Inicia el buffer de salida para evitar que se envíe contenido antes de tiempo
ob_start();

// Incluye los archivos de configuración de la base de datos y el servicio de usuario
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../services/UserService.php';

// Define el tipo de contenido de la respuesta como JSON
header('Content-Type: application/json');

// Estructura inicial de la respuesta en caso de error
$response = [
    'success' => false,
    'message' => '',
    'user_id' => null,
    'python_status' => 'not_executed',
    'python_message' => '',
    'biometric_data' => null
];

// Procesa solo solicitudes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Intenta inicializar el servicio de usuario para interactuar con la DB
    try {
        $userService = new UserService();
    } catch (Exception $e) {
        $response['message'] = "Error interno del servidor: Fallo al inicializar servicio de base de datos.";
        error_log("Error al inicializar UserService en register.php: " . $e->getMessage());
        ob_end_clean();
        echo json_encode($response);
        exit;
    }

    // Lee el cuerpo de la solicitud POST (JSON)
    $json_input = file_get_contents("php://input");
    $data = json_decode($json_input, true);

    // Verifica si hubo un error al decodificar el JSON de entrada
    if (json_last_error() !== JSON_ERROR_NONE) {
        $response['message'] = 'Error al decodificar JSON de entrada: ' . json_last_error_msg();
        error_log("Error al decodificar JSON en register.php: " . json_last_error_msg() . " | Input: " . substr($json_input, 0, 200));
        ob_end_clean();
        echo json_encode($response);
        exit;
    }

    // Obtiene los datos del nombre de usuario, imagen Base64 y correo electrónico
    $username = $data['user_id'] ?? null;
    $base64_image = $data['image_data'] ?? null;
    $email = $data['email'] ?? $username . "@example.com"; // Asigna un email por defecto si no se proporciona

    // Valida que los datos necesarios no estén vacíos
    if (empty($username) || empty($base64_image)) {
        $response['message'] = 'Error: Se requiere "user_id" y "image_data" para el registro.';
        error_log("Datos JSON incompletos en register.php: user_id o image_data faltantes.");
        ob_end_clean();
        echo json_encode($response);
        exit;
    }

    $response['user_id'] = $username;

    // Reemplaza espacios por signos '+' en Base64 para una decodificación correcta
    $base64_image = str_replace(' ', '+', $base64_image);

    // Decodifica la imagen Base64
    $image_data = base64_decode($base64_image);

    // Verifica si la decodificación de la imagen falló
    if ($image_data === false) {
        $response['message'] = 'Error: No se pudo decodificar la imagen Base64. Datos inválidos.';
        error_log("Error decodificando Base64 para registro de usuario: " . $username);
        ob_end_clean();
        echo json_encode($response);
        exit;
    }

    // Define la ruta para la carpeta de imágenes temporales
    $temp_dir = dirname(__DIR__) . '/temp_images/';

    // Crea la carpeta temporal si no existe
    if (!is_dir($temp_dir)) {
        if (!mkdir($temp_dir, 0777, true)) {
            $response['message'] = 'Error: No se pudo crear el directorio temporal para imágenes.';
            error_log("Error: No se pudo crear directorio temp_images en register.php: " . $temp_dir);
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
    }

    // Genera un nombre único para el archivo de imagen temporal
    $temp_image_filename = 'temp_register_image_' . uniqid() . '.png';
    $temp_image_path = $temp_dir . $temp_image_filename;

    // Guarda la imagen decodificada en el archivo temporal
    if (file_put_contents($temp_image_path, $image_data)) {
        $response['message'] = "Imagen recibida y guardada temporalmente para registro: $temp_image_filename.";

        // Define las rutas absolutas al script Python y su ejecutable
        $python_script = 'C:\\xampp\\htdocs\\biometric_prototype\\python_scripts\\core\\biometric_processor.py';
        $python_executable = 'C:\\Users\\ASUS\\AppData\\Local\\Programs\\Python\\Python39\\python.exe';

        // Escapa las rutas y el nombre de usuario para que sean seguras en el comando de shell
        $escaped_image_path = escapeshellarg($temp_image_path);
        $escaped_username = escapeshellarg($username);

        // Construye el comando para ejecutar el script Python.
        // El último argumento es una cadena vacía porque en el registro no hay vector almacenado para comparar.
        // '2> NUL' redirige la salida de error de Python a la nada para no mezclarla con el JSON.
        $command = "$python_executable $python_script $escaped_image_path $escaped_username \"\" 2> NUL";

        // Registra el comando en el log de PHP
        error_log("Comando Python a ejecutar (register.php): " . $command);
        // Ejecuta el comando Python y captura su salida
        $python_output = shell_exec($command);
        // Registra la salida bruta de Python en el log de PHP
        error_log("Salida bruta de Python (register.php): " . $python_output);

        // Encuentra y extrae la cadena JSON de la salida de Python
        $json_start_pos = strpos($python_output, '{');
        $json_end_pos = strrpos($python_output, '}');

        $json_string = '';
        if ($json_start_pos !== false && $json_end_pos !== false && $json_end_pos > $json_start_pos) {
            $json_string = substr($python_output, $json_start_pos, $json_end_pos - $json_start_pos + 1);
            $json_string = trim($json_string); // Elimina espacios en blanco alrededor del JSON
        }

        // Decodifica la cadena JSON extraída de Python
        $python_result = json_decode($json_string, true);

        // Elimina la imagen temporal después de procesarla
        if (file_exists($temp_image_path)) {
            unlink($temp_image_path);
            error_log("DEBUG: Imagen temporal eliminada: " . $temp_image_path);
        }

        // Verifica si la decodificación del JSON de Python fue exitosa y si es un array
        if (json_last_error() === JSON_ERROR_NONE && is_array($python_result)) {
            // Actualiza la respuesta con el estado y mensaje de Python
            $response['python_status'] = $python_result['status'] ?? 'unknown_status';
            $response['python_message'] = $python_result['message'] ?? 'No message from Python.';
            $response['biometric_data'] = $python_result['biometric_result'] ?? null;
            $response['message'] .= " Python: " . $response['python_message'];

            $biometric_vector = $python_result['biometric_result'] ?? null;

            // Si se obtuvo un vector biométrico válido de Python
            if ($biometric_vector) {
                // Verifica si el usuario ya existe en la base de datos
                $existingUser = $userService->getUserByUsername($username);

                if ($existingUser) {
                    $response['message'] = "Error: El usuario '{$username}' ya existe.";
                    $response['success'] = false;
                } else {
                    // Registra el nuevo usuario en la base de datos
                    $newUserId = $userService->registerUser($username, $email);

                    if ($newUserId) {
                        $response['message'] .= " Usuario '{$username}' registrado con ID {$newUserId}.";
                        // Guarda la plantilla biométrica asociada al nuevo usuario
                        if ($userService->saveBiometricTemplate($newUserId, json_encode($biometric_vector))) {
                            $response['message'] .= " Plantilla biométrica guardada. Registro Completo.";
                            $response['success'] = true;
                            $response['user_id'] = $newUserId;
                        } else {
                            $response['message'] .= " Falló la guarda de plantilla biométrica para el usuario ID {$newUserId}.";
                        }
                    } else {
                        $response['message'] .= " Falló el registro del nuevo usuario '{$username}'.";
                    }
                }
            } else {
                $response['message'] .= " Error: No se recibió un vector biométrico válido de Python.";
            }
        } else {
            // Si hubo un error al interpretar la salida JSON de Python
            $response['python_status'] = 'error_parsing_python_output';
            $response['python_message'] = "Error al interpretar la salida de Python. Salida cruda: " . $python_output . " | JSON extraído: " . $json_string;
            $response['message'] .= " Error en Python: " . $response['python_message'];
            error_log("JSON Error en PHP (register.php): " . json_last_error_msg());
        }
    } else {
        // Si falló la escritura del archivo temporal de la imagen
        $response['message'] = 'Error: No se pudo guardar la imagen temporalmente.';
        error_log("Error file_put_contents (register.php): No se pudo escribir en " . $temp_image_path);
    }
} else {
    // Si la solicitud no es POST
    $response['message'] = 'Error: Solo se permiten solicitudes POST a este endpoint.';
}

// Limpia el buffer de salida y envía la respuesta JSON
ob_end_clean();
echo json_encode($response);
exit();
