/**
 * Módulo de Captura de Imagens com Celular (Photo Finish)
 * Linha de Chegada
 * 
 * Permite que um celular posicionado na linha de chegada fotografe automaticamente
 * o momento exato em que o competidor cruza a linha (interrupção do laser LDR).
 */

const CelularCamera = (function () {
  let videoEl = null;
  let canvasEl = null;
  let stream = null;
  let facingMode = 'environment'; // Câmera traseira por padrão
  let autoCapture = true;
  let lastCapturedId = 0;
  let isCapturing = false;

  async function iniciarCamera() {
    videoEl = document.getElementById('camera-preview');
    canvasEl = document.getElementById('camera-canvas') || document.createElement('canvas');

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
   * Captura frame instantâneo e envia para o servidor PHP
   */
  async function capturarFrame(registroId = null) {
    if (!videoEl || videoEl.readyState < 2 || isCapturing) {
      return;
    }

    isCapturing = true;

    try {
      // Efeito visual de flash na tela do celular
      flashScreen();

      canvasEl.width = videoEl.videoWidth || 1280;
      canvasEl.height = videoEl.videoHeight || 720;
      const ctx = canvasEl.getContext('2d');

      // Desenha o frame da câmera
      ctx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);

      // Converte para JPEG otimizado
      const base64Data = canvasEl.toDataURL('image/jpeg', 0.85);

      // Envia via POST para api/salvar_foto.php
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

  // Listener para evento de passagem automática vindo do ESP32/Cronômetro
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
    toggleAutoCapture: () => {
      autoCapture = !autoCapture;
      Toast.info(`Captura automática ${autoCapture ? 'ativada' : 'desativada'}.`);
      return autoCapture;
    }
  };
})();

window.CelularCamera = CelularCamera;
