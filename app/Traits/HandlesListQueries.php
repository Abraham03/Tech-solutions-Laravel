<?php

namespace App\Traits;

use Illuminate\Http\Request;

/**
 * Lectura y saneado de los parametros comunes de un listado paginado.
 *
 * Vive en un solo sitio para que los cinco modulos se comporten igual y para
 * que el limite de tamano no dependa de recordarlo en cada controlador.
 */
trait HandlesListQueries
{
    /** Tamanos permitidos. Es una lista blanca, no un maximo. */
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100];

    private const DEFAULT_PER_PAGE = 15;

    /** Un termino mas largo que esto no aporta y solo castiga a la base. */
    private const MAX_SEARCH_LENGTH = 100;

    /**
     * Cuantos registros por pagina.
     *
     * Se valida contra una lista blanca y no con un simple tope: asi
     * `?per_page=10000` cae al valor por defecto en vez de intentar traer diez
     * mil filas, y no hay forma de tumbar la API desde la barra de direcciones.
     */
    protected function perPage(Request $request): int
    {
        $solicitado = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);

        return in_array($solicitado, self::PER_PAGE_OPTIONS, true)
            ? $solicitado
            : self::DEFAULT_PER_PAGE;
    }

    /**
     * Termino de busqueda ya recortado, o null si viene vacio.
     */
    protected function searchTerm(Request $request): ?string
    {
        $termino = trim((string) $request->query('search', ''));

        if ($termino === '') {
            return null;
        }

        return mb_substr($termino, 0, self::MAX_SEARCH_LENGTH);
    }
}
