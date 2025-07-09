document.addEventListener('DOMContentLoaded', () => {
    // Obtención de referencias a los elementos del DOM
    const webcamVideo = document.getElementById('webcamVideo');
    const webcamCanvas = document.getElementById('webcamCanvas');
    const startButton = document.getElementById('startButton');
    const captureButton = document.getElementById('captureButton');

    const usernameInput = document.getElementById('usernameInput');
    const registerButton = document.getElementById('registerButton');
    const authenticateButton = document.getElementById('authenticateButton');

    const statusMessage = document.getElementById('statusMessage');
    const debugOutput = document.getElementById('debugOutput');

    let stream = null; // Variable para almacenar el stream de la cámara

    // Función para añadir mensajes a la consola de depuración en la interfaz
    function logDebug(message) {
        const now = new Date();
        const time = now.toLocaleTimeString();
        debugOutput.textContent += `[${time}] ${message}\n`;
        debugOutput.scrollTop = debugOutput.scrollHeight; // Mantiene el scroll al final
    }

    // Event Listener para el botón 'Iniciar Cámara'
    startButton.addEventListener('click', async () => {
        statusMessage.textContent = 'Iniciando cámara...';
        logDebug('Intentando iniciar la cámara...');
        try {
            // Solicita acceso a la cámara de video del usuario
            stream = await navigator.mediaDevices.getUserMedia({ video: true });
            webcamVideo.srcObject = stream; // Asigna el stream al elemento de video
            webcamVideo.play(); // Inicia la reproducción del video

            // Una vez que el video de la cámara está cargado
            webcamVideo.onloadedmetadata = () => {
                statusMessage.textContent = 'Cámara iniciada. ¡Listo para capturar!';
                startButton.disabled = true; // Deshabilita el botón de inicio
                captureButton.disabled = false; // Habilita el botón de captura
                logDebug('Cámara iniciada y video reproduciéndose.');
                logDebug(`Dimensiones del video: ${webcamVideo.videoWidth}x${webcamVideo.videoHeight}`);
            };
        } catch (err) {
            // Manejo de errores si no se puede acceder a la cámara
            console.error("Error al acceder a la cámara: ", err);
            statusMessage.textContent = `Error: No se pudo iniciar la cámara. (${err.name})`;
            logDebug(`Error al iniciar la cámara: ${err.message}`);
            // Restablece el estado de los botones en caso de error
            startButton.disabled = false;
            captureButton.disabled = true;
            if (registerButton) registerButton.disabled = true;
            if (authenticateButton) authenticateButton.disabled = true;
        }
    });

    // Event Listener para el botón 'Capturar Rostro'
    captureButton.addEventListener('click', () => {
        if (stream) { // Solo si la cámara está activa
            statusMessage.textContent = 'Capturando rostro...';
            logDebug('Capturando imagen del video...');
            const context = webcamCanvas.getContext('2d'); // Contexto 2D del canvas

            // Ajusta el tamaño del canvas al tamaño del video
            webcamCanvas.width = webcamVideo.videoWidth;
            webcamCanvas.height = webcamVideo.videoHeight;

            // Transforma el contexto para voltear la imagen horizontalmente (efecto espejo)
            context.translate(webcamCanvas.width, 0);
            context.scale(-1, 1);
            // Dibuja el fotograma actual del video en el canvas
            context.drawImage(webcamVideo, 0, 0, webcamCanvas.width, webcamCanvas.height);
            context.setTransform(1, 0, 0, 1, 0, 0); // Restaura la transformación del contexto

            statusMessage.textContent = 'Rostro capturado. ¡Listo para enviar!';
            // Habilita los botones de acción (registro/autenticación) después de la captura
            if (registerButton) registerButton.disabled = false;
            if (authenticateButton) authenticateButton.disabled = false;
            logDebug('Imagen capturada en el canvas.');
        } else {
            statusMessage.textContent = 'Error: Cámara no iniciada.';
            logDebug('Intento de captura sin cámara activa.');
        }
    });

    // Función genérica para enviar datos al backend (registro o autenticación)
    async function sendDataToBackend(endpoint, actionType) {
        // Valida que haya una imagen capturada en el canvas
        if (!webcamCanvas.width || !webcamCanvas.height) {
            statusMessage.textContent = 'Error: No hay imagen capturada para enviar.';
            logDebug('Intento de envío sin imagen en el canvas.');
            return;
        }

        // Obtiene y valida el nombre de usuario
        const username = usernameInput.value.trim();
        if (!username) {
            statusMessage.textContent = `Error: Ingresa un nombre de usuario para ${actionType}.`;
            logDebug(`Error: Nombre de usuario vacío para ${actionType}.`);
            return;
        }

        statusMessage.textContent = `Enviando imagen para ${actionType}...`;
        logDebug('Convirtiendo imagen del canvas a Base64...');

        // Convierte la imagen del canvas a formato Base64 (PNG)
        const imageDataURL = webcamCanvas.toDataURL('image/png');
        const base64Image = imageDataURL.split(',')[1]; // Extrae solo los datos Base64

        logDebug(`Enviando solicitud POST a ${endpoint} para ${actionType}...`);

        try {
            // Realiza la solicitud POST al endpoint del backend
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json' // Indica que el cuerpo es JSON
                },
                body: JSON.stringify({ // Convierte los datos a JSON para el cuerpo de la solicitud
                    image_data: base64Image,
                    user_id: username
                })
            });

            const result = await response.json(); // Parsea la respuesta JSON del backend

            if (response.ok) { // Si la respuesta HTTP fue exitosa (código 2xx)
                // Determina si la operación fue un éxito lógico basado en el tipo de acción
                const isSuccess = (actionType === 'registro' && result.success) ||
                    (actionType === 'autenticación' && result.status === 'success');

                if (isSuccess) {
                    statusMessage.textContent = `¡${actionType} exitoso! ${result.message || ''}`;
                } else {
                    statusMessage.textContent = `Fallo en ${actionType}: ${result.message || 'Error desconocido'}`;
                }
                logDebug(`Respuesta del backend (${actionType}): ${JSON.stringify(result)}`);
            } else { // Si la respuesta HTTP indica un error (código 4xx o 5xx)
                statusMessage.textContent = `Error del servidor durante ${actionType}: ${result.message || 'Desconocido'}`;
                logDebug(`Error en la respuesta del backend (${actionType}): ${JSON.stringify(result)}`);
            }
        } catch (error) {
            // Manejo de errores de red o de la solicitud fetch
            console.error(`Error al enviar la imagen para ${actionType}: `, error);
            statusMessage.textContent = `Error de conexión durante ${actionType}: ${error.message}`;
            logDebug(`Error de red o conexión durante ${actionType}: ${error.message}`);
        } finally {
            // Siempre habilita los botones al finalizar la operación
            if (registerButton) registerButton.disabled = false;
            if (authenticateButton) authenticateButton.disabled = false;
        }
    }

    // Event Listener para el botón 'Registrar Usuario'
    if (registerButton) { // Verifica que el botón exista
        registerButton.addEventListener('click', () => {
            registerButton.disabled = true; // Deshabilita los botones durante el envío
            if (authenticateButton) authenticateButton.disabled = true;
            sendDataToBackend('../backend/api/register.php', 'registro'); // Llama a la función genérica para registro
        });
    }

    // Event Listener para el botón 'Autenticar Usuario'
    if (authenticateButton) { // Verifica que el botón exista
        authenticateButton.addEventListener('click', () => {
            if (registerButton) registerButton.disabled = true; // Deshabilita los botones durante el envío
            authenticateButton.disabled = true;
            sendDataToBackend('../backend/api/authenticate.php', 'autenticación'); // Llama a la función genérica para autenticación
        });
    }
});
