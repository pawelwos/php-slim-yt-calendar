<?php

declare(strict_types=1);

namespace App;

interface VideoSource
{
    /**
     * Uploads from the last $days days, newest first.
     *
     * @return list<array{videoId:string,title:string,publishedAt:string}>
     */
    public function recentUploads(int $days): array;
}
