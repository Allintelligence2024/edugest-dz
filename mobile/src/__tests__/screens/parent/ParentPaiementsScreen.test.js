import React from 'react'
import { render, waitFor } from '@testing-library/react-native'
import PaiementsScreen from '../../../screens/parent/PaiementsScreen'
import { paiementsApi } from '../../../api/endpoints'
import { useEnfants } from '../../../context/EnfantContext'

jest.mock('../../../api/endpoints', () => ({ paiementsApi: { byEleve: jest.fn() } }))
jest.mock('../../../context/EnfantContext', () => ({ useEnfants: jest.fn() }))

const ENFANT = { id: 'e1', prenom: 'Lina', nom_complet: 'BENALI Lina' }

describe('ParentPaiementsScreen (P1-C4)', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    useEnfants.mockReturnValue({ enfantActif: ENFANT, loading: false })
    paiementsApi.byEleve.mockResolvedValue({
      success: true,
      data: {
        financier: { total_paye: 10000, total_dette: 5000, nb_impayes: 1 },
        factures: [{
          id: 'f1', numero_facture: 'FAC-2026-001', statut: 'partiellement_payée',
          date_emission: '2026-09-01', date_echeance: '2026-10-01', total_ttc: 15000,
          paiements: [{ id: 'p1' }],
        }],
      },
    })
  })

  it('affiche le financier + numero_facture, sans bouton SATIM', async () => {
    const { getByTestId, findByText, queryByText } = render(<PaiementsScreen />)
    await waitFor(() => expect(getByTestId('paiements-dette')).toBeTruthy())
    expect(await findByText('FAC-2026-001')).toBeTruthy()
    expect(await findByText(/sec.*tariat/)).toBeTruthy()
    expect(queryByText(/SATIM|CIB/)).toBeNull()
  })
})
