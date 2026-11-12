import {fileURLToPath,URL} from 'node:url'
import {defineConfig} from 'vitest/config'
import vue from '@vitejs/plugin-vue'
const origin = new URL(process.env.EREPAIR_LIVE_ORIGIN || 'http://127.0.0.1:5173/')
if (origin.protocol !== 'http:' || !['127.0.0.1', 'localhost'].includes(origin.hostname) || origin.username || origin.password || origin.pathname !== '/' || origin.search || origin.hash) throw new Error('Live tests require a root HTTP loopback origin.')
export default defineConfig({plugins:[vue()],resolve:{alias:{'@':fileURLToPath(new URL('./src',import.meta.url)),vue:'vue/dist/vue.esm-bundler.js'}},test:{include:['live-tests/**/*.test.ts'],environment:'jsdom',environmentOptions:{jsdom:{url:origin.href}},setupFiles:['./src/test/setup.ts'],testTimeout:300000,fileParallelism:false}})
