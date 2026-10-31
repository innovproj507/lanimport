-- La huella de las pantallas en vivo (App\Libraries\HuellaCambios) pide MAX(updated_at) y
-- MAX(escaneado_at) cada pocos segundos: con indice es una lectura, sin el recorre la tabla.
ALTER TABLE lpns ADD INDEX idx_lpns_updated_at (updated_at);
ALTER TABLE cargas ADD INDEX idx_cargas_updated_at (updated_at);
ALTER TABLE salida_lineas ADD INDEX idx_salida_lineas_escaneado_at (escaneado_at);
