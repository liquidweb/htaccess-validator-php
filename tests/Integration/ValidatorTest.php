<?php

namespace Tests\Integration;

use LiquidWeb\HtaccessValidator\Exceptions\ValidationException;
use LiquidWeb\HtaccessValidator\Validator;
use PHPUnit\Framework\TestCase;

/**
 * @covers LiquidWeb\HtaccessValidator\Validator
 * @testdox Validator integration tests
 */
class ValidatorTest extends TestCase
{
    /**
     * @test
     */
    public function it_should_recognize_valid_configurations()
    {
        $content  = <<<EOT
<IfModule mod_rewrite>
    Options +FollowSymLinks
    RewriteEngine on
    RewriteCond %{HTTP_HOST} ^www.example.com [NC]
    RewriteRule ^(.*)$ https://example.com/$1 [R=301,L]
</IfModule>
EOT;
        $this->assertTrue(Validator::createFromString($content)->validate());
    }

    /**
     * @test
     */
    public function it_should_recognize_invalid_configurations()
    {
        $content  = <<<EOT
<IfModule mod_rewrite>
    Options +FollowSymLinks!!
    RewriteEngine 👍
    RewriteCond %{HTTP_HOST ^www.example.com [NC]
    RewriteRules ^(.*)$ https://example.com/$1 [R=301,L]
EOT;

        $this->expectException(ValidationException::class);
        Validator::createFromString($content)->validate();
    }
}
