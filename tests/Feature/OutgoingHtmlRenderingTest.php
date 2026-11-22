<?php

namespace Tests\Feature;

use App\Mail\OutgoingEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutgoingHtmlRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_outgoing_rich_text_is_rendered_as_html_not_plain_text(): void
    {
        $mail = new OutgoingEmail(
            'Formatting test',
            'Hello formatted world',
            '<p>Hello <strong>formatted</strong> <em>world</em>.</p>',
        );

        $html = $mail->render();

        $this->assertStringContainsString('<strong>formatted</strong>', $html);
        $this->assertStringContainsString('<em>world</em>', $html);
        $this->assertStringNotContainsString('&lt;strong&gt;formatted', $html);
        $this->assertSame('emails.outgoing', $mail->content()->text);
    }

    public function test_outgoing_html_does_not_emit_executable_script(): void
    {
        $mail = new OutgoingEmail(
            'Sanitization test',
            'Safe message',
            '<p>Safe message</p><script>window.xssMarker=1</script>',
        );

        $html = $mail->render();

        $this->assertStringContainsString('Safe message', $html);
        $this->assertStringNotContainsString('<script', strtolower($html));
        $this->assertStringNotContainsString('window.xssMarker=1', $html);
    }
}
