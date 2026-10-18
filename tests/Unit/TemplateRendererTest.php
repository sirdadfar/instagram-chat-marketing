<?php

namespace Tests\Unit;

use App\Services\Automation\TemplateRenderer;
use Tests\TestCase;

class TemplateRendererTest extends TestCase
{
    public function test_it_replaces_known_variables(): void
    {
        $output = (new TemplateRenderer)->render(
            'Hi {{username}} from {{account_username}}!',
            ['username' => 'ali', 'account_username' => 'shop'],
        );

        $this->assertSame('Hi ali from shop!', $output);
    }

    public function test_unknown_variables_are_left_untouched(): void
    {
        $output = (new TemplateRenderer)->render('Hello {{unknown}}', []);

        $this->assertSame('Hello {{unknown}}', $output);
    }

    public function test_it_resolves_dotted_paths(): void
    {
        $output = (new TemplateRenderer)->render(
            '{{profile.username}}',
            ['profile' => ['username' => 'zahra']],
        );

        $this->assertSame('zahra', $output);
    }

    public function test_it_exposes_date_and_time_defaults(): void
    {
        $output = (new TemplateRenderer)->render('{{date}} {{time}}', []);

        $this->assertStringNotContainsString('{{', $output);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $output);
    }
}
