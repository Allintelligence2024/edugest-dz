import React from 'react'
import { render, waitFor } from '@testing-library/react-native'
import PresencesScreen from '../../../screens/parent/PresencesScreen'
import { presencesApi } from '../../../api/endpoints'
import { useEnfants } from '../../../context/EnfantContext'

jest.mock('../../../api/endpoints', () => ({ presencesApi: { byEleve: jest.fn() } }))
jest.mock('../../../context/EnfantContext', () => ({ useEnfants: jest.fn() }))

const ENFANT = { id: 'e1', prenom: 'Lina', nom_complet: 'BENALI Lina' }

describe('ParentPresencesScreen (P1)', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    useEnfants.mockReturnValue({ enfantActif: ENFANT, loading: false })
  })

  it('affiche le taux + statuts accentués backend', async () => {
    presencesApi.byEleve.mockResolvedValue({
      success: true,
      data: [{
        id: 'p1', statut: 'absent', motif: 'Malade', created_at: '2026-09-10T08:00:00Z',
        seance: { cours: { groupe: { matiere: { nom_fr: 'Maths' } }, enseignant: { nom: 'B', prenom: 'K' } } },
      }],
      meta: { stats: { total: 10, presents: 9, absents: 1, taux: 90 } },
    })
    const { getByTestId, findByText } = render(<PresencesScreen />)
    await waitFor(() => expect(getByTestId('presences-taux')).toBeTruthy())
    expect(await findByText('Maths')).toBeTruthy()
    expect(presencesApi.byEleve).toHaveBeenCalledWith('e1', { per_page: 50 })
  })
})
