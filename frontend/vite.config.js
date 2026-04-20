import { fileURLToPath } from 'node:url'
import vue from '@vitejs/plugin-vue'
import vueJsx from '@vitejs/plugin-vue-jsx'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { VueRouterAutoImports, getPascalCaseRouteName } from 'unplugin-vue-router'
import VueRouter from 'unplugin-vue-router/vite'
import { defineConfig, loadEnv } from 'vite'
import MetaLayouts from 'vite-plugin-vue-meta-layouts'
import vuetify from 'vite-plugin-vuetify'
import svgLoader from 'vite-svg-loader'

// https://vitejs.dev/config/
// https://vitejs.dev/config/
export default defineConfig(({ mode }) => {
  // Load env file based on `mode` in the current working directory.
  // Set the third parameter to '' to load all env regardless of the `VITE_` prefix.
  const env = loadEnv(mode, process.cwd(), '')

  return {
    server: {
      host: '0.0.0.0',
      port: 5173,
      hmr: {
        host: env.VITE_HMR_HOST || '127.0.0.1',
        protocol: 'ws',
        port: 5173,
        clientPort: 5173,
      },
      proxy: {
        '/api': {
          target: env.VITE_PROXY_TARGET || 'http://app:8000',
          changeOrigin: true,
          secure: false,
          timeout: 300000,
          proxyTimeout: 300000,
        },
        '/broadcasting': {
          target: env.VITE_PROXY_TARGET || 'http://app:8000',
          changeOrigin: true,
          secure: false,
        },
      },
    },
    plugins: [
      // Docs: https://github.com/posva/unplugin-vue-router
      // ℹ️ This plugin should be placed before vue plugin
      VueRouter({
        getRouteName: routeNode => {
          // Convert pascal case to kebab case
          return getPascalCaseRouteName(routeNode)
            .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
            .toLowerCase()
        },
      }),
      vue({
        template: {
          compilerOptions: {
            isCustomElement: tag => tag === 'swiper-container' || tag === 'swiper-slide',
          },
        },
      }),
      vueJsx(),

      // Docs: https://github.com/vuetifyjs/vuetify-loader/tree/master/packages/vite-plugin
      vuetify({
        // styles: {
        //   configFile: 'src/assets/styles/variables/_vuetify.scss',
        // },
      }),

      // Docs: https://github.com/dishait/vite-plugin-vue-meta-layouts?tab=readme-ov-file
      MetaLayouts({
        target: './src/layouts',
        defaultLayout: 'default',
      }),

      // Docs: https://github.com/antfu/unplugin-vue-components#unplugin-vue-components
      Components({
        dirs: ['src/@core/components', 'src/views/demos', 'src/components'],
        dts: true,
        resolvers: [
          componentName => {
            // Auto import `VueApexCharts`
            if (componentName === 'VueApexCharts')
              return { name: 'default', from: 'vue3-apexcharts', as: 'VueApexCharts' }
          },
        ],
      }),

      // Docs: https://github.com/antfu/unplugin-auto-import#unplugin-auto-import
      AutoImport({
        imports: ['vue', VueRouterAutoImports, '@vueuse/core', '@vueuse/math', 'vue-i18n', 'pinia'],
        dirs: [
          './src/@core/utils',
          './src/@core/composable/',
          './src/composables/',
          './src/utils/',
          './src/plugins/*/composables/*',
        ],
        vueTemplate: true,

        // ℹ️ Disabled to avoid confusion & accidental usage
        ignore: ['useCookies', 'useStorage'],
        eslintrc: {
          enabled: true,
          filepath: './.eslintrc-auto-import.json',
        },
      }),
      svgLoader(),
    ],
    define: { 'process.env': {} },
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
        '@themeConfig': fileURLToPath(new URL('./themeConfig.js', import.meta.url)),
        '@core': fileURLToPath(new URL('./src/@core', import.meta.url)),
        '@layouts': fileURLToPath(new URL('./src/@layouts', import.meta.url)),
        '@images': fileURLToPath(new URL('./src/assets/images/', import.meta.url)),
        '@styles': fileURLToPath(new URL('./src/assets/styles/', import.meta.url)),
        '@configured-variables': fileURLToPath(new URL('./src/assets/styles/variables/_template.scss', import.meta.url)),
        '@db': fileURLToPath(new URL('./src/plugins/fake-api/handlers/', import.meta.url)),
        '@api-utils': fileURLToPath(new URL('./src/plugins/fake-api/utils/', import.meta.url)),
      },
    },
    build: {
      chunkSizeWarningLimit: 5000,
    },
    optimizeDeps: {
      exclude: ['vuetify'],
      entries: [
        './src/**/*.vue',
      ],
    },
    test: {
      globals: true,
      environment: 'jsdom',
      server: {
        deps: {
          inline: ['vuetify'],
        },
      },
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
        '@themeConfig': fileURLToPath(new URL('./themeConfig.js', import.meta.url)),
        '@core': fileURLToPath(new URL('./src/@core', import.meta.url)),
        '@layouts': fileURLToPath(new URL('./src/@layouts', import.meta.url)),
        '@images': fileURLToPath(new URL('./src/assets/images/', import.meta.url)),
        '@styles': fileURLToPath(new URL('./src/assets/styles/', import.meta.url)),
        '@configured-variables': fileURLToPath(new URL('./src/assets/styles/variables/_template.scss', import.meta.url)),
        '@db': fileURLToPath(new URL('./src/plugins/fake-api/handlers/', import.meta.url)),
        '@api-utils': fileURLToPath(new URL('./src/plugins/fake-api/utils/', import.meta.url)),
      },
      setupFiles: ['./vitest.setup.js']
    },
  }
})
