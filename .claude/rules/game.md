# Game rules

## Plate
- 3 letters from the Spanish plate alphabet: `B C D F G H J K L M N P R S T V W X Y Z` (no vowels, no Ñ, no Q).
- "Simple letters" set excludes `K V W X Y Z` (hard to use in Spanish words).
- Plates are generated server-side. Once the local dictionary exists, only plates with at least N valid solutions for the active settings may be generated.

## Settings (chosen when a game or lobby starts)
These replace the old Easy/Medium/Hard difficulty and can be combined:
- Letter set: simple | full.
- Ordered letters: off (letters in any order) | on (letters must appear in plate order as a subsequence).
- Mode: countdown (30 s, 1, 2, 3, 4, 5 min) | infinite (solo only).
- Multiplayer format (lobbies only), see below.

## Word acceptance
- Minimum 3 characters.
- Must contain every plate letter, with repeated letters counted (plate `SSL` needs two S).
- Must exist in the dictionary after normalization (see `dictionary.md`).
- A word already accepted in the same run does not score again.

## Scoring (current values, keep unless the user changes them)
- Valid word: +10. Invalid word: −5. Skip plate ("Pasamatrícula", also Control, or Command on Mac, pressed alone): −5.

## Multiplayer formats (all three are lobby options)
Letter set and ordered letters work as in solo. Scoring is the solo one (+10 / −5) in every format.

1. **Parallel race**: every player gets the same seeded plate sequence and advances independently. Highest score at the end wins. Length: any countdown, custom included (1–60 min). "Pasamatrícula" allowed (−5).
2. **Shared plate rounds**: everyone sees the same plate each round. A word already used by another player in that round does not score (0, not a penalty). Host sets rounds (1–30) and seconds per round (up to 5 min). No "Pasamatrícula".
3. **First wins**: everyone sees the same plate. The first valid answer takes the round and the plate changes for everybody; wrong answers cost −5. Host sets the number of plates (1–50) and a per-plate limit (default 30 s, 10 s–2 min). The plate also passes, with no penalty, once every player has failed it or the limit runs out. No "Pasamatrícula". Resolve ties atomically on the server (lock or unique constraint), never on the client.

## Ties
- **Near-simultaneous answers ("first wins")**: the server timestamps each accepted word on arrival. The first valid word opens a tie window (lobby option: 0 / 150 / 300 ms, default 150). Every valid word received inside the window also scores; the round closes when the window ends.
- **Equal final scores** (lobby option `tie_resolution`):
  - `shared`: tied players share the position.
  - `tiebreak`: only the tied players play one extra plate ("versus"); first valid word wins, the rest watch. Applies only to podium positions, so the other players are not kept waiting mid-game.

## Lobbies
- 2–16 players, registered users only. Host configures the settings, players mark "ready", the server sets `starts_at`/`ends_at`.
- Join by short code only (no public list for now); optional password stored hashed. No joining once the match has started (no spectators).
- If the host leaves, the player who has been in the lobby longest becomes host. The host can kick players; a kicked player cannot rejoin that lobby.
- Each player picks a car colour on the starting grid.
- "Revancha" restarts the lobby with the same players after a match. A lobby with no activity for 30 minutes closes.
- Quick reactions: 👏 😂 😱 🔥 😭 👀 🤯 💀 as bubbles over the player's car. Horn ("¡Pita!") and shout ("¡Grita!", the owner's recordings) buttons: a random sound for everyone, each once every 3 s per player (and not before your own sound ends); anyone can mute other players' horns and shouts in the sound menu.
- End-of-game stats: ranking, longest word, shortest word, fastest answer, most words, accuracy.
- Multiplayer matches do not count for the solo rankings; they get their own stats (wins, podiums). No multiplayer achievements (owner's choice: platinum must stay reachable playing solo).
