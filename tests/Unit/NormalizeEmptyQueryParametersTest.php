<?php

namespace Tests\Unit;

use App\Http\Middleware\NormalizeEmptyQueryParameters;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class NormalizeEmptyQueryParametersTest extends TestCase
{
    public function test_it_removes_empty_integer_filters(): void
    {
        $request = Request::create('/api/v1/master/budget-lines', 'GET', [
            'per_page' => '500',
            'fiscal_year_id' => '',
            'project_id' => '   ',
        ]);

        (new NormalizeEmptyQueryParameters)->handle(
            $request,
            fn () => new Response('ok')
        );

        $this->assertFalse($request->query->has('fiscal_year_id'));
        $this->assertFalse($request->query->has('project_id'));
        $this->assertSame('500', $request->query('per_page'));
    }

    public function test_it_preserves_valid_integer_filters(): void
    {
        $request = Request::create('/api/v1/master/project-logframes', 'GET', [
            'fiscal_year_id' => '2',
            'project_id' => '7',
        ]);

        (new NormalizeEmptyQueryParameters)->handle(
            $request,
            fn () => new Response('ok')
        );

        $this->assertSame('2', $request->query('fiscal_year_id'));
        $this->assertSame('7', $request->query('project_id'));
    }
}
