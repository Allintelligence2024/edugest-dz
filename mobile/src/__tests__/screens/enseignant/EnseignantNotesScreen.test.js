import React from 'react'
import { render, fireEvent, waitFor } from '@testing-library/react-native'
import EnseignantNotesScreen from '../../../screens/enseignant/NotesScreen'
import { enseignantApi } from '../../../api/endpoints'

// PILOTE-30OCT (P1-C1/P1-C6) — l'écran passe par le client API officiel.
// On mocke la couche endpoints, pas le réseau.
jest.mock('../../../api/endpoints', () => ({
  enseignantApi: {
    groupes: jest.fn(),
    evaluations: {
      list: jest.fn(),
      notes: jest.fn(),
      saisirNotes: jest.fn(),
    },
  },
}))

jest.mock('@react-navigation/native', () => ({
  ...jest.requireActual('@react-navigation/native'),
  useNavigation: () => ({ navigate: jest.fn() }),
}))

describe('EnseignantNotesScreen (P1-C1)', () => {
  beforeEach(() => jest.clearAllMocks())

  it('charge et affiche les groupes via le client officiel', async () => {
    enseignantApi.groupes.mockResolvedValue({
      success: true,
      data: [{ id: 'g1', nom: '3AS-1', matiere: { nom_fr: 'Maths' }, niveau: '3AS' }],
    })

    const { findByText } = render(<EnseignantNotesScreen />)

    expect(await findByText('3AS-1')).toBeTruthy()
    expect(enseignantApi.groupes).toHaveBeenCalledTimes(1)
  })

  it('parcours complet : groupe → évaluation → saisie → enregistrement', async () => {
    enseignantApi.groupes.mockResolvedValue({
      success: true, data: [{ id: 'g1', nom: '3AS-1' }],
    })
    enseignantApi.evaluations.list.mockResolvedValue({
      success: true,
      data: [{ id: 'e1', titre: 'Devoir 1', type_eval: 'devoir', date_evaluation: '2026-09-20', note_sur: 20, coefficient: 2 }],
    })
    enseignantApi.evaluations.notes.mockResolvedValue({
      success: true,
      data: [{ eleve_id: 'el1', nom_complet: 'BENALI Ahmed', note: 14, absent: false, commentaire: '' }],
    })
    enseignantApi.evaluations.saisirNotes.mockResolvedValue({ success: true, stats: { nb_notes: 1 } })

    const { findByText, getByTestId } = render(<EnseignantNotesScreen />)

    fireEvent.press(await findByText('3AS-1'))
    expect(enseignantApi.evaluations.list).toHaveBeenCalledWith({ groupe_id: 'g1', per_page: 50 })

    fireEvent.press(await findByText('Devoir 1'))
    expect(await findByText('BENALI Ahmed')).toBeTruthy()

    fireEvent.press(getByTestId('btn-enregistrer'))
    await waitFor(() =>
      expect(enseignantApi.evaluations.saisirNotes).toHaveBeenCalledWith('e1', {
        notes: [expect.objectContaining({ eleve_id: 'el1', note: 14, absent: false })],
      })
    )
  })

  it('erreur réseau : message visible, pas de crash', async () => {
    enseignantApi.groupes.mockRejectedValue({
      success: false, error: { code: 'NETWORK_ERROR', message: 'Serveur injoignable' },
    })

    const { findByText } = render(<EnseignantNotesScreen />)

    expect(await findByText('Serveur injoignable')).toBeTruthy()
    expect(await findByText('Aucun groupe trouvé')).toBeTruthy()
  })
})
