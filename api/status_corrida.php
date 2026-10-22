<?php
/**
 * API: Status da Corrida em Tempo Real
 * Retorna dados da corrida atual, estado do cronômetro e registros em ordem cronológica
 */

require_once __DIR__ . '/../config/bd.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!$pdo) {
    responderJson(false, 'Erro de conexão com o banco.', [], 500);
}

$ultimoIdRecebido = isset($_GET['ultimo_id']) ? (int)$_GET['ultimo_id'] : 0;

try {
    // 1. Obtém a corrida mais recente
    $stmt = $pdo->query("SELECT * FROM `corridas` ORDER BY `id` DESC LIMIT 1");
    $corrida = $stmt->fetch();

    if (!$corrida) {
        responderJson(true, 'Nenhuma corrida cadastrada.', [
            'status'         => 'sem_corrida',
            'corrida'        => null,
            'tempo_decorrido'=> 0,
            'registros'      => [],
            'novos_registros'=> []
        ]);
    }

    $tempoDecorridoMs = 0;
    if ($corrida['status'] === 'em_andamento' && !empty($corrida['data_hora_inicio'])) {
        $inicio = strtotime($corrida['data_hora_inicio']);
        $agora = microtime(true);
        $tempoDecorridoMs = max(0, (int)(($agora - $inicio) * 1000));
    } elseif ($corrida['status'] === 'finalizada') {
        $tempoDecorridoMs = (int)$corrida['tempo_total_ms'];
    }

    // 2. Busca todos os registros da corrida em ordem cronológica (requisito SDM-01)
    $stmtReg = $pdo->prepare("
        SELECT `id`, `posicao`, `atleta_identificador`, `tempo_ms`, `tempo_formatado`, 
               `foto_path`, `tipo_evento`, `origem_sensor`, `data_hora` 
        FROM `registros_tempo` 
        WHERE `corrida_id` = :corrida_id 
        ORDER BY `id` ASC
    ");
    $stmtReg->execute([':corrida_id' => $corrida['id']]);
    $registros = $stmtReg->fetchAll();

    // 3. Filtra novos registros para disparo da câmera do celular
    $novosRegistros = [];
    if ($ultimoIdRecebido > 0) {
        foreach ($registros as $r) {
            if ($r['id'] > $ultimoIdRecebido && $r['tipo_evento'] === 'chegada') {
                $novosRegistros[] = $r;
            }
        }
    }

    responderJson(true, 'Status obtido.', [
        'status'             => $corrida['status'],
        'corrida'            => $corrida,
        'tempo_decorrido_ms' => $tempoDecorridoMs,
        'tempo_formatado'    => formatarMilissegundos($tempoDecorridoMs),
        'total_chegadas'     => count(array_filter($registros, fn($r) => $r['tipo_evento'] === 'chegada')),
        'registros'          => $registros,
        'novos_registros'    => $novosRegistros
    ]);

} catch (PDOException $e) {
    responderJson(false, 'Erro ao consultar status: ' . $e->getMessage(), [], 500);
}
