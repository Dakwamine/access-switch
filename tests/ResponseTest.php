<?php

declare(strict_types=1);

namespace AccessSwitch\Tests;

use AccessSwitch\Http\Response;

final class ResponseTest extends TestCase
{
    public function testEmptyResponse(): void
    {
        $response = Response::empty(503);
        $this->assertSame(503, $response->status);
        $this->assertSame('', $response->body);
        $this->assertSame([], $response->headers);
    }

    public function testJsonResponse(): void
    {
        $response = Response::json(['status' => 'ok'], 200);
        $this->assertSame(200, $response->status);
        $this->assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
        $this->assertSame('{"status":"ok"}', $response->body);
    }
}
