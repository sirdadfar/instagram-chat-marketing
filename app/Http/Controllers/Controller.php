<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;

/**
 * Base controller for the application.
 *
 * Laravel 12 does not require controllers to extend a base class, but the
 * application controllers intentionally share the conventional Controller
 * base so middleware/dependencies can be added consistently later.
 */
abstract class Controller extends BaseController
{
}
