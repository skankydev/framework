<?php

namespace TestApp\Controller;

use SkankyDev\Controller\MasterController;
use SkankyDev\Http\Request;

/**
 * Minimal controller used exclusively as a test fixture for MasterFactory.
 */
class TestController extends MasterController
{
    public function index(Request $request): string
    {
        return 'from index';
    }

    public function greet(Request $request, string $name = 'world'): string
    {
        return "hello {$name}";
    }

    public function noArgs(): string
    {
        return 'no args';
    }
}
