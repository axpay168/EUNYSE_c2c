<template>
  <div class="loading-inline-spinner" role="status" aria-live="polite" aria-busy="true">
    <div class="loading-inline-spinner__ring" />
  </div>
</template>

<script>
export default {
  name: 'LoadingInlineSpinner'
}
</script>

<style scoped>
.loading-inline-spinner {
  display: flex;
  min-height: 72px;
  align-items: center;
  justify-content: center;
  padding: 16px 0;
}

.loading-inline-spinner__ring {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background:
    conic-gradient(
      from 20deg,
      rgba(35, 136, 255, 0) 0deg,
      rgba(35, 136, 255, 0.14) 85deg,
      #2388ff 160deg,
      #0f63dd 215deg,
      rgba(35, 136, 255, 0) 310deg
    ),
    rgba(255, 255, 255, 0.36);
  animation: loading-inline-spin 1.1s linear infinite;
  position: relative;
}

.loading-inline-spinner__ring::before {
  content: '';
  position: absolute;
  inset: 5px;
  border-radius: 50%;
  background: var(--color-surface, #fff);
}

@keyframes loading-inline-spin {
  to { transform: rotate(360deg); }
}
</style>
