<template>
  <ion-page class="dashboard">
    <div class="dash-grid">
      <!-- Header -->
      <header class="dash-header">
        <div class="dash-brand">
          <span class="brand-mark">◈</span>
          <span class="brand-name">SocialPulse</span>
        </div>
        <nav class="dash-nav">
          <router-link to="/compose" class="nav-cta">
            <ion-icon name="add" /> New Post
          </router-link>
          <router-link to="/history" class="nav-link">History</router-link>
          <router-link to="/platforms" class="nav-link">Platforms</router-link>
          <router-link to="/portfolio" class="nav-link">Portfolio</router-link>
        </nav>
        <div class="dash-user">
          <span class="user-avatar">{{ userInitial }}</span>
        </div>
      </header>

      <!-- Stat bar -->
      <section class="stat-bar">
        <div class="stat" v-for="stat in stats" :key="stat.label">
          <span class="stat__value">{{ stat.value }}</span>
          <span class="stat__label">{{ stat.label }}</span>
          <span class="stat__delta" :class="stat.delta > 0 ? 'up' : 'down'" v-if="stat.delta !== undefined">
            {{ stat.delta > 0 ? '↑' : '↓' }} {{ Math.abs(stat.delta) }}%
          </span>
        </div>
      </section>

      <!-- Platform health row -->
      <section class="platform-health">
        <h3 class="section-title">Connections</h3>
        <div class="health-grid">
          <div
            v-for="p in allPlatforms"
            :key="p"
            class="health-card"
            :class="{ 'health-card--connected': isConnected(p) }"
            :style="{ '--pcolor': platformColor(p) }"
            @click="isConnected(p) ? null : $router.push('/platforms')"
          >
            <div class="health-icon">
              <ion-icon :name="`logo-${p}`" />
            </div>
            <span class="health-label">{{ p }}</span>
            <span class="health-status">
              <span class="status-dot" :class="isConnected(p) ? 'on' : 'off'" />
              {{ isConnected(p) ? getUsername(p) : 'Connect' }}
            </span>
          </div>
        </div>
      </section>

      <!-- Recent Publications -->
      <section class="recent-pubs">
        <div class="section-header">
          <h3 class="section-title">Recent</h3>
          <router-link to="/history" class="see-all">See all →</router-link>
        </div>
        <div class="pub-list" v-if="recentPubs.length">
          <div
            v-for="pub in recentPubs"
            :key="pub.id"
            class="pub-row"
            @click="$router.push(`/publications/${pub.id}`)"
          >
            <div class="pub-thumb">
              <img v-if="pub.media_files[0]?.thumbnail_url" :src="pub.media_files[0].thumbnail_url" alt="" />
              <div v-else class="pub-thumb--text">
                <ion-icon name="text-outline" />
              </div>
            </div>
            <div class="pub-info">
              <p class="pub-caption">{{ pub.caption?.slice(0, 80) || '(no caption)' }}</p>
              <div class="pub-meta">
                <span
                  v-for="p in pub.platforms"
                  :key="p"
                  class="pub-platform-tag"
                  :style="{ color: platformColor(p) }"
                >{{ p }}</span>
              </div>
            </div>
            <div class="pub-status-col">
              <span class="pub-status-badge" :class="`badge--${pub.status}`">{{ pub.status }}</span>
              <span class="pub-date">{{ formatDate(pub.created_at) }}</span>
            </div>
          </div>
        </div>
        <div v-else class="empty-state">
          <ion-icon name="megaphone-outline" />
          <p>No publications yet.</p>
          <router-link to="/compose" class="nav-cta">Create your first post</router-link>
        </div>
      </section>
    </div>
  </ion-page>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { IonPage, IonIcon } from '@ionic/vue'
import { useAuthStore } from '@/stores/auth'
import { usePlatformsStore, PLATFORM_META, type Platform } from '@/stores/platforms'
import { usePublicationsStore } from '@/stores/publications'

const authStore = useAuthStore()
const platformsStore = usePlatformsStore()
const publicationsStore = usePublicationsStore()

const allPlatforms: Platform[] = ['facebook', 'instagram', 'tiktok', 'youtube', 'snapchat', 'pinterest']

const userInitial = computed(() => authStore.user?.name?.[0]?.toUpperCase() ?? 'U')
const recentPubs = computed(() => publicationsStore.publications.slice(0, 8))

function isConnected(p: Platform) { return platformsStore.isConnected(p) }
function getUsername(p: Platform) { return platformsStore.getAccount(p)?.platform_username ?? '' }
function platformColor(p: Platform) { return PLATFORM_META[p]?.color ?? '#666' }

const stats = computed(() => {
  const pubs = publicationsStore.publications
  const total = pubs.length
  const published = pubs.filter(p => p.status === 'published').length
  const failed = pubs.filter(p => p.status === 'failed').length
  const connected = platformsStore.connectedPlatforms.length
  return [
    { label: 'Posts Published', value: published, delta: 12 },
    { label: 'Platforms Active', value: connected },
    { label: 'Total Posts', value: total },
    { label: 'Failed', value: failed, delta: failed > 0 ? -100 : 0 },
  ]
})

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

onMounted(async () => {
  await Promise.all([
    platformsStore.fetchAccounts(),
    publicationsStore.fetch(),
  ])
})
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=DM+Mono:ital,wght@0,300;0,400;0,500&family=Cormorant+Garamond:wght@300;500;600&display=swap');

.dashboard {
  --bg: #080810;
  --surface: #0f0f1a;
  --surface-2: #161624;
  --border: rgba(255,255,255,0.07);
  --text: #e8e8f0;
  --text-muted: #5a5a72;
  --accent: #7c6fff;
  background: var(--bg);
  font-family: 'DM Mono', monospace;
  color: var(--text);
  min-height: 100vh;
}

.dash-grid {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 32px 48px;
  display: flex;
  flex-direction: column;
  gap: 0;
}

/* Header */
.dash-header {
  display: flex;
  align-items: center;
  gap: 32px;
  padding: 24px 0 20px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 40px;
}

.dash-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-right: auto;
}

.brand-mark {
  color: var(--accent);
  font-size: 20px;
  line-height: 1;
}

.brand-name {
  font-family: 'Cormorant Garamond', serif;
  font-size: 20px;
  font-weight: 600;
  letter-spacing: 0.04em;
}

.dash-nav { display: flex; align-items: center; gap: 24px; }

.nav-link {
  font-size: 11px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--text-muted);
  text-decoration: none;
  transition: color 0.15s;
}
.nav-link:hover { color: var(--text); }

.nav-cta {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 18px;
  background: var(--accent);
  color: white;
  border-radius: 8px;
  font-size: 11px;
  letter-spacing: 0.08em;
  text-decoration: none;
  transition: opacity 0.2s;
}
.nav-cta:hover { opacity: 0.85; }

.user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--surface-2);
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 500;
}

/* Stats */
.stat-bar {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 40px;
}

.stat {
  padding: 24px 28px;
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.stat:last-child { border-right: none; }

.stat__value {
  font-family: 'Cormorant Garamond', serif;
  font-size: 36px;
  font-weight: 500;
  line-height: 1;
  color: var(--text);
}

.stat__label {
  font-size: 10px;
  letter-spacing: 0.15em;
  text-transform: uppercase;
  color: var(--text-muted);
}

.stat__delta { font-size: 11px; margin-top: 4px; }
.stat__delta.up { color: #4ade80; }
.stat__delta.down { color: #f87171; }

/* Platform Health */
.section-title {
  font-size: 10px;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--text-muted);
  margin: 0 0 16px;
  font-weight: 400;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.see-all {
  font-size: 11px;
  color: var(--accent);
  text-decoration: none;
  letter-spacing: 0.06em;
}

.platform-health { margin-bottom: 48px; }

.health-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 8px;
}

.health-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 16px 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  overflow: hidden;
}

.health-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 50% 0%, color-mix(in srgb, var(--pcolor) 8%, transparent), transparent 70%);
  opacity: 0;
  transition: opacity 0.3s;
}

.health-card--connected::before { opacity: 1; }
.health-card--connected { border-color: color-mix(in srgb, var(--pcolor) 25%, transparent); }

.health-icon {
  font-size: 22px;
  color: var(--text-muted);
  transition: color 0.2s;
}
.health-card--connected .health-icon { color: var(--pcolor); }

.health-label {
  font-size: 10px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--text-muted);
}

.health-status {
  font-size: 9px;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  gap: 4px;
}

.status-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
}
.status-dot.on { background: #4ade80; box-shadow: 0 0 6px #4ade80; animation: pulse 2s infinite; }
.status-dot.off { background: var(--text-muted); }

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

/* Recent Publications */
.pub-list { display: flex; flex-direction: column; gap: 2px; }

.pub-row {
  display: grid;
  grid-template-columns: 48px 1fr auto;
  gap: 16px;
  align-items: center;
  padding: 12px 16px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
  transition: border-color 0.15s;
}
.pub-row:hover { border-color: rgba(255,255,255,0.14); }

.pub-thumb {
  width: 44px;
  height: 44px;
  border-radius: 6px;
  overflow: hidden;
  background: var(--surface-2);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.pub-thumb img { width: 100%; height: 100%; object-fit: cover; }
.pub-thumb--text { color: var(--text-muted); font-size: 18px; }

.pub-caption {
  font-family: 'Cormorant Garamond', serif;
  font-size: 14px;
  color: var(--text);
  margin: 0 0 4px;
  line-height: 1.4;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pub-meta { display: flex; gap: 8px; flex-wrap: wrap; }

.pub-platform-tag {
  font-size: 9px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.pub-status-col {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  flex-shrink: 0;
}

.pub-status-badge {
  font-size: 9px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  padding: 3px 8px;
  border-radius: 4px;
}
.badge--published { background: rgba(74,222,128,0.12); color: #4ade80; }
.badge--failed    { background: rgba(248,113,113,0.12); color: #f87171; }
.badge--pending   { background: rgba(251,191,36,0.12); color: #fbbf24; }
.badge--processing { background: rgba(124,111,255,0.12); color: var(--accent); }
.badge--partial   { background: rgba(251,146,60,0.12); color: #fb923c; }
.badge--draft     { background: rgba(90,90,114,0.2); color: var(--text-muted); }

.pub-date { font-size: 10px; color: var(--text-muted); }

/* Empty state */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 60px 0;
  color: var(--text-muted);
}
.empty-state ion-icon { font-size: 40px; opacity: 0.3; }
.empty-state p { font-size: 13px; }
</style>
