import React, { useState, useEffect, useCallback } from 'react';
import { messageApi } from '@api/message.api';
import toast from 'react-hot-toast';

export default function MessagesPage() {
  const [conversations, setConversations] = useState([]);
  const [selectedConv, setSelectedConv] = useState(null);
  const [messages, setMessages] = useState([]);
  const [newMessage, setNewMessage] = useState('');
  const [sujet, setSujet] = useState('');
  const [showNewConv, setShowNewConv] = useState(false);
  const [loading, setLoading] = useState(false);

  const fetchConversations = useCallback(async () => {
    try {
      const res = await messageApi.conversations({ per_page: 50 });
      setConversations(res.data || []);
    } catch { /* ignore */ }
  }, []);

  useEffect(() => { fetchConversations(); }, [fetchConversations]);

  const openConversation = async (conv) => {
    setSelectedConv(conv);
    try {
      const res = await messageApi.conversation(conv.id);
      setMessages(res.data?.messages || []);
    } catch { toast.error('Erreur chargement'); }
  };

  const handleSend = async (e) => {
    e.preventDefault();
    if (!newMessage.trim()) return;
    try {
      const res = await messageApi.envoyerMessage(selectedConv.id, { message: newMessage });
      setMessages(prev => [...prev, res.data]);
      setNewMessage('');
      fetchConversations();
    } catch { toast.error('Erreur envoi'); }
  };

  const handleNewConv = async (e) => {
    e.preventDefault();
    if (!sujet.trim() || !newMessage.trim()) return;
    try {
      const res = await messageApi.creerConversation({ sujet, participants: [], message: newMessage });
      setSelectedConv(res.data);
      setMessages(res.data?.messages || []);
      setShowNewConv(false);
      setSujet('');
      setNewMessage('');
      fetchConversations();
      toast.success('Conversation créée');
    } catch { toast.error('Erreur création'); }
  };

  return (
    <div className="flex h-[calc(100vh-7rem)] gap-4">
      <div className="w-80 bg-white rounded-2xl border border-neutral-100 shadow-sm flex flex-col">
        <div className="p-4 border-b border-neutral-100 flex items-center justify-between">
          <h2 className="font-bold text-neutral-800">Messages</h2>
          <button onClick={() => setShowNewConv(true)} className="px-3 py-1.5 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">Nouveau</button>
        </div>
        <div className="flex-1 overflow-y-auto p-2 space-y-1">
          {conversations.map(conv => (
            <button
              key={conv.id}
              onClick={() => openConversation(conv)}
              className={`w-full text-left p-3 rounded-xl text-sm transition-colors ${selectedConv?.id === conv.id ? 'bg-primary-50 text-primary-700' : 'hover:bg-neutral-50 text-neutral-700'}`}
            >
              <p className="font-medium truncate">{conv.sujet || 'Sans sujet'}</p>
              <p className="text-xs text-neutral-400 mt-0.5 truncate">
                {conv.dernier_message?.message || 'Aucun message'}
              </p>
            </button>
          ))}
        </div>
      </div>

      <div className="flex-1 bg-white rounded-2xl border border-neutral-100 shadow-sm flex flex-col">
        {showNewConv ? (
          <form onSubmit={handleNewConv} className="p-6 space-y-4">
            <h3 className="font-bold text-neutral-800">Nouvelle conversation</h3>
            <input value={sujet} onChange={e => setSujet(e.target.value)} placeholder="Sujet" className="w-full px-4 py-2.5 border border-neutral-300 rounded-xl text-sm" />
            <textarea value={newMessage} onChange={e => setNewMessage(e.target.value)} placeholder="Votre message..." rows={4} className="w-full px-4 py-2.5 border border-neutral-300 rounded-xl text-sm resize-none" />
            <div className="flex gap-3">
              <button type="button" onClick={() => setShowNewConv(false)} className="px-4 py-2 border border-neutral-300 rounded-xl text-sm">Annuler</button>
              <button type="submit" className="px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-medium">Créer</button>
            </div>
          </form>
        ) : selectedConv ? (
          <>
            <div className="p-4 border-b border-neutral-100">
              <h3 className="font-bold text-neutral-800">{selectedConv.sujet || 'Conversation'}</h3>
            </div>
            <div className="flex-1 overflow-y-auto p-4 space-y-3">
              {messages.map(msg => (
                <div key={msg.id} className="flex gap-3">
                  <div className="w-8 h-8 bg-primary-100 text-primary-700 rounded-full flex items-center justify-center text-xs font-bold shrink-0">
                    {msg.expediteur?.nom?.[0]}{msg.expediteur?.prenom?.[0]}
                  </div>
                  <div className="bg-neutral-50 rounded-xl px-4 py-2.5 max-w-[70%]">
                    <p className="text-xs text-neutral-400 mb-1">{msg.expediteur?.prenom} {msg.expediteur?.nom}</p>
                    <p className="text-sm text-neutral-700">{msg.message}</p>
                    {msg.fichier_url && <a href={msg.fichier_url} target="_blank" className="text-primary-600 text-xs underline mt-1 block">📎 {msg.fichier_nom}</a>}
                  </div>
                </div>
              ))}
            </div>
            <form onSubmit={handleSend} className="p-4 border-t border-neutral-100 flex gap-3">
              <input value={newMessage} onChange={e => setNewMessage(e.target.value)} placeholder="Écrivez un message..." className="flex-1 px-4 py-2.5 border border-neutral-300 rounded-xl text-sm" />
              <button type="submit" className="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-medium">Envoyer</button>
            </form>
          </>
        ) : (
          <div className="flex-1 flex items-center justify-center text-neutral-400">Sélectionnez une conversation</div>
        )}
      </div>
    </div>
  );
}
