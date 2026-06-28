<script setup>
import { RouterView } from 'vue-router'
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <header class="border-b border-gray-200 bg-white px-4 py-3 shadow-sm">
      <div class="mx-auto flex max-w-7xl items-center gap-3">
        <svg class="h-7 w-7 text-blue-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v14a2 2 0 002 2h16a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1z"/>
        </svg>
        <h1 class="text-lg font-semibold text-gray-900">Order Hub</h1>
      </div>
    </header>
    <main class="mx-auto max-w-7xl px-4 py-6">
      <RouterView />
    </main>
  </div>
</template>
