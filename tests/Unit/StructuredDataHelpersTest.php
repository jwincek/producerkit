<?php
/**
 * Pure helpers in modules/core/includes/structured-data.php.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use function Leftfield\Core\StructuredData\availability_url;
use function Leftfield\Core\StructuredData\parse_price;

final class StructuredDataHelpersTest extends TestCase {

    /** @dataProvider price_provider */
    public function test_parse_price(string $display, ?string $expected): void {
        $this->assertSame($expected, parse_price($display));
    }

    public function price_provider(): array {
        return [
            'plain dollars'        => ['$4.50 / bunch', '4.50'],
            'integer'              => ['$8', '8.00'],
            'no currency sign'     => ['5 per loaf', '5.00'],
            'donation is skipped'  => ['donation', null],
            'Donation any case'    => ['Suggested Donation $5', null],
            'empty'                => ['', null],
            'no digits'            => ['market price', null],
            'first number wins'    => ['$3.25 ea / $12 flat', '3.25'],
        ];
    }

    /** @dataProvider availability_provider */
    public function test_availability_url(string $status, ?string $expected): void {
        $this->assertSame($expected, availability_url($status));
    }

    public function availability_provider(): array {
        return [
            'abundant'    => ['abundant', 'https://schema.org/InStock'],
            'available'   => ['available', 'https://schema.org/InStock'],
            'limited'     => ['limited', 'https://schema.org/LimitedAvailability'],
            'sold_out'    => ['sold_out', 'https://schema.org/SoldOut'],
            'unavailable' => ['unavailable', 'https://schema.org/OutOfStock'],
            'unknown'     => ['weird', null],
            'empty'       => ['', null],
        ];
    }
}
