<?php

declare(strict_types=1);

namespace App\Controllers;

final class ImpresoraController extends BaseController
{
    public function index(): string
    {
        return view('impresoras/index', []);
    }
}
