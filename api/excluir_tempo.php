<?php
/**
 * API: Excluir Registro ou Limpar Dados
 */

require_once __DIR__ . '/../config/bd.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!$pdo) {
    responderJson(false, 'Erro de conexão.', [], 500);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$registroId = isset($input['registro_id']) ? (int)$input['registro_id'] : 0;
$corridaId = isset($input['corrida_id']) ? (int)$input['corrida_id'] : 0;

try {
    if ($registroId > 0) {
        $stmt = $pdo->prepare("DELETE FROM `registros_tempo` WHERE `id` = :id");
        $stmt->execute([':id' => $registroId]);
        responderJson(true, 'Registro excluído com sucesso.');
    } elseif ($corridaId > 0) {
        $stmt = $pdo->prepare("DELETE FROM `corridas` WHERE `id` = :id");
        $stmt->execute([':id' => $corridaId]);
        responderJson(true, 'Corrida e seus tempos excluídos.');
    } else {
        responderJson(false, 'ID não fornecido.', [], 400);
    }
} catch (PDOException $e) {
    responderJson(false, 'Erro ao excluir: ' . $e->getMessage(), [], 500);
}
