<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\AiDiagnostics;

class AiDiagnosticsTest extends TestCase
{
    public function test_logging_is_disabled_by_default(): void
    {
        Functions\expect('error_log')->never();

        AiDiagnostics::log([], 'request_started', 'correlation', ['field' => 'body']);
    }

    public function test_default_logging_redacts_content_and_drops_unknown_credentials(): void
    {
        $line = '';
        Functions\expect('error_log')->once()->with(\Mockery::capture($line));

        AiDiagnostics::log(
            ['diagnostics' => true, 'diagnostics_content' => false],
            'attempt_finished',
            'test-correlation',
            [
                'provider' => 'openai',
                'field' => 'body',
                'attempt' => 1,
                'article_text' => 'vertrouwelijk artikel',
                'prompt' => 'geheime prompt',
                'generated_output' => 'conceptantwoord',
                'api_key' => 'sk-never-log-this',
                'authorization' => 'Bearer never-log-this',
                'cookie' => 'never-log-this',
                'nonce' => 'never-log-this',
            ]
        );

        $this->assertStringContainsString('test-correlation', $line);
        $this->assertSame(3, substr_count($line, '[redacted]'));
        $this->assertStringNotContainsString('vertrouwelijk', $line);
        $this->assertStringNotContainsString('sk-never', $line);
        $this->assertStringNotContainsString('Bearer', $line);
        $this->assertStringNotContainsString('cookie', $line);
        $this->assertStringNotContainsString('nonce', $line);
    }

    public function test_content_logging_requires_separate_opt_in(): void
    {
        $line = '';
        Functions\expect('error_log')->once()->with(\Mockery::capture($line));

        AiDiagnostics::log(
            ['diagnostics' => true, 'diagnostics_content' => true],
            'attempt_finished',
            'test-correlation',
            ['generated_output' => 'expliciet gelogde inhoud api_key=sk-never-log-this']
        );

        $this->assertStringContainsString('expliciet gelogde inhoud', $line);
        $this->assertStringNotContainsString('sk-never-log-this', $line);
        $this->assertStringContainsString('[credential-redacted]', $line);
    }
}
