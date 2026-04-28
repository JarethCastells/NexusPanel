import { createRouter, createWebHistory } from "vue-router";

const HomeView = {
  template: "<main style='min-height:100vh;display:grid;place-items:center;font-family:Arial,sans-serif;'>Funciona</main>"
};

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: "/",
      name: "home",
      component: HomeView
    }
  ]
});

export default router;
