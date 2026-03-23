import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/services/api'
import type { Platform } from '@/stores/platforms'

export interface MediaFile {
  id: number
  type: 'image' | 'video' | 'short_video'
  original_url: string
  thumbnail_url: string | null
  processed_url: string | null
  duration: number | null
  width: number | null
  height: number | null
}

export interface PublicationResult {
  id: number
  platform: Platform
  status: 'pending' | 'success' | 'failed'
  platform_post_id: string | null
  platform_url: string | null
  error_message: string | null
  published_at: string | null
}

export interface Publication {
  id: number
  caption: string | null
  hashtags: string[]
  platforms: Platform[]
  status: 'draft' | 'pending' | 'processing' | 'published' | 'partial' | 'failed'
  scheduled_at: string | null
  media_files: MediaFile[]
  results: PublicationResult[]
  created_at: string
}

export interface PublicationDraft {
  caption: string
  hashtags: string[]
  platforms: Platform[]
  media_id?: number | null
  scheduled_at?: string | null
}

export const usePublicationsStore = defineStore('publications', () => {
  const publications = ref<Publication[]>([])
  const currentPublication = ref<Publication | null>(null)
  const loading = ref(false)
  const pagination = ref({ current_page: 1, last_page: 1, total: 0 })

  async function fetch(page = 1) {
    loading.value = true
    try {
      const { data } = await api.get(`/publications?page=${page}`)
      publications.value = data.data
      pagination.value = {
        current_page: data.current_page,
        last_page: data.last_page,
        total: data.total,
      }
    } finally {
      loading.value = false
    }
  }

  async function fetchOne(id: number) {
    const { data } = await api.get(`/publications/${id}`)
    currentPublication.value = data
    return data as Publication
  }

  async function create(draft: PublicationDraft): Promise<Publication> {
    const { data } = await api.post('/publications', draft)
    publications.value.unshift(data)
    return data
  }

  async function retry(id: number) {
    const { data } = await api.post(`/publications/${id}/retry`)
    return data
  }

  async function remove(id: number) {
    await api.delete(`/publications/${id}`)
    publications.value = publications.value.filter(p => p.id !== id)
  }

  function updateFromWebSocket(publicationId: number, status: string, platformData: Record<string, any>) {
    const pub = publications.value.find(p => p.id === publicationId)
    if (pub) {
      pub.status = status as Publication['status']
      // Update individual platform results
      for (const [platform, result] of Object.entries(platformData)) {
        const existing = pub.results.find(r => r.platform === platform)
        if (existing) {
          Object.assign(existing, result)
        }
      }
    }
  }

  // Media
  async function uploadMedia(file: File, publicationId?: number, onProgress?: (p: number) => void): Promise<MediaFile> {
    const formData = new FormData()
    formData.append('file', file)
    if (publicationId) formData.append('publication_id', String(publicationId))

    const { data } = await api.post('/media/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (e) => {
        if (onProgress && e.total) onProgress(Math.round((e.loaded * 100) / e.total))
      },
    })
    return data
  }

  async function convertMediaToVideo(mediaId: number, options: { duration?: number; platform?: string }): Promise<void> {
    await api.post(`/media/${mediaId}/convert-video`, options)
  }

  async function getMediaPreview(mediaId: number): Promise<MediaFile> {
    const { data } = await api.get(`/media/${mediaId}/preview`)
    return data
  }

  return {
    publications,
    currentPublication,
    loading,
    pagination,
    fetch,
    fetchOne,
    create,
    retry,
    remove,
    updateFromWebSocket,
    uploadMedia,
    convertMediaToVideo,
    getMediaPreview,
  }
})
