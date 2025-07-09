Prototipo de Autenticación Biométrica Facial
Este proyecto es un sistema de autenticación biométrica que usa el reconocimiento facial para registrar y verificar usuarios. Combina tecnologías web, un backend PHP y procesamiento de imágenes con Python e Inteligencia Artificial.

Características
Registro de Usuario: Permite registrar nuevos usuarios capturando su rostro.

Autenticación Facial: Verifica la identidad comparando un rostro actual con uno registrado.

Detección de Rostros: Identifica rostros en las imágenes.

Extracción de Características: Convierte rostros en "huellas digitales" únicas (vectores biométricos).

Comparación Biométrica: Mide la similitud entre dos huellas digitales para confirmar una coincidencia.

Estructura del Proyecto
biometric_prototype/
├── backend/                  # Lógica del servidor (PHP)
│   ├── api/                  # Puntos de entrada para registro y autenticación
│   ├── config/               # Configuración de base de datos
│   ├── services/             # Lógica de negocio y acceso a datos
│   ├── temp_images/          # Imágenes y datos biométricos temporales
│   └── php_error.log         # Errores de PHP
├── database/                 # Archivos de la base de datos
│   └── schema.sql            # Esquema para crear tablas
├── python_scripts/           # Scripts Python para biometría
│   ├── core/
│   │   └── biometric_processor.py # Procesamiento facial principal
│   ├── models/
│   │   └── buffalo_l/        # Modelos ONNX de InsightFace
│   └── python_error.log      # Errores de Python
├── public/                   # Archivos web (accesibles por navegador)
│   ├── css/
│   │   └── styles.css        # Estilos de la aplicación
│   ├── js/
│   │   └── main.js           # Lógica JavaScript del frontend
│   └── index.html            # Página principal
├── README.md                 # Este archivo
└── .gitignore                # Reglas para Git

Requisitos Previos
Necesitas instalar:

XAMPP: Para el servidor web (Apache) y PHP (versión 8.x o superior).

PostgreSQL: Servidor de base de datos.

Python 3.9 o superior: Con pip y las librerías: opencv-python, numpy, insightface, onnxruntime.

Configuración del Proyecto
Sigue estos pasos para configurar y ejecutar:

1. Configuración de la Base de Datos (PostgreSQL)
a.  Crear la Base de Datos:
Crea una base de datos llamada biometric_db en tu cliente de PostgreSQL.

b.  Ejecutar el Esquema SQL:
Ejecuta el contenido de database/schema.sql en biometric_db. Esto creará las tablas users y biometric_templates.

```sql
-- Contenido de database/schema.sql
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS biometric_templates (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    template_data BYTEA NOT NULL, -- O TEXT/JSONB según tu base de datos
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```
**Nota:** `template_data` almacena el vector biométrico como una cadena JSON. `TEXT` o `JSONB` son tipos de datos adecuados en PostgreSQL.

c.  Configurar db.php:
Abre backend/config/db.php y ajusta las credenciales de la base de datos a las tuyas.

```php
// backend/config/db.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'biometric_db');
define('DB_USER', 'postgres'); // Tu usuario
define('DB_PASS', 'tu_contraseña'); // Tu contraseña
define('DB_PORT', '5432');
```

2. Configuración del Entorno Python
a.  Instalar Librerías Python:
En tu terminal, ejecuta:

```bash
pip install opencv-python numpy insightface onnxruntime
```

b.  Descargar Modelos InsightFace:
La primera vez que biometric_processor.py se ejecute, InsightFace descargará los modelos (buffalo_l). Necesitas conexión a internet. Los modelos se guardan en C:\Users\TU_USUARIO\.insightface\models\buffalo_l (o similar). Verifica que esta carpeta contenga los 5 archivos .onnx.

c.  Verificar Ruta del Ejecutable Python:
En backend/api/authenticate.php y backend/api/register.php, asegúrate de que $pythonExecutable apunte a la ruta correcta de tu Python.

```php
// En authenticate.php y register.php
$pythonExecutable = 'C:\\Users\\ASUS\\AppData\\Local\\Programs\\Python\\Python39\\python.exe'; // Ajusta a tu ruta
```

3. Configuración del Servidor Web (XAMPP)
a.  Colocar el Proyecto:
Copia biometric_prototype dentro de C:\xampp\htdocs\.

b.  Iniciar Apache:
Asegúrate de que Apache esté funcionando en XAMPP.

c.  Permisos de Carpeta:
Asegúrate de que biometric_prototype/backend/temp_images/ tenga permisos de escritura para el servidor web.

Cómo Usar la Aplicación
Abrir la Aplicación:
Ve a http://localhost/biometric_prototype/public/index.html en tu navegador.

Iniciar la Cámara:
Haz clic en "Iniciar Cámara" y concede los permisos.

Capturar Rostro:
Haz clic en "Capturar Rostro" para tomar una instantánea.

Registro de Usuario:

Ingresa un "Nombre de Usuario".

Haz clic en "Registrar Usuario".

El sistema guardará tu plantilla biométrica.

Autenticación de Usuario:

Asegúrate de que tu rostro esté visible.

Haz clic en "Capturar Rostro".

Ingresa tu "Nombre de Usuario" registrado.

Haz clic en "Autenticar Usuario".

El sistema verificará tu identidad.

Tecnologías Utilizadas
Frontend: HTML5, CSS3, JavaScript (WebRTC, Canvas, Fetch API)

Backend: PHP, PostgreSQL (Base de Datos), PDO

Procesamiento Biométrico: Python, OpenCV (cv2), NumPy, InsightFace, ONNX Runtime

Solución de Problemas Comunes
"Error al iniciar la cámara": Cámara no conectada o permisos denegados.

"Error Python/JSON": Revisa rutas de Python en PHP, logs (php_error.log, python_error.log), instalación de librerías y descarga de modelos InsightFace.

"No existe la columna 'last_login'": Ejecuta ALTER TABLE users ADD COLUMN last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP; en PostgreSQL.

"No se detectó ningún rostro": Asegura buena iluminación y que tu rostro esté centrado.

¡Esperamos que disfrutes este prototipo!