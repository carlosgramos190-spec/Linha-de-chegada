<?php
/**
 * API: Registrar Tempo de Passagem / Linha de Chegada (Alta Precisão em Milissegundos)
 * Caso de Uso: SDM-01 (Sensor Fotoresistor LDR + Laser na Porta 4)
 */

require_once __DIR__ . '/../config/bd.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!$pdo) {
    responderJson(false, 'Erro de conexão com o banco de dados.', ['erro' => $db_error ?? 'Desconhecido'], 500);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

try {
    $stmt = $pdo->prepare("
        SELECT * FROM `corridas` 
        WHERE `status` = 'em_andamento' 
        ORDER BY `id` DESC LIMIT 1
    ");
    $stmt->execute();
    $corrida = $stmt->fetch();

    if (!$corrida) {
        responderJson(false, 'Não há corrida em andamento no momento. Dê a largada primeiro (MDA-01).', [
            'alerta' => 'Inicie o cronômetro antes de registrar passagens.'
        ], 400);
    }

    $corridaId = (int)$corrida['id'];
    $agoraEpochMs = (int)round(microtime(true) * 1000);

    // Determina o tempo decorrido em milissegundos:
    // 1. Se o ESP32 enviou tempo_ms > 0 (quando o próprio ESP32 deu a largada), usa o clock de hardware do ESP32.
    // 2. Se a largada veio do Microfone do Celular/PC (tempo_ms == 0 no ESP32), calcula com precisão de 1ms pelo epoch_inicio_ms!
    if (isset($input['tempo_ms']) && is_numeric($input['tempo_ms']) && (int)$input['tempo_ms'] > 0) {
        $tempoMs = (int)$input['tempo_ms'];
    } elseif (!empty($corrida['observacoes']) && is_numeric($corrida['observacoes'])) {
        $inicioEpochMs = (int)$corrida['observacoes'];
        $tempoMs = max(1, $agoraEpochMs - $inicioEpochMs);
    } else {
        $inicioTimestamp = strtotime($corrida['data_hora_inicio']);
        $tempoMs = max(1, (int)((microtime(true) - $inicioTimestamp) * 1000));
    }

    // Verifica debounce (evita registros duplicados pelo mesmo corredor)
    $stmtUltimo = $pdo->prepare("
        SELECT `tempo_ms` FROM `registros_tempo` 
        WHERE `corrida_id` = :corrida_id AND `tipo_evento` = 'chegada' 
        ORDER BY `id` DESC LIMIT 1
    ");
    $stmtUltimo->execute([':corrida_id' => $corridaId]);
    $ultimo = $stmtUltimo->fetch();

    $debounceMs = 450;
    if ($ultimo && abs($tempoMs - (int)$ultimo['tempo_ms']) < $debounceMs) {
        responderJson(false, 'Detecção ignorada por filtro de debounce.', [
            'delta_ms' => abs($tempoMs - (int)$ultimo['tempo_ms'])
        ], 429);
    }

    $stmtCount = $pdo->prepare("
        SELECT COUNT(*) as total FROM `registros_tempo` 
        WHERE `corrida_id` = :corrida_id AND `tipo_evento` = 'chegada'
    ");
    $stmtCount->execute([':corrida_id' => $corridaId]);
    $posicao = (int)$stmtCount->fetchColumn() + 1;

    $tempoFormatado = formatarMilissegundos($tempoMs);
    $identificador = !empty($input['atleta']) ? trim($input['atleta']) : "Corredor #{$posicao}";
    $origem = isset($input['origem']) && in_array($input['origem'], ['ldr_laser_esp32', 'manual_web']) 
        ? $input['origem'] 
        : 'ldr_laser_esp32';

    $insert = $pdo->prepare("
        INSERT INTO `registros_tempo` 
        (`corrida_id`, `posicao`, `atleta_identificador`, `tempo_ms`, `tempo_formatado`, `tipo_evento`, `origem_sensor`) 
        VALUES (:corrida_id, :posicao, :identificador, :tempo_ms, :tempo_formatado, 'chegada', :origem)
    ");
    $insert->execute([
        ':corrida_id'     => $corridaId,
        ':posicao'        => $posicao,
        ':identificador'  => $identificador,
        ':tempo_ms'       => $tempoMs,
        ':tempo_formatado'=> $tempoFormatado,
        ':origem'         => $origem
    ]);

    $registroId = (int)$pdo->lastInsertId();

    $updateCorrida = $pdo->prepare("UPDATE `corridas` SET `tempo_total_ms` = :tempo_ms WHERE `id` = :id");
    $updateCorrida->execute([':tempo_ms' => $tempoMs, ':id' => $corridaId]);

    responderJson(true, "Tempo registrado: {$posicao}º lugar ({$tempoFormatado})!", [
        'registro_id'     => $registroId,
        'corrida_id'      => $corridaId,
        'posicao'         => $posicao,
        'atleta'          => $identificador,
        'tempo_ms'        => $tempoMs,
        'tempo_formatado' => $tempoFormatado,
        'origem'          => $origem,
        'solicitar_foto'  => true
    ]);

} catch (PDOException $e) {
    responderJson(false, 'Erro ao registrar tempo: ' . $e->getMessage(), [], 500);
}
