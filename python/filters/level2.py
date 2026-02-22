class MinValueFilter:
    def __init__(self, min_value: float):
        self.min_value = min_value

    def filter(self, killmail: dict) -> bool:
        return killmail.get("zkb", {}).get("totalValue", 0) >= self.min_value


class MaxValueFilter:
    def __init__(self, max_value: float):
        self.max_value = max_value

    def filter(self, killmail: dict) -> bool:
        return killmail.get("zkb", {}).get("totalValue", 0) <= self.max_value


class ShipTypeFilter:
    def __init__(self, type_ids: list[int], mode: str = "include"):
        self.type_ids = set(type_ids)
        self.mode = mode

    def filter(self, killmail: dict) -> bool:
        ship_type_id = killmail.get("esi", {}).get("victim", {}).get("ship_type_id")
        match = ship_type_id in self.type_ids
        return match if self.mode == "include" else not match


class CharacterFilter:
    def __init__(self, character_ids: list[int], mode: str = "include"):
        self.character_ids = set(character_ids)
        self.mode = mode

    def filter(self, killmail: dict) -> bool:
        match = self._matches_any(killmail)
        return match if self.mode == "include" else not match

    def _matches_any(self, killmail: dict) -> bool:
        esi = killmail.get("esi", {})
        if esi.get("victim", {}).get("character_id") in self.character_ids:
            return True
        for attacker in esi.get("attackers", []):
            if attacker.get("character_id") in self.character_ids:
                return True
        return False


class CorporationFilter:
    def __init__(self, corporation_ids: list[int], mode: str = "include"):
        self.corporation_ids = set(corporation_ids)
        self.mode = mode

    def filter(self, killmail: dict) -> bool:
        match = self._matches_any(killmail)
        return match if self.mode == "include" else not match

    def _matches_any(self, killmail: dict) -> bool:
        esi = killmail.get("esi", {})
        if esi.get("victim", {}).get("corporation_id") in self.corporation_ids:
            return True
        for attacker in esi.get("attackers", []):
            if attacker.get("corporation_id") in self.corporation_ids:
                return True
        return False


class AllianceFilter:
    def __init__(self, alliance_ids: list[int], mode: str = "include"):
        self.alliance_ids = set(alliance_ids)
        self.mode = mode

    def filter(self, killmail: dict) -> bool:
        match = self._matches_any(killmail)
        return match if self.mode == "include" else not match

    def _matches_any(self, killmail: dict) -> bool:
        esi = killmail.get("esi", {})
        if esi.get("victim", {}).get("alliance_id") in self.alliance_ids:
            return True
        for attacker in esi.get("attackers", []):
            if attacker.get("alliance_id") in self.alliance_ids:
                return True
        return False


class SolarSystemFilter:
    def __init__(self, system_ids: list[int], mode: str = "include"):
        self.system_ids = set(system_ids)
        self.mode = mode

    def filter(self, killmail: dict) -> bool:
        system_id = killmail.get("esi", {}).get("solar_system_id")
        match = system_id in self.system_ids
        return match if self.mode == "include" else not match


class RegionFilter:
    def __init__(self, region_ids: list[int], mode: str = "include"):
        self.region_labels = {f"reg:{rid}" for rid in region_ids}
        self.mode = mode

    def filter(self, killmail: dict) -> bool:
        labels = set(killmail.get("zkb", {}).get("labels", []))
        match = bool(labels & self.region_labels)
        return match if self.mode == "include" else not match
