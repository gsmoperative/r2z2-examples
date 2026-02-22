import logging
import time
from pathlib import Path

import httpx

from filters import FilterPipeline

logger = logging.getLogger(__name__)


class ZKillboardR2Z2:
    BASE_URL = "https://r2z2.zkillboard.com/ephemeral"
    SLEEP_ON_SUCCESS = 0.1
    SLEEP_ON_404 = 6
    SLEEP_ON_429 = 2

    def __init__(
        self,
        state_file: str | None = None,
        filters: FilterPipeline | None = None,
        client: httpx.Client | None = None,
    ):
        self.state_file = Path(state_file) if state_file else None
        self.filters = filters
        self.client = client or httpx.Client(
            base_url=self.BASE_URL,
            timeout=10.0,
            headers={
                "Accept": "application/json",
                "User-Agent": "R2Z2-Examples-Python/1.0",
            },
        )
        self.last_sequence_id = 0
        if self.state_file and self.state_file.exists():
            self.last_sequence_id = int(self.state_file.read_text().strip())

    def get_current_sequence(self) -> int:
        data = self._request("/sequence.json")
        return data["sequence_id"]

    def get_killmail(self, sequence_id: int) -> dict | None:
        return self._request(f"/{sequence_id}.json", allow_not_found=True)

    def poll(self, callback, start_from: int | None = None) -> None:
        sequence_id = start_from or self.last_sequence_id or self.get_current_sequence()
        logger.info("Poller starting at sequence %d", sequence_id)

        while True:
            killmail = self.get_killmail(sequence_id)

            if killmail is None:
                time.sleep(self.SLEEP_ON_404)
                continue

            if self.filters is None or self.filters.evaluate(killmail):
                callback(killmail, sequence_id)

            self.last_sequence_id = sequence_id
            self._save_state()
            sequence_id += 1
            time.sleep(self.SLEEP_ON_SUCCESS)

    def _request(self, path: str, allow_not_found: bool = False) -> dict | None:
        try:
            response = self.client.get(path)
            response.raise_for_status()
            return response.json()
        except httpx.HTTPStatusError as e:
            if e.response.status_code == 404 and allow_not_found:
                return None
            if e.response.status_code == 429:
                time.sleep(self.SLEEP_ON_429)
                return self._request(path, allow_not_found)
            raise

    def _save_state(self) -> None:
        if self.state_file:
            self.state_file.write_text(str(self.last_sequence_id))
