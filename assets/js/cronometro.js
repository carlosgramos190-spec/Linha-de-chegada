/**
 * Motor do Cronômetro Esportivo de Alta Precisão (Otimizado para Tempo Real / Zero Delay)
 * Com Detecção Híbrida de Largada: Microfone do Celular/PC (Web Audio API) + Microfone do ESP32
 */

const Cronometro = (function () {
  let timerDisplay = null;
  let statusBadge = null;
  let animFrameId = null;
  
  let estado = 'sem_corrida'; // 'aguardando_largada', 'em_andamento', 'finalizada', 'sem_corrida'
  let horaInicio = null;
  let tempoDecorridoMs = 0;
  let pollTimeoutId = null;
  let requisicaoEmAndamento = false;
  let ultimoRegistroId = 0;
  let ultimaAssinaturaTabela = '';

  function init() {
    timerDisplay = document.getElementById('cronometro-display');
    statusBadge = document.getElementById('status-badge');

    cicloTempoReal();
  }

  async function cicloTempoReal() {
    await atualizarStatus();
    if (pollTimeoutId) clearTimeout(pollTimeoutId);
    pollTimeoutId = setTimeout(cicloTempoReal, 150);
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
    estado = 'em_andamento';
    atualizarBadge(estado);
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
    if (requisicaoEmAndamento) return;
    requisicaoEmAndamento = true;

    try {
      const res = await fetch(`api/status_corrida.php?ultimo_id=${ultimoRegistroId}`, {
        cache: 'no-store'
      });
      const json = await res.json();

      if (!json.success) {
        return;
      }

      const d = json.data;
      const novoEstado = d.status;

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

      if (estado !== novoEstado) {
        estado = novoEstado;
        atualizarBadge(estado);
      }

      if (d.registros) {
        const assinatura = d.registros.map(r => `${r.id}:${r.tempo_ms}:${r.foto_path || ''}`).join('|');
        if (assinatura !== ultimaAssinaturaTabela) {
          ultimaAssinaturaTabela = assinatura;
          renderizarTabelaTempos(d.registros);
        }
      }

      if (d.novos_registros && d.novos_registros.length > 0) {
        d.novos_registros.forEach(reg => {
          if (reg.id > ultimoRegistroId) {
            ultimoRegistroId = reg.id;
            Toast.info(`⚡ Passagem na linha de chegada: ${reg.posicao}º lugar (${reg.tempo_formatado})`);
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
      // Silencioso em ciclo rápido
    } finally {
      requisicaoEmAndamento = false;
    }
  }

  function atualizarBadge(st) {
    if (!statusBadge) return;

    switch (st) {
      case 'aguardando_largada':
        statusBadge.className = 'px-3 py-1 text-xs font-semibold rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center gap-1.5';
        statusBadge.innerHTML = `<span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span> Aguardando Pico (Celular ou ESP32)`;
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
    atualizarStatus,
    iniciarContagemLocal,
    getEstado: () => estado
  };
})();

// Ações Globais Instantâneas
async function acaoIniciarManual(origemDisparo = 'manual_web') {
  try {
    Cronometro.iniciarContagemLocal(0);
    // Notifica também o ESP32 em paralelo se estiver acessível
    SensibilidadeESP32.notificarLargadaEsp32();

    const res = await fetch('api/iniciar_corrida.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ origem: origemDisparo })
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

async function excluirRegistro(id) {
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

document.addEventListener('DOMContentLoaded', () => {
  Cronometro.init();
  SensibilidadeESP32.init();
});

/**
 * Controlador Híbrido de Sensibilidade:
 * - Microfone do Celular/PC (Web Audio API 60fps em Tempo Real) + Microfone do ESP32 (Porta 5)
 * - Sensor LDR do ESP32 (Porta 4)
 */
const SensibilidadeESP32 = (function () {
  let audioCtx = null;
  let analyser = null;
  let micStream = null;
  let dataArray = null;
  let micLocalAtivo = false;
  let gravandoPico3s = false;
  let picoMaximoGravacao3s = 0;
  let ultimoDisparoLocalEpoch = 0;

  function fetchComTimeout(url, options = {}, timeoutMs = 1200) {
    const controller = new AbortController();
    const id = setTimeout(() => controller.abort(), timeoutMs);
    return fetch(url, { ...options, signal: controller.signal })
      .finally(() => clearTimeout(id));
  }

  function getEsp32BaseUrl() {
    const input = document.getElementById('esp32-ip-input');
    let ip = input ? input.value.trim() : (localStorage.getItem('esp32_ip') || '192.168.4.1');
    if (!ip) ip = '192.168.4.1';
    localStorage.setItem('esp32_ip', ip);
    if (!ip.startsWith('http://') && !ip.startsWith('https://')) {
      ip = 'http://' + ip;
    }
    return ip.replace(/\/+$/, '');
  }

  function atualizarBadgeEsp32(online, texto) {
    const badge = document.getElementById('esp32-conn-badge');
    if (!badge) return;
    if (online) {
      badge.className = 'text-[11px] px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-mono';
      badge.textContent = texto || 'ESP32 Sincronizado';
    } else {
      badge.className = 'text-[11px] px-2.5 py-0.5 rounded-full bg-sky-500/20 text-sky-300 border border-sky-500/30 font-mono';
      badge.textContent = texto || 'Mic Celular/PC Pronto';
    }
  }

  function atualizarPreviewLimiarMic(val) {
    const num = Math.max(1, Math.min(100, parseInt(val, 10) || 35));
    const elRange = document.getElementById('input-mic-range');
    const elNum = document.getElementById('input-mic-limiar');
    const elLabel = document.getElementById('ui-mic-limiar-label');
    const elMarcador = document.getElementById('ui-mic-marcador-limiar');

    if (elRange) elRange.value = num;
    if (elNum) elNum.value = num;
    if (elLabel) elLabel.textContent = num;
    if (elMarcador) elMarcador.style.left = num + '%';
  }

  function aplicarValoresNaTela(cfg) {
    if (cfg.mic_limiar !== undefined) {
      atualizarPreviewLimiarMic(cfg.mic_limiar);
    }
    if (cfg.mic_pico_gravado !== undefined) {
      const el = document.getElementById('ui-mic-pico-gravado');
      if (el) el.textContent = cfg.mic_pico_gravado;
    }
    if (cfg.mic_pico_recente !== undefined && !micLocalAtivo) {
      const el = document.getElementById('ui-mic-pico-atual');
      if (el) el.textContent = cfg.mic_pico_recente;
      const barra = document.getElementById('ui-mic-barra');
      if (barra) barra.style.width = Math.min(100, cfg.mic_pico_recente) + '%';
    }
    if (cfg.mic_debounce !== undefined) {
      const el = document.getElementById('input-mic-debounce');
      if (el) el.value = cfg.mic_debounce;
    }

    if (cfg.ldr_sensibilidade !== undefined) {
      const elSens = document.getElementById('input-ldr-sens');
      const elRange = document.getElementById('input-ldr-range');
      const elLabel = document.getElementById('ui-ldr-sens-label');
      const elBarra = document.getElementById('ui-ldr-barra');
      if (elSens) elSens.value = cfg.ldr_sensibilidade;
      if (elRange) elRange.value = cfg.ldr_sensibilidade;
      if (elLabel) elLabel.textContent = cfg.ldr_sensibilidade + '%';
      if (elBarra) elBarra.style.width = Math.min(100, cfg.ldr_sensibilidade) + '%';
    }
    if (cfg.ldr_limiar_adc !== undefined) {
      const el = document.getElementById('input-ldr-adc');
      if (el) el.value = cfg.ldr_limiar_adc;
    }
    if (cfg.ldr_corte_gravado !== undefined) {
      const el = document.getElementById('ui-ldr-corte-gravado');
      if (el) el.textContent = cfg.ldr_corte_gravado + '%';
    }
    if (cfg.ldr_corte_recente !== undefined) {
      const el = document.getElementById('ui-ldr-corte-atual');
      if (el) el.textContent = cfg.ldr_corte_recente;
    }
    if (cfg.ldr_debounce !== undefined) {
      const el = document.getElementById('input-ldr-debounce');
      if (el) el.value = cfg.ldr_debounce;
    }
  }

  /**
   * Ativa/Desativa o Microfone do próprio Celular/PC via Web Audio API (60fps Zero Delay)
   */
  async function alternarEscutaMicLocal(forcarAtivar = false) {
    const btn = document.getElementById('btn-ativar-mic-local');
    const statusTxt = document.getElementById('ui-mic-status-esc');

    if (micLocalAtivo && !forcarAtivar) {
      micLocalAtivo = false;
      if (micStream) micStream.getTracks().forEach(t => t.stop());
      if (btn) {
        btn.className = 'py-2.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs transition flex items-center justify-center gap-1.5';
        btn.innerHTML = '<span>🎤 Ativar Mic do Celular/PC</span>';
      }
      if (statusTxt) statusTxt.textContent = 'Microfone do Celular/PC desativado (Usando apenas ESP32)';
      return false;
    }

    if (micLocalAtivo && forcarAtivar) {
      return true;
    }

    try {
      micStream = await navigator.mediaDevices.getUserMedia({
        audio: {
          echoCancellation: false,
          noiseSuppression: false,
          autoGainControl: false
        },
        video: false
      });

      audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      const source = audioCtx.createMediaStreamSource(micStream);
      analyser = audioCtx.createAnalyser();
      analyser.fftSize = 512;
      analyser.smoothingTimeConstant = 0.1;
      source.connect(analyser);
      dataArray = new Uint8Array(analyser.fftSize);

      micLocalAtivo = true;
      if (btn) {
        btn.className = 'py-2.5 px-3 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-semibold text-xs transition flex items-center justify-center gap-1.5 ring-2 ring-sky-400';
        btn.innerHTML = '<span>🎙️ Mic Celular/PC Escutando...</span>';
      }
      if (statusTxt) statusTxt.textContent = '🟢 Escutando Pico de Áudio (Celular/PC + ESP32 em Tempo Real)';
      Toast.success('Microfone do Celular/PC ativado para disparo de largada!');

      loopAnaliseAudioLocal();
      return true;
    } catch (err) {
      Toast.error('Permissão do microfone negada no navegador: ' + err.message);
      return false;
    }
  }

  /**
   * Mede o Pico de Áudio do Microfone do Celular/PC a 60fps (a cada 16ms)
   */
  function loopAnaliseAudioLocal() {
    if (!micLocalAtivo || !analyser) return;

    analyser.getByteTimeDomainData(dataArray);
    let maxDesvio = 0;
    for (let i = 0; i < dataArray.length; i++) {
      const desvio = Math.abs(dataArray[i] - 128);
      if (desvio > maxDesvio) maxDesvio = desvio;
    }

    // Converte de 0..128 para escala percentual de 0..100
    const picoAtual = Math.min(100, Math.round((maxDesvio / 128) * 100));

    // Atualiza UI instantaneamente
    const elPico = document.getElementById('ui-mic-pico-atual');
    const elBarra = document.getElementById('ui-mic-barra');
    if (elPico) elPico.textContent = picoAtual;
    if (elBarra) elBarra.style.width = picoAtual + '%';

    // Se estiver gravando o pico de referência durante os 3 segundos
    if (gravandoPico3s) {
      if (picoAtual > picoMaximoGravacao3s) {
        picoMaximoGravacao3s = picoAtual;
      }
    } else {
      // Verifica se deve disparar a largada!
      const limiar = parseInt(document.getElementById('input-mic-limiar')?.value || '35', 10);
      const debounceMs = parseInt(document.getElementById('input-mic-debounce')?.value || '3000', 10);
      const agora = Date.now();
      const estadoCorrida = Cronometro.getEstado();

      if (picoAtual >= limiar &&
          estadoCorrida !== 'em_andamento' &&
          (agora - ultimoDisparoLocalEpoch > debounceMs)) {
        ultimoDisparoLocalEpoch = agora;
        Toast.success(`💥 Pico de Áudio detectado (${picoAtual} >= ${limiar})! Largada iniciada!`);
        acaoIniciarManual('microfone_esp32');
      }
    }

    requestAnimationFrame(loopAnaliseAudioLocal);
  }

  async function notificarLargadaEsp32() {
    try {
      const form = new URLSearchParams({ acao: 'iniciar_remoto' });
      await fetchComTimeout(`${getEsp32BaseUrl()}/api/sensibilidade`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: form.toString()
      }, 800);
    } catch (_) {}
  }

  async function sincronizarLeitura() {
    try {
      const url = `${getEsp32BaseUrl()}/api/sensibilidade`;
      const res = await fetchComTimeout(url, { method: 'GET' }, 1000);
      const data = await res.json();
      if (data && data.success) {
        aplicarValoresNaTela(data);
        localStorage.setItem('sensibilidade_cfg', JSON.stringify(data));
        atualizarBadgeEsp32(true, 'ESP32 + Celular Sincronizados');
      }
    } catch (e) {
      const localCfg = localStorage.getItem('sensibilidade_cfg');
      if (localCfg) {
        try { aplicarValoresNaTela(JSON.parse(localCfg)); } catch (_) {}
      }
      atualizarBadgeEsp32(false, 'Modo Híbrido (Mic Celular/PC Pronto)');
    }
  }

  /**
   * Grava o Pico de Áudio durante 3 segundos simultaneamente no Microfone do Celular/PC e no ESP32!
   */
  async function gravarPicoMicrofone() {
    const btn = document.getElementById('btn-gravar-pico-mic');
    const txtOriginal = btn ? btn.innerHTML : '';
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span>🎙️ Escutando 3s... Bata palma / faça o som!</span>';
    }

    // Garante que o microfone do Celular/PC está ligado para capturar o pico
    await alternarEscutaMicLocal(true);

    gravandoPico3s = true;
    picoMaximoGravacao3s = 0;

    // Dispara também a gravação no ESP32 em paralelo (sem travar se o ESP32 estiver offline)
    const promessaEsp32 = (async () => {
      try {
        const form = new URLSearchParams({ acao: 'gravar_mic' });
        const res = await fetchComTimeout(`${getEsp32BaseUrl()}/api/sensibilidade`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: form.toString()
        }, 3500);
        return await res.json();
      } catch (_) {
        return null;
      }
    })();

    // Aguarda 2.6 segundos medindo o pico do microfone do Celular/PC
    await new Promise(r => setTimeout(r, 2600));
    gravandoPico3s = false;

    const respEsp32 = await promessaEsp32;
    const picoEsp32 = (respEsp32 && respEsp32.pico) ? parseInt(respEsp32.pico, 10) : 0;
    const maiorPicoCapturado = Math.max(picoMaximoGravacao3s, picoEsp32);

    if (maiorPicoCapturado > 0) {
      const novoLimiar = Math.max(5, Math.round(maiorPicoCapturado * 0.80));
      aplicarValoresNaTela({
        mic_limiar: novoLimiar,
        mic_pico_gravado: maiorPicoCapturado,
        mic_pico_recente: maiorPicoCapturado
      });

      const atual = JSON.parse(localStorage.getItem('sensibilidade_cfg') || '{}');
      atual.mic_limiar = novoLimiar;
      atual.mic_pico_gravado = maiorPicoCapturado;
      localStorage.setItem('sensibilidade_cfg', JSON.stringify(atual));

      Toast.success(`✅ Pico de áudio gravado: ${maiorPicoCapturado}! Cronômetro iniciará em >= ${novoLimiar}`);
    } else {
      Toast.warning('Nenhum som forte detectado nos 3 segundos. Tente novamente!');
    }

    if (btn) {
      btn.disabled = false;
      btn.innerHTML = txtOriginal;
    }
  }

  async function calibrarLdr() {
    const btn = document.getElementById('btn-calibrar-ldr');
    const txtOriginal = btn ? btn.innerHTML : '';
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span>🔦 Passe a mão no feixe laser agora (3s)...</span>';
    }

    try {
      const form = new URLSearchParams({ acao: 'calibrar_ldr' });
      const res = await fetchComTimeout(`${getEsp32BaseUrl()}/api/sensibilidade`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: form.toString()
      }, 4000);
      const data = await res.json();
      if (data && data.success) {
        aplicarValoresNaTela({
          ldr_sensibilidade: data.ldr_sensibilidade,
          ldr_corte_gravado: data.corte,
          ldr_corte_recente: data.corte
        });
        atualizarBadgeEsp32(true, 'LDR Calibrado no ESP32!');
        Toast.success(`Corte do LDR gravado: ${data.corte}%! Sensibilidade ajustada para >= ${data.ldr_sensibilidade}%`);
      }
    } catch (e) {
      Toast.warning('ESP32 não respondeu no IP informado. Verifique o IP do ESP32.');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = txtOriginal;
      }
    }
  }

  async function salvarMicrofone() {
    const micLimiar = parseInt(document.getElementById('input-mic-limiar').value, 10) || 35;
    const micDebounce = parseInt(document.getElementById('input-mic-debounce').value, 10) || 3000;

    aplicarValoresNaTela({ mic_limiar: micLimiar, mic_debounce: micDebounce });
    const atual = JSON.parse(localStorage.getItem('sensibilidade_cfg') || '{}');
    atual.mic_limiar = micLimiar;
    atual.mic_debounce = micDebounce;
    localStorage.setItem('sensibilidade_cfg', JSON.stringify(atual));

    try {
      const form = new URLSearchParams({
        mic_limiar: String(micLimiar),
        mic_debounce: String(micDebounce)
      });
      const res = await fetchComTimeout(`${getEsp32BaseUrl()}/api/sensibilidade`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: form.toString()
      }, 1200);
      const data = await res.json();
      if (data && data.success) {
        atualizarBadgeEsp32(true, 'Sensibilidade Salva (Celular + ESP32)!');
        Toast.success(`Sensibilidade do Microfone salva (Dispara em Pico >= ${micLimiar})!`);
      }
    } catch (e) {
      atualizarBadgeEsp32(false, 'Salvo no Celular/PC (Pronto para largada)');
      Toast.success(`Sensibilidade do Microfone salva (Dispara em Pico >= ${micLimiar})!`);
    }
  }

  async function salvarLdr() {
    const ldrSens = parseInt(document.getElementById('input-ldr-sens').value, 10) || 30;
    const ldrAdc = parseInt(document.getElementById('input-ldr-adc').value, 10) || 2200;
    const ldrDeb = parseInt(document.getElementById('input-ldr-debounce').value, 10) || 600;

    aplicarValoresNaTela({
      ldr_sensibilidade: ldrSens,
      ldr_limiar_adc: ldrAdc,
      ldr_debounce: ldrDeb
    });
    const atual = JSON.parse(localStorage.getItem('sensibilidade_cfg') || '{}');
    atual.ldr_sensibilidade = ldrSens;
    atual.ldr_limiar_adc = ldrAdc;
    atual.ldr_debounce = ldrDeb;
    localStorage.setItem('sensibilidade_cfg', JSON.stringify(atual));

    try {
      const form = new URLSearchParams({
        ldr_sensibilidade: String(ldrSens),
        ldr_limiar_adc: String(ldrAdc),
        ldr_debounce: String(ldrDeb)
      });
      const res = await fetchComTimeout(`${getEsp32BaseUrl()}/api/sensibilidade`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: form.toString()
      }, 1200);
      const data = await res.json();
      if (data && data.success) {
        atualizarBadgeEsp32(true, 'Sensibilidade do LDR Salva no ESP32!');
        Toast.success(`Sensibilidade do LDR salva (Corte >= ${ldrSens}%)!`);
      }
    } catch (e) {
      atualizarBadgeEsp32(false, 'Salvo Localmente');
      Toast.info(`Sensibilidade do LDR salva (Corte >= ${ldrSens}%).`);
    }
  }

  function init() {
    const ipSalvo = localStorage.getItem('esp32_ip');
    const ipInput = document.getElementById('esp32-ip-input');
    if (ipSalvo && ipInput) {
      ipInput.value = ipSalvo;
    }
    const localCfg = localStorage.getItem('sensibilidade_cfg');
    if (localCfg) {
      try { aplicarValoresNaTela(JSON.parse(localCfg)); } catch (_) {}
    }
    sincronizarLeitura();
  }

  return {
    init,
    sincronizarLeitura,
    alternarEscutaMicLocal,
    atualizarPreviewLimiarMic,
    notificarLargadaEsp32,
    gravarPicoMicrofone,
    calibrarLdr,
    salvarMicrofone,
    salvarLdr
  };
})();
