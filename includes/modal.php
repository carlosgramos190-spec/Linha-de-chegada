<!-- Modal Global Reutilizável (Substitui alert e confirm nativos) -->
<div id="global-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
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
</div>
