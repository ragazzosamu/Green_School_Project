import time
from dataclasses import dataclass, field

@dataclass
class Sessione:
    id_punto: str    
    badge_usato: str
    ora_inizio: float    = field(default_factory=time.time)
    ora_fine: float|None = None    
    quantità_kwh : float