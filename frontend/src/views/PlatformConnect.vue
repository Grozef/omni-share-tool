<template>
  <ion-page class="platforms-page">
    <div class="platforms-wrap">
      <header class="page-header">
        <router-link to="/" class="back-btn"><ion-icon name="arrow-back-outline" /></router-link>
        <div>
          <h1 class="page-title">Platforms</h1>
          <p class="page-sub">Manage your social accounts</p>
        </div>
      </header>

      <div class="platforms-grid">
        <div
          v-for="platform in allPlatforms"
          :key="platform"
          class="platform-card"
          :class="{ 'platform-card--connected': isConnected(platform) }"
          :style="{ '--pcolor': platformColor(platform) }"
        >
          <!-- Glow background -->
          <div class="card-glow" />

          <div class="card-top">
            <div class="platform-icon-wrap">
              <ion-icon :name="`logo-${platform}`" class="platform-icon" />
            </div>
            <div class="platform-info">
              <span class="platform-name">{{ PLATFORM_META[platform].label }}</span>
              <div class="connection-status">
                <span class="status-indicator" :class="isConnected(platform) ? 'status-on' : 'status-off'" />
                <span class="status-text">{{ isConnected(platform) ? 'Connected' : 'Not connected' }}</span>
              </div>
            </div>
            <div class="card-badge" v-if="isConnected(platform)">
              <ion-icon name="checkmark-circle" />
            </div>
          </div>

          <!-- Connected state details -->
          <div v-if="isConnected(platform)" class="card-details">
            <div class="detail-row">
              <span class="detail-key">Account</span>
              <span class="detail-val">@{{ getAccount(platform)?.platform_username ?? '—' }}</span>
            </div>
            <div class="detail-row" v-if="getAccount(platform)?.token_expires_at">
              <span class="detail-key">Expires</span>
              <span class="detail-val" :class="{ 'expires-soon': isExpiringSoon(platform) }">
                {{ formatExpiry(getAccount(platform)!.token_expires_at!) }}
              </span>
            </div>
            <div class="detail-row">
              <span class="detail-key">Since</span>
              <span class="detail-val">{{ formatDate(getAccount(platform)?.created_at ?? '') }}</span>
            </div>
          </div>

          <!-- Constraint hints -->
          <div class="constraint-badges">
            <span v-for="c in getPlatformConstraints(platform)" :key="c" class="constraint-badge">{{ c }}</span>
          </div>

          <!-- Actions -->
          <div class="card-actions">
            <button
              v-if="!isConnected(platform)"
              class="btn-connect"
              :class="{ loading: connecting === platform }"
              @click="connect(platform)"
            >
              <span class="spinner-sm" v-if="connecting === platform" />
              <ion-icon name="link-outline" v-else />
              Connect
            </button>
            <template v-else>
              <button class="btn-reconnect" @click="connect(platform)">
                <ion-icon name="refresh-outline" /> Refresh
              </button>
              <button class="btn-disconnect" @click="disconnect(platform)">
                <ion-icon name="unlink-outline" />
              </button>
            </template>
          </div>
        </div>
      </div>
    </div>
  </ion-page>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { IonPage, IonIcon } from '@ionic/vue'
import { Browser } from '@capacitor/browser'
import { usePlatformsStore, PLATFORM_META, type Platform } from '@/stores/platforms'

const platformsStore = usePlatformsStore()
const connecting = ref<Platform | null>(null)

const allPlatforms: Platform[] = ['facebook', 'instagram', 'tiktok', 'youtube', 'snapchat', 'pinterest']

function isConnected(p: Platform) { return platformsStore.isConnected(p) }
function getAccount(p: Platform) { return platformsStore.getAccount(p) }
function platformColor(p: Platform) { return PLATFORM_META[p]?.color ?? '#666' }

const platformConstraintMap: Record<Platform, string[]> = {
  instagram: ['9:16 Reels', '90s max', '100MB'],
  tiktok:    ['9:16 video', '10min max', '4GB'],
  youtube:   ['Shorts <60s', '9:16', '256GB'],
  facebook:  ['Posts + Reels', '4GB', '4hr max'],
  snapchat:  ['9:16 only', '60s max', '32MB'],
  pinterest: ['2:3 image', '15min video', '2GB'],
}

function getPlatformConstraints(p: Platform): string[] {
  return platformConstraintMap[p] ?? []
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatExpiry(d: string) {
  const diff = new Date(d).getTime() - Date.now()
  const days = Math.floor(diff / 86400000)
  if (days < 0) return 'Expired'
  if (days === 0) return 'Today'
  if (days < 7) return `${days}d`
  return formatDate(d)
}

function isExpiringSoon(p: Platform): boolean {
  const exp = getAccount(p)?.token_expires_at
  if (!exp) return false
  return new Date(exp).getTime() - Date.now() < 7 * 86400000
}

async function connect(platform: Platform) {
  connecting.value = platform
  try {
    const authUrl = await platformsStore.connect(platform)
    // Open OAuth in system browser (Capacitor) or new window (web)
    if (typeof (window as any).Capacitor !== 'undefined') {
      await Browser.open({ url: authUrl })
    } else {
      const popup = window.open(authUrl, 'oauth', 'width=600,height=700')
      // Poll for callback completion
      const timer = setInterval(() => {
        if (popup?.closed) {
          clearInterval(timer)
          platformsStore.fetchAccounts()
        }
      }, 500)
    }
  } finally {
    connecting.value = null
  }
}

async function disconnect(platform: Platform) {
  if (!confirm(`Disconnect from ${PLATFORM_META[platform].label}?`)) return
  await platformsStore.disconnect(platform)
}

onMounted(() => platformsStore.fetchAccounts())
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Cormorant+Garamond:wght@300;600&display=swap');

.platforms-page {
  --bg: #080810;
  --surface: #0f0f1a;
  --border: rgba(255,255,255,0.07);
  --text: #e8e8f0;
  --text-muted: #5a5a72;
  background: var(--bg);
  font-family: 'DM Mono', monospace;
  color: var(--text);
  min-height: 100vh;
}

.platforms-wrap {
  max-width: 1100px;
  margin: 0 auto;
  padding: 32px;
}

.page-header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 40px;
}

.back-btn {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text-muted);
  text-decoration: none;
  transition: all 0.15s;
  font-size: 18px;
}
.back-btn:hover { border-color: rgba(255,255,255,0.15); color: var(--text); }

.page-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: 28px;
  font-weight: 600;
  margin: 0 0 2px;
}

.page-sub {
  font-size: 11px;
  color: var(--text-muted);
  letter-spacing: 0.06em;
  margin: 0;
}

.platforms-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.platform-card {
  position: relative;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 22px;
  overflow: hidden;
  transition: border-color 0.3s, transform 0.2s;
}

.platform-card:hover { transform: translateY(-1px); }

.platform-card--connected {
  border-color: color-mix(in srgb, var(--pcolor) 20%, transparent);
}

.card-glow {
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse at 0% 0%, color-mix(in srgb, var(--pcolor) 6%, transparent) 0%, transparent 65%);
  pointer-events: none;
  opacity: 0;
  transition: opacity 0.3s;
}
.platform-card--connected .card-glow { opacity: 1; }

.card-top {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 18px;
}

.platform-icon-wrap {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  background: color-mix(in srgb, var(--pcolor) 10%, transparent);
  border: 1px solid color-mix(in srgb, var(--pcolor) 20%, transparent);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.platform-icon {
  font-size: 22px;
  color: var(--pcolor);
}

.platform-info { flex: 1; min-width: 0; }

.platform-name {
  font-size: 14px;
  font-weight: 500;
  display: block;
  margin-bottom: 4px;
}

.connection-status {
  display: flex;
  align-items: center;
  gap: 5px;
}

.status-indicator {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}
.status-on  { background: #4ade80; box-shadow: 0 0 6px #4ade80; animation: pulse 2s infinite; }
.status-off { background: var(--text-muted); }

.status-text { font-size: 10px; color: var(--text-muted); letter-spacing: 0.06em; }

.card-badge ion-icon {
  font-size: 20px;
  color: #4ade80;
}

.card-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px 0;
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
  margin-bottom: 14px;
}

.detail-row { display: flex; justify-content: space-between; align-items: center; }
.detail-key { font-size: 10px; color: var(--text-muted); letter-spacing: 0.1em; }
.detail-val { font-size: 11px; }
.expires-soon { color: #fbbf24; }

.constraint-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  margin-bottom: 16px;
}

.constraint-badge {
  font-size: 9px;
  padding: 2px 7px;
  background: rgba(255,255,255,0.04);
  border: 1px solid var(--border);
  border-radius: 4px;
  color: var(--text-muted);
  letter-spacing: 0.06em;
}

.card-actions { display: flex; gap: 8px; }

.btn-connect, .btn-reconnect {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px;
  border-radius: 8px;
  font-family: inherit;
  font-size: 11px;
  letter-spacing: 0.08em;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-connect {
  background: color-mix(in srgb, var(--pcolor) 15%, transparent);
  border: 1px solid color-mix(in srgb, var(--pcolor) 35%, transparent);
  color: var(--pcolor);
}
.btn-connect:hover { background: color-mix(in srgb, var(--pcolor) 25%, transparent); }
.btn-connect.loading { opacity: 0.5; pointer-events: none; }

.btn-reconnect {
  background: rgba(255,255,255,0.04);
  border: 1px solid var(--border);
  color: var(--text-muted);
}
.btn-reconnect:hover { border-color: rgba(255,255,255,0.15); color: var(--text); }

.btn-disconnect {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: rgba(248,113,113,0.08);
  border: 1px solid rgba(248,113,113,0.2);
  color: #f87171;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  transition: all 0.2s;
  flex-shrink: 0;
}
.btn-disconnect:hover { background: rgba(248,113,113,0.15); }

.spinner-sm {
  display: inline-block;
  width: 12px;
  height: 12px;
  border: 2px solid transparent;
  border-top-color: currentColor;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
</style>
