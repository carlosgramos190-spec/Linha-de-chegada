<?php
/**
 * Funções auxiliares para o Linha de Chegada
 */

/**
 * Converte milissegundos para string formatada MM:SS.mmm
 * 
 * @param int|float $ms
 * @return string
 */
function formatarMilissegundos($ms): string {
    $ms = max(0, (int)$ms);
    $minutos = floor($ms / 60000);
    $segundos = floor(($ms % 60000) / 1000);
    $restoMs = $ms % 1000;

    return sprintf('%02d:%02d.%03d', $minutos, $segundos, $restoMs);
}

/**
 * Retorna uma resposta JSON padronizada e encerra o script
 * 
 * @param bool $success
 * @param string $message
 * @param array $data
 * @param int $httpCode
 */
function responderJson(bool $success, string $message, array $data = [], int $httpCode = 200): void {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'   => $success,
        'message'   => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'data'      => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Obtém a corrida ativa atual ou cria uma nova se nenhuma estiver em andamento ou aguardando
 * 
 * @param PDO $pdo
 * @return array
 */
function obterCorridaAtiva(PDO $pdo): ?array {
    $stmt = $pdo->prepare("
        SELECT * FROM `corridas` 
        WHERE `status` IN ('aguardando_largada', 'em_andamento') 
        ORDER BY `id` DESC LIMIT 1
    ");
    $stmt->execute();
    $corrida = $stmt->fetch();

    return $corrida ?: null;
}
