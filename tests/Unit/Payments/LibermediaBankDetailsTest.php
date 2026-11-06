<?php

namespace Tests\Unit\Payments;

use App\Services\Payments\LibermediaBankDetails;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LibermediaBankDetailsTest extends TestCase
{
    private const IBAN = 'HR1210010051863000160';

    public function test_imported_crlf_instructions_supply_the_complete_receiver_without_hardcoded_identity(): void
    {
        $description = "Virman - Uplatnica. Uplatite na račun.\r\n\r\nPrimatelj uplate:\r\nPrimjer trgovina d.o.o.\r\nUlica Primjera 17, Zadar, 23000\r\nNaziv banke d.d.\r\nIBAN: HR12 1001 0051 8630 0016 0\r\nSWIFT: EXAMPLE1";
        $details = LibermediaBankDetails::fromDescription($description);
        $this->assertSame('Primjer trgovina d.o.o.', $details['upi_receiver_name']);
        $this->assertSame('Ulica Primjera 17', $details['upi_receiver_street']);
        $this->assertSame('23000 Zadar', $details['upi_receiver_place']);
        $this->assertSame(self::IBAN, $details['upi_receiver_iban']);
        $this->assertStringContainsString('Uplatite na račun.', $details['bank_instructions']);
        $this->assertStringNotContainsString("\r", $details['bank_instructions']);
    }

    public function test_html_and_encoded_instructions_keep_line_boundaries_but_discard_active_content(): void
    {
        $html = '<p>Primatelj uplate:</p><div>Druga tvrtka d.o.o.</div><p>Druga ulica 2, 10000 Zagreb</p><p><strong>IBAN:</strong> '.self::IBAN.'</p><script>alert("discard")</script><style>.discard{color:red}</style>';
        foreach ([$html, htmlentities($html, ENT_QUOTES | ENT_HTML5, 'UTF-8')] as $description) {
            $details = LibermediaBankDetails::fromDescription($description);
            $this->assertSame('Druga tvrtka d.o.o.', $details['upi_receiver_name']);
            $this->assertSame('Druga ulica 2', $details['upi_receiver_street']);
            $this->assertSame('10000 Zagreb', $details['upi_receiver_place']);
            $this->assertStringNotContainsString('<', $details['bank_instructions']);
            $this->assertStringNotContainsString('discard', $details['bank_instructions']);
        }
    }

    public function test_name_and_address_can_use_explicit_separate_postcode_city_lines(): void
    {
        $details = LibermediaBankDetails::fromDescription("Primatelj: Treća trgovina\nUlica Igre 4\nHR-21000 Split\nIBAN:\n".self::IBAN);
        $this->assertSame('Treća trgovina', $details['upi_receiver_name']);
        $this->assertSame('Ulica Igre 4', $details['upi_receiver_street']);
        $this->assertSame('21000 Split', $details['upi_receiver_place']);
    }

    #[DataProvider('untrustworthyInstructions')]
    public function test_incomplete_invalid_or_ambiguous_instructions_do_not_supply_receiver_fields(string $description): void
    {
        $details = LibermediaBankDetails::fromDescription($description);
        $this->assertSame(['bank_instructions'], array_keys($details));
    }

    public static function untrustworthyInstructions(): array
    {
        $receiver = "Primatelj uplate:\nPrimjer d.o.o.\nPrimjerna 5, Zagreb, 10000\n";

        return [
            'missing label' => ['Primjer d.o.o. Zagreb '.self::IBAN],
            'missing address' => ["Primatelj uplate:\nPrimjer d.o.o.\nIBAN: ".self::IBAN],
            'invalid checksum' => [$receiver.'IBAN: HR1210010051863000161'],
            'invalid length' => [$receiver.'IBAN: '.self::IBAN.'1'],
            'missing IBAN label' => [$receiver.self::IBAN],
            'two labelled accounts' => [$receiver.'IBAN: '.self::IBAN."\nIBAN: HR1723600001101234565"],
            'unlabelled second account' => [$receiver.'IBAN: '.self::IBAN."\nDrugi račun: HR1723600001101234565"],
            'two recipient blocks' => [$receiver.'IBAN: '.self::IBAN."\n".$receiver],
            'unstructured city without postal code' => ["Primatelj uplate:\nPrimjer d.o.o.\nPrimjerna 5, Zagreb\nIBAN: ".self::IBAN],
        ];
    }

    public function test_missing_description_is_safe_and_has_no_receiver_defaults(): void
    {
        $this->assertSame(['bank_instructions' => ''], LibermediaBankDetails::fromDescription(null));
    }
}
