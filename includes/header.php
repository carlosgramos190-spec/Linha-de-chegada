<?php
/**
 * Header Global - Linha de Chegada
 * Mobile-First, Tailwind CSS, Fontes e Metatags Responsivas
 */
$pageTitle = $pageTitle ?? 'Linha de Chegada - Cronometragem Esportiva';
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            brand: {
              50: '#eff6ff',
              500: '#3b82f6',
              600: '#2563eb',
              700: '#1d4ed8',
              900: '#1e3a8a',
            },
            laser: '#ef4444',
          }
        }
      }
    }
  </script>

  <!-- Google Fonts & Custom CSS -->
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏁</text></svg>">
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex flex-col antialiased selection:bg-red-500 selection:text-white">

  <!-- Barra de Navegação Superior (Mobile-First) -->
  <header class="sticky top-0 z-40 bg-gray-900/90 backdrop-blur-md border-b border-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">
        
        <!-- Logo e Título -->
        <a href="index.php" class="flex items-center space-x-2.5 group">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-red-600 via-rose-500 to-amber-500 flex items-center justify-center shadow-lg shadow-red-500/20 group-hover:scale-105 transition-transform">
            <span class="text-xl">🏁</span>
          </div>
          <div>
            <h1 class="text-lg font-bold font-brand tracking-wider text-white flex items-center gap-1.5">
              LINHA DE CHEGADA
              <span class="hidden sm:inline-block px-1.5 py-0.5 text-[10px] bg-red-500/20 text-red-400 border border-red-500/30 rounded font-mono">ESP32 + LDR</span>
            </h1>
            <p class="text-[10px] text-gray-400 hidden sm:block">Cronometragem Óptica & Photo Finish</p>
          </div>
        </a>

        <!-- Links de Navegação -->
        <nav class="flex items-center space-x-1 sm:space-x-3 text-sm">
          <a href="index.php" class="px-3 py-2 rounded-lg font-medium transition <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-gray-800 text-white shadow-sm border border-gray-700/60' : 'text-gray-400 hover:text-white hover:bg-gray-800/50' ?>">
            ⏱️ <span class="hidden md:inline">Cronômetro</span>
          </a>
          <a href="camera.php" class="px-3 py-2 rounded-lg font-medium transition <?= basename($_SERVER['PHP_SELF']) == 'camera.php' ? 'bg-red-950/80 text-red-300 border border-red-500/40 shadow-sm' : 'text-gray-400 hover:text-white hover:bg-gray-800/50' ?>">
            📸 <span class="hidden md:inline">Celular (Photo Finish)</span>
          </a>
          <a href="historico.php" class="px-3 py-2 rounded-lg font-medium transition <?= basename($_SERVER['PHP_SELF']) == 'historico.php' ? 'bg-gray-800 text-white border border-gray-700/60 shadow-sm' : 'text-gray-400 hover:text-white hover:bg-gray-800/50' ?>">
            🏆 <span class="hidden md:inline">Histórico</span>
          </a>
        </nav>

      </div>
    </div>
  </header>

  <!-- Container de Conteúdo Principal -->
  <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
