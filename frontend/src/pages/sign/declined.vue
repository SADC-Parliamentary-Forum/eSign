<template>
  <VCard
    class="mx-auto my-8 text-center"
    max-width="500"
  >
    <VCardText class="py-10">
      <VIcon
        icon="ri-close-circle-fill"
        size="80"
        color="error"
        class="mb-4"
      />
      <h2 class="text-h5 font-weight-bold mb-2">
        Signing Declined
      </h2>
      <p class="text-body-1 text-disabled mb-6">
        You have declined to sign this document. The document owner has been notified.
      </p>
      <VBtn
        to="/"
        variant="outlined"
      >
        Go to Dashboard
      </VBtn>
    </VCardText>
  </VCard>
</template>
