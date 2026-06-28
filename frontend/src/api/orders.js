import axios from 'axios'

const api = axios.create({ baseURL: '/api' })

export const fetchOrders = (params) => api.get('/orders', { params })

export const fetchOrder = (id) => api.get(`/orders/${id}`)

export const fetchMetrics = () => api.get('/orders/metrics')

export const updateOrderStatus = (id, status, notes = '') =>
  api.post(`/orders/${id}/status`, { status, notes })

export const fetchAffiliateSummary = (id) => api.get(`/affiliates/${id}/summary`)
