<?php
/**
 * Interface da Câmera do Celular - Linha de Chegada
 * Destinada a rodar no smartphone fixado na linha de chegada.
 * Captura fotos instantâneas (Photo Finish) automaticamente ao sinal do ESP32.
 */

require_once __DIR__ . '/config/bd.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Câmera Celular - Photo Finish';
include __DIR__ . '/includes/header.php';
?>

<div class="space-y-4 max-w-2xl mx-auto">
  
  <!-- Cabeçalho da Câmera -->
  <div class="flex items-center justify-between bg-gray-900/80 p-4 rounded-2xl border border-gray-800">
    <div class="flex items-center space-x-3">
      <div class="w-10 h-10 rounded-xl bg-red-950/80 border border-red-500/40 flex items-center justify-center text-red-400">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path></svg>
      </div>
      <div>
        <h2 class="text-base font-bold text-white font-brand">Photo Finish Celular</h2>
        <p class="text-xs text-gray-400">Alinhe a linha vermelha com o feixe laser</p>
      </div>
    </div>
    
    <div id="camera-status-badge" class="px-2.5 py-1 text-xs rounded-full bg-gray-800 text-gray-400 border border-gray-700 flex items-center gap-1.5">
      <span class="w-2 h-2 rounded-full bg-gray-500"></span> Conectando...
    </div>
  </div>

  <!-- Viewport da Câmera do Smartphone com Linha Guia do Feixe Laser -->
  <div class="relative overflow-hidden rounded-3xl bg-black border-2 border-red-500/40 shadow-2xl aspect-[4/3] sm:aspect-video flex items-center justify-center">
    
    <!-- Vídeo da Câmera -->
    <video id="camera-preview" autoplay playsinline muted class="w-full h-full object-cover"></video>

    <!-- Linha Laser de Chegada Virtual (Alinhamento da Câmera com o Laser do ESP32) -->
    <div class="camera-crosshair pointer-events-none"></div>

    <!-- Rótulo Superior Virtual -->
    <div class="absolute top-3 inset-x-0 flex justify-center pointer-events-none">
      <div class="bg-black/70 backdrop-blur-sm px-3 py-1 rounded-full border border-red-500/30 text-[10px] font-mono text-red-300 uppercase tracking-widest flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-red-500 animate-ping"></span>
        Alinhe com o Feixe Laser
      </div>
    </div>

    <!-- Efeito Flash na Captura -->
    <div id="camera-flash" class="absolute inset-0 bg-white pointer-events-none opacity-0 transition-opacity duration-150"></div>

    <!-- Miniatura da Última Foto Capturada -->
    <div class="absolute bottom-3 left-3 z-30">
      <img id="camera-last-preview" src="" alt="Último Photo Finish" class="hidden w-16 h-16 sm:w-20 sm:h-20 object-cover rounded-xl border-2 border-emerald-400 shadow-xl cursor-pointer" onclick="Modal.photoFinish(this.src, 'Último Registro', '', '')">
    </div>

  </div>

  <!-- Microfone de Largada do Celular (Pico de Áudio em Tempo Real - MDA-01) -->
  <div class="bg-gray-900/90 border border-sky-500/30 p-4 rounded-2xl space-y-3">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="text-lg">🎙️</span>
        <div>
          <h3 class="text-xs sm:text-sm font-bold text-white">Microfone de Largada do Celular (MDA-01)</h3>
          <p class="text-[11px] text-sky-400 font-mono" id="cam-mic-status">Inicia o cronômetro automaticamente ao detectar o pico salvo</p>
        </div>
      </div>
      <span class="text-xs px-2 py-1 rounded-lg bg-sky-950 text-sky-300 border border-sky-500/30 font-mono">
        Pico: <strong id="cam-mic-atual">0</strong> / Dispara &ge; <strong id="cam-mic-limiar">35</strong>
      </span>
    </div>

    <div class="relative w-full h-3 bg-gray-800 rounded-full overflow-hidden">
      <div id="cam-mic-barra" class="h-full bg-gradient-to-r from-emerald-400 via-sky-500 to-red-500 transition-all duration-75" style="width: 0%"></div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
      <button onclick="CelularCamera.alternarMicLargada()" id="btn-cam-mic-toggle"
              class="py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs transition">
        🎤 Ativar Mic Largada
      </button>
      <button onclick="CelularCamera.gravarPicoLargada()" id="btn-cam-mic-gravar"
              class="py-2 px-3 rounded-xl bg-red-600 hover:bg-red-500 text-white font-semibold text-xs transition">
        🔴 Gravar Pico (3s)
      </button>
      <div class="col-span-2 sm:col-span-1 flex items-center gap-2 bg-gray-950 px-3 py-1.5 rounded-xl border border-gray-800">
        <span class="text-[11px] text-gray-400 whitespace-nowrap">Limiar:</span>
        <input type="range" id="cam-mic-slider" min="5" max="95" value="35"
               oninput="CelularCamera.ajustarLimiarMic(this.value)"
               class="w-full accent-sky-500">
      </div>
    </div>
  </div>

  <!-- Controles da Câmera (Mobile-First Touch Friendly) -->
  <div class="bg-gray-900/90 border border-gray-800 p-4 rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-3">
    
    <div class="flex items-center gap-2 w-full sm:w-auto justify-center">
      <!-- Iniciar / Conectar Câmera -->
      <button onclick="CelularCamera.iniciarCamera()" class="px-4 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-white text-xs font-semibold flex items-center gap-1.5 transition">
        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        Reconectar
      </button>

      <!-- Alternar Câmera Traseira/Frontal -->
      <button onclick="CelularCamera.alternarCamera()" class="px-4 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-white text-xs font-semibold flex items-center gap-1.5 transition">
        <svg class="w-4 h-4 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
        Trocar Lente
      </button>
    </div>

    <!-- Botão de Disparo Manual de Foto Finish -->
    <button onclick="CelularCamera.capturarFrame()" class="w-full sm:w-auto px-6 py-3 rounded-2xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 active:scale-95 text-white font-bold text-sm flex items-center justify-center gap-2 shadow-lg shadow-red-600/30 transition">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path></svg>
      <span>Fotografar Chegada Agora</span>
    </button>

  </div>

  <!-- Informações e Dicas de Uso com Celular -->
  <div class="bg-gray-900/50 border border-gray-800/80 p-4 rounded-2xl text-xs text-gray-400 space-y-2">
    <div class="font-bold text-gray-300 flex items-center gap-1.5">
      <span>💡 Como posicionar o celular:</span>
    </div>
    <ul class="list-disc list-inside space-y-1 text-gray-400">
      <li>Coloque o celular em um tripé ou suporte estável perpendicular à linha de chegada.</li>
      <li>Alinhe a <strong>linha vermelha guia</strong> da tela exatamente onde o laser atinge o sensor LDR.</li>
      <li>Assim que o ESP32 detectar o corte do laser (atleta cruzando), a imagem é capturada automaticamente.</li>
    </ul>
  </div>

</div>

<!-- Canvas Invisível para Conversão de Frame -->
<canvas id="camera-canvas" class="hidden"></canvas>

<!-- Scripts da Câmera e Sincronização -->
<script src="assets/js/camera.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    // Inicia câmera automaticamente
    CelularCamera.iniciarCamera();

    // Inicia escuta contínua de status do backend para trigger automático
    let ultimoIdVisto = 0;
    setInterval(async () => {
      try {
        const res = await fetch(`api/status_corrida.php?ultimo_id=${ultimoIdVisto}`);
        const json = await res.json();
        if (json.success && json.data.novos_registros && json.data.novos_registros.length > 0) {
          json.data.novos_registros.forEach(reg => {
            if (reg.id > ultimoIdVisto) {
              ultimoIdVisto = reg.id;
              // Captura frame do celular associado a este registro
              CelularCamera.capturarFrame(reg.id);
            }
          });
        } else if (json.data && json.data.registros && json.data.registros.length > 0) {
          const maior = Math.max(...json.data.registros.map(r => r.id));
          if (maior > ultimoIdVisto) ultimoIdVisto = maior;
        }
      } catch (e) {
        // Silencioso em caso de oscilação momentânea de rede
      }
    }, 400);
  });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
