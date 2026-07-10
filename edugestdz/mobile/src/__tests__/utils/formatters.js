const formatters = {
  formatDate: (date) => {
    if (!date) return ''
    const d = new Date(date)
    const day = String(d.getDate()).padStart(2, '0')
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const year = d.getFullYear()
    return `${day}/${month}/${year}`
  },

  formatCurrency: (amount, currency = 'DZD') => {
    if (amount == null || isNaN(amount)) return '0,00 DZD'
    const parts = Number(amount).toFixed(2).split('.')
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
    return `${parts.join(',')} ${currency}`
  },

  formatPhone: (phone) => {
    if (!phone) return ''
    const cleaned = phone.replace(/\D/g, '')
    if (cleaned.length === 10) {
      return `${cleaned.slice(0, 2)} ${cleaned.slice(2, 4)} ${cleaned.slice(4, 6)} ${cleaned.slice(6, 8)} ${cleaned.slice(8, 10)}`
    }
    return phone
  },

  getNameInitials: (firstName, lastName) => {
    const first = firstName ? firstName.charAt(0).toUpperCase() : ''
    const last = lastName ? lastName.charAt(0).toUpperCase() : ''
    return `${first}${last}`
  },

  capitalize: (str) => {
    if (!str) return ''
    return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase()
  },

  truncate: (str, maxLength = 50) => {
    if (!str) return ''
    if (str.length <= maxLength) return str
    return `${str.slice(0, maxLength)}…`
  },

  formatAbsenceRate: (absences, totalDays) => {
    if (!totalDays || totalDays === 0) return '0%'
    const rate = (absences / totalDays) * 100
    return `${Math.round(rate)}%`
  },

  getStatusColor: (status) => {
    const colors = {
      present: '#4CAF50',
      absent: '#F44336',
      late: '#FF9800',
      excused: '#2196F3',
      pending: '#9E9E9E',
    }
    return colors[status] || '#757575'
  },

  formatMontant: (montant) => {
    if (montant == null || isNaN(montant)) return '0,00 DA'
    return `${Number(montant).toLocaleString('fr-DZ', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })} DA`
  },

  parseMontant: (str) => {
    if (!str) return 0
    const cleaned = String(str).replace(/[^\d,]/g, '').replace(',', '.')
    return parseFloat(cleaned) || 0
  },
}

export default formatters
