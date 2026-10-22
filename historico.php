<?php
/**
 * Histórico de Corridas - Linha de Chegada
 * Visualização de Corridas Anteriores, Classificações e Fotos
 */

require_once __DIR__ . '/config/bd.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Histórico de Corridas - Linha de Chegada';
include __DIR__ . '/includes/header.php';

$corridas = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT c.*, 
                   COUNT(CASE WHEN r.tipo_evento = 'chegada' THEN 1 END) as total_atletas,
                   MIN(CASE WHEN r.tipo_evento = 'chegada' THEN r.tempo_ms END) as melhor_tempo_ms
            FROM `corridas` c
            LEFT JOIN `registros_tempo` r ON r.corrida_id = c.id
            GROUP BY c.id
            ORDER BY c.id DESC
        ");
        $corridas = $stmt->fetchAll();
    } catch (PDOException $e) {
        $erroSql = $e->getMessage();
    }
}
?>

<div class="space-y-6">

  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-gray-900/60 p-5 rounded-2xl border border-gray-800">
    <div>
      <h2 class="text-xl font-bold font-brand tracking-wide text-white">🏆 Histórico de Corridas</h2>
      <p class="text-xs text-gray-400 mt-1">Registros oficiais arquivados de todas as corridas e disputas cronometradas.</p>
    </div>
    <a href="index.php" class="self-start sm:self-auto px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition">
      ⏱️ Cronômetro Ativo
    </a>
  </div>

  <?php if (!empty($erroSql)): ?>
    <div class="p-4 rounded-xl bg-red-950/50 border border-red-500/40 text-red-200 text-sm">
      Erro ao consultar banco de dados: <?= htmlspecialchars($erroSql) ?>
    </div>
  <?php endif; ?>

  <?php if (empty($corridas)): ?>
    <div class="p-12 text-center rounded-3xl bg-gray-900/40 border border-gray-800">
      <div class="w-12 h-12 mx-auto rounded-2xl bg-gray-800/60 flex items-center justify-center text-gray-400 mb-3">
        🏁
      </div>
      <h4 class="text-white font-medium">Nenhuma corrida no histórico</h4>
      <p class="text-xs text-gray-500 mt-1">Assim que uma corrida for realizada com o ESP32, ela aparecerá aqui.</p>
    </div>
  <?php else: ?>

    <div class="space-y-4">
      <?php foreach ($corridas as $c): ?>
        <div class="rounded-2xl bg-gray-900/80 border border-gray-800 overflow-hidden shadow-lg hover:border-gray-700 transition">
          <div class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
            
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <h3 class="text-base font-bold text-white"><?= htmlspecialchars($c['titulo']) ?></h3>
                <span class="text-xs px-2 py-0.5 rounded-full font-mono <?= $c['status'] === 'em_andamento' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-gray-800 text-gray-400' ?>">
                  <?= $c['status'] ?>
                </span>
              </div>
              <p class="text-xs text-gray-400">
                Início: <span class="font-mono text-gray-300"><?= $c['data_hora_inicio'] ? date('d/m/Y H:i:s', strtotime($c['data_hora_inicio'])) : 'Não iniciado' ?></span>
                &bull; Origem do disparo: <span class="text-gray-300 font-mono"><?= $c['disparo_origem'] ?></span>
              </p>
            </div>

            <div class="flex items-center gap-4 text-xs font-mono">
              <div class="bg-gray-950/70 px-3 py-2 rounded-xl border border-gray-800">
                <span class="text-gray-500 block">Total Atletas</span>
                <span class="text-white font-bold text-sm"><?= (int)$c['total_atletas'] ?></span>
              </div>
              <div class="bg-gray-950/70 px-3 py-2 rounded-xl border border-gray-800">
                <span class="text-gray-500 block">Melhor Tempo</span>
                <span class="text-emerald-400 font-bold text-sm">
                  <?= $c['melhor_tempo_ms'] ? formatarMilissegundos($c['melhor_tempo_ms']) : '--:--.---' ?>
                </span>
              </div>
              <button onclick="carregarDetalhesCorrida(<?= (int)$c['id'] ?>)" class="px-3.5 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-white font-sans font-medium transition">
                Ver Resultados
              </button>
              <button onclick="excluirCorrida(<?= (int)$c['id'] ?>)" class="p-2 text-gray-500 hover:text-red-400 transition" title="Excluir Corrida">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
              </button>
            </div>

          </div>

          <!-- Container dinâmico para carregar detalhes da corrida -->
          <div id="detalhes-corrida-<?= (int)$c['id'] ?>" class="hidden border-t border-gray-800 bg-gray-950/40 p-4"></div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>

</div>

<script>
async function carregarDetalhesCorrida(corridaId) {
  const container = document.getElementById(`detalhes-corrida-${corridaId}`);
  if (!container) return;

  if (!container.classList.contains('hidden')) {
    container.classList.add('hidden');
    return;
  }

  try {
    const res = await fetch(`api/listar_tempos.php?corrida_id=${corridaId}`);
    const json = await res.json();

    if (!json.success || !json.data.tempos) {
      container.innerHTML = '<p class="text-xs text-gray-500">Nenhum dado encontrado.</p>';
      container.classList.remove('hidden');
      return;
    }

    const tempos = json.data.tempos;
    if (tempos.length === 0) {
      container.innerHTML = '<p class="text-xs text-gray-500">Nenhum tempo registrado para esta corrida.</p>';
      container.classList.remove('hidden');
      return;
    }

    let html = `
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs font-mono">
          <thead class="text-gray-500 border-b border-gray-800">
            <tr>
              <th class="py-2 px-2">Pos</th>
              <th class="py-2 px-2">Atleta</th>
              <th class="py-2 px-2">Tempo</th>
              <th class="py-2 px-2">Sensor</th>
              <th class="py-2 px-2">Foto Finish</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-800/40">
    `;

    tempos.forEach((t, i) => {
      let fotoBtn = '<span class="text-gray-600">--</span>';
      if (t.foto_path) {
        fotoBtn = `<button onclick="Modal.photoFinish('${t.foto_path}', '${t.atleta_identificador}', '${t.tempo_formatado}', ${i+1})" class="text-red-400 hover:underline">Ver Foto</button>`;
      }
      html += `
        <tr>
          <td class="py-2 px-2 text-white font-bold">#${i + 1}</td>
          <td class="py-2 px-2 text-gray-300 font-sans">${t.atleta_identificador}</td>
          <td class="py-2 px-2 text-emerald-400 font-bold">${t.tempo_formatado}</td>
          <td class="py-2 px-2 text-gray-500">${t.origem_sensor}</td>
          <td class="py-2 px-2">${fotoBtn}</td>
        </tr>
      `;
    });

    html += `</tbody></table></div>`;
    container.innerHTML = html;
    container.classList.remove('hidden');

  } catch (e) {
    Toast.error('Erro ao carregar detalhes: ' + e.message);
  }
}

async function excluirCorrida(corridaId) {
  Modal.confirm(
    'Excluir Corrida',
    'Tem certeza de que deseja apagar permanentemente esta corrida e todos os seus tempos e fotos associadas?',
    async () => {
      try {
        const res = await fetch('api/excluir_tempo.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ corrida_id: corridaId })
        });
        const json = await res.json();
        if (json.success) {
          Toast.success('Corrida excluída.');
          setTimeout(() => location.reload(), 800);
        } else {
          Toast.error(json.message);
        }
      } catch (e) {
        Toast.error('Falha ao excluir: ' + e.message);
      }
    }
  );
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
