<?php
/**
 * Conexão com o Banco de Dados MySQL via PDO
 * Linha de Chegada - Cronometragem Esportiva
 * 
 * Regra: Uso obrigatório de PDO com prepared statements para segurança contra SQL Injection.
 */

// Inicia sessão PHP nativa se ainda não iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurações de conexão
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'linha_de_chegada');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Obtém ou inicializa a conexão PDO com o MySQL
 * 
 * @return PDO
 * @throws PDOException
 */
function getDBConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Previne SQL Injection a nível de driver nativo
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Se o erro for de banco inexistente (código 1049), tenta criá-lo automaticamente
            if ($e->getCode() == 1049) {
                try {
                    $dsnSemBanco = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
                    $pdoTemp = new PDO($dsnSemBanco, DB_USER, DB_PASS, $options);
                    $pdoTemp->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                    
                    // Conecta novamente com o banco recém-criado
                    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                    inicializarTabelas($pdo);
                } catch (PDOException $ex) {
                    throw new PDOException("Falha ao inicializar banco de dados: " . $ex->getMessage(), (int)$ex->getCode());
                }
            } else {
                throw new PDOException("Falha na conexão com MySQL: " . $e->getMessage(), (int)$e->getCode());
            }
        }
    }

    return $pdo;
}

/**
 * Criação automática das tabelas essenciais caso ainda não existam
 * 
 * @param PDO $pdo
 */
function inicializarTabelas(PDO $pdo): void {
    $sql = "
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

    CREATE TABLE IF NOT EXISTS `registros_tempo` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `corrida_id` INT NOT NULL,
        `posicao` INT NOT NULL DEFAULT 1,
        `atleta_identificador` VARCHAR(100) DEFAULT 'Corredor',
        `tempo_ms` BIGINT NOT NULL,
        `tempo_formatado` VARCHAR(20) NOT NULL,
        `foto_path` VARCHAR(255) NULL,
        `tipo_evento` ENUM('largada', 'passagem', 'chegada') NOT NULL DEFAULT 'chegada',
        `origem_sensor` ENUM('ldr_laser_esp32', 'microfone_esp32', 'manual_web') NOT NULL DEFAULT 'ldr_laser_esp32',
        `data_hora` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`corrida_id`),
        CONSTRAINT `fk_registros_corrida` FOREIGN KEY (`corrida_id`) 
            REFERENCES `corridas` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `configuracoes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `chave` VARCHAR(64) UNIQUE NOT NULL,
        `valor` TEXT NOT NULL,
        `descricao` VARCHAR(255) NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
}

// Instância padrão para compatibilidade direta de scripts legados que usam $pdo
try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    // Em APIs pode ser tratado via JSON; em páginas via modal/toast
    $pdo = null;
    $db_error = $e->getMessage();
}
