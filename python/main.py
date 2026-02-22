import logging
import threading

from fastapi import FastAPI, Depends, Query, HTTPException
from sqlalchemy import text
from sqlalchemy.orm import Session

from config import settings
from database import get_db, SessionLocal
from filters import FilterPipeline
from filters.level1 import NpcFilter, SecurityFilter
from filters.level2 import MinValueFilter
from models import KillmailListResponse, KillmailDetail, StatsResponse
from poller import ZKillboardR2Z2
from repository import KillmailRepository

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s [%(name)s] %(message)s",
)
logger = logging.getLogger(__name__)

app = FastAPI(title="zKillboard R2Z2 Client", version="1.0.0")

shutdown_event = threading.Event()


# ── Poller ──────────────────────────────────────────────────────────


def _run_poller():
    pipeline = FilterPipeline()
    if settings.poller_exclude_npc:
        pipeline.add_level1(NpcFilter(exclude=True))
    if settings.poller_security_zones:
        pipeline.add_level1(SecurityFilter(allow=settings.poller_security_zones))
    if settings.poller_min_value:
        pipeline.add_level2(MinValueFilter(settings.poller_min_value))

    zkill = ZKillboardR2Z2(
        state_file=settings.poller_state_file,
        filters=pipeline,
        shutdown_event=shutdown_event,
    )

    def on_killmail(killmail: dict, sequence_id: int):
        db = SessionLocal()
        try:
            repo = KillmailRepository(db)
            saved = repo.save(killmail)
            kill_id = killmail["killmail_id"]
            value = killmail.get("zkb", {}).get("totalValue", 0)
            status = "saved" if saved else "skipped (duplicate)"
            logger.info("[#%d] Kill %d | %s ISK | %s", sequence_id, kill_id, f"{value:,.0f}", status)
        except Exception:
            logger.exception("Error saving killmail at sequence %d", sequence_id)
        finally:
            db.close()

    zkill.poll(on_killmail)


@app.on_event("startup")
def startup():
    if settings.poller_enabled:
        thread = threading.Thread(target=_run_poller, daemon=True)
        thread.start()
        logger.info("Poller thread started")


@app.on_event("shutdown")
def shutdown():
    logger.info("Shutting down poller...")
    shutdown_event.set()


# ── Routes ──────────────────────────────────────────────────────────


@app.get("/health")
def health(db: Session = Depends(get_db)):
    try:
        db.execute(text("SELECT 1"))
        return {"status": "ok"}
    except Exception:
        raise HTTPException(status_code=503, detail="Database unavailable")


@app.get("/kills", response_model=KillmailListResponse)
def list_kills(
    limit: int = Query(50, ge=1, le=1000),
    offset: int = Query(0, ge=0),
    min_value: float | None = Query(None),
    max_value: float | None = Query(None),
    solar_system_id: int | None = Query(None, ge=1),
    ship_type_id: int | None = Query(None, ge=1),
    character_id: int | None = Query(None, ge=1),
    corporation_id: int | None = Query(None, ge=1),
    alliance_id: int | None = Query(None, ge=1),
    npc: bool | None = Query(None),
    solo: bool | None = Query(None),
    awox: bool | None = Query(None),
    db: Session = Depends(get_db),
):
    repo = KillmailRepository(db)
    kills, total = repo.list_kills(
        limit=limit,
        offset=offset,
        min_value=min_value,
        max_value=max_value,
        solar_system_id=solar_system_id,
        ship_type_id=ship_type_id,
        character_id=character_id,
        corporation_id=corporation_id,
        alliance_id=alliance_id,
        npc=npc,
        solo=solo,
        awox=awox,
    )
    return KillmailListResponse(total=total, kills=kills)


@app.get("/kills/{killmail_id}", response_model=KillmailDetail)
def get_kill(killmail_id: int, db: Session = Depends(get_db)):
    repo = KillmailRepository(db)
    kill = repo.get_kill(killmail_id)
    if kill is None:
        raise HTTPException(status_code=404, detail="Killmail not found")
    return kill


@app.get("/stats", response_model=StatsResponse)
def get_stats(db: Session = Depends(get_db)):
    repo = KillmailRepository(db)
    return repo.get_stats()
