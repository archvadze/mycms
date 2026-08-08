<?php

namespace Tests\Feature;

use App\Services\Mail\AttachmentPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttachmentPolicyTest extends TestCase
{
    #[Test]
    public function it_allows_a_normal_pdf_attachment(): void
    {
        $policy = new AttachmentPolicy();

        $result = $policy->evaluate([
            [
                'filename' => 'document.pdf',
                'content_type' => 'application/pdf',
                'size' => 22118,
            ],
        ]);

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['reason']);
    }

    #[Test]
    public function it_blocks_executable_files(): void
    {
        $policy = new AttachmentPolicy();

        $result = $policy->evaluate([
            [
                'filename' => 'virus.exe',
                'content_type' => 'application/octet-stream',
                'size' => 1024,
            ],
        ]);

        $this->assertFalse($result['allowed']);
    }

    #[Test]
    public function it_blocks_files_larger_than_ten_megabytes(): void
    {
        $policy = new AttachmentPolicy();

        $result = $policy->evaluate([
            [
                'filename' => 'large.pdf',
                'content_type' => 'application/pdf',
                'size' => (10 * 1024 * 1024) + 1,
            ],
        ]);

        $this->assertFalse($result['allowed']);
    }

    #[Test]
    public function it_blocks_more_than_ten_attachments(): void
    {
        $policy = new AttachmentPolicy();

        $attachments = [];

        for ($i = 1; $i <= 11; $i++) {
            $attachments[] = [
                'filename' => "file{$i}.pdf",
                'content_type' => 'application/pdf',
                'size' => 1024,
            ];
        }

        $result = $policy->evaluate($attachments);

        $this->assertFalse($result['allowed']);
    }

    #[Test]
    public function it_blocks_when_total_size_exceeds_twenty_megabytes(): void
    {
        $policy = new AttachmentPolicy();

        $result = $policy->evaluate([
            [
                'filename' => 'one.pdf',
                'content_type' => 'application/pdf',
                'size' => 10 * 1024 * 1024,
            ],
            [
                'filename' => 'two.pdf',
                'content_type' => 'application/pdf',
                'size' => 10 * 1024 * 1024,
            ],
            [
                'filename' => 'three.pdf',
                'content_type' => 'application/pdf',
                'size' => 1,
            ],
        ]);

        $this->assertFalse($result['allowed']);
    }

    public function test_batch_limits_allow_ten_normal_attachments(): void
    {
        $policy = new AttachmentPolicy();

        $attachments = [];

        for ($i = 1; $i <= 10; $i++) {
            $attachments[] = [
                'filename' => "file{$i}.pdf",
                'content_type' => 'application/pdf',
                'size' => 1024,
            ];
        }

        $result = $policy->evaluateBatchLimits($attachments);

        $this->assertTrue($result['allowed']);
    }

    public function test_batch_limits_block_more_than_ten_attachments(): void
    {
        $policy = new AttachmentPolicy();

        $attachments = [];

        for ($i = 1; $i <= 11; $i++) {
            $attachments[] = [
                'filename' => "file{$i}.pdf",
                'content_type' => 'application/pdf',
                'size' => 1024,
            ];
        }

        $result = $policy->evaluateBatchLimits($attachments);

        $this->assertFalse($result['allowed']);
        $this->assertSame(
            'Too many attachments. Maximum is 10.',
            $result['reason']
        );
    }
}
