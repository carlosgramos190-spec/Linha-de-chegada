<?php
/**
 * API: Listar Tempos e Histórico de Corridas
 */

require_once __DIR__ . '/../config/bd.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!$pdo) {
    responderJson(false, 'Erro de conexão.', [], 500);
}

$corridaId = isset($_GET['corrida_id']) ? (int)$_GET['corrida_id'] : null;

try {
    if ($corridaId) {
        // Retorna tempos de uma corrida específica
        $stmt = $pdo->prepare("
            SELECT r.*, c.titulo as corrida_titulo 
            FROM `registros_tempo` r
            JOIN `corridas` c ON c.id = r.corrida_id
            WHERE r.corrida_id = :cid
            ORDER BY r.tempo_ms ASC
        ");
        $stmt->execute([':cid' => $corridaId]);
        $tempos = $stmt->fetchAll();

        responderJson(true, 'Tempos da corrida.', ['tempos' => $tempos]);
    } else {
        // Retorna resumo de todas as corridas
        $stmt = $pdo->query("
            SELECT c.*, 
                   COUNT(CASE WHEN r.tipo_evento = 'chegada' THEN 1 END) as total_corredores,
                   MIN(CASE WHEN r.tipo_evento = 'chegada' THEN r.tempo_ms END) as melhor_tempo_ms
            FROM `corridas` c
            LEFT JOIN `registros_tempo` r ON r.corrida_id = c.id
            GROUP BY c.id
            ORDER BY c.id DESC
        ");
        $corridas = $stmt->fetchAll();

        responderJson(true, 'Lista de corridas.', ['corridas' => $corridas]);
    }
} catch (PDOException $e) {
    responderJson(false, 'Erro ao carregar dados: ' . $e->getMessage(), [], 500);
}
