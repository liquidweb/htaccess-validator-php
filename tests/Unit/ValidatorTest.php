<?php

namespace Tests\Unit;

use LiquidWeb\HtaccessValidator\Exceptions\ValidationException;
use LiquidWeb\HtaccessValidator\Validator;
use PHPUnit\Framework\TestCase;

/**
 * @covers LiquidWeb\HtaccessValidator\Validator
 * @testdox Validator class unit tests
 */
class ValidatorTest extends TestCase
{
    /**
     * @test
     * @testdox getFilePath() should return the system path to regular files
     */
    public function getFilePath_should_return_the_path_to_regular_files()
    {
        $this->assertSame('/path/to/some-file', (new Validator('/path/to/some-file'))->getFilePath());
    }

    /**
     * @test
     * @testdox getFilePath() should return the system path to temporary files
     */
    public function getFilePath_should_return_the_path_to_resources()
    {
        $fh = tmpfile();

        $this->assertSame(
            stream_get_meta_data($fh)['uri'],
            (new Validator($fh))->getFilePath()
        );
    }

    /**
     * @test
     * @testdox isValid() should return true if validation passes
     */
    public function isValid_should_return_true_if_validation_passes()
    {
        $instance = $this->getMockBuilder(Validator::class)
            ->setConstructorArgs(['/path/to/some-file'])
            ->setMethodsExcept(['isValid'])
            ->getMock();
        $instance->expects($this->once())
            ->method('validate')
            ->willReturn(true);

        $this->assertTrue($instance->isValid());
    }

    /**
     * @test
     * @testdox isValid() should return false if validation fails
     */
    public function isValid_should_return_false_if_validation_fails()
    {
        $instance = $this->getMockBuilder(Validator::class)
            ->setConstructorArgs(['/path/to/some-file'])
            ->setMethodsExcept(['isValid'])
            ->getMock();
        $instance->expects($this->once())
            ->method('validate')
            ->will($this->throwException(new ValidationException('Something went wrong')));

        $this->assertFalse($instance->isValid());
    }

    /**
     * @test
     * @testdox validate() should return true if validation passes
     */
    public function validate_should_return_true_if_validation_passes()
    {
        $instance = $this->getMockBuilder(Validator::class)
            ->setConstructorArgs(['/path/to/some-file'])
            ->setMethods(['runValidator'])
            ->getMock();
        $instance->expects($this->once())
            ->method('runValidator')
            ->willReturn((object) [
                'exitCode' => 0,
            ]);

        $instance->validate();
    }

    /**
     * @test
     * @testdox validate() should throw a ValidationException if the script encounters an error
     */
    public function validate_should_throw_a_ValidationException_if_the_validator_encountered_an_error()
    {
        $instance = $this->getMockBuilder(Validator::class)
            ->setConstructorArgs(['/path/to/some-file'])
            ->setMethods(['runValidator'])
            ->getMock();
        $instance->method('runValidator')
            ->will($this->throwException(new \RuntimeException('Shell script is missing')));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Shell script is missing');

        $instance->validate();
    }

    /**
     * @test
     * @testdox validate() should throw a ValidationException if validation fails
     */
    public function validate_should_throw_an_exception_if_validation_fails()
    {
        $instance = $this->getMockBuilder(Validator::class)
            ->setConstructorArgs(['/path/to/some-file'])
            ->setMethods(['runValidator'])
            ->getMock();
        $instance->method('runValidator')
            ->willReturn((object) [
                'exitCode' => 2,
                'errors'   => 'Some error describing some issue',
            ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Some error describing some issue');

        $instance->validate();
    }

    /**
     * @test
     * @testdox createFromString() should create a temp file with the given content
     */
    public function createFromString_should_create_a_temp_file_with_the_given_content()
    {
        $contents = <<<EOT
Options +FollowSymLinks
RewriteEngine on
RewriteCond %{HTTP_HOST} ^www.example.com [NC]
RewriteRule ^(.*)$ https://example.com/$1 [R=301,L]
EOT;

        $validator = Validator::createFromString($contents);
        $tmpfile   = $validator->getFilePath();

        $this->assertTrue(file_exists($tmpfile));
        $this->assertSame($contents, file_get_contents($tmpfile));
    }
}
