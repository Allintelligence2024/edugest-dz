import React from 'react'
import { render, waitFor } from '@testing-library/react-native'
import NotesScreen from '../../../screens/parent/NotesScreen'
import { notesApi } from '../../../api/endpoints'
import { useEnfants } from '../../../context/EnfantContext'

jest.mock('../../../api/endpoints', () => ({ notesApi: { byEleve: jest.fn() } }))
jest.mock('../../../context/EnfantContext', () => ({ useEnfants: jest.fn() }))

const ENFANT = { id: 'e1', prenom: 'Lina', nom_complet: 'BENALI Lina' }

describe('ParentNotesScreen (P1)', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    useEnfants.mockReturnValue({ enfantActif: ENFANT, loading: false })
  })

  it('affiche la moyenne officielle + notes groupées', async () => {
    notesApi.byEleve.mockResolvedValue({
      success: true,
      data: {
        moyenne_generale: 13.75,
        notes: [{
          matiere: 'Mathématiques', couleur: '#f00', coefficient: 5, moyenne: 14,
          notes: [{ id: 'n1', note: 14, note_sur: 20, type: 'devoir', date: '2026-09-01', absent: false }],
        }],
      },
    })
    const { getByTestId, findByText } = render(<NotesScreen />)
    await waitFor(() => expect(getByTestId('notes-moyenne')).toBeTruthy())
    expect(await findByText('Mathématiques')).toBeTruthy()
    expect(notesApi.byEleve).toHaveBeenCalledWith('e1')
  })

  it('état vide sans notes', async () => {
    notesApi.byEleve.mockResolvedValue({ success: true, data: { moyenne_generale: null, notes: [] } })
    const { findByText } = render(<NotesScreen />)
    expect(await findByText('Aucune note pour le moment.')).toBeTruthy()
  })
})
