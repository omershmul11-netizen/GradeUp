# GradeUp shared Git workflow

This repository is shared between the user, Codex, and Claude. The user expects to move between assistants without losing changes.

## Required workflow

1. Work on the `resume-ready` branch unless the user explicitly requests another branch.
2. Before editing, verify the working tree is clean and run `git pull --rebase origin resume-ready`.
3. If local changes already exist, preserve them. Do not discard, overwrite, reset, or stash them without the user's approval.
4. Make only changes relevant to the current request and run proportionate checks before committing.
5. At the end of every completed change, stage the relevant files, create a clear commit, pull with rebase once more, and push immediately to `origin/resume-ready`.
6. Confirm to the user that the push succeeded and include the short commit hash.
7. Never force-push. If a push or rebase conflicts, stop, preserve both sides, and resolve carefully or ask the user when intent is unclear.
8. Never commit real passwords, API keys, tokens, local environment files, database exports, or runtime-generated private data.

Avoid simultaneous work by multiple assistants on the same files. Git synchronization happens at task boundaries, after a coherent change has been verified—not after every individual keystroke.
