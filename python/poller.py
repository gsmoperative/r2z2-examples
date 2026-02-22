import logging
import os
import tempfile
import threading
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
    MAX_RETRIES = 5

    def __init__(
        self,
        state_file: str | None = None,
        filters: FilterPipeline | None = None,
        client: httpx.Client | None = None,
        shutdown_event: threading.Event | None = None,
    ):
        self.state_file = Path(state_file) if state_file else None
        self.filters = filters
        self.shutdown_event = shutdown_event or threading.Event()
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

        while not self.shutdown_event.is_set():
            killmail = self.get_killmail(sequence_id)

            if killmail is None:
                if self.shutdown_event.wait(self.SLEEP_ON_404):
                    break
                continue

            if self.filters is None or self.filters.evaluate(killmail):
                callback(killmail, sequence_id)

            self.last_sequence_id = sequence_id
            self._save_state()
            sequence_id += 1
            if self.shutdown_event.wait(self.SLEEP_ON_SUCCESS):
                break

        logger.info("Poller stopped at sequence %d", self.last_sequence_id)

    def _request(self, path: str, allow_not_found: bool = False) -> dict | None:
        for attempt in range(self.MAX_RETRIES + 1):
            try:
                response = self.client.get(path)
                response.raise_for_status()
                return response.json()
            except httpx.HTTPStatusError as e:
                if e.response.status_code == 404 and allow_not_found:
                    return None
                if e.response.status_code == 429:
                    if attempt < self.MAX_RETRIES:
                        logger.warning("Rate limited (429), retry %d/%d", attempt + 1, self.MAX_RETRIES)
                        time.sleep(self.SLEEP_ON_429)
                        continue
                    raise
                raise

    def _save_state(self) -> None:
        if self.state_file:
            fd, tmp_path = tempfile.mkstemp(dir=self.state_file.parent)
            try:
                with os.fdopen(fd, "w") as f:
                    f.write(str(self.last_sequence_id))
                os.replace(tmp_path, self.state_file)
            except BaseException:
                if os.path.exists(tmp_path):
                    os.unlink(tmp_path)
                raise
