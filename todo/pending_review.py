"""Вывести готовые к приёмке задачи из соседнего TASKS.md."""

import re
from pathlib import Path

tasks = Path(__file__).with_name("TASKS.md").read_text(encoding="utf-8")
sections = re.split(r"^### (№ [0-9]+\.[^\n]+)$", tasks, flags=re.MULTILINE)
pending = []

for title, body in zip(sections[1::2], sections[2::2]):
    done = re.search(r"^- \[[xX]\][ \t]+Сделал[ \t]*$", body, re.MULTILINE)
    awaiting_review = re.search(r"^- \[ \][ \t]+Проверил[ \t]*$", body, re.MULTILINE)

    if done and awaiting_review:
        pending.append(title)

print("\n".join(pending) if pending else "Задач к приёмке нет.")
