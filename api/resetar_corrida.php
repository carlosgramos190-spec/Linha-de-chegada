<?php
/**
 * API: Resetar Corrida / Preparar Cronômetro Zerado
 * Caso de Uso: MDA-01 (Pré-condição: Iniciar cronômetro zerado)
 */

require_once __DIR__ . '/../config/bd.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!$pdo) {
    responderJson(false, 'Erro de conexão com o banco.', [], 500);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$novoTitulo = !empty($input['titulo']) ? trim($input['titulo']) : 'Corrida #' . date('d/m/Y H:i');

try {
    // 1. Finaliza qualquer corrida em andamento
    $pdo->query("UPDATE `corridas` SET `status` = 'finalizada' WHERE `status` = 'em_andamento'");

    // 2. Cria nova sessão pronta para receber o disparo (MDA-01)
    $stmt = $pdo->prepare("
        INSERT INTO `corridas` (`titulo`, `status`, `data_hora_inicio`, `disparo_origem`) 
        VALUES (:titulo, 'aguardando_largada', NULL, 'microfone_esp32')
    ");
    $stmt->execute([':titulo' => $novoTitulo]);
    $novaId = (int)$pdo->lastInsertId();

    responderJson(true, 'Cronômetro zerado e preparado! Aguardando detecção do disparo de som pelo microfone.', [
        'corrida_id' => $novaId,
        'status'     => 'aguardando_largada',
        'titulo'     => $novoTitulo
    ]);

} catch (PDOException $e) {
    responderJson(false, 'Erro ao zerar cronômetro: ' . $e->getMessage(), [], 500);
}
