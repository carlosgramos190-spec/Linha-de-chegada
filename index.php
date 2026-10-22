<?php
/**
 * Dashboard Principal - Linha de Chegada
 * Cronômetro em Tempo Real, Controles de Corrida e Tabela de Tempos (SDM-01 & MDA-01)
 */

require_once __DIR__ . '/config/bd.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Dashboard - Linha de Chegada';
include __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">

  <!-- Bloco do Cronômetro Esportivo Principal -->
  <div class="relative overflow-hidden rounded-3xl bg-gradient-to-b from-gray-900 via-gray-900/95 to-gray-950 border border-gray-800 p-6 sm:p-10 shadow-2xl">
    
    <!-- Linha Laser Decorativa no Topo -->
    <div class="laser-line inset-x-0 top-0"></div>

    <div class="flex flex-col items-center text-center space-y-4">
      
      <!-- Status Badge Interativo -->
      <div class="flex items-center gap-2">
        <div id="status-badge" class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-800 text-gray-300 border border-gray-700 flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-full bg-gray-400"></span> Conectando...
        </div>
        <span class="text-xs text-gray-400 font-mono">|</span>
        <span class="text-xs text-gray-400 flex items-center gap-1">
          <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span> Feixe Laser Ativo
        </span>
      </div>

      <!-- Display Digital Gigante -->
      <div class="py-2">
        <div id="cronometro-display" class="font-digital text-5xl sm:text-7xl md:text-8xl font-black tracking-widest text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 via-teal-300 to-cyan-400 drop-shadow-[0_0_25px_rgba(16,185,129,0.35)] select-none">
          00:00.000
        </div>
        <p class="text-xs text-gray-400 uppercase tracking-widest mt-1 font-mono">Minutos : Segundos . Milissegundos</p>
      </div>

      <!-- Botoeira de Ações e Controles (Mobile-Friendly) -->
      <div class="grid grid-cols-2 sm:flex sm:flex-wrap items-center justify-center gap-2.5 w-full pt-2">
        
        <!-- Botão Iniciar Manualmente (Fallback caso não use o disparo do microfone) -->
        <button onclick="acaoIniciarManual()" class="flex items-center justify-center gap-2 px-4 py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-500 active:scale-95 text-white font-semibold text-sm transition shadow-lg shadow-emerald-600/20">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <span>Disparo Manual</span>
        </button>

        <!-- Simular Passagem (Para testes sem ESP32 conectado) -->
        <button onclick="acaoSimularPassagem()" class="flex items-center justify-center gap-2 px-4 py-3 rounded-2xl bg-amber-600/30 hover:bg-amber-600/50 border border-amber-500/40 active:scale-95 text-amber-200 font-semibold text-sm transition">
          <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
          <span>Simular Cortar Laser</span>
        </button>

        <!-- Zerar / Resetar Cronômetro -->
        <button onclick="acaoResetarCorrida()" class="flex items-center justify-center gap-2 px-4 py-3 rounded-2xl bg-gray-800 hover:bg-gray-700 active:scale-95 text-gray-200 font-medium text-sm transition border border-gray-700/80">
          <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
          <span>Zerar (MDA-01)</span>
        </button>

        <!-- Abrir Modo Câmera Celular -->
        <a href="camera.php" class="flex items-center justify-center gap-2 px-4 py-3 rounded-2xl bg-red-950/80 hover:bg-red-900 border border-red-500/40 text-red-200 font-semibold text-sm transition">
          <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path></svg>
          <span>Modo Celular</span>
        </a>

      </div>

    </div>
  </div>

  <!-- Cards de Arquitetura e Sensores Conectados -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    
    <!-- Microfone (Largada - MDA-01) -->
    <div class="p-5 rounded-2xl bg-gray-900/60 border border-gray-800/80 flex items-start space-x-4">
      <div class="w-10 h-10 rounded-xl bg-sky-950/70 border border-sky-500/30 flex items-center justify-center flex-shrink-0 text-sky-400">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
      </div>
      <div>
        <div class="flex items-center gap-2">
          <h4 class="font-bold text-sm text-white">Microfone de Largada</h4>
          <span class="text-[10px] px-1.5 py-0.5 rounded bg-sky-900/40 text-sky-300 font-mono">MDA-01</span>
        </div>
        <p class="text-xs text-gray-400 mt-1">Disparo sonoro detectado pelo módulo sensor acústico acoplado ao ESP32.</p>
      </div>
    </div>

    <!-- Sensor LDR + Laser (Chegada - SDM-01) -->
    <div class="p-5 rounded-2xl bg-gray-900/60 border border-gray-800/80 flex items-start space-x-4">
      <div class="w-10 h-10 rounded-xl bg-red-950/70 border border-red-500/30 flex items-center justify-center flex-shrink-0 text-red-400">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
      </div>
      <div>
        <div class="flex items-center gap-2">
          <h4 class="font-bold text-sm text-white">LDR + Feixe Laser</h4>
          <span class="text-[10px] px-1.5 py-0.5 rounded bg-red-900/40 text-red-300 font-mono">SDM-01</span>
        </div>
        <p class="text-xs text-gray-400 mt-1">Quando o atleta corta o feixe laser, o tempo é registrado em ordem cronológica.</p>
      </div>
    </div>

    <!-- Celular Photo Finish -->
    <div class="p-5 rounded-2xl bg-gray-900/60 border border-gray-800/80 flex items-start space-x-4">
      <div class="w-10 h-10 rounded-xl bg-purple-950/70 border border-purple-500/30 flex items-center justify-center flex-shrink-0 text-purple-400">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
      </div>
      <div>
        <div class="flex items-center gap-2">
          <h4 class="font-bold text-sm text-white">Câmera Celular</h4>
          <span class="text-[10px] px-1.5 py-0.5 rounded bg-purple-900/40 text-purple-300 font-mono">Photo Finish</span>
        </div>
        <p class="text-xs text-gray-400 mt-1">Celular posicionado na fita de chegada captando fotografia em alta velocidade.</p>
      </div>
    </div>

  </div>

  <!-- Tabela de Tempos Registrados em Ordem Cronológica (Requisito SDM-01) -->
  <div class="rounded-3xl bg-gray-900/80 border border-gray-800/90 shadow-xl overflow-hidden">
    <div class="p-5 sm:p-6 border-b border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h3 class="text-lg font-bold font-brand tracking-wide text-white flex items-center gap-2">
          <span>🏆 Tempos Registrados</span>
          <span class="text-xs font-normal text-gray-400 font-mono">(Ordem de Chegada)</span>
        </h3>
        <p class="text-xs text-gray-400 mt-0.5">Atende ao Caso de Uso SDM-01: Anotação cronológica e registro de passagens.</p>
      </div>
      <button onclick="Cronometro.atualizarStatus()" class="self-start sm:self-auto inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-xs font-medium text-gray-300 transition">
        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
        Atualizar Tabela
      </button>
    </div>

    <!-- Tabela Responsiva -->
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="bg-gray-950/60 text-xs uppercase tracking-wider text-gray-400 font-mono border-b border-gray-800">
          <tr>
            <th class="py-3 px-3 sm:px-4 text-center">Colocação</th>
            <th class="py-3 px-3 sm:px-4">Atleta / Identificador</th>
            <th class="py-3 px-3 sm:px-4">Tempo Oficial</th>
            <th class="py-3 px-3 sm:px-4 text-center">Photo Finish</th>
            <th class="py-3 px-3 sm:px-4 text-right">Ação</th>
          </tr>
        </thead>
        <tbody id="tabela-tempos-body" class="divide-y divide-gray-800/60 font-medium">
          <!-- Injetado dinamicamente via cronometro.js -->
        </tbody>
      </table>
    </div>

    <!-- Empty State -->
    <div id="empty-state-tempos" class="py-12 px-4 text-center">
      <div class="w-12 h-12 mx-auto rounded-2xl bg-gray-800/50 flex items-center justify-center text-gray-500 mb-3">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
      </div>
      <p class="text-sm font-medium text-gray-300">Nenhum tempo registrado nesta corrida ainda.</p>
      <p class="text-xs text-gray-500 mt-1">Dispare a largada e aguarde o corte do feixe laser pelo sensor LDR.</p>
    </div>
  </div>

</div>

<!-- Script do Cronômetro -->
<script src="assets/js/cronometro.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
