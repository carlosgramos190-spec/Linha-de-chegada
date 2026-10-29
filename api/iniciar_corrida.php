<?php
/**
 * API: Iniciar Corrida (Com Precisão de Milissegundos para Microfone do Celular/PC + ESP32)
 * Caso de Uso: MDA-01 (Microfone / Disparo de Largada)
 */

require_once __DIR__ . '/../config/bd.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!$pdo) {
    responderJson(false, 'Erro de conexão com o banco de dados.', ['erro' => $db_error ?? 'Desconhecido'], 500);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$origemRecebida = $input['origem'] ?? 'microfone_esp32';
// Mapeia para valores válidos do ENUM do banco mantendo identificação
$origemBanco = in_array($origemRecebida, ['microfone_esp32', 'manual_web']) ? $origemRecebida : 'microfone_esp32';

$titulo = !empty($input['titulo']) ? trim($input['titulo']) : 'Corrida #' . date('d/m/Y H:i:s');
$epochInicioMs = (int)round(microtime(true) * 1000);
$agora = date('Y-m-d H:i:s');

try {
    // Evita duplo disparo se a corrida já iniciou há menos de 2 segundos (caso Celular e ESP32 disparem juntos)
    $stmtAtiva = $pdo->query("SELECT * FROM `corridas` WHERE `status` = 'em_andamento' ORDER BY `id` DESC LIMIT 1");
    $corridaAtiva = $stmtAtiva->fetch();
    if ($corridaAtiva && !empty($corridaAtiva['observacoes']) && is_numeric($corridaAtiva['observacoes'])) {
        $inicioAnteriorMs = (int)$corridaAtiva['observacoes'];
        if (($epochInicioMs - $inicioAnteriorMs) < 2500) {
            responderJson(true, 'Corrida já iniciada em sincronia.', [
                'corrida_id'       => (int)$corridaAtiva['id'],
                'status'           => 'em_andamento',
                'epoch_inicio_ms'  => $inicioAnteriorMs,
                'origem'           => $origemRecebida
            ]);
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM `corridas` WHERE `status` = 'aguardando_largada' ORDER BY `id` DESC LIMIT 1");
    $stmt->execute();
    $corridaExistente = $stmt->fetch();

    if ($corridaExistente) {
        $corridaId = (int)$corridaExistente['id'];
        $update = $pdo->prepare("
            UPDATE `corridas` 
            SET `status` = 'em_andamento', 
                `data_hora_inicio` = :agora, 
                `disparo_origem` = :origem,
                `observacoes` = :epoch_ms
            WHERE `id` = :id
        ");
        $update->execute([
            ':agora'    => $agora,
            ':origem'   => $origemBanco,
            ':epoch_ms' => (string)$epochInicioMs,
            ':id'       => $corridaId
        ]);
    } else {
        $pdo->query("UPDATE `corridas` SET `status` = 'finalizada' WHERE `status` = 'em_andamento'");

        $insert = $pdo->prepare("
            INSERT INTO `corridas` (`titulo`, `status`, `data_hora_inicio`, `disparo_origem`, `observacoes`) 
            VALUES (:titulo, 'em_andamento', :agora, :origem, :epoch_ms)
        ");
        $insert->execute([
            ':titulo'   => $titulo,
            ':agora'    => $agora,
            ':origem'   => $origemBanco,
            ':epoch_ms' => (string)$epochInicioMs
        ]);
        $corridaId = (int)$pdo->lastInsertId();
    }

    $reg = $pdo->prepare("
        INSERT INTO `registros_tempo` (`corrida_id`, `posicao`, `atleta_identificador`, `tempo_ms`, `tempo_formatado`, `tipo_evento`, `origem_sensor`) 
        VALUES (:corrida_id, 0, 'Largada Geral', 0, '00:00.000', 'largada', :origem)
    ");
    $reg->execute([
        ':corrida_id' => $corridaId,
        ':origem'     => $origemBanco
    ]);

    responderJson(true, 'Largada disparada! Cronômetro iniciado em t = 0.000s (MDA-01).', [
        'corrida_id'       => $corridaId,
        'status'           => 'em_andamento',
        'data_hora_inicio' => $agora,
        'epoch_inicio_ms'  => $epochInicioMs,
        'origem'           => $origemRecebida
    ]);

} catch (PDOException $e) {
    responderJson(false, 'Erro ao iniciar corrida: ' . $e->getMessage(), [], 500);
}
