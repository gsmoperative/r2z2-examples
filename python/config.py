from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    db_host: str = "127.0.0.1"
    db_port: int = 3306
    db_name: str = "zkillboard"
    db_user: str = "root"
    db_pass: str = ""

    poller_enabled: bool = True
    poller_state_file: str = "zkill_sequence.txt"
    poller_exclude_npc: bool = True
    poller_security_zones: list[str] = ["nullsec", "lowsec"]
    poller_min_value: float = 10_000_000

    @property
    def database_url(self) -> str:
        return (
            f"mysql+pymysql://{self.db_user}:{self.db_pass}"
            f"@{self.db_host}:{self.db_port}/{self.db_name}"
            "?charset=utf8mb4"
        )


settings = Settings()
