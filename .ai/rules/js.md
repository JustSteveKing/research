---
paths:
  - 'resources/js/**'
---

# Js

## Pages are panes inside the persistent WorkspaceShell
createInertiaApp uses `layout: () => WorkspaceShell`, so every page renders inside the app frame (rail, title bar, status bar, palette, composer) and the frame survives navigation. Pages must not render their own chrome or page-level scroll: set the frame's data with `setLayoutProps({ threadId, title, commandContext })` and give each scrollable region the `.pane` class. Rail and status bar read the `rail` and `activity` props shared from HandleInertiaRequests (built by WorkspaceState), refreshed by the shell's own 5s `usePoll(['rail','activity'])` — pages should not duplicate that poll. The React compiler lint bans synchronous setState inside useEffect: derive state during render instead (see threads/show.tsx run selection).
