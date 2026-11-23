<?php

namespace Tests\Unit;

use App\Pixelforge\Rates;
use App\Pixelforge\TokenUsage;
use PHPUnit\Framework\TestCase;

class RatesTest extends TestCase
{
    private Rates $rates;

    protected function setUp(): void
    {
        $this->rates = Rates::fromArray([
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ]);
    }

    public function test_it_prices_the_documented_succeeded_generation(): void
    {
        $usage = new TokenUsage(inputTextTokens: 12, inputImageTokens: 765, outputTokens: 6450, reasoningTokens: 3184);

        $this->assertSame('0.305510000', $this->rates->costOf($usage)->toString());
    }

    public function test_it_matches_the_documented_usage_report_day(): void
    {
        $usage = new TokenUsage(inputTextTokens: 41, inputImageTokens: 0, outputTokens: 3300, reasoningTokens: 2950);

        $this->assertSame('0.169080000', $this->rates->costOf($usage)->toString());
    }

    public function test_it_matches_the_documented_maximum_cost(): void
    {
        $usage = new TokenUsage(inputTextTokens: 115, inputImageTokens: 1530, outputTokens: 6450, reasoningTokens: 8000);

        $this->assertSame('0.373875000', $this->rates->costOf($usage)->toString());
    }

    public function test_one_token_at_one_dollar_per_million_is_one_micro_dollar(): void
    {
        $rates = Rates::fromArray([
            'input_text_tokens' => '1.00',
            'input_image_tokens' => '0',
            'output_tokens' => '0',
            'reasoning_tokens' => '0',
        ]);

        $this->assertSame('0.000001000', $rates->costOf(new TokenUsage(1, 0, 0, 0))->toString());
    }

    public function test_it_round_trips_the_exact_rate_strings(): void
    {
        $this->assertSame([
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ], $this->rates->toArray());
    }
}
