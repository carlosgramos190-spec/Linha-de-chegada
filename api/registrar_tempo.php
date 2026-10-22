<?php
/**
 * API: Registrar Tempo de Passagem / Linha de Chegada
 * Caso de Uso: SDM-01 (Sensor de Movimento / Fotoresistor LDR + Laser)
 * Resumo: Registra quando algo passar e anota o tempo em ordem cronológica.
 * Pré-condição: Cronômetro iniciado (corrida em andamento).
 */

require_once __DIR__ . '/../config/bd.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!$pdo) {
    responderJson(false, 'Erro de conexão com o banco de dados.', ['erro' => $db_error ?? 'Desconhecido'], 500);
}

// Aceita POST JSON ou Form
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

try {
    // 1. Obtém a corrida em andamento
    $stmt = $pdo->prepare("
        SELECT * FROM `corridas` 
        WHERE `status` = 'em_andamento' 
        ORDER BY `id` DESC LIMIT 1
    ");
    $stmt->execute();
    $corrida = $stmt->fetch();

    if (!$corrida) {
        responderJson(false, 'Não há nenhuma corrida em andamento no momento. Pré-condição não atendida (SDM-01).', [
            'alerta' => 'Inicie o cronômetro antes de registrar passagens.'
        ], 400);
    }

    $corridaId = (int)$corrida['id'];
    $inicioTimestamp = strtotime($corrida['data_hora_inicio']);
    $agoraTimestamp = microtime(true);

    // 2. Determina o tempo decorrido em milissegundos
    if (isset($input['tempo_ms']) && is_numeric($input['tempo_ms']) && (int)$input['tempo_ms'] > 0) {
        // Tempo de alta precisão vindo diretamente do ESP32
        $tempoMs = (int)$input['tempo_ms'];
    } else {
        // Cálculo pelo relógio do servidor com frações de segundo
        $tempoMs = (int)(($agoraTimestamp - $inicioTimestamp) * 1000);
    }

    // 3. Verifica debounce (evita registros duplicados em fração de segundo pelo mesmo corredor)
    $stmtUltimo = $pdo->prepare("
        SELECT `tempo_ms` FROM `registros_tempo` 
        WHERE `corrida_id` = :corrida_id AND `tipo_evento` = 'chegada' 
        ORDER BY `id` DESC LIMIT 1
    ");
    $stmtUltimo->execute([':corrida_id' => $corridaId]);
    $ultimo = $stmtUltimo->fetch();

    $debounceMs = 500; // Mínimo de 500ms entre passagens consecutivas
    if ($ultimo && ($tempoMs - (int)$ultimo['tempo_ms']) < $debounceMs) {
        responderJson(false, 'Detecção ignorada por filtro de debounce (intervalo muito curto).', [
            'delta_ms' => $tempoMs - (int)$ultimo['tempo_ms']
        ], 429);
    }

    // 4. Calcula a posição sequencial (1º, 2º, 3º...)
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

    // 5. Insere o tempo registrado em ordem cronológica
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

    // Atualiza o tempo_total_ms na corrida
    $updateCorrida = $pdo->prepare("UPDATE `corridas` SET `tempo_total_ms` = :tempo_ms WHERE `id` = :id");
    $updateCorrida->execute([':tempo_ms' => $tempoMs, ':id' => $corridaId]);

    responderJson(true, "Tempo registrado com sucesso! Posição: {$posicao}º lugar.", [
        'registro_id'     => $registroId,
        'corrida_id'      => $corridaId,
        'posicao'         => $posicao,
        'atleta'          => $identificador,
        'tempo_ms'        => $tempoMs,
        'tempo_formatado' => $tempoFormatado,
        'origem'          => $origem,
        'solicitar_foto'  => true // Sinaliza para o celular tirar a foto do Photo Finish
    ]);

} catch (PDOException $e) {
    responderJson(false, 'Erro ao registrar tempo: ' . $e->getMessage(), [], 500);
}
