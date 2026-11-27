<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function publicRoutes(): array
    {
        return [
            'home' => ['home.view'],
            'guide' => ['guide.view'],
            'credits' => ['credits.view'],
            'login' => ['login.view'],
            'register' => ['register.view'],
        ];
    }

    #[DataProvider('publicRoutes')]
    public function test_public_pages_have_seo_basics_and_one_h1(string $route): void
    {
        $html = $this->get(route($route))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertMatchesRegularExpression('/<title>[^<]+<\/title>/', $html);
        $this->assertStringContainsString('<meta name="description" content="', $html);
        $this->assertStringContainsString('<meta property="og:title"', $html);
        $this->assertStringContainsString('<link rel="canonical"', $html);
    }

    public function test_titles_and_descriptions_are_unique(): void
    {
        $heads = collect(self::publicRoutes())->map(function (array $route): array {
            $html = $this->get(route($route[0]))->getContent();
            preg_match('/<title>([^<]+)<\/title>/', $html, $title);
            preg_match('/<meta name="description" content="([^"]+)"/', $html, $description);

            return [$title[1], $description[1]];
        });

        $this->assertSame($heads->count(), $heads->pluck(0)->unique()->count());
        $this->assertSame($heads->count(), $heads->pluck(1)->unique()->count());
    }

    public function test_home_invites_guests_to_sign_up_and_players_to_play(): void
    {
        $this->get(route('home.view'))
            ->assertSee(route('register.view'))
            ->assertSee('Crear cuenta y jugar');

        $this->actingAs(User::factory()->create())
            ->get(route('home.view'))
            ->assertSee(route('menu.view'))
            ->assertDontSee('Crear cuenta y jugar');
    }

    public function test_profile_is_not_indexed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile.view'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function test_flash_messages_are_shown(): void
    {
        $this->withSession(['success' => 'Registro exitoso. Por favor, inicia sesión.'])
            ->get(route('login.view'))
            ->assertSee('Registro exitoso. Por favor, inicia sesión.');
    }

    public function test_sitemap_lists_the_public_pages(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml');

        foreach (self::publicRoutes() as [$route]) {
            $response->assertSee('<loc>'.route($route).'</loc>', false);
        }
    }

    public function test_credits_attribute_the_fonts(): void
    {
        $this->get(route('credits.view'))->assertSee('Chango')->assertSee('Big Shoulders')->assertSee('SIL Open Font License')->assertSee('Kenney');
    }
}
