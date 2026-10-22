/**
 * Motor do Cronômetro Esportivo de Alta Precisão
 * Linha de Chegada
 */

const Cronometro = (function () {
  let timerDisplay = null;
  let statusBadge = null;
  let animFrameId = null;
  
  let estado = 'sem_corrida'; // 'aguardando_largada', 'em_andamento', 'finalizada', 'sem_corrida'
  let horaInicio = null;
  let tempoDecorridoMs = 0;
  let pollingInterval = null;
  let ultimoRegistroId = 0;

  function init() {
    timerDisplay = document.getElementById('cronometro-display');
    statusBadge = document.getElementById('status-badge');

    // Inicia polling com o backend
    atualizarStatus();
    pollingInterval = setInterval(atualizarStatus, 600);
  }

  function formatarMs(ms) {
    const totalMs = Math.max(0, Math.floor(ms));
    const min = Math.floor(totalMs / 60000);
    const seg = Math.floor((totalMs % 60000) / 1000);
    const msec = totalMs % 1000;

    const pad = (n, s = 2) => String(n).padStart(s, '0');
    return `${pad(min)}:${pad(seg)}.${pad(msec, 3)}`;
  }

  function loopRender() {
    if (estado === 'em_andamento' && horaInicio) {
      const agora = performance.now();
      const tempo = tempoDecorridoMs + (agora - horaInicio);
      if (timerDisplay) {
        timerDisplay.textContent = formatarMs(tempo);
      }
      animFrameId = requestAnimationFrame(loopRender);
    }
  }

  function iniciarContagemLocal(tempoInicialMs = 0) {
    if (animFrameId) cancelAnimationFrame(animFrameId);
    tempoDecorridoMs = tempoInicialMs;
    horaInicio = performance.now();
    animFrameId = requestAnimationFrame(loopRender);
  }

  function pararContagemLocal(tempoFinalMs) {
    if (animFrameId) cancelAnimationFrame(animFrameId);
    if (timerDisplay) {
      timerDisplay.textContent = formatarMs(tempoFinalMs);
    }
  }

  async function atualizarStatus() {
    try {
      const res = await fetch(`api/status_corrida.php?ultimo_id=${ultimoRegistroId}`);
      const json = await res.json();

      if (!json.success) {
        return;
      }

      const d = json.data;
      const novoEstado = d.status;

      // Se mudou de estado para 'em_andamento' (ex: disparo do microfone captado pelo ESP32)
      if (estado !== 'em_andamento' && novoEstado === 'em_andamento') {
        Toast.success('🎤 Disparo de largada detectado! Cronômetro iniciado.');
        iniciarContagemLocal(d.tempo_decorrido_ms);
      } else if (estado === 'em_andamento' && novoEstado === 'finalizada') {
        pararContagemLocal(d.tempo_decorrido_ms);
        Toast.info('🏁 Corrida finalizada!');
      } else if (novoEstado === 'aguardando_largada' && estado !== 'aguardando_largada') {
        if (timerDisplay) timerDisplay.textContent = '00:00.000';
        if (animFrameId) cancelAnimationFrame(animFrameId);
      }

      estado = novoEstado;
      atualizarBadge(estado);

      // Atualiza tabela de tempos
      if (d.registros) {
        renderizarTabelaTempos(d.registros);
      }

      // Notifica se houver novos registros de chegada (SDM-01)
      if (d.novos_registros && d.novos_registros.length > 0) {
        d.novos_registros.forEach(reg => {
          if (reg.id > ultimoRegistroId) {
            ultimoRegistroId = reg.id;
            Toast.info(`⚡ Passagem na linha de chegada: ${reg.posicao}º lugar (${reg.tempo_formatado})`);
            
            // Dispara evento customizado para captura de câmera se estiver ativo
            window.dispatchEvent(new CustomEvent('linhachegada:passagem', { detail: reg }));
          }
        });
      } else if (d.registros && d.registros.length > 0) {
        const maiorId = Math.max(...d.registros.map(r => r.id));
        if (maiorId > ultimoRegistroId) {
          ultimoRegistroId = maiorId;
        }
      }

    } catch (e) {
      console.warn('Erro na atualização de status:', e);
    }
  }

  function atualizarBadge(st) {
    if (!statusBadge) return;

    switch (st) {
      case 'aguardando_largada':
        statusBadge.className = 'px-3 py-1 text-xs font-semibold rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center gap-1.5';
        statusBadge.innerHTML = `<span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span> Aguardando Disparo (Microfone)`;
        break;
      case 'em_andamento':
        statusBadge.className = 'px-3 py-1 text-xs font-semibold rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex items-center gap-1.5';
        statusBadge.innerHTML = `<span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span> Corrida em Andamento`;
        break;
      case 'finalizada':
        statusBadge.className = 'px-3 py-1 text-xs font-semibold rounded-full bg-blue-500/20 text-blue-300 border border-blue-500/30 flex items-center gap-1.5';
        statusBadge.innerHTML = `<span class="w-2 h-2 rounded-full bg-blue-400"></span> Finalizada`;
        break;
      default:
        statusBadge.className = 'px-3 py-1 text-xs font-semibold rounded-full bg-gray-700/40 text-gray-300 border border-gray-600/30 flex items-center gap-1.5';
        statusBadge.innerHTML = `<span class="w-2 h-2 rounded-full bg-gray-400"></span> Aguardando Corrida`;
        break;
    }
  }

  function renderizarTabelaTempos(registros) {
    const tbody = document.getElementById('tabela-tempos-body');
    const emptyState = document.getElementById('empty-state-tempos');
    if (!tbody) return;

    // Apenas eventos de chegada na tabela de classificação
    const chegadas = registros.filter(r => r.tipo_evento === 'chegada');

    if (chegadas.length === 0) {
      tbody.innerHTML = '';
      if (emptyState) emptyState.classList.remove('hidden');
      return;
    }

    if (emptyState) emptyState.classList.add('hidden');

    tbody.innerHTML = chegadas.map((r, index) => {
      const pos = index + 1;
      let badgePos = `<span class="font-bold text-gray-300">#${pos}</span>`;
      if (pos === 1) badgePos = `<span class="px-2 py-0.5 rounded bg-yellow-500/20 text-yellow-300 font-bold border border-yellow-500/40 text-xs">🥇 1º</span>`;
      if (pos === 2) badgePos = `<span class="px-2 py-0.5 rounded bg-slate-400/20 text-slate-200 font-bold border border-slate-400/40 text-xs">🥈 2º</span>`;
      if (pos === 3) badgePos = `<span class="px-2 py-0.5 rounded bg-amber-700/20 text-amber-500 font-bold border border-amber-600/40 text-xs">🥉 3º</span>`;

      let fotoCell = `<span class="text-xs text-gray-500 italic">Sem foto</span>`;
      if (r.foto_path) {
        fotoCell = `
          <button onclick="Modal.photoFinish('${r.foto_path}', '${r.atleta_identificador}', '${r.tempo_formatado}', ${pos})" 
                  class="group relative inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-red-950/40 border border-red-500/30 text-red-300 hover:bg-red-900/60 transition text-xs">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path></svg>
            <span>Ver Foto</span>
          </button>
        `;
      }

      return `
        <tr class="border-b border-gray-800/80 hover:bg-gray-800/30 transition">
          <td class="py-3 px-3 sm:px-4 text-center">${badgePos}</td>
          <td class="py-3 px-3 sm:px-4 font-medium text-white">${r.atleta_identificador}</td>
          <td class="py-3 px-3 sm:px-4 font-mono font-bold text-emerald-400 text-sm sm:text-base">${r.tempo_formatado}</td>
          <td class="py-3 px-3 sm:px-4 text-center">${fotoCell}</td>
          <td class="py-3 px-3 sm:px-4 text-right">
            <button onclick="excluirRegistro(${r.id})" class="text-gray-500 hover:text-red-400 transition p-1" title="Excluir">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            </button>
          </td>
        </tr>
      `;
    }).join('');
  }

  return {
    init,
    atualizarStatus
  };
})();

// Ações Globais de Botões (integrados com Modais)
async function acaoIniciarManual() {
  Modal.confirm(
    'Disparo Manual de Largada',
    'Deseja disparar a largada manualmente agora? O cronômetro iniciará imediatamente do zero.',
    async () => {
      try {
        const res = await fetch('api/iniciar_corrida.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ origem: 'manual_web' })
        });
        const d = await res.json();
        if (d.success) {
          Toast.success(d.message);
          Cronometro.atualizarStatus();
        } else {
          Toast.error(d.message);
        }
      } catch (e) {
        Toast.error('Falha ao comunicar com o servidor: ' + e.message);
      }
    }
  );
}

async function acaoSimularPassagem() {
  try {
    const res = await fetch('api/registrar_tempo.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ origem: 'manual_web' })
    });
    const d = await res.json();
    if (d.success) {
      Toast.success(d.message);
      Cronometro.atualizarStatus();
    } else {
      Toast.warning(d.message);
    }
  } catch (e) {
    Toast.error('Erro ao registrar passagem: ' + e.message);
  }
}

async function acaoResetarCorrida() {
  Modal.confirm(
    'Zerar Cronômetro',
    'Deseja zerar o cronômetro e iniciar uma nova sessão? Os tempos anteriores serão arquivados.',
    async () => {
      try {
        const res = await fetch('api/resetar_corrida.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' }
        });
        const d = await res.json();
        if (d.success) {
          Toast.info(d.message);
          Cronometro.atualizarStatus();
        } else {
          Toast.error(d.message);
        }
      } catch (e) {
        Toast.error('Erro ao resetar: ' + e.message);
      }
    }
  );
}

async function excluirRegistro(id) {
  Modal.confirm(
    'Excluir Registro',
    'Tem certeza que deseja apagar este tempo registrado?',
    async () => {
      try {
        const res = await fetch('api/excluir_tempo.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ registro_id: id })
        });
        const d = await res.json();
        if (d.success) {
          Toast.info('Registro removido.');
          Cronometro.atualizarStatus();
        } else {
          Toast.error(d.message);
        }
      } catch (e) {
        Toast.error('Erro ao excluir: ' + e.message);
      }
    }
  );
}

document.addEventListener('DOMContentLoaded', () => {
  Cronometro.init();
});
