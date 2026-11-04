<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use InvalidArgumentException;

/** Pure cartesian builder: Color/Size/Weight do not change Piece Count pricing. */
final class OptionWiseMatrix
{
    /** @param array<string,array<int,int>> $options
     * @return array<int,array<string,int>>
     */
    public static function combinations(array $options, int $limit = 200): array
    {
        if ($options === [] || $limit < 1) {
            throw new InvalidArgumentException('Select at least one option dimension.');
        }
        $rows = [[]];
        foreach ($options as $code => $ids) {
            if (! is_string($code) || $ids === [] || count($ids) !== count(array_unique($ids))) {
                throw new InvalidArgumentException('Option values must be unique and nonempty.');
            }
            if (count($rows) > intdiv($limit, count($ids))) {
                throw new InvalidArgumentException('Too many combinations. Select fewer values.');
            }
            $next = [];
            foreach ($rows as $row) {
                foreach ($ids as $id) {
                    if (! is_int($id) || $id < 1) {
                        throw new InvalidArgumentException('Invalid option value ID.');
                    }
                    $next[] = $row + [$code => $id];
                }
            }
            $rows = $next;
        }
        return $rows;
    }
}
