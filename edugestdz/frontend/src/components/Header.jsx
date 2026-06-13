import React from 'react';

export default function Header({ user }) {
  return (
    <header className="bg-white border-b border-neutral-200 px-6 py-3 flex items-center justify-between">
      <div className="flex items-center gap-3">
        <h1 className="text-lg font-bold text-neutral-800">EduGest DZ</h1>
      </div>
      <div className="flex items-center gap-4">
        <span className="text-sm text-neutral-500">{user?.nom} {user?.prenom}</span>
        <div className="w-9 h-9 bg-primary-100 text-primary-700 rounded-full flex items-center justify-center text-sm font-bold">
          {user?.nom?.[0]}{user?.prenom?.[0]}
        </div>
      </div>
    </header>
  );
}
