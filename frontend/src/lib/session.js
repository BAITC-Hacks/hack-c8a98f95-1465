import { reactive } from 'vue'
import { api, setProfile } from './api'

const STORAGE_KEY = 'alemedu.profile'
let pendingProfiles = null

export const session = reactive({
  profile: null,
  profiles: { customers: [], teams: [] },
  loading: false,
  error: null,
})

export function profileKey(profile) {
  return profile ? `${profile.role}:${profile.id}` : ''
}

function storedProfileKey() {
  try {
    return localStorage.getItem(STORAGE_KEY)
  } catch {
    return null
  }
}

export function selectProfile(key) {
  const profiles = [...session.profiles.customers, ...session.profiles.teams]
  session.profile = profiles.find((profile) => profileKey(profile) === key) || null
  setProfile(session.profile)
  try {
    if (session.profile) localStorage.setItem(STORAGE_KEY, profileKey(session.profile))
    else localStorage.removeItem(STORAGE_KEY)
  } catch {
    // Private browsing can disable storage; the current session still works.
  }
  return session.profile
}

export async function loadProfiles() {
  if (pendingProfiles) return pendingProfiles
  session.loading = true
  session.error = null
  pendingProfiles = (async () => {
    try {
      const profiles = await api.profiles()
      session.profiles = {
        customers: Array.isArray(profiles.customers) ? profiles.customers : [],
        teams: Array.isArray(profiles.teams) ? profiles.teams : [],
      }
      const available = [...session.profiles.customers, ...session.profiles.teams]
      const preferred = profileKey(session.profile) || storedProfileKey()
      const selected = available.find((profile) => profileKey(profile) === preferred) || available[0]
      selectProfile(profileKey(selected))
      return session.profiles
    } catch (error) {
      session.error = error
      return null
    } finally {
      session.loading = false
      pendingProfiles = null
    }
  })()
  return pendingProfiles
}
