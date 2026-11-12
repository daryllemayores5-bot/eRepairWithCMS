import {fileURLToPath,URL} from 'node:url'
import {defineConfig} from 'vitest/config'
import vue from '@vitejs/plugin-vue'
export default defineConfig({plugins:[vue()],resolve:{alias:{'@':fileURLToPath(new URL('./src',import.meta.url)),vue:'vue/dist/vue.esm-bundler.js'}},test:{include:['src/**/*.test.ts'],environment:'jsdom',setupFiles:['./src/test/setup.ts'],clearMocks:true,maxWorkers:2}})

