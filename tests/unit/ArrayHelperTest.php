<?php
namespace tests\unit;
use uhi67\umvc\ArrayHelper;

class ArrayHelperTest extends \Codeception\Test\Unit
{

    /**
     * Tests the orderByDependency method by comparing the method's output
     * to an expected value. If the expected value is an integer, an exception
     * is expected to be thrown.
     *
     * @param array $input The input data to order by dependency.
     * @param callable|null $getDependencies The function to retrieve the dependencies.
     * @param array|int $expected The expected result or exception condition.
     * @dataProvider provOrderByDependency
     */
    public function testOrderByDependency(array $input, ?callable $getDependencies, array|int $expected)
    {
        if (is_int($expected)) {
            $this->expectException(\Exception::class);
        }
        $order = \uhi67\umvc\ArrayHelper::orderByDependency($input, $getDependencies);
        $this->assertEquals($expected, $order);
    }

    public static function provOrderByDependency(): array
    {
        return [
            1 => [
                [
                    'A' => ['require' => 'B'],
                    'B' => ['require' => ['C', 'D']],
                    'C' => [],
                    'D' => ['require' => 'E'], // E must precede D
                    'E' => ['require' => 'C'], // C must precede E
                    'F' => ['require' => 'A'],
                ], null,
                ['C', 'E', 'D', 'B', 'A', 'F'],
            ],
            2 => [
                [
                    'X' => ['require' => 'Y'],
                    'Y' => ['require' => 'Z'],
                    'Z' => ['require' => 'X'], // Z -> X, X -> Y,  Y -> Z
                ], null,
                ArrayHelper::ERROR_CYCLIC,
            ],
            3 => [
                [
                    'P' => ['require' => 'Q'],
                    'Q' => ['require' => 'XXX'], // missing key
                ], null,
                ArrayHelper::ERROR_NONEXISTENT
            ],
            4 => [
                [
                    'A' => ['dependency' => 'B'],
                    'B' => ['dependency' => ['C', 'D']],
                    'C' => [],
                    'D' => ['dependency' => 'E'], // E must precede D
                    'E' => ['dependency' => 'C'], // C must precede E
                    'F' => ['dependency' => 'A'],
                ], fn($item) => $item['dependency'] ?? [],
                ['C', 'E', 'D', 'B', 'A', 'F'],
            ]
        ];
    }
}
