import React from 'react'
import { render, waitFor } from '@testing-library/react-native'
import DashboardScreen from '../../../screens/parent/DashboardScreen'
import { notesApi, presencesApi, paiementsApi, planningApi } from '../../../api/endpoints'
import { useEnfants } from '../../../context/EnfantContext'

jest.mock('../../../api/endpoints', () => ({
  notesApi: { byEleve: jest.fn() },
  presencesApi: { byEleve: jest.fn() },
  paiementsApi: { byEleve: jest.fn() },
  planningApi: { list: jest.fn() },
}))
jest.mock('../../../context/EnfantContext', () => ({ useEnfants: jest.fn() }))
jest.mock('../../../context/AuthContext', () => ({
  useAuth: () => ({ user: { prenom: 'Yasmine' } }),
}))

const ENFANT = { id: 'e1', prenom: 'Lina', nom_complet: 'BENALI Lina' }

describe('ParentDashboardScreen (P1-C3)', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    useEnfants.mockReturnValue({ enfants: [ENFANT], enfantActif: ENFANT, loading: false })
    notesApi.byEleve.mockResolvedValue({ success: true, data: { moyenne_generale: 14.5 } })
    presencesApi.byEleve.mockResolvedValue({ success: true, data: [], meta: { stats: { taux: 96 } } })
    paiementsApi.byEleve.mockResolvedValue({ success: true, data: { financier: { total_dette: 5000 } } })
    planningApi.list.mockResolvedValue({
      success: true,
      data: [{ date: '2999-01-01', jour: 'lundi', seances: [{ id: 'c1', heure_debut: '08:00', groupe: { matiere: { nom_fr: 'Maths' } } }] }],
    })
  })

  it('affiche les 4 cartes réelles', async () => {
    const { getByTestId } = render(<DashboardScreen navigation={{ navigate: jest.fn() }} />)
    await waitFor(() => expect(getByTestId('dash-moyenne')).toBeTruthy())
    expect(getByTestId('dash-presence')).toBeTruthy()
    expect(getByTestId('dash-dette')).toBeTruthy()
    expect(getByTestId('dash-prochain')).toBeTruthy()
    expect(notesApi.byEleve).toHaveBeenCalledWith('e1')
    expect(planningApi.list).toHaveBeenCalledWith({ eleve_id: 'e1' })
  })

  it('affiche un message sans enfant', async () => {
    useEnfants.mockReturnValue({ enfants: [], enfantActif: null, loading: false })
    const { findByText } = render(<DashboardScreen navigation={{ navigate: jest.fn() }} />)
    expect(await findByText('Aucun enfant rattaché à ce compte.')).toBeTruthy()
  })
})
