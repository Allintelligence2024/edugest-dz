import React from 'react'
import { render, waitFor } from '@testing-library/react-native'
import PlanningScreen from '../../../screens/enseignant/PlanningScreen'
import { enseignantApi } from '../../../api/endpoints'

jest.mock('../../../api/endpoints', () => ({
  enseignantApi: { seances: jest.fn() },
}))

describe('EnseignantPlanningScreen (P1-C5)', () => {
  beforeEach(() => jest.clearAllMocks())

  it('lit les séances plates de /seances et filtre le jour', async () => {
    const auj = new Date().toISOString().split('T')[0]
    enseignantApi.seances.mockResolvedValue({
      success: true,
      data: [{
        id: 's1', date_seance: `${auj}T00:00:00.000000Z`, statut: 'planifiée',
        heure_debut: '08:00', heure_fin: '09:00',
        cours: { groupe: { nom: '3AS-1', matiere: { nom_fr: 'Maths' } }, salle: { nom: 'S12' } },
      }],
    })
    const { findByText } = render(<PlanningScreen navigation={{ navigate: jest.fn() }} />)
    expect(await findByText('3AS-1 — Maths')).toBeTruthy()
    expect(enseignantApi.seances).toHaveBeenCalled()
  })
})
