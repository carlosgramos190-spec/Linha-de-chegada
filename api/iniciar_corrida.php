<?php
/**
 * API: Iniciar Corrida
 * Caso de Uso: MDA-01 (Microfone / Disparo de Largada)
 * Resumo: Recebe som de disparo no início da corrida e inicia cronômetro zerado.
 */

require_once __DIR__ . '/../config/bd.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!$pdo) {
    responderJson(false, 'Erro de conexão com o banco de dados.', ['erro' => $db_error ?? 'Desconhecido'], 500);
}

// Aceita POST em formato JSON ou Form-Data
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$origem = isset($input['origem']) && in_array($input['origem'], ['microfone_esp32', 'manual_web']) 
    ? $input['origem'] 
    : 'microfone_esp32';

$titulo = !empty($input['titulo']) ? trim($input['titulo']) : 'Corrida #' . date('d/m/Y H:i:s');

try {
    // Verifica se já existe uma corrida 'aguardando_largada'
    $stmt = $pdo->prepare("SELECT * FROM `corridas` WHERE `status` = 'aguardando_largada' ORDER BY `id` DESC LIMIT 1");
    $stmt->execute();
    $corridaExistente = $stmt->fetch();

    $agora = date('Y-m-d H:i:s');

    if ($corridaExistente) {
        $corridaId = $corridaExistente['id'];
        $update = $pdo->prepare("
            UPDATE `corridas` 
            SET `status` = 'em_andamento', 
                `data_hora_inicio` = :agora, 
                `disparo_origem` = :origem 
            WHERE `id` = :id
        ");
        $update->execute([
            ':agora'  => $agora,
            ':origem' => $origem,
            ':id'     => $corridaId
        ]);
    } else {
        // Encerra qualquer corrida que tenha ficado em andamento anterior
        $pdo->query("UPDATE `corridas` SET `status` = 'finalizada' WHERE `status` = 'em_andamento'");

        // Cria nova corrida
        $insert = $pdo->prepare("
            INSERT INTO `corridas` (`titulo`, `status`, `data_hora_inicio`, `disparo_origem`) 
            VALUES (:titulo, 'em_andamento', :agora, :origem)
        ");
        $insert->execute([
            ':titulo' => $titulo,
            ':agora'  => $agora,
            ':origem' => $origem
        ]);
        $corridaId = (int)$pdo->lastInsertId();
    }

    // Registra evento de largada (t = 0 ms)
    $reg = $pdo->prepare("
        INSERT INTO `registros_tempo` (`corrida_id`, `posicao`, `atleta_identificador`, `tempo_ms`, `tempo_formatado`, `tipo_evento`, `origem_sensor`) 
        VALUES (:corrida_id, 0, 'Largada Geral', 0, '00:00.000', 'largada', :origem)
    ");
    $reg->execute([
        ':corrida_id' => $corridaId,
        ':origem'     => $origem
    ]);

    responderJson(true, 'Largada autorizada! Cronômetro iniciado zerado (Caso MDA-01).', [
        'corrida_id'       => $corridaId,
        'status'           => 'em_andamento',
        'data_hora_inicio' => $agora,
        'origem'           => $origem
    ]);

} catch (PDOException $e) {
    responderJson(false, 'Erro ao iniciar corrida: ' . $e->getMessage(), [], 500);
}
