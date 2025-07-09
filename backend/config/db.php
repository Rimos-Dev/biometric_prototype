<?php

// Configuración para conectar la base de datos PostgreSQL.
define('DB_HOST', 'localhost');
define('DB_NAME', 'biometric_db');
define('DB_USER', 'postgres');
define('DB_PASS', '261093');
define('DB_PORT', '5432');

/**
 * Establece y devuelve una conexión a la base de datos.
 * Esta función es llamada por el código PHP para interactuar con la DB.
 */
function getDbConnection()
{
    try {
        // Conecta a la base de datos usando PDO.
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);

        // Configura PDO para lanzar errores como excepciones.
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo; // Devuelve la conexión activa.
    } catch (PDOException $e) {
        // Si hay un problema de conexión, registra el error y devuelve null.
        error_log("Error de conexión a la base de datos: " . $e->getMessage());
        return null;
    }
}
