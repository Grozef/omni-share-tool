<template>
  <div class="sp-widget" :data-theme="theme">
    <div v-if="loading" class="sp-loading">
      <div class="sp-spinner" />
    </div>

    <template v-else-if="portfolio">
      <header class="sp-header">
        <div class="sp-avatar">{{ portfolio.title?.[0]?.toUpperCase() ?? '?' }}</div>
        <div class="sp-profile">
          <span class="sp-name">{{ portfolio.title }}</span>
          <span class="sp-bio">{{ portfolio.bio }}</span>
        </div>
      </header>

      <div class="sp-grid">
        <a
          v-for="post in displayedFeed"
          :key="post.platform + post.published_at"
          :href="post.platform_url || '#'"
          target="_blank"
          class="sp-post"
          :title="`${post.platform}: ${post.caption?.slice(0,80)}`"
        >
          <img v-if="post.thumbnail" :src="post.thumbnail" :alt="post.caption ?? ''" class="sp-img" loading="lazy" />
          <div v-else class="sp-post-placeholder">
            <span class="sp-platform-tag">{{ post.platform }}</span>
          </div>
          <div class="sp-overlay">
            <span class="sp-icon">{{ platformEmoji(post.platform) }}</span>
          </div>
        </a>
      </div>

      <footer class="sp-footer">
        <a :href="portfolioUrl" target="_blank" class="sp-link">
          View full portfolio →
        </a>
      </footer>
    </template>

    <div v-else class="sp-empty">No content found.</div>
  </div>
</template>

<script lang="ts">
import { defineComponent, ref, computed, onMounted, PropType } from 'vue'

// This component is compiled as a Web Component via defineCustomElement in main.ts
export default defineComponent({
  name: 'SocialPortfolio',
  props: {
    slug:  { type: String, required: true },
    theme: { type: String as PropType<'dark'|'light'|'minimal'>, default: 'minimal' },
    limit: { type: [String, Number], default: 9 },
    apiBase: { type: String, default: '' },
  },
  setup(props) {
    const loading = ref(true)
    const portfolio = ref<any>(null)
    const feed = ref<any[]>([])

    const displayedFeed = computed(() => feed.value.slice(0, Number(props.limit)))

    const portfolioUrl = computed(() => {
      const base = props.apiBase || window.location.origin
      return `${base}/portfolio/${props.slug}`
    })

    function platformEmoji(p: string): string {
      const map: Record<string, string> = {
        instagram: '📷', tiktok: '🎵', youtube: '▶', facebook: 'f', snapchat: '👻', pinterest: '📌',
      }
      return map[p] ?? '◈'
    }

    async function fetchData() {
      loading.value = true
      const base = props.apiBase || `${window.location.origin}/api`
      try {
        const [portfolioRes, feedRes] = await Promise.all([
          fetch(`${base}/portfolio/${props.slug}`).then(r => r.json()),
          fetch(`${base}/portfolio/${props.slug}/feed`).then(r => r.json()),
        ])
        portfolio.value = portfolioRes
        feed.value = feedRes
      } catch {
        portfolio.value = null
      } finally {
        loading.value = false
      }
    }

    onMounted(fetchData)

    return { loading, portfolio, feed, displayedFeed, portfolioUrl, platformEmoji }
  },
})
</script>

<style>
/* NOTE: :host selector targets the custom element shadow root boundary */
:host {
  display: block;
  font-family: system-ui, -apple-system, sans-serif;
  --sp-bg: #ffffff;
  --sp-text: #111111;
  --sp-muted: #888888;
  --sp-border: #e5e5e5;
  --sp-accent: #7c6fff;
}

:host([data-theme="dark"]),
.sp-widget[data-theme="dark"] {
  --sp-bg: #111111;
  --sp-text: #f0f0f0;
  --sp-muted: #666666;
  --sp-border: #2a2a2a;
}

:host([data-theme="minimal"]),
.sp-widget[data-theme="minimal"] {
  --sp-bg: #fafafa;
  --sp-text: #1a1a1a;
  --sp-muted: #999999;
  --sp-border: #eeeeee;
}

.sp-widget {
  background: var(--sp-bg);
  color: var(--sp-text);
  border: 1px solid var(--sp-border);
  border-radius: 12px;
  overflow: hidden;
  max-width: 480px;
  font-size: 14px;
}

.sp-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 48px;
}

.sp-spinner {
  width: 24px;
  height: 24px;
  border: 2px solid var(--sp-border);
  border-top-color: var(--sp-accent);
  border-radius: 50%;
  animation: sp-spin 0.7s linear infinite;
}

@keyframes sp-spin { to { transform: rotate(360deg); } }

.sp-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-bottom: 1px solid var(--sp-border);
}

.sp-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--sp-accent);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  font-weight: 600;
  flex-shrink: 0;
}

.sp-profile { display: flex; flex-direction: column; gap: 2px; }
.sp-name    { font-weight: 600; font-size: 14px; }
.sp-bio     { font-size: 12px; color: var(--sp-muted); line-height: 1.4; }

.sp-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2px;
  background: var(--sp-border);
}

.sp-post {
  aspect-ratio: 1;
  position: relative;
  overflow: hidden;
  text-decoration: none;
  background: var(--sp-border);
}

.sp-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.3s;
}

.sp-post:hover .sp-img { transform: scale(1.05); }

.sp-post-placeholder {
  width: 100%;
  height: 100%;
  background: var(--sp-bg);
  display: flex;
  align-items: center;
  justify-content: center;
}

.sp-platform-tag {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--sp-muted);
}

.sp-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.3);
  display: flex;
  align-items: flex-end;
  justify-content: flex-start;
  padding: 6px;
  opacity: 0;
  transition: opacity 0.2s;
}
.sp-post:hover .sp-overlay { opacity: 1; }

.sp-icon { font-size: 14px; }

.sp-footer {
  padding: 12px 16px;
  border-top: 1px solid var(--sp-border);
  text-align: center;
}

.sp-link {
  font-size: 12px;
  color: var(--sp-accent);
  text-decoration: none;
}
.sp-link:hover { text-decoration: underline; }

.sp-empty {
  padding: 32px;
  text-align: center;
  font-size: 12px;
  color: var(--sp-muted);
}
</style>
