/**
 * Sistema de Notificações TOAST
 * Linha de Chegada
 * 
 * Atende ao requisito: "Retorne sempre as mensagens de erros de forma claras na interface para o usuário (TOAST)"
 */
const Toast = (function () {
  let container = null;

  function initContainer() {
    if (!container) {
      container = document.getElementById('toast-container');
      if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-4 right-4 z-50 flex flex-col space-y-2 max-w-sm w-full pointer-events-none px-4 sm:px-0';
        document.body.appendChild(container);
      }
    }
    return container;
  }

  function show(message, type = 'info', duration = 4000) {
    const cont = initContainer();

    const toast = document.createElement('div');
    toast.className = `pointer-events-auto flex items-start p-4 rounded-xl shadow-2xl border text-sm backdrop-blur-md transition-all duration-300 toast-animate-in`;

    // Ícones e paletas por tipo
    let iconSvg = '';
    let colorClasses = '';

    switch (type) {
      case 'success':
        colorClasses = 'bg-emerald-950/90 border-emerald-500/40 text-emerald-200';
        iconSvg = `<svg class="w-5 h-5 text-emerald-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`;
        break;
      case 'error':
        colorClasses = 'bg-rose-950/90 border-rose-500/40 text-rose-200';
        iconSvg = `<svg class="w-5 h-5 text-rose-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>`;
        break;
      case 'warning':
        colorClasses = 'bg-amber-950/90 border-amber-500/40 text-amber-200';
        iconSvg = `<svg class="w-5 h-5 text-amber-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>`;
        break;
      case 'info':
      default:
        colorClasses = 'bg-sky-950/90 border-sky-500/40 text-sky-200';
        iconSvg = `<svg class="w-5 h-5 text-sky-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
        break;
    }

    toast.className += ` ${colorClasses}`;
    toast.innerHTML = `
      ${iconSvg}
      <div class="flex-1 font-medium leading-tight">${message}</div>
      <button class="ml-3 text-gray-400 hover:text-white transition focus:outline-none" onclick="this.parentElement.remove()">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
      </button>
    `;

    cont.appendChild(toast);

    if (duration > 0) {
      setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px)';
        setTimeout(() => toast.remove(), 300);
      }, duration);
    }
  }

  return {
    success: (msg, dur) => show(msg, 'success', dur),
    error: (msg, dur) => show(msg, 'error', dur || 5000),
    warning: (msg, dur) => show(msg, 'warning', dur),
    info: (msg, dur) => show(msg, 'info', dur)
  };
})();

// Disponível globalmente
window.Toast = Toast;
