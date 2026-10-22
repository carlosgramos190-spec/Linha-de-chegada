<?php
/**
 * API: Salvar Foto Capturada pelo Celular (Photo Finish)
 * Recebe imagem via base64 ou multipart upload e associa ao registro de chegada.
 */

require_once __DIR__ . '/../config/bd.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!$pdo) {
    responderJson(false, 'Erro de conexão com o banco de dados.', [], 500);
}

$uploadDir = __DIR__ . '/../uploads/photo_finish/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Aceita JSON ou POST com arquivo
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$registroId = isset($input['registro_id']) ? (int)$input['registro_id'] : null;

try {
    // Se nenhum registro_id for informado, vincula ao último registro de chegada feito
    if (!$registroId) {
        $stmtUltimo = $pdo->query("
            SELECT `id` FROM `registros_tempo` 
            WHERE `tipo_evento` = 'chegada' 
            ORDER BY `id` DESC LIMIT 1
        ");
        $ultimo = $stmtUltimo->fetch();
        if ($ultimo) {
            $registroId = (int)$ultimo['id'];
        }
    }

    $nomeArquivo = 'photofinish_' . ($registroId ? "reg{$registroId}_" : '') . date('Ymd_His') . '_' . uniqid() . '.jpg';
    $caminhoCompleto = $uploadDir . $nomeArquivo;
    $caminhoRelativo = 'uploads/photo_finish/' . $nomeArquivo;

    $sucesso = false;

    // 1. Caso venha como Base64 (Data URL da câmera do celular via Canvas)
    if (!empty($input['imagem_base64'])) {
        $dados = $input['imagem_base64'];
        if (preg_match('/^data:image\/(\w+);base64,/', $dados, $tipo)) {
            $dados = substr($dados, strpos($dados, ',') + 1);
            $dados = base64_decode($dados);
            if ($dados !== false) {
                file_put_contents($caminhoCompleto, $dados);
                $sucesso = true;
            }
        }
    }
    // 2. Caso venha via Upload tradicional Multipart ($_FILES)
    elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['foto']['tmp_name'];
        if (move_uploaded_file($tmpName, $caminhoCompleto)) {
            $sucesso = true;
        }
    }

    if (!$sucesso) {
        responderJson(false, 'Nenhuma imagem válida recebida.', [], 400);
    }

    // Atualiza o registro no banco se tiver registro_id
    if ($registroId) {
        $stmtUpdate = $pdo->prepare("
            UPDATE `registros_tempo` 
            SET `foto_path` = :foto 
            WHERE `id` = :id
        ");
        $stmtUpdate->execute([
            ':foto' => $caminhoRelativo,
            ':id'   => $registroId
        ]);
    }

    responderJson(true, 'Foto da linha de chegada salva com sucesso!', [
        'registro_id' => $registroId,
        'foto_url'    => $caminhoRelativo,
        'nome_arquivo'=> $nomeArquivo
    ]);

} catch (Exception $e) {
    responderJson(false, 'Erro ao processar imagem: ' . $e->getMessage(), [], 500);
}
