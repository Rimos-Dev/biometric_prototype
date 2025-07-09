-- Eliminar tablas si existen para empezar de cero 
DROP TABLE IF EXISTS biometric_templates;
DROP TABLE IF EXISTS users;

-- Tabla para almacenar información de los usuarios
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Tabla para almacenar las plantillas biométricas faciales
CREATE TABLE biometric_templates (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    template_data BYTEA NOT NULL, -- BYTEA para almacenar datos binarios (el vector de características)
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user
        FOREIGN KEY(user_id)
        REFERENCES users(id)
        ON DELETE CASCADE -- Si se elimina un usuario, sus plantillas también se eliminan
);

-- Índices para mejorar el rendimiento de las búsquedas
CREATE INDEX idx_biometric_user_id ON biometric_templates(user_id);
