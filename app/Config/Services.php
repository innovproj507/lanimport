<?php

namespace Config;

use App\Libraries\ApiAuthContext;
use CodeIgniter\Config\BaseService;
use Courier\Shared\Infrastructure\Container;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /**
     * Composition root del sistema Courier original: mismo `Container`
     * (service locator lazy-singleton) y los mismos ~40 bindings de
     * handlers/repositorios de `src/`, reusados sin cambios. Ver
     * `bootstrap/courier.php` (copia de `lan/bootstrap/app.php`).
     */
    public static function courierContainer(bool $getShared = true): Container
    {
        if ($getShared) {
            return static::getSharedInstance('courierContainer');
        }

        return require ROOTPATH . 'bootstrap/courier.php';
    }

    /**
     * Contexto request-scoped para pasar los claims del JWT verificado
     * (usuario_id, rol) desde JwtAuthFilter hacia los controllers de la API,
     * equivalente a JsonRequest::setAttribute()/attribute() del sistema original.
     */
    public static function apiAuthContext(bool $getShared = true): ApiAuthContext
    {
        if ($getShared) {
            return static::getSharedInstance('apiAuthContext');
        }

        return new ApiAuthContext();
    }
}
