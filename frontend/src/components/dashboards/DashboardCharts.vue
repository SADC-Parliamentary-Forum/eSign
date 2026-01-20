<script setup>
/**
 * DashboardCharts - Mini charts for dashboard analytics
 * Lightweight sparklines and progress indicators
 */

const props = defineProps({
  data: {
    type: Array,
    default: () => [],
  },
  type: {
    type: String,
    default: 'sparkline', // 'sparkline', 'bar', 'donut'
  },
  color: {
    type: String,
    default: 'primary',
  },
  height: {
    type: [Number, String],
    default: 50,
  },
  showLabels: {
    type: Boolean,
    default: false,
  },
  label: {
    type: String,
    default: '',
  },
})

// Convert data to percentages for visualization
const normalizedData = computed(() => {
  if (!props.data.length) return []
  const max = Math.max(...props.data, 1)
  return props.data.map(val => (val / max) * 100)
})

const total = computed(() => props.data.reduce((sum, val) => sum + val, 0))
const average = computed(() => props.data.length ? Math.round(total.value / props.data.length) : 0)
const trend = computed(() => {
  if (props.data.length < 2) return 0
  const recent = props.data.slice(-3).reduce((a, b) => a + b, 0) / 3
  const earlier = props.data.slice(-6, -3).reduce((a, b) => a + b, 0) / 3 || recent
  return Math.round(((recent - earlier) / (earlier || 1)) * 100)
})

const trendColor = computed(() => trend.value >= 0 ? 'success' : 'error')
const trendIcon = computed(() => trend.value >= 0 ? 'mdi-trending-up' : 'mdi-trending-down')
</script>

<template>
  <div class="dashboard-chart">
    <!-- Sparkline Type -->
    <template v-if="type === 'sparkline'">
      <div class="sparkline-container" :style="{ height: `${height}px` }">
        <div 
          v-for="(value, index) in normalizedData" 
          :key="index"
          class="sparkline-bar"
          :style="{
            height: `${value}%`,
            backgroundColor: `rgb(var(--v-theme-${color}))`,
            opacity: 0.3 + (index / normalizedData.length) * 0.7,
          }"
        />
      </div>
      <div v-if="showLabels" class="chart-labels mt-2 d-flex justify-space-between">
        <div class="text-caption text-medium-emphasis">
          {{ label }}
        </div>
        <div class="d-flex align-center">
          <VIcon :icon="trendIcon" :color="trendColor" size="16" class="mr-1" />
          <span :class="`text-${trendColor}`" class="text-caption font-weight-medium">
            {{ trend }}%
          </span>
        </div>
      </div>
    </template>

    <!-- Bar Chart Type -->
    <template v-else-if="type === 'bar'">
      <div class="bar-chart-container" :style="{ height: `${height}px` }">
        <div 
          v-for="(value, index) in normalizedData" 
          :key="index"
          class="bar-chart-bar"
          :style="{
            height: `${value}%`,
            backgroundColor: `rgb(var(--v-theme-${color}))`,
          }"
        >
          <VTooltip activator="parent" location="top">
            {{ data[index] }}
          </VTooltip>
        </div>
      </div>
    </template>

    <!-- Donut Type -->
    <template v-else-if="type === 'donut'">
      <div class="donut-container">
        <VProgressCircular
          :model-value="normalizedData[0] || 0"
          :size="height"
          :width="8"
          :color="color"
        >
          <span class="text-h6 font-weight-bold">{{ data[0] || 0 }}</span>
        </VProgressCircular>
      </div>
    </template>
  </div>
</template>

<style scoped>
.dashboard-chart {
  width: 100%;
}

.sparkline-container {
  display: flex;
  align-items: flex-end;
  gap: 2px;
}

.sparkline-bar {
  flex: 1;
  min-width: 4px;
  border-radius: 2px 2px 0 0;
  transition: height 0.3s ease;
}

.bar-chart-container {
  display: flex;
  align-items: flex-end;
  gap: 4px;
}

.bar-chart-bar {
  flex: 1;
  min-width: 8px;
  border-radius: 4px 4px 0 0;
  transition: height 0.3s ease;
  cursor: pointer;
}

.bar-chart-bar:hover {
  opacity: 0.8;
}

.donut-container {
  display: flex;
  justify-content: center;
}
</style>
