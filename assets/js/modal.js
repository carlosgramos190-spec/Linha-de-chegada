/**
 * Sistema de Modais Responsivos (Substitui alerts e confirms nativos)
 * Linha de Chegada
 * 
 * Atende ao requisito: "sempre trate as mensagens de caixa de mensagens nativas do navegador em um MODAL"
 */
const Modal = (function () {
  let modalWrapper = null;
  let modalTitle = null;
  let modalBody = null;
  let modalFooter = null;
  let modalBackdrop = null;

  function init() {
    if (!modalWrapper) {
      modalWrapper = document.getElementById('global-modal');
      if (!modalWrapper) {
        modalWrapper = document.createElement('div');
        modalWrapper.id = 'global-modal';
        modalWrapper.className = 'fixed inset-0 z-50 flex items-center justify-center p-4 hidden';
        modalWrapper.innerHTML = `
          <div id="modal-backdrop" class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity"></div>
          <div class="relative bg-gray-900 border border-gray-700/80 rounded-2xl max-w-lg w-full p-6 shadow-2xl z-10 transform transition-all text-white">
            <div class="flex items-center justify-between pb-3 border-b border-gray-800">
              <h3 id="modal-title" class="text-lg font-bold font-brand tracking-wide text-white">Mensagem</h3>
              <button id="modal-close-btn" class="text-gray-400 hover:text-white transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
              </button>
            </div>
            <div id="modal-body" class="py-4 text-gray-300 text-sm leading-relaxed"></div>
            <div id="modal-footer" class="flex items-center justify-end space-x-3 pt-3 border-t border-gray-800"></div>
          </div>
        `;
        document.body.appendChild(modalWrapper);
      }

      modalTitle = modalWrapper.querySelector('#modal-title');
      modalBody = modalWrapper.querySelector('#modal-body');
      modalFooter = modalWrapper.querySelector('#modal-footer');
      modalBackdrop = modalWrapper.querySelector('#modal-backdrop');
      const closeBtn = modalWrapper.querySelector('#modal-close-btn');

      closeBtn.onclick = hide;
      modalBackdrop.onclick = hide;
    }
  }

  function show() {
    init();
    modalWrapper.classList.remove('hidden');
  }

  function hide() {
    if (modalWrapper) {
      modalWrapper.classList.add('hidden');
    }
  }

  /**
   * Alerta customizado (substitui window.alert)
   */
  function alert(title, message, onClose = null) {
    init();
    modalTitle.innerHTML = title;
    modalBody.innerHTML = message;
    modalFooter.innerHTML = `
      <button id="modal-btn-ok" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-blue-400">
        Entendido
      </button>
    `;

    show();

    const btnOk = modalFooter.querySelector('#modal-btn-ok');
    btnOk.onclick = () => {
      hide();
      if (typeof onClose === 'function') onClose();
    };
  }

  /**
   * Confirmação customizada (substitui window.confirm)
   */
  function confirm(title, message, onConfirm, onCancel = null) {
    init();
    modalTitle.innerHTML = title;
    modalBody.innerHTML = message;
    modalFooter.innerHTML = `
      <button id="modal-btn-cancel" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 font-medium rounded-xl text-sm transition">
        Cancelar
      </button>
      <button id="modal-btn-confirm" class="px-5 py-2 bg-red-600 hover:bg-red-500 text-white font-medium rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-red-400">
        Confirmar
      </button>
    `;

    show();

    modalFooter.querySelector('#modal-btn-cancel').onclick = () => {
      hide();
      if (typeof onCancel === 'function') onCancel();
    };

    modalFooter.querySelector('#modal-btn-confirm').onclick = () => {
      hide();
      if (typeof onConfirm === 'function') onConfirm();
    };
  }

  /**
   * Visualizador de Foto da Linha de Chegada (Photo Finish)
   */
  function photoFinish(imageUrl, athlete, time, position) {
    init();
    modalTitle.innerHTML = `🏁 Photo Finish - ${position}º Lugar`;
    modalBody.innerHTML = `
      <div class="space-y-3">
        <div class="relative overflow-hidden rounded-xl border border-red-500/50 bg-black flex items-center justify-center">
          <img src="${imageUrl}" alt="Photo Finish" class="w-full max-h-96 object-contain">
          <!-- Linha Laser de Chegada Virtual sobreposta na foto -->
          <div class="absolute inset-y-0 left-1/2 w-0.5 bg-red-500 shadow-[0_0_8px_#ef4444]"></div>
          <span class="absolute top-2 left-2 bg-black/70 px-2 py-1 rounded text-xs text-red-400 font-mono font-bold">LINHA DE CHEGADA</span>
        </div>
        <div class="flex items-center justify-between text-sm bg-gray-800/60 p-3 rounded-lg border border-gray-700">
          <div>
            <span class="text-gray-400 block text-xs">Identificação</span>
            <span class="text-white font-semibold">${athlete}</span>
          </div>
          <div class="text-right">
            <span class="text-gray-400 block text-xs">Tempo Registrado</span>
            <span class="text-emerald-400 font-mono font-bold text-base">${time}</span>
          </div>
        </div>
      </div>
    `;

    modalFooter.innerHTML = `
      <a href="${imageUrl}" download="photo_finish_${position}.jpg" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-xl text-sm transition">
        Baixar Imagem
      </a>
      <button id="modal-btn-close" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-xl text-sm transition">
        Fechar
      </button>
    `;

    show();
    modalFooter.querySelector('#modal-btn-close').onclick = hide;
  }

  return {
    alert,
    confirm,
    photoFinish,
    hide
  };
})();

// Reatribui alert nativo para usar nosso Modal
window.Modal = Modal;
window.alert = function (msg) {
  Modal.alert('Aviso do Sistema', msg);
};
