<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * A feed large enough to exceed the 500-meeting fast path in Controller::pdf()
     * and exercise the chunked render + FPDI merge instead.
     *
     * @return list<array<string, mixed>>
     */
    protected function largeMultiRegionFeed(int $regions): array
    {
        $meetings = [];

        foreach (range(1, $regions) as $r) {
            $region = 'Region'.str_pad((string) $r, 2, '0', STR_PAD_LEFT);

            foreach ([1, 3, 5] as $day) {
                for ($i = 0; $i < 3; $i++) {
                    $meetings[] = [
                        'slug' => strtolower($region)."-$day-$i",
                        'name' => $region.' AA Group '.$i,
                        'day' => $day,
                        'time' => '19:00',
                        'address' => (100 + $i).' Main St, '.$region.', MS',
                        'regions' => ['Mississippi', 'North', $region],
                        'types' => ['O'],
                    ];
                }
            }
        }

        return $meetings;
    }

    protected function countPdfPages(string $pdf): int
    {
        return preg_match_all('~/Type\s*/Page[^s]~', $pdf);
    }
}
