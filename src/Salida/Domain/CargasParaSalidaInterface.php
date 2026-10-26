<?php

declare(strict_types=1);

namespace Courier\Salida\Domain;

/**
 * Lectura para "Armar salida": todas las cargas que estan en bodega con el estado de sus
 * bultos, para que trafico vea tambien las que aun no se pueden despachar (y por que).
 */
interface CargasParaSalidaInterface
{
    /**
     * Solo cargan "listos" los bultos (LPNs) escaneados y libres: los demas se informan.
     * peso, valor y m3 son de los bultos listos (prorrateados como el manifiesto); pesoTotal y valorTotal, de toda la carga.
     *
     * @return array<int, array{
     *     cargaId: string, tracking: string, clienteId: string, cliente: string, marca: ?string,
     *     proveedor: string, fechaIngreso: string, dias: int, bultos: int, etiquetas: int,
     *     listos: int, porEscanear: int, enOtraSalida: int, enReempaque: int, enCaja: int, peso: float, valor: float, m3: float,
     *     pesoTotal: float, valorTotal: float
     * }>
     */
    public function listar(): array;

    /**
     * Cajas/paletas reempacadas listas para salir (LPN sin carga propia), con lo que llevan.
     *
     * @return array<int, array{lpnId: string, codigo: string, clienteId: string, cliente: string, marca: ?string,
     *     dias: int, bultos: int, cargas: array<int, string>, peso: float, valor: float, m3: float}>
     */
    public function cajasListas(): array;

    /**
     * LPNs escaneados y libres de esas cargas: los que entran en la salida.
     *
     * @param array<int, string> $cargaIds
     * @return array<int, string> ids de LPN
     */
    public function lpnsListos(array $cargaIds): array;

    /**
     * LPNs escaneados y libres de cada carga, en orden de consecutivo (1/115, 2/115...), para
     * que trafico suba solo una parte de la carga en un contenedor (los primeros N).
     *
     * @param array<int, string> $cargaIds
     * @return array<string, array<int, string>> cargaId => ids de LPN
     */
    public function lpnsListosPorCarga(array $cargaIds): array;

    /**
     * Bultos listos (escaneados y libres) de una carga, para que trafico elija cuales suben.
     *
     * @return array<int, array{id: string, codigo: string, numero: ?int, total: int, ubicacion: ?string}>
     *         numero/total: el "12/30" de la etiqueta
     */
    public function lpnsListosDetalle(string $cargaId): array;
}
