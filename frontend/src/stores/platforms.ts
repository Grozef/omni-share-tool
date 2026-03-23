import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '@/services/api'

export type Platform = 'facebook' | 'instagram' | 'snapchat' | 'tiktok' | 'youtube' | 'pinterest'

export interface PlatformAccount {
  id: number
  platform: Platform
  platform_username: string | null
  token_expires_at: string | null
  is_active: boolean
  created_at: string
}

export const PLATFORM_META: Record<Platform, { label: string; color: string; icon: string }> = {
  facebook:  { label: 'Facebook',  color: '#1877F2', icon: 'logo-facebook' },
  instagram: { label: 'Instagram', color: '#E4405F', icon: 'logo-instagram' },
  snapchat:  { label: 'Snapchat',  color: '#FFFC00', icon: 'logo-snapchat' },
  tiktok:    { label: 'TikTok',    color: '#000000', icon: 'logo-tiktok' },
  youtube:   { label: 'YouTube',   color: '#FF0000', icon: 'logo-youtube' },
  pinterest: { label: 'Pinterest', color: '#E60023', icon: 'logo-pinterest' },
}

export const usePlatformsStore = defineStore('platforms', () => {
  const accounts = ref<PlatformAccount[]>([])
  const loading = ref(false)

  const connectedPlatforms = computed(() =>
    accounts.value.filter(a => a.is_active).map(a => a.platform)
  )

  const isConnected = (platform: Platform) =>
    accounts.value.some(a => a.platform === platform && a.is_active)

  const getAccount = (platform: Platform) =>
    accounts.value.find(a => a.platform === platform)

  async function fetchAccounts() {
    loading.value = true
    try {
      const { data } = await api.get('/platforms')
      accounts.value = data
    } finally {
      loading.value = false
    }
  }

  async function connect(platform: Platform): Promise<string> {
    const { data } = await api.get(`/platforms/${platform}/connect`)
    return data.auth_url
  }

  async function disconnect(platform: Platform) {
    await api.delete(`/platforms/${platform}`)
    accounts.value = accounts.value.filter(a => a.platform !== platform)
  }

  return {
    accounts,
    loading,
    connectedPlatforms,
    isConnected,
    getAccount,
    fetchAccounts,
    connect,
    disconnect,
  }
})
