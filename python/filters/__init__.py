from typing import Protocol


class KillmailFilter(Protocol):
    def filter(self, killmail: dict) -> bool: ...


class FilterPipeline:
    def __init__(self):
        self._level1: list[KillmailFilter] = []
        self._level2: list[KillmailFilter] = []

    def add_level1(self, f: KillmailFilter) -> "FilterPipeline":
        self._level1.append(f)
        return self

    def add_level2(self, f: KillmailFilter) -> "FilterPipeline":
        self._level2.append(f)
        return self

    def evaluate(self, killmail: dict) -> bool:
        for f in self._level1:
            if not f.filter(killmail):
                return False
        for f in self._level2:
            if not f.filter(killmail):
                return False
        return True
