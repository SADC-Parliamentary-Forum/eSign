<template>
  <div class="h-100 d-flex align-center justify-space-between text-medium-emphasis w-100">
    <!-- Left: Copyright -->
    <div class="d-flex align-center text-caption">
      &copy; {{ new Date().getFullYear() }}
      <a 
        href="https://www.sadcpf.org" 
        target="_blank" 
        rel="noopener noreferrer" 
        class="text-primary font-weight-bold ms-1 text-decoration-none"
      >
        SADC Parliamentary Forum
      </a>
      <span class="ms-1 hidden-sm-and-down">- All Rights Reserved</span>
    </div>

    <!-- Right: Version/Clean -->
    <div class="text-caption text-disabled d-none d-md-block d-flex align-center">
      <VChip
        color="primary"
        size="x-small"
        class="me-2 text-uppercase font-weight-bold"
        label
      >
        Beta
      </VChip>
      Secure eSign Portal
    </div>
  </div>
</template>
