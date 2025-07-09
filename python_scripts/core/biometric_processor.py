import sys
import json
import cv2
import numpy as np
from insightface.app import FaceAnalysis
import logging
import os
import traceback

# Configuración del log de errores de Python
PYTHON_ERROR_LOG = 'C:\\xampp\\htdocs\\biometric_prototype\\python_scripts\\python_error.log'


def calculate_cosine_similarity(vec1, vec2):
    """Calcula la similitud coseno entre dos vectores."""
    vec1 = np.array(vec1)
    vec2 = np.array(vec2)

    vec1_norm = np.linalg.norm(vec1)
    vec2_norm = np.linalg.norm(vec2)

    if vec1_norm == 0 or vec2_norm == 0:
        return 0.0

    return np.dot(vec1, vec2) / (vec1_norm * vec2_norm)


def main():
    # Configura los loggers de insightface y onnxruntime para que solo muestren errores.
    logging.getLogger('insightface').setLevel(logging.ERROR)
    logging.getLogger('onnxruntime').setLevel(logging.ERROR)

    # Redirige sys.stderr (salida de errores) a un archivo de log al inicio
    try:
        sys.stderr = open(PYTHON_ERROR_LOG, 'a', encoding='utf-8')
        sys.stderr.write(
            f"[{os.path.basename(__file__)}] Script Python iniciado. sys.argv: {sys.argv}\n")
        sys.stderr.flush()
    except Exception as e:
        print(
            f"ERROR: No se pudo abrir el log de errores de Python: {e}", file=sys.stderr)

    # Guarda el stdout original para restaurarlo después.
    original_stdout = sys.stdout
    # Abre /dev/null (o NUL en Windows) para redirigir la salida no deseada de las librerías.
    devnull = open(os.devnull, 'w')

    try:
        # Redirige sys.stdout a devnull antes de inicializar FaceAnalysis.
        sys.stdout = devnull

        # Los argumentos esperados: image_path, user_id, [stored_vector_json_or_path]
        if len(sys.argv) < 3:
            raise ValueError(
                "Argumentos insuficientes. Uso: biometric_processor.py <image_path> <user_id> [stored_vector_json_or_path]")

        image_path = sys.argv[1]
        user_id = sys.argv[2]

        stored_vector_data = None
        if len(sys.argv) > 3:
            stored_vector_data = sys.argv[3]

        # Inicializa FaceAnalysis.
        app = FaceAnalysis(name='buffalo_l', root='~/.insightface')
        app.prepare(ctx_id=-1, det_size=(640, 640))

        # Carga la imagen.
        img = cv2.imread(image_path)  # type: ignore
        if img is None:
            raise FileNotFoundError(
                f"No se pudo cargar la imagen desde: {image_path}. Verifique la ruta y los permisos.")

        # Detecta rostros en la imagen.
        faces = app.get(img)

        # Restaura sys.stdout a su valor original antes de imprimir la respuesta JSON.
        sys.stdout = original_stdout

        if not faces:
            response = {
                "status": "error",
                "message": "No se detectó ningún rostro en la imagen.",
                "user_id": user_id,
                "biometric_result": None,
                "match_result": "no_match"
            }
            print(json.dumps(response))
            sys.exit(0)

        # Procesa el primer rostro detectado.
        face = faces[0]
        current_biometric_vector = face.embedding.tolist()

        match_result = "no_match"
        similarity_score = 0.0

        # Si se proporcionó un vector almacenado (o una ruta a él), realiza la comparación.
        if stored_vector_data:
            stored_biometric_vector = None
            try:
                # Intentar cargar como JSON directo (para registro)
                stored_biometric_vector = json.loads(stored_vector_data)
            except json.JSONDecodeError:
                # Si no es JSON directo, intentar cargar como ruta de archivo (para autenticación)
                try:
                    with open(stored_vector_data, 'r') as f:
                        stored_biometric_vector = json.load(f)
                except FileNotFoundError:
                    similarity_score = -1.0
                    match_result = f"error_reading_biometric_file: {stored_vector_data} not found"
                except json.JSONDecodeError:
                    similarity_score = -1.0
                    match_result = f"error_decoding_biometric_file: {stored_vector_data}"
                except Exception as e:
                    similarity_score = -1.0
                    match_result = f"error_loading_biometric_file: {str(e)}"
            except Exception as e:
                similarity_score = -1.0
                match_result = f"error_processing_stored_vector_data: {str(e)}"

            if stored_biometric_vector:
                similarity_score = calculate_cosine_similarity(
                    current_biometric_vector, stored_biometric_vector)

                # Define el umbral de coincidencia. Ajusta según sea necesario.
                threshold = 0.35

                if similarity_score >= threshold:
                    match_result = "match"
                else:
                    match_result = "no_match"
            else:
                match_result = "no_stored_vector_found"

        # Prepara la respuesta JSON.
        response = {
            "status": "success",
            "message": f"Características biométricas extraídas correctamente para el usuario '{user_id}'. Rostros detectados: {len(faces)}.",
            "user_id": user_id,
            "biometric_result": current_biometric_vector,
            "match_result": match_result,
            "similarity_score": similarity_score
        }
        print(json.dumps(response))

    except FileNotFoundError as e:
        sys.stdout = original_stdout
        sys.stderr.write(
            f"[{os.path.basename(__file__)}] FileNotFoundError: {e}\n")
        sys.stderr.write(traceback.format_exc())
        response = {"status": "error", "message": str(e)}
        print(json.dumps(response))
        sys.exit(1)
    except ValueError as e:
        sys.stdout = original_stdout
        sys.stderr.write(f"[{os.path.basename(__file__)}] ValueError: {e}\n")
        sys.stderr.write(traceback.format_exc())
        response = {"status": "error", "message": str(e)}
        print(json.dumps(response))
        sys.exit(1)
    except Exception as e:
        sys.stdout = original_stdout
        sys.stderr.write(
            f"[{os.path.basename(__file__)}] Error en el script Python: {e}\n")
        sys.stderr.write(traceback.format_exc())
        response = {"status": "error",
                    "message": f"Error en el script Python: {str(e)}"}
        print(json.dumps(response))
        sys.exit(1)
    finally:
        if devnull:
            devnull.close()
        if sys.stderr:
            sys.stderr.close()


if __name__ == "__main__":
    main()
