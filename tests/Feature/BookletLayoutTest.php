<?php

namespace Tests\Feature;

use App\Support\BookletLayout;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookletLayoutTest extends TestCase
{
    #[Test]
    public function one_sheet_holds_four_pages(): void
    {
        $this->assertSame([['front' => [4, 1], 'back' => [2, 3]]], BookletLayout::sheets(4));
    }

    #[Test]
    public function eight_pages_nest_in_folding_order(): void
    {
        $this->assertSame([
            ['front' => [8, 1], 'back' => [2, 7]],
            ['front' => [6, 3], 'back' => [4, 5]],
        ], BookletLayout::sheets(8));
    }

    #[Test]
    public function every_page_appears_exactly_once(): void
    {
        foreach ([4, 8, 12, 40, 200] as $pageCount) {
            $all = collect(BookletLayout::sheets($pageCount))
                ->flatMap(fn (array $sheet): array => [...$sheet['front'], ...$sheet['back']])
                ->sort()
                ->values()
                ->all();

            $this->assertSame(range(1, $pageCount), $all, "P={$pageCount}");
        }
    }

    #[Test]
    public function sheets_rejects_a_count_that_is_not_a_multiple_of_four(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BookletLayout::sheets(6);
    }

    #[Test]
    public function blanks_go_before_the_back_pages(): void
    {
        $this->assertSame([
            ['source' => 0, 'page' => 1],
            ['source' => 1, 'page' => 1], ['source' => 1, 'page' => 2], ['source' => 1, 'page' => 3],
            ['source' => 1, 'page' => 4], ['source' => 1, 'page' => 5],
            null,
            ['source' => 2, 'page' => 1],
        ], BookletLayout::pageSequence([1, 5, 1], true));
    }

    #[Test]
    public function blanks_go_at_the_end_without_a_back(): void
    {
        $sequence = BookletLayout::pageSequence([6], false);

        $this->assertCount(8, $sequence);
        $this->assertSame([null, null], array_slice($sequence, 6));
    }

    #[Test]
    public function a_single_page_pads_to_four(): void
    {
        $this->assertSame([['source' => 0, 'page' => 1], null, null, null], BookletLayout::pageSequence([1], false));
    }

    #[Test]
    public function no_blanks_when_already_a_multiple_of_four(): void
    {
        $this->assertNotContains(null, BookletLayout::pageSequence([2, 5, 1], true));
    }

    #[Test]
    public function a_multi_page_back_ends_the_book(): void
    {
        $sequence = BookletLayout::pageSequence([3, 2], true);

        $this->assertSame([null, null, null], array_slice($sequence, 3, 3));
        $this->assertSame([['source' => 1, 'page' => 1], ['source' => 1, 'page' => 2]], array_slice($sequence, 6));
    }
}
