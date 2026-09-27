<?php

namespace App\Support;

/**
 * Request-scoped collector for usage-analytics facts the controller learns
 * while handling a request. `RecordUsage::terminate()` reads it back after
 * the response has been sent, so recording never affects the response.
 */
final class UsageRecorder
{
    private ?string $outcome = null;

    private ?int $upstreamStatus = null;

    private ?int $meetingCount = null;

    private ?int $regionCount = null;

    private ?bool $chunked = null;

    public function outcome(string $outcome, ?int $upstreamStatus = null): void
    {
        $this->outcome = $outcome;
        $this->upstreamStatus = $upstreamStatus;
    }

    public function meetings(int $count, int $regions, bool $chunked): void
    {
        $this->meetingCount = $count;
        $this->regionCount = $regions;
        $this->chunked = $chunked;
    }

    public function getOutcome(): ?string
    {
        return $this->outcome;
    }

    public function getUpstreamStatus(): ?int
    {
        return $this->upstreamStatus;
    }

    public function getMeetingCount(): ?int
    {
        return $this->meetingCount;
    }

    public function getRegionCount(): ?int
    {
        return $this->regionCount;
    }

    public function getChunked(): ?bool
    {
        return $this->chunked;
    }
}
