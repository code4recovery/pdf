<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Saddle-stitch arithmetic for booklet printing: pads a book to a multiple
 * of four pages and says which pages share each side of each sheet. Knows
 * nothing about PDFs.
 */
final class BookletLayout
{
    /**
     * The book's pages in order, with blank slots added so the length is a
     * multiple of four. Blanks go immediately before the back PDF's pages so
     * its last page stays the outside back; with no back PDF they go last.
     *
     * @param  list<int>  $pageCounts  One entry per source file in book order; the last is the back PDF when $hasBack.
     * @return list<array{source: int, page: int}|null>
     */
    public static function pageSequence(array $pageCounts, bool $hasBack): array
    {
        $pages = [];

        foreach ($pageCounts as $source => $count) {
            for ($page = 1; $page <= $count; $page++) {
                $pages[] = ['source' => $source, 'page' => $page];
            }
        }

        $total = count($pages);
        $blanks = array_fill(0, max(4, (int) ceil($total / 4) * 4) - $total, null);
        $backCount = $hasBack ? $pageCounts[array_key_last($pageCounts)] : 0;

        array_splice($pages, $total - $backCount, 0, $blanks);

        return $pages;
    }

    /**
     * The 1-based book positions on each sheet, as [left, right] per side.
     *
     * @return list<array{front: array{0: int, 1: int}, back: array{0: int, 1: int}}>
     */
    public static function sheets(int $pageCount): array
    {
        if ($pageCount <= 0 || $pageCount % 4 !== 0) {
            throw new InvalidArgumentException("A booklet needs a positive multiple of four pages, got {$pageCount}.");
        }

        $sheets = [];

        for ($sheet = 0; $sheet < $pageCount / 4; $sheet++) {
            $sheets[] = [
                'front' => [$pageCount - 2 * $sheet, 2 * $sheet + 1],
                'back' => [2 * $sheet + 2, $pageCount - 2 * $sheet - 1],
            ];
        }

        return $sheets;
    }
}
