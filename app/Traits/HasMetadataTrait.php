<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasMetadataTrait
{
    public function getSeoTitle(): string
    {
        return $this->meta_title
            ?: ($this->title ?? $this->name ?? $this->page_name ?? config('app.name'));
    }

    public function getSeoDescription(): string
    {
        $text = $this->meta_description
            ?: ($this->short_description ?? $this->description ?? $this->full_description ?? '');

        return Str::limit(strip_tags($text), 160);
    }

    public function getSeoTags(): array
    {
        return [
            'title' => $this->getSeoTitle(),
            'description' => $this->getSeoDescription(),
        ];
    }
}