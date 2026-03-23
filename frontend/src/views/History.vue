<template>
  <ion-page class="history-page">
    <div class="history-wrap">
      <header class="page-header">
        <router-link to="/" class="back-btn"><ion-icon name="arrow-back-outline" /></router-link>
        <div>
          <h1 class="page-title">Publication History</h1>
          <p class="page-sub">{{ publicationsStore.pagination.total }} posts total</p>
        </div>
        <div class="filters">
          <button
            v-for="f in statusFilters"
            :key="f.value"
            class="filter-btn"
            :class="{ active: activeFilter === f.value }"
            @click="activeFilter = f.value"
          >{{ f.label }}</button>
        </div>
      </header>

      <div class="pub-timeline" v-if="filteredPubs.length">
        <div
          v-for="pub in filteredPubs"
          :key="pub.id"
          class="pub-card"
          :class="`pub-card--${pub.status}`"
        >
          <!-- Status stripe -->
          <div class="status-stripe" :class="`stripe--${pub.status}`" />

          <!-- Media -->
          <div class="pub-media">
            <img
              v-if="pub.media_files[0]?.thumbnail_url"
              :src="pub.media_files[0].thumbnail_url"
              alt=""
              class="pub-media-img"
            />
            <div v-else class="pub-media-placeholder">
              <ion-icon name="text-outline" />
            </div>
          </div>

          <!-- Content -->
          <div class="pub-content">
            <div class="pub-content-top">
              <p class="pub-text">{{ pub.caption?.slice(0, 120) || '(no caption)' }}</p>
              <time class="pub-time">{{ formatDateTime(pub.created_at) }}</time>
            </div>

            <!-- Platform results grid -->
            <div class="platform-results">
              <div
                v-for="result in pub.results"
                :key="result.id"
                class="platform-result"
                :class="`result--${result.status}`"
                :style="{ '--pcolor': platformColor(result.platform as Platform) }"
                :title="result.error_message || result.platform_url || result.platform"
              >
                <ion-icon :name="`logo-${result.platform}`" class="result-icon" />
                <span class="result-label">{{ result.platform }}</span>
                <span class="result-state">
                  <ion-icon v-if="result.status === 'success'"  name="checkmark-circle" class="icon-success" />
                  <ion-icon v-else-if="result.status === 'failed'"  name="close-circle" class="icon-fail" />
                  <span v-else class="spinner-xs" />
                </span>
                <a
                  v-if="result.platform_url"
                  :href="result.platform_url"
                  target="_blank"
                  class="result-link"
                  @click.stop
                >
                  <ion-icon name="open-outline" />
                </a>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="pub-actions">
            <span class="pub-status-badge" :class="`badge--${pub.status}`">{{ pub.status }}</span>
            <button
              v-if="hasFailures(pub)"
              class="retry-btn"
              @click.stop="retry(pub.id)"
              :disabled="retrying === pub.id"
            >
              <span class="spinner-xs" v-if="retrying === pub.id" />
              <ion-icon name="refresh-outline" v-else />
              Retry
            </button>
          </div>
        </div>
      </div>

      <div v-else class="empty-state">
        <ion-icon name="megaphone-outline" />
        <p>No {{ activeFilter !== 'all' ? activeFilter : '' }} publications yet.</p>
      </div>

      <!-- Pagination -->
      <div class="pagination" v-if="publicationsStore.pagination.last_page > 1">
        <button
          class="page-btn"
          :disabled="publicationsStore.pagination.current_page <= 1"
          @click="goPage(publicationsStore.pagination.current_page - 1)"
        >←</button>
        <span class="page-info">
          {{ publicationsStore.pagination.current_page }} / {{ publicationsStore.pagination.last_page }}
        </span>
        <button
          class="page-btn"
          :disabled="publicationsStore.pagination.current_page >= publicationsStore.pagination.last_page"
          @click="goPage(publicationsStore.pagination.current_page + 1)"
        >→</button>
      </div>
    </div>
  </ion-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { IonPage, IonIcon } from '@ionic/vue'
import { usePublicationsStore, type Publication } from '@/stores/publications'
import { PLATFORM_META, type Platform } from '@/stores/platforms'

const publicationsStore = usePublicationsStore()
const activeFilter = ref<string>('all')
const retrying = ref<number | null>(null)

const statusFilters = [
  { label: 'All', value: 'all' },
  { label: 'Published', value: 'published' },
  { label: 'Partial', value: 'partial' },
  { label: 'Failed', value: 'failed' },
  { label: 'Pending', value: 'pending' },
]

const filteredPubs = computed(() => {
  if (activeFilter.value === 'all') return publicationsStore.publications
  return publicationsStore.publications.filter(p => p.status === activeFilter.value)
})

function platformColor(p: Platform) { return PLATFORM_META[p]?.color ?? '#666' }
function hasFailures(pub: Publication) { return pub.results.some(r => r.status === 'failed') }

function formatDateTime(d: string) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function retry(id: number) {
  retrying.value = id
  try {
    await publicationsStore.retry(id)
    await publicationsStore.fetch()
  } finally {
    retrying.value = null
  }
}

async function goPage(page: number) {
  await publicationsStore.fetch(page)
  window.scrollTo(0, 0)
}

onMounted(() => publicationsStore.fetch())
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Cormorant+Garamond:wght@300;600&display=swap');

.history-page {
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

.history-wrap {
  max-width: 900px;
  margin: 0 auto;
  padding: 32px;
}

.page-header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 32px;
  flex-wrap: wrap;
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
  font-size: 18px;
  flex-shrink: 0;
  transition: all 0.15s;
}
.back-btn:hover { border-color: rgba(255,255,255,0.15); color: var(--text); }

.page-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: 28px;
  font-weight: 600;
  margin: 0 0 2px;
}
.page-sub { font-size: 11px; color: var(--text-muted); margin: 0; }

.filters {
  display: flex;
  gap: 4px;
  margin-left: auto;
  flex-wrap: wrap;
}

.filter-btn {
  padding: 6px 14px;
  background: none;
  border: 1px solid var(--border);
  border-radius: 100px;
  color: var(--text-muted);
  font-family: inherit;
  font-size: 10px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  cursor: pointer;
  transition: all 0.15s;
}
.filter-btn.active, .filter-btn:hover {
  border-color: rgba(124, 111, 255, 0.5);
  color: #7c6fff;
}

.pub-timeline {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pub-card {
  display: grid;
  grid-template-columns: 4px 72px 1fr auto;
  gap: 0 16px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  align-items: stretch;
  transition: border-color 0.2s;
}
.pub-card:hover { border-color: rgba(255,255,255,0.12); }

.status-stripe { width: 4px; flex-shrink: 0; }
.stripe--published { background: #4ade80; }
.stripe--failed    { background: #f87171; }
.stripe--partial   { background: #fb923c; }
.stripe--pending   { background: #fbbf24; animation: blink 1.5s step-end infinite; }
.stripe--processing { background: #7c6fff; animation: blink 0.8s step-end infinite; }
.stripe--draft     { background: var(--text-muted); }

@keyframes blink { 50% { opacity: 0; } }

.pub-media {
  width: 72px;
  display: flex;
  align-items: center;
  padding: 14px 0;
}

.pub-media-img {
  width: 56px;
  height: 56px;
  border-radius: 8px;
  object-fit: cover;
}

.pub-media-placeholder {
  width: 56px;
  height: 56px;
  border-radius: 8px;
  background: rgba(255,255,255,0.04);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  color: var(--text-muted);
}

.pub-content { padding: 16px 0; display: flex; flex-direction: column; gap: 12px; }

.pub-content-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }

.pub-text {
  font-family: 'Cormorant Garamond', serif;
  font-size: 15px;
  line-height: 1.5;
  margin: 0;
  color: var(--text);
}

.pub-time { font-size: 10px; color: var(--text-muted); white-space: nowrap; flex-shrink: 0; }

.platform-results {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.platform-result {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 10px;
  letter-spacing: 0.06em;
  border: 1px solid;
  background: rgba(255,255,255,0.02);
  position: relative;
}

.result--success { border-color: rgba(74,222,128,0.2); }
.result--failed  { border-color: rgba(248,113,113,0.2); }
.result--pending { border-color: rgba(251,191,36,0.15); }

.result-icon { font-size: 13px; color: var(--pcolor); }
.result-label { color: var(--text-muted); text-transform: uppercase; }

.icon-success { color: #4ade80; font-size: 12px; }
.icon-fail    { color: #f87171; font-size: 12px; }

.result-link {
  color: var(--text-muted);
  font-size: 11px;
  transition: color 0.15s;
}
.result-link:hover { color: var(--text); }

.pub-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  justify-content: center;
  gap: 8px;
  padding: 16px;
  border-left: 1px solid var(--border);
}

.pub-status-badge {
  font-size: 9px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  padding: 3px 8px;
  border-radius: 4px;
  white-space: nowrap;
}
.badge--published { background: rgba(74,222,128,0.12);  color: #4ade80; }
.badge--failed    { background: rgba(248,113,113,0.12); color: #f87171; }
.badge--pending   { background: rgba(251,191,36,0.12);  color: #fbbf24; }
.badge--processing { background: rgba(124,111,255,0.12); color: #7c6fff; }
.badge--partial   { background: rgba(251,146,60,0.12);  color: #fb923c; }
.badge--draft     { background: rgba(90,90,114,0.2);    color: var(--text-muted); }

.retry-btn {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  background: rgba(124,111,255,0.1);
  border: 1px solid rgba(124,111,255,0.25);
  color: #7c6fff;
  border-radius: 6px;
  font-family: inherit;
  font-size: 10px;
  letter-spacing: 0.08em;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s;
}
.retry-btn:hover { background: rgba(124,111,255,0.2); }
.retry-btn:disabled { opacity: 0.5; }

.spinner-xs {
  display: inline-block;
  width: 10px;
  height: 10px;
  border: 1.5px solid transparent;
  border-top-color: currentColor;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 80px 0;
  color: var(--text-muted);
}
.empty-state ion-icon { font-size: 44px; opacity: 0.2; }
.empty-state p { font-size: 13px; }

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-top: 32px;
}

.page-btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: var(--surface);
  border: 1px solid var(--border);
  color: var(--text);
  cursor: pointer;
  font-size: 16px;
  transition: all 0.15s;
}
.page-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.page-btn:not(:disabled):hover { border-color: rgba(255,255,255,0.2); }

.page-info { font-size: 11px; color: var(--text-muted); }

@keyframes spin { to { transform: rotate(360deg); } }
</style>
