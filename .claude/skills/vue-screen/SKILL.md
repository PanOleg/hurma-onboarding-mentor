---
name: vue-screen
description: Add one Vue 3 + Vuetify screen with a Pinia store and API client calls matching spec section 8.
---
1. Read the endpoint rows in spec section 8 for this screen. Copy the exact paths and payloads.
2. Store in resources/js/stores/<name>.js (Pinia setup store). Write the vitest first in resources/js/stores/<name>.test.js, mocking resources/js/api.js with vi.mock.
3. View in resources/js/views/<Name>View.vue using Vuetify components only (no custom CSS frameworks). Register the route in resources/js/router.js.
4. UI copy in Ukrainian. Errors show the `error.message` from the API envelope.
5. `npm run test`, `npm run build`, commit `feat(web): <screen> (AC-n)`.
