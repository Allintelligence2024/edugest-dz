import React from 'react'
import { Alert } from 'react-native'
import { render, waitFor, fireEvent } from '@testing-library/react-native'
import PresencesScreen from '../../../screens/enseignant/PresencesScreen'
import { enseignantApi } from '../../../api/endpoints'

jest.mock('../../../api/endpoints', () => ({
  enseignantApi: { presences: { parSeance: jest.fn(), saisir: jest.fn() } },
}))

describe('EnseignantPresencesScreen appel (P1-C5)', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    jest.spyOn(Alert, 'alert').mockImplementation(() => {})
    enseignantApi.presences.parSeance.mockResolvedValue({
      success: true,
      data: [
        { eleve_id: 'el1', nom_complet: 'BENALI Ahmed', statut: null, motif: null },
        { eleve_id: 'el2', nom_complet: 'CHRAIBI Sara', statut: 'absent', motif: 'Malade' },
      ],
      seance: { id: 's1', date: '2026-09-11', groupe: '3AS-1' },
    })
    enseignantApi.presences.saisir.mockResolvedValue({ success: true, message: '2 présence(s)' })
  })

  it('liste plate + défaut présent + envoi du payload', async () => {
    const goBack = jest.fn()
    const { getByTestId, findByText } = render(
      <PresencesScreen route={{ params: { seanceId: 's1', titreSeance: '3AS-1' } }} navigation={{ goBack }} />
    )
    expect(await findByText('BENALI Ahmed')).toBeTruthy()
    expect(await findByText('CHRAIBI Sara')).toBeTruthy()
    fireEvent.press(getByTestId('appel-valider'))
    await waitFor(() => expect(enseignantApi.presences.saisir).toHaveBeenCalledTimes(1))
    expect(enseignantApi.presences.saisir).toHaveBeenCalledWith('s1', {
      presences: [
        { eleve_id: 'el1', statut: 'présent', motif: null },
        { eleve_id: 'el2', statut: 'absent', motif: 'Malade' },
      ],
    })
  })
})
