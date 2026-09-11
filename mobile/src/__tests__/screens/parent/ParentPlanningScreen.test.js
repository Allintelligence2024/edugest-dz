import React from 'react'
import { render, waitFor } from '@testing-library/react-native'
import PlanningScreen from '../../../screens/parent/PlanningScreen'
import { planningApi } from '../../../api/endpoints'
import { useEnfants } from '../../../context/EnfantContext'

jest.mock('../../../api/endpoints', () => ({ planningApi: { list: jest.fn() } }))
jest.mock('../../../context/EnfantContext', () => ({ useEnfants: jest.fn() }))

const ENFANT = { id: 'e1', prenom: 'Lina', nom_complet: 'BENALI Lina' }

describe('ParentPlanningScreen (P1-C5)', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    useEnfants.mockReturnValue({ enfantActif: ENFANT, loading: false })
  })

  it('affiche le planning groupé par jour, filtré eleve_id', async () => {
    planningApi.list.mockResolvedValue({
      success: true,
      data: [{ date: '2026-09-14', jour: 'lundi', seances: [{
        id: 'c1', heure_debut: '08:00:00', heure_fin: '09:00:00',
        groupe: { matiere: { nom_fr: 'Mathématiques' } },
        enseignant: { nom: 'BENALI', prenom: 'Karim' }, salle: { nom: 'S12' },
      }] }],
    })
    const { getByTestId, findByText } = render(<PlanningScreen />)
    await waitFor(() => expect(getByTestId('jour-2026-09-14')).toBeTruthy())
    expect(await findByText('Mathématiques')).toBeTruthy()
    expect(planningApi.list).toHaveBeenCalledWith({ eleve_id: 'e1' })
  })
})
