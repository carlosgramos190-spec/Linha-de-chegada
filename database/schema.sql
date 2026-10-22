-- =======================================================
-- Sistema Linha de Chegada - Cronometragem Esportiva com ESP32
-- Banco de Dados: MySQL (Compatível com PDO)
-- =======================================================

CREATE DATABASE IF NOT EXISTS `linha_de_chegada` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `linha_de_chegada`;

-- 1. Tabela de Corridas / Sessões
CREATE TABLE IF NOT EXISTS `corridas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `titulo` VARCHAR(120) NOT NULL DEFAULT 'Corrida sem título',
    `status` ENUM('aguardando_largada', 'em_andamento', 'finalizada', 'cancelada') NOT NULL DEFAULT 'aguardando_largada',
    `data_hora_inicio` DATETIME NULL,
    `data_hora_fim` DATETIME NULL,
    `tempo_total_ms` BIGINT NULL DEFAULT 0,
    `disparo_origem` ENUM('microfone_esp32', 'manual_web') DEFAULT 'microfone_esp32',
    `observacoes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabela de Registros de Tempo (Passagens e Chegadas)
-- Atende aos Casos de Uso SDM-01 (Sensor LDR) e MDA-01 (Microfone)
CREATE TABLE IF NOT EXISTS `registros_tempo` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `corrida_id` INT NOT NULL,
    `posicao` INT NOT NULL DEFAULT 1,
    `atleta_identificador` VARCHAR(100) DEFAULT 'Corredor',
    `tempo_ms` BIGINT NOT NULL COMMENT 'Tempo decorrido em milissegundos desde o disparo',
    `tempo_formatado` VARCHAR(20) NOT NULL COMMENT 'Formato MM:SS.mmm',
    `foto_path` VARCHAR(255) NULL COMMENT 'Caminho da foto capturada pelo celular (Photo Finish)',
    `tipo_evento` ENUM('largada', 'passagem', 'chegada') NOT NULL DEFAULT 'chegada',
    `origem_sensor` ENUM('ldr_laser_esp32', 'microfone_esp32', 'manual_web') NOT NULL DEFAULT 'ldr_laser_esp32',
    `data_hora` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_registros_corrida` FOREIGN KEY (`corrida_id`) 
        REFERENCES `corridas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabela de Configurações do Sistema e Sensores
CREATE TABLE IF NOT EXISTS `configuracoes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `chave` VARCHAR(64) UNIQUE NOT NULL,
    `valor` TEXT NOT NULL,
    `descricao` VARCHAR(255) NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserção de configurações padrão para sensores e cronômetro
INSERT INTO `configuracoes` (`chave`, `valor`, `descricao`) VALUES
('limiar_ldr', '2500', 'Limiar analógico do LDR para detecção de interrupção do feixe laser (0 a 4095 no ESP32)'),
('limiar_som', '650', 'Sensibilidade mínima do microfone para disparar largada'),
('tempo_debounce_ms', '800', 'Tempo mínimo entre detecções de passagens consecutivas para evitar repetição acidental'),
('foto_finish_auto', '1', 'Habilitar captura fotográfica automática do celular ao cortar o laser (1 = Sim, 0 = Não)'),
('servidor_url', 'http://192.168.1.100/linha_de_chegada', 'URL base do servidor PHP para o ESP32')
ON DUPLICATE KEY UPDATE `chave` = `chave`;
