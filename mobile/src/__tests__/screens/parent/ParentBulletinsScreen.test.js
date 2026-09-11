import React from 'react'
import { Linking } from 'react-native'
import { render, waitFor, fireEvent } from '@testing-library/react-native'
import BulletinsScreen from '../../../screens/parent/BulletinsScreen'
import { bulletinsApi } from '../../../api/endpoints'
import { useEnfants } from '../../../context/EnfantContext'

jest.mock('../../../api/endpoints', () => ({ bulletinsApi: { byEleve: jest.fn() } }))
jest.mock('../../../context/EnfantContext', () => ({ useEnfants: jest.fn() }))

const ENFANT = { id: 'e1', prenom: 'Lina', nom_complet: 'BENALI Lina' }

describe('ParentBulletinsScreen (P1-C2)', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    useEnfants.mockReturnValue({ enfantActif: ENFANT, loading: false })
    bulletinsApi.byEleve.mockResolvedValue({
      success: true,
      data: { bulletins: [{
        id: 'b1', trimestre: 'T1', annee_scolaire: '2025-2026', moyenne_generale: 14.2,
        rang: 3, effectif_classe: 30, appreciation_gen: 'Bien', fichier_url: 'bulletins/b1.pdf',
        groupe: { nom: '3AS-1' },
      }] },
    })
  })

  it('affiche la liste + ouvre le PDF via Linking', async () => {
    jest.spyOn(Linking, 'canOpenURL').mockResolvedValue(true)
    const open = jest.spyOn(Linking, 'openURL').mockResolvedValue(true)
    const { getByTestId, findByText } = render(<BulletinsScreen />)
    await waitFor(() => expect(getByTestId('bulletin-b1')).toBeTruthy())
    expect(await findByText('1er trimestre')).toBeTruthy()
    fireEvent.press(getByTestId('bulletin-pdf-b1'))
    await waitFor(() => expect(open).toHaveBeenCalled())
    expect(open.mock.calls[0][0]).toMatch(/\/storage\/bulletins\/b1\.pdf$/)
  })
})
