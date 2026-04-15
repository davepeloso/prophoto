Yes, the new documentation explicitly confirms the authentication format! 

The Astria API uses a **Bearer Authorization header scheme**. You must pass your API key (which typically starts with `sd_`) in the headers using the exact following format:

`Authorization: Bearer {api_key}`

Here is how it looks in various environments based on the API code examples provided:

*   **cURL:** `-H "Authorization: Bearer $API_KEY"`
*   **JavaScript/Node.js:** `headers: { 'Authorization': 'Bearer ' + API_KEY }`
*   **Python:** `headers = {'Authorization': f'Bearer {API_KEY}'}`

So, your initial assumption was spot on—you will need to use `Authorization: Bearer` followed by your generated key.

Here are the specific technical details about the Astria API responses, webhooks, statuses, and limits based on the documentation:

### Tune Response Shape
When you send a `POST /tunes` request, the API returns a JSON object where the unique identifier is stored under the key **`id`** (not `tune_id`). 
The immediate JSON response **does not include a `status` field**. Instead, it returns tracking timestamps such as `eta` (estimated time of arrival), `started_training_at`, and `trained_at` (which will be `null` initially). 

### Prompt Response Shape
When you send a `POST /prompts` request, the JSON returned also uses the key **`id`** for the prompt/generation ID. 
When the generation successfully completes, the image URLs are returned as a simple **array of URL strings** (e.g., `"images": [ "https://...", "https://..." ]`) rather than complex objects with metadata. *(Note: The Laravel tutorial example references storing `$payload['output']['images']`, but the official Pack/Prompt objects simply list them as an array of strings under the `images` key).*

### Webhook Payload Shape
When Astria calls your callback URL, the POST body contains the **full entity object** (the complete tune or prompt JSON), not just an ID and status. 
**There is no documented signature header for verification.** Because a signature is not provided natively by the API, the documentation recommends appending context arguments (like an internal `user_id` or a UUID) directly to your callback query string (e.g., `?user_id=1&transaction_id=123`) to securely identify and route the webhook.

### Tune & Prompt Status Values
The official API documentation does not detail an exhaustive list of intermediate string statuses (like `training` or `processing`) for the `GET /tunes/{id}` or `GET /prompts/{id}` endpoints. Progress is primarily tracked via the presence of the `started_training_at` and `trained_at` timestamps. 
However, when the asynchronous webhook fires, the payload will include a status field that evaluates to **`completed`** or **`failed`**. 

### The `num_images` Parameter
Yes, `POST /prompts` accepts a `num_images` parameter that allows you to override the default. You can request between **1 and 8 images** per prompt (8 is the maximum).

### Rate Limits
There are **no specific requests-per-minute or requests-per-second rate limits documented**. The documentation only mentions `429 - Rate limiting` errors in the context of polling the API too frequently. To avoid hitting this limit, they heavily recommend relying entirely on webhooks/callbacks rather than polling the `GET` endpoints.