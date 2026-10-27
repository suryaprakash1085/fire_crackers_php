# AI image generation (OpenAI Images API)
1. Create an API key at platform.openai.com (billing must be enabled; images are billed per generation).
2. In `.env`: `OPENAI_API_KEY=sk-...` and optionally `AI_IMAGE_MODEL=gpt-image-1` (or `dall-e-3`; sizes/quality options adapt automatically).
3. Open Admin → AI Image Generator. Without a key the page shows a configuration notice and generation is disabled — no fake images are ever produced.
Flow: prompt/style/aspect/quality → Generate → private draft preview (served only to logged-in admins) → **Save to Media Library** (validated + re-encoded) → select it in Banners/Products/Categories. A generated image is never live until you choose it there and activate that banner/product. Every attempt (success or failure) is stored in `ai_image_history`. Rate limit: 20 generations/hour/admin. The key stays server-side only.
