<?php
/**
 * @copyright 2017 Matthew Nagi
 * @license http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause License
 */

namespace Pleo\BloomFilter;

use JsonSerializable;
use RangeException;
use RuntimeException;

class HasherList implements JsonSerializable
{
    private string $algo;
    private int $count;
    private int $maxResult;

    /**
     * @param array $data The result of json_decode()ing a json_encode()ed
     *    instance of this class. Note to always decode with the second
     *    argument as true.
     * @return static
     */
    public static function initFromJson(array $data): static
    {
        return new static($data['algo'], $data['count'], $data['max']);
    }

    private static function hashValidation($algo, $maxResult): void
    {
        $testHash = @hash_hmac($algo, 'test', 'key', true);

        if (false === $testHash) {
            throw new RuntimeException("The algorithm `$algo` is invalid.");
        }
    }

    /**
     * @param string $algo
     * @param int $count
     * @param int $maxResult
     */
    public function __construct(string $algo, int $count, int $maxResult)
    {
        if ($maxResult <= 0) {
            throw new RangeException("Your maxResult value must be an integer greater than 0");
        }

        if ($count <= 0) {
            throw new RangeException("Your count value must be an integer greater than 0");
        }

        self::hashValidation($algo, $maxResult);
        $this->algo = $algo;
        $this->count = $count;
        $this->maxResult = $maxResult;
    }

    /**
     * @param string $value
     * @return int[] An array of $this->count ints that are between 0 and $this->maxResult
     */
    public function hash(string $value): array
    {
        $returns = [];
        for ($i = 0; $i < $this->count; $i++) {
            $rawHash = hash_hmac($this->algo, $value, (string) $i, true);
            $hashParts = unpack('n*', $rawHash);
            $hashParts[1] &= 0x7FFF;
            $num = 0;
            if (PHP_INT_SIZE === 4) {
                // Only for 32-bit versions of PHP
                // @codeCoverageIgnoreStart
                $num |= $hashParts[1] << 0x10;
                $num |= $hashParts[2] << 0x00;
                // @codeCoverageIgnoreEnd
            } else {
                $num |= $hashParts[1] << 0x30;
                $num |= $hashParts[2] << 0x20;
                $num |= $hashParts[3] << 0x10;
                $num |= $hashParts[4] << 0x00;
            }
            $returns[] = $num % $this->maxResult;
        }
        return $returns;
    }

    /**
     * @return array{algo: string, count: int, max: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'algo' => $this->algo,
            'count' => $this->count,
            'max' => $this->maxResult,
        ];
    }
}
