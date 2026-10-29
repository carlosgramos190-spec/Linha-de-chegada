/**
 * Módulo de Captura de Imagens com Celular (Photo Finish) + Microfone de Largada do Celular (MDA-01)
 * Linha de Chegada
 */

const CelularCamera = (function () {
  let videoEl = null;
  let canvasEl = null;
  let stream = null;
  let facingMode = 'environment';
  let autoCapture = true;
  let lastCapturedId = 0;
  let isCapturing = false;

  // Variáveis do Microfone de Largada do Celular (Web Audio API)
  let audioCtx = null;
  let analyser = null;
  let micStream = null;
  let dataArray = null;
  let micAtivo = false;
  let gravandoPico = false;
  let maiorPicoGravacao = 0;
  let limiarMic = 35;
  let ultimoDisparoMs = 0;

  async function iniciarCamera() {
    videoEl = document.getElementById('camera-preview');
    canvasEl = document.getElementById('camera-canvas') || document.createElement('canvas');

    // Carrega limiar salvo do localStorage se existir
    try {
      const cfg = JSON.parse(localStorage.getItem('sensibilidade_cfg') || '{}');
      if (cfg.mic_limiar) {
        ajustarLimiarMic(cfg.mic_limiar);
      }
    } catch (_) {}

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      Toast.error('Seu navegador ou celular não suporta acesso à câmera.');
      return false;
    }

    try {
      if (stream) {
        stream.getTracks().forEach(t => t.stop());
      }

      const constraints = {
        video: {
          facingMode: { ideal: facingMode },
          width: { ideal: 1920, min: 1280 },
          height: { ideal: 1080, min: 720 },
          frameRate: { ideal: 60, min: 30 }
        },
        audio: false
      };

      stream = await navigator.mediaDevices.getUserMedia(constraints);
      if (videoEl) {
        videoEl.srcObject = stream;
        await videoEl.play();
      }

      Toast.success('Câmera conectada e alinhada com a linha de chegada!');
      atualizarStatusCamera(true);
      return true;
    } catch (err) {
      console.error('Erro na câmera:', err);
      Toast.error('Permissão de câmera negada ou câmera ocupada: ' + err.message);
      atualizarStatusCamera(false);
      return false;
    }
  }

  function alternarCamera() {
    facingMode = (facingMode === 'environment') ? 'user' : 'environment';
    iniciarCamera();
  }

  /**
   * Ativa/Desativa o Microfone do próprio Celular para detectar o disparo de largada
   */
  async function alternarMicLargada(forcarAtivar = false) {
    const btn = document.getElementById('btn-cam-mic-toggle');
    const st = document.getElementById('cam-mic-status');

    if (micAtivo && !forcarAtivar) {
      micAtivo = false;
      if (micStream) micStream.getTracks().forEach(t => t.stop());
      if (btn) {
        btn.className = 'py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs transition';
        btn.textContent = '🎤 Ativar Mic Largada';
      }
      if (st) st.textContent = 'Microfone do celular pausado';
      return false;
    }

    if (micAtivo && forcarAtivar) return true;

    try {
      micStream = await navigator.mediaDevices.getUserMedia({
        audio: { echoCancellation: false, noiseSuppression: false, autoGainControl: false },
        video: false
      });

      audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      const source = audioCtx.createMediaStreamSource(micStream);
      analyser = audioCtx.createAnalyser();
      analyser.fftSize = 512;
      analyser.smoothingTimeConstant = 0.1;
      source.connect(analyser);
      dataArray = new Uint8Array(analyser.fftSize);

      micAtivo = true;
      if (btn) {
        btn.className = 'py-2 px-3 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-semibold text-xs transition ring-2 ring-sky-400';
        btn.textContent = '🎙️ Escutando Largada...';
      }
      if (st) st.textContent = '🟢 Escutando som de largada em Tempo Real!';
      Toast.success('Microfone do celular ativado para iniciar a corrida!');
      loopAudioCelular();
      return true;
    } catch (e) {
      Toast.error('Permissão de microfone negada: ' + e.message);
      return false;
    }
  }

  function loopAudioCelular() {
    if (!micAtivo || !analyser) return;

    analyser.getByteTimeDomainData(dataArray);
    let maxDesvio = 0;
    for (let i = 0; i < dataArray.length; i++) {
      const d = Math.abs(dataArray[i] - 128);
      if (d > maxDesvio) maxDesvio = d;
    }

    const pico = Math.min(100, Math.round((maxDesvio / 128) * 100));
    const elAtual = document.getElementById('cam-mic-atual');
    const elBarra = document.getElementById('cam-mic-barra');
    if (elAtual) elAtual.textContent = pico;
    if (elBarra) elBarra.style.width = pico + '%';

    if (gravandoPico) {
      if (pico > maiorPicoGravacao) maiorPicoGravacao = pico;
    } else {
      const agora = Date.now();
      if (pico >= limiarMic && (agora - ultimoDisparoMs > 3000)) {
        ultimoDisparoMs = agora;
        Toast.success(`💥 Disparo de largada detectado pelo celular (Pico ${pico})!`);
        fetch('api/iniciar_corrida.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ origem: 'microfone_esp32' })
        }).catch(() => {});
      }
    }

    requestAnimationFrame(loopAudioCelular);
  }

  async function gravarPicoLargada() {
    const btn = document.getElementById('btn-cam-mic-gravar');
    const orig = btn ? btn.textContent : '';
    if (btn) {
      btn.disabled = true;
      btn.textContent = '⏳ Faça o som (3s)...';
    }

    await alternarMicLargada(true);
    gravandoPico = true;
    maiorPicoGravacao = 0;

    await new Promise(r => setTimeout(r, 2600));
    gravandoPico = false;

    if (maiorPicoGravacao > 0) {
      const novoLimiar = Math.max(5, Math.round(maiorPicoGravacao * 0.80));
      ajustarLimiarMic(novoLimiar);
      Toast.success(`✅ Pico gravado (${maiorPicoGravacao})! Disparo ajustado para >= ${novoLimiar}`);
    } else {
      Toast.warning('Nenhum som forte detectado. Tente novamente!');
    }

    if (btn) {
      btn.disabled = false;
      btn.textContent = orig;
    }
  }

  function ajustarLimiarMic(val) {
    limiarMic = Math.max(5, Math.min(95, parseInt(val, 10) || 35));
    const elLim = document.getElementById('cam-mic-limiar');
    const elSlider = document.getElementById('cam-mic-slider');
    if (elLim) elLim.textContent = limiarMic;
    if (elSlider) elSlider.value = limiarMic;

    try {
      const cfg = JSON.parse(localStorage.getItem('sensibilidade_cfg') || '{}');
      cfg.mic_limiar = limiarMic;
      localStorage.setItem('sensibilidade_cfg', JSON.stringify(cfg));
    } catch (_) {}
  }

  async function capturarFrame(registroId = null) {
    if (!videoEl || videoEl.readyState < 2 || isCapturing) {
      return;
    }

    isCapturing = true;

    try {
      flashScreen();

      canvasEl.width = videoEl.videoWidth || 1280;
      canvasEl.height = videoEl.videoHeight || 720;
      const ctx = canvasEl.getContext('2d');

      ctx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);
      const base64Data = canvasEl.toDataURL('image/jpeg', 0.85);

      const res = await fetch('api/salvar_foto.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          imagem_base64: base64Data,
          registro_id: registroId
        })
      });

      const json = await res.json();
      if (json.success) {
        Toast.success('📸 Photo Finish registrado e salvo no servidor!');
        mostrarPreviewMiniatura(json.data.foto_url);
      } else {
        Toast.warning(json.message);
      }
    } catch (err) {
      console.error('Falha no upload do Photo Finish:', err);
      Toast.error('Erro ao enviar foto: ' + err.message);
    } finally {
      setTimeout(() => { isCapturing = false; }, 300);
    }
  }

  function flashScreen() {
    const flash = document.getElementById('camera-flash');
    if (flash) {
      flash.classList.remove('opacity-0');
      flash.classList.add('opacity-90');
      setTimeout(() => {
        flash.classList.remove('opacity-90');
        flash.classList.add('opacity-0');
      }, 120);
    }
  }

  function mostrarPreviewMiniatura(url) {
    const preview = document.getElementById('camera-last-preview');
    if (preview) {
      preview.src = url;
      preview.classList.remove('hidden');
    }
  }

  function atualizarStatusCamera(ativa) {
    const badge = document.getElementById('camera-status-badge');
    if (badge) {
      if (ativa) {
        badge.className = 'px-2.5 py-1 text-xs rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center gap-1.5';
        badge.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Câmera Ativa';
      } else {
        badge.className = 'px-2.5 py-1 text-xs rounded-full bg-rose-500/20 text-rose-400 border border-rose-500/30 flex items-center gap-1.5';
        badge.innerHTML = '<span class="w-2 h-2 rounded-full bg-rose-400"></span> Câmera Inativa';
      }
    }
  }

  window.addEventListener('linhachegada:passagem', (e) => {
    if (autoCapture && e.detail && e.detail.id) {
      if (e.detail.id !== lastCapturedId) {
        lastCapturedId = e.detail.id;
        capturarFrame(e.detail.id);
      }
    }
  });

  return {
    iniciarCamera,
    alternarCamera,
    capturarFrame,
    alternarMicLargada,
    gravarPicoLargada,
    ajustarLimiarMic,
    toggleAutoCapture: () => {
      autoCapture = !autoCapture;
      Toast.info(`Captura automática ${autoCapture ? 'ativada' : 'desativada'}.`);
      return autoCapture;
    }
  };
})();

window.CelularCamera = CelularCamera;
