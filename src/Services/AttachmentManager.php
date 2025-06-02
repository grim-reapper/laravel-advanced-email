<?php

namespace GrimReapper\AdvancedEmail\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log; // For logging file existence checks

class AttachmentManager
{
    protected array $attachments = [];
    protected array $rawAttachments = [];
    protected array $storageAttachments = [];

    public function addFile(string $file, array $options = []): static
    {
        // Check if the file exists before attempting to attach
        if (!file_exists($file)) {
            Log::warning("Attachment file not found and skipped: {$file}");
            return $this;
        }
        $this->attachments[] = ['file' => $file, 'options' => $options];
        return $this;
    }

    public function addData(string $data, string $name, array $options = []): static
    {
        $this->rawAttachments[] = ['data' => $data, 'name' => $name, 'options' => $options];
        return $this;
    }

    public function addFromStorage(string $disk, string $path, ?string $name = null, array $options = []): static
    {
        // Note: The original EmailService had 'disk' in options, but it's a direct param here.
        // This is a slightly cleaner signature.
        $this->storageAttachments[] = [
            'disk' => $disk,
            'path' => $path,
            'name' => $name,
            'options' => $options
        ];
        return $this;
    }

    public function prepareForStorage(): array
    {
        $result = [];
        
        foreach ($this->attachments as $attachment) {
            $result[] = [
                'type' => 'file',
                'path' => $attachment['file'],
                'options' => $attachment['options'] ?? [],
            ];
        }
        
        foreach ($this->rawAttachments as $attachment) {
            $result[] = [
                'type' => 'raw',
                'data' => base64_encode($attachment['data']), // Ensure data is encodable
                'name' => $attachment['name'],
                'options' => $attachment['options'] ?? [],
            ];
        }
        
        foreach ($this->storageAttachments as $attachment) {
            $result[] = [
                'type' => 'storage',
                'disk' => $attachment['disk'], // Make sure disk is included
                'path' => $attachment['path'],
                'name' => $attachment['name'], // name might be part of options in some contexts
                'options' => $attachment['options'] ?? [],
            ];
        }
        
        return $result;
    }

    public function attachToMailable(Mailable $mailable): void
    {
        foreach ($this->attachments as $attachment) {
            $mailable->attach($attachment['file'], $attachment['options']);
        }

        foreach ($this->rawAttachments as $attachment) {
            $mailable->attachData($attachment['data'], $attachment['name'], $attachment['options']);
        }

        foreach ($this->storageAttachments as $attachment) {
            $mailable->attachFromStorageDisk(
                $attachment['disk'],
                $attachment['path'],
                $attachment['name'],
                $attachment['options']
            );
        }
    }

    public function reset(): void
    {
        $this->attachments = [];
        $this->rawAttachments = [];
        $this->storageAttachments = [];
    }

    public function hasAttachments(): bool
    {
        return !empty($this->attachments) || !empty($this->rawAttachments) || !empty($this->storageAttachments);
    }
}
