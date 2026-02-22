class NpcFilter:
    def __init__(self, exclude: bool = False):
        self.exclude = exclude

    def filter(self, killmail: dict) -> bool:
        is_npc = bool(killmail.get("zkb", {}).get("npc", False))
        return not is_npc if self.exclude else is_npc


class SoloFilter:
    def __init__(self, exclude: bool = False):
        self.exclude = exclude

    def filter(self, killmail: dict) -> bool:
        is_solo = bool(killmail.get("zkb", {}).get("solo", False))
        return not is_solo if self.exclude else is_solo


class AwoxFilter:
    def __init__(self, exclude: bool = False):
        self.exclude = exclude

    def filter(self, killmail: dict) -> bool:
        is_awox = bool(killmail.get("zkb", {}).get("awox", False))
        return not is_awox if self.exclude else is_awox


class SecurityFilter:
    def __init__(self, allow: list[str]):
        self.allow = [f"loc:{z.removeprefix('loc:')}" for z in allow]

    def filter(self, killmail: dict) -> bool:
        labels = killmail.get("zkb", {}).get("labels", [])
        return any(label in self.allow for label in labels)
