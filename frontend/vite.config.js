import { defineConfig, loadEnv } from "vite";
import vue from "@vitejs/plugin-vue";

export default defineConfig(({ command, mode }) => {
  const env = loadEnv(mode, process.cwd(), "");

  return {
    plugins: [vue()],
    base: command === "serve" ? "/" : (env.VITE_APP_BASE || "/"),
    server: {
      host: "127.0.0.1",
      port: 5173,
      strictPort: true,
      proxy: {
        "/api": {
          target: "http://localhost/login-demo",
          changeOrigin: true
        },
        "/uploads": {
          target: "http://localhost/login-demo",
          changeOrigin: true
        }
      }
    },
    build: {
      outDir: "dist",
      sourcemap: false
    }
  };
});
