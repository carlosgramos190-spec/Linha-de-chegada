<?php
/**
 * Conexão com o Banco de Dados MySQL via PDO (Otimizada para Tempo Real / Baixa Latência)
 * Linha de Chegada - Cronometragem Esportiva
 * 
 * Otimizações aplicadas:
 * - Uso de 127.0.0.1 em vez de localhost (elimina atraso de ~1s de resolução IPv6 no Windows)
 * - Conexão persistente PDO (PDO::ATTR_PERSISTENT => true) para reutilizar socket TCP (< 0.5ms)
 * - Sem session_start() bloqueante nas APIs de tempo real
 */

// Configurações de conexão (127.0.0.1 evita delay de DNS IPv6 no Windows)
$envHost = getenv('DB_HOST');
define('DB_HOST', ($envHost && $envHost !== 'localhost') ? $envHost : '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'linha_de_chegada');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Obtém ou inicializa a conexão PDO persistente com o MySQL
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
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => true, // Reutiliza conexão TCP em tempo real
            PDO::ATTR_TIMEOUT            => 2
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if ($e->getCode() == 1049) {
                try {
                    $dsnSemBanco = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
                    $pdoTemp = new PDO($dsnSemBanco, DB_USER, DB_PASS, $options);
                    $pdoTemp->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                    
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

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    $pdo = null;
    $db_error = $e->getMessage();
}
