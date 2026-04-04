# Lessons

- For non-trivial spec work in this repo, create or update `tasks/todo.md` before implementation and keep task progress and review notes current.
- When the user asks to continue spec execution, keep moving the active task forward and avoid meta replies about future actions unless that work is happening in the same turn.
- When the user explicitly asks for spec mode on broad architectural work, create the spec artifacts first and stop at the implementation gate for plan verification before editing application code.
- When the user says the project is not live and compatibility does not matter, prefer direct normalization over compatibility layers or alias-heavy migrations.
- When the user asks for a dedicated read model in this repo, do not stop at extracting a read service boundary; finish the persisted or projection-backed read path if live-query debt still remains.