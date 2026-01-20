-- ============================================
-- Tabla para OAuth states de Mercado Pago
-- Almacena tokens temporales para validar callbacks
-- ============================================

CREATE TABLE IF NOT EXISTS wp_mp_oauth_states (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    state_token VARCHAR(255) NOT NULL UNIQUE,
    usuario_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    INDEX idx_state_token (state_token),
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limpiar states expirados (ejecutar periódicamente)
-- DELETE FROM wp_mp_oauth_states WHERE expires_at < NOW() OR used = 1;

SELECT 'Tabla wp_mp_oauth_states creada exitosamente' as resultado;
