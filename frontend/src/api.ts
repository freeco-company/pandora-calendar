import axios from 'axios'
import { ref } from 'vue'

const API_BASE = (import.meta.env.VITE_API_BASE as string) ?? 'http://localhost:8000/api'

export const api = axios.create({
  baseURL: API_BASE,
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
})

const TOKEN_KEY = 'pandora_calendar_token'
const USER_KEY = 'pandora_calendar_user'

export const tokenRef = ref<string | null>(localStorage.getItem(TOKEN_KEY))

export function getToken(): string | null {
  return tokenRef.value
}

export function setToken(t: string | null): void {
  tokenRef.value = t
  if (t) localStorage.setItem(TOKEN_KEY, t)
  else localStorage.removeItem(TOKEN_KEY)
}

export function getStoredUser(): { id: number; name: string; email: string } | null {
  const raw = localStorage.getItem(USER_KEY)
  return raw ? JSON.parse(raw) : null
}

export function setStoredUser(u: { id: number; name: string; email: string } | null): void {
  if (u) localStorage.setItem(USER_KEY, JSON.stringify(u))
  else localStorage.removeItem(USER_KEY)
}

api.interceptors.request.use((config) => {
  const token = getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

export async function demoLogin(email: string) {
  const { data } = await api.post('/demo/login', { email })
  setToken(data.token)
  setStoredUser(data.user)
  return data.user
}

export async function logout() {
  setToken(null)
  setStoredUser(null)
}

export type Phase = 'menstrual' | 'follicular' | 'ovulation' | 'luteal' | 'unknown'

export interface BodyRhythm {
  date: string
  phase: Phase
  cycle_day: number | null
  next_period_eta: string | null
  days_until_next_period: number | null
}

export interface CyclePrediction {
  today: string
  latest_cycle_start: string | null
  avg_cycle_length: number
  avg_period_length: number
  next_period_eta: string | null
  ovulation_eta: string | null
  sample_size: number
  confidence: 'high' | 'low' | 'none'
}

export interface CycleRecord {
  id: number
  start_date: string
  end_date: string | null
  peak_flow: number | null
  length_days: number | null
  notes: string | null
}

export interface SymptomRecord {
  id: number
  logged_on: string
  tags: string[]
  mood: string | null
  basal_temperature: number | null
  note: string | null
}

export interface DodoCheckin {
  checked_on: string
  mood: string
  phase: Phase | null
  cycle_day: number | null
  dodo_response: string
}

export const CalendarApi = {
  cycles: () => api.get<{ data: CycleRecord[]; prediction: CyclePrediction; body_rhythm: BodyRhythm }>('/v1/cycles'),
  storeCycle: (payload: { start_date: string; end_date?: string; peak_flow?: number; notes?: string }) =>
    api.post<{ data: CycleRecord }>('/v1/cycles', payload),
  deleteCycle: (id: number) => api.delete(`/v1/cycles/${id}`),
  symptoms: (params?: { from?: string; to?: string }) =>
    api.get<{ data: SymptomRecord[] }>('/v1/symptoms', { params }),
  storeSymptom: (payload: { logged_on: string; tags: string[]; mood?: string; note?: string }) =>
    api.post<{ data: SymptomRecord }>('/v1/symptoms', payload),
  bodyRhythm: () => api.get<{ data: BodyRhythm; prediction: CyclePrediction }>('/v1/body-rhythm/me'),
  dodoCheckin: (mood: 'good' | 'okay' | 'bad') =>
    api.post<{ data: DodoCheckin & { id: number } }>('/v1/dodo/checkin', { mood }),
  dodoRecent: () => api.get<{ data: DodoCheckin[] }>('/v1/dodo/recent'),
}
