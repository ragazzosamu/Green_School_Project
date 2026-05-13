import time
from dataclasses import dataclass, field


@dataclass
class Sessione:
    id_punto: str
    id_sessione: str | None = None            # id assegnato da Laravel quando viene creata
    quantita_kwh: float = 0.0
    ora_inizio: float = field(default_factory=time.time)
    ora_fine: float | None = None
