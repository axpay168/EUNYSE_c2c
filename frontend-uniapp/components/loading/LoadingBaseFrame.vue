<template>
  <div class="loading-base-frame" role="status" aria-live="polite" aria-busy="true">
    <main class="loading-base-frame__page">
      <section class="loading-base-frame__panel">
        <slot />
      </section>
    </main>
  </div>
</template>

<script>
export default {
  name: 'LoadingBaseFrame'
}
</script>

<style scoped>
.loading-base-frame {
  position: fixed;
  inset: 0;
  z-index: 2147482990;
  pointer-events: none;
}

.loading-base-frame__page {
  min-height: 100vh;
  min-height: 100dvh;
  display: grid;
  place-items: center;
  padding: 20px;
}

.loading-base-frame__panel {
  display: grid;
  place-items: center;
}
</style>

