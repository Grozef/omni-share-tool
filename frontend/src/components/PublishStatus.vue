<template>
  <div class="publish-status">
    <div class="status-header">
      <span class="status-title">Broadcasting</span>
      <span class="status-overall" :class="`overall--${overallStatus}`">{{ overallStatus }}</span>
    </div>

    <div class="platform-statuses">
      <div
        v-for="platform in platforms"
        :key="platform"
        class="ps-row"
        :class="`ps-row--${platformStatus(platform)}`"
        :style="{ '--pcolor': platformColor(platform) }"
      >
        <div class="ps-icon">
          <ion-icon :name="`logo-${platform}`" />
        </div>
        <span class="ps-name">{{ platform }}</span>

        <div class="ps-indicator">
          <template v-if="platformStatus(platform) === 'success'">
            <ion-icon name="checkmark-circle" class="icon-ok" />
          </template>
          <template v-else-if="platformStatus(platform) === 'failed'">
            <ion-icon name="close-circle" class="icon-err" />
          </template>
          <template v-else>
            <div class="pulse-ring" />
            <div class="pulse-dot" />
          </template>
        </div>

        <a
          v-if="platformUrl(platform)"
          :href="platformUrl(platform)!"
          target="_blank"
          class="ps-link"
        >↗</a>
      </div>
    </div>

    <div class="error-messages" v-if="errorMessages.length">
      <div v-for="err in errorMessages" :key="err.platform" class="error-item">
        <span class="error-platform" :style="{ color: platformColor(err.platform as Platform) }">{{ err.platform }}</span>
        <span class="error-msg">{{ err.message }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { IonIcon } from '@ionic/vue'
import { usePublicationsStore, type PublicationResult } from '@/stores/publications'
import { useAuthStore } from '@/stores/auth'
import { PLATFORM_META, type Platform } from '@/stores/platforms'

const props = defineProps<{
  publicationId: number
  platforms: Platform[]
}>()

const publicationsStore = usePublicationsStore()
const authStore = useAuthStore()

const results = ref<Record<string, PublicationResult>>({})
let echoChannel: any = null

const overallStatus = computed(() => {
  const statuses = props.platforms.map(p => results.value[p]?.status ?? 'pending')
  if (statuses.every(s => s === 'success')) return 'published'
  if (statuses.some(s => s === 'failed') && statuses.some(s => s === 'success')) return 'partial'
  if (statuses.every(s => s === 'failed')) return 'failed'
  return 'processing'
})

function platformStatus(p: Platform): 'success' | 'failed' | 'pending' {
  return (results.value[p]?.status as any) ?? 'pending'
}

function platformUrl(p: Platform): string | null {
  return results.value[p]?.platform_url ?? null
}

function platformColor(p: Platform) { return PLATFORM_META[p]?.color ?? '#666' }

const errorMessages = computed(() =>
  props.platforms
    .filter(p => results.value[p]?.status === 'failed' && results.value[p]?.error_message)
    .map(p => ({ platform: p, message: results.value[p].error_message! }))
)

async function loadResults() {
  const pub = await publicationsStore.fetchOne(props.publicationId)
  for (const result of pub.results) {
    results.value[result.platform] = result
  }
}

function setupWebSocket() {
  // @ts-ignore
  const Echo = window.Echo
  if (!Echo) return

  echoChannel = Echo.private(`user.${authStore.user?.id}`)
    .listen('.publication.status', (event: any) => {
      if (event.publication_id !== props.publicationId) return

      if (event.status === 'platform_update') {
        for (const [platform, data] of Object.entries(event.data)) {
          if (!results.value[platform]) {
            results.value[platform] = { platform } as any
          }
          Object.assign(results.value[platform], data)
        }
      }
    })
}

onMounted(async () => {
  await loadResults()
  setupWebSocket()

  // Poll as fallback if WebSocket not available
  const interval = setInterval(async () => {
    if (overallStatus.value !== 'processing') {
      clearInterval(interval)
      return
    }
    await loadResults()
  }, 4000)

  onUnmounted(() => {
    clearInterval(interval)
    echoChannel?.stopListening('.publication.status')
  })
})
</script>

<style scoped>
.publish-status {
  margin-top: 20px;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.07);
  border-radius: 10px;
  overflow: hidden;
}

.status-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid rgba(255,255,255,0.07);
}

.status-title {
  font-size: 10px;
  letter-spacing: 0.15em;
  text-transform: uppercase;
  color: #5a5a72;
}

.status-overall {
  font-size: 10px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  padding: 2px 8px;
  border-radius: 4px;
}
.overall--processing { color: #7c6fff; background: rgba(124,111,255,0.12); }
.overall--published  { color: #4ade80; background: rgba(74,222,128,0.12); }
.overall--partial    { color: #fb923c; background: rgba(251,146,60,0.12); }
.overall--failed     { color: #f87171; background: rgba(248,113,113,0.12); }

.platform-statuses { padding: 8px; display: flex; flex-direction: column; gap: 2px; }

.ps-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 7px;
  transition: background 0.2s;
}

.ps-row--success { background: rgba(74,222,128,0.04); }
.ps-row--failed  { background: rgba(248,113,113,0.04); }

.ps-icon { font-size: 14px; color: var(--pcolor); flex-shrink: 0; }
.ps-name { font-size: 11px; flex: 1; color: #e8e8f0; letter-spacing: 0.04em; }

.ps-indicator { position: relative; width: 16px; height: 16px; flex-shrink: 0; }

.icon-ok  { font-size: 15px; color: #4ade80; }
.icon-err { font-size: 15px; color: #f87171; }

/* Pulse animation for pending */
.pulse-ring, .pulse-dot {
  position: absolute;
  top: 50%; left: 50%;
  border-radius: 50%;
  transform: translate(-50%, -50%);
}
.pulse-dot {
  width: 6px; height: 6px;
  background: #7c6fff;
}
.pulse-ring {
  width: 12px; height: 12px;
  border: 1px solid #7c6fff;
  animation: pulsate 1.5s ease-out infinite;
  opacity: 0;
}

@keyframes pulsate {
  0%   { transform: translate(-50%,-50%) scale(0.5); opacity: 1; }
  100% { transform: translate(-50%,-50%) scale(1.5); opacity: 0; }
}

.ps-link {
  font-size: 10px;
  color: #5a5a72;
  text-decoration: none;
  padding: 2px 4px;
  transition: color 0.15s;
}
.ps-link:hover { color: #e8e8f0; }

.error-messages {
  border-top: 1px solid rgba(255,255,255,0.07);
  padding: 8px 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.error-item { display: flex; gap: 8px; align-items: baseline; }
.error-platform { font-size: 10px; letter-spacing: 0.08em; text-transform: uppercase; flex-shrink: 0; }
.error-msg { font-size: 10px; color: #5a5a72; line-height: 1.4; }
</style>
