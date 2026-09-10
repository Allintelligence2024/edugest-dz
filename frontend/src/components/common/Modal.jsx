import React from 'react';

export default function Modal({ isOpen, onClose, title, children }) {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50" onClick={onClose} data-testid="modal-backdrop" />
      <div className="relative bg-white rounded-2xl shadow-modal w-full max-w-md p-5 animate-slide-up" data-testid="modal-content">
        {title && (
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-bold">{title}</h2>
            <button onClick={onClose} className="p-2 hover:bg-neutral-100 rounded-lg" data-testid="modal-close-btn">✕</button>
          </div>
        )}
        {children}
      </div>
    </div>
  );
}
