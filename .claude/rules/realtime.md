# Realtime / multiplayer

- WebSockets with Laravel Reverb; client with laravel-echo. Presence channel per lobby (membership, ready state); private channel per match for game events.
- Players submit words over HTTP POST (Form Request + rate limit + Policy). The server validates, updates state inside a transaction or lock, then broadcasts the result.
- Broadcast game events with `ShouldBroadcastNow` (no queue latency). Keep payloads small: ids, scores, plate, timestamps. Never broadcast emails or other private data.
- Timers: the server stores `starts_at`/`ends_at`. Clients compute a clock offset from server time and only display. Late submissions are rejected server-side.
- Shared state that several players race on (format "first wins", used-word checks) must be resolved atomically: DB unique constraints or `Cache::lock` on Redis.
- Reconnection: a player who reloads rejoins the running match and gets its current state from an HTTP endpoint.
- Cover with tests: feature tests for each endpoint and a Playwright E2E with several browser contexts in one lobby.
