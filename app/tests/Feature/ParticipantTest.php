<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ParticipantTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_management_requires_login(): void
    {
        $this->get('/panel/participantes')->assertRedirect('/login');
        $this->post('/panel/participantes')->assertRedirect('/login');
        $this->post('/panel/participantes/importar')->assertRedirect('/login');
        $this->get('/panel/participantes/exportar')->assertRedirect('/login');
        $this->assertDatabaseCount('participants', 0);
    }

    public function test_participant_management_returns_403_for_an_unauthorized_account(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'other@example.com']));

        $this->get('/panel/participantes')->assertForbidden();
        $this->post('/panel/participantes', ['name' => 'Ana', 'email' => 'ana@example.com'])->assertForbidden();
        $this->post('/panel/participantes/importar')->assertForbidden();
        $this->get('/panel/participantes/exportar')->assertForbidden();
        $this->assertDatabaseCount('participants', 0);
    }

    public function test_moderator_can_create_a_participant_with_an_automatically_generated_uid(): void
    {
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->post('/panel/participantes', ['name' => 'Ana Pérez', 'email' => 'ANA@example.com', 'uid' => str_repeat('A', 40)])
            ->assertRedirect('/panel/participantes')->assertSessionHas('status');

        $participant = Participant::sole();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $participant->uid);
        $this->assertNotSame(str_repeat('A', 40), $participant->uid);
        $this->assertDatabaseHas('participants', ['name' => 'Ana Pérez', 'email' => 'ana@example.com']);
        $this->assertDatabaseCount('users', 1);
        $this->get('/panel/participantes')->assertOk()->assertSee(route('formulario', ['uid' => $participant->uid]));
    }

    public function test_participant_names_are_escaped_in_the_management_page(): void
    {
        Participant::factory()->create(['name' => '<script>alert("name")</script>']);
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->get('/panel/participantes')->assertOk()
            ->assertSee('<script>alert("name")</script>')
            ->assertDontSee('<script>alert("name")</script>', false);
    }

    public function test_csv_import_updates_existing_names_and_preserves_personal_links(): void
    {
        $participant = Participant::factory()->create(['name' => 'Nombre anterior', 'email' => 'ana@example.com']);
        $uid = $participant->uid;
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));
        $file = UploadedFile::fake()->createWithContent('participantes.csv', "\xEF\xBB\xBFnombre;email\nAna Pérez;ANA@example.com\nLuis Gómez;luis@example.com\n");

        $this->post('/panel/participantes/importar', ['csv' => $file])->assertRedirect('/panel/participantes')->assertSessionHas('status');

        $this->assertDatabaseCount('participants', 2);
        $this->assertDatabaseHas('participants', ['id' => $participant->id, 'uid' => $uid, 'name' => 'Ana Pérez', 'email' => 'ana@example.com']);
        $this->assertDatabaseHas('participants', ['name' => 'Luis Gómez', 'email' => 'luis@example.com']);
        $this->assertNotSame($uid, Participant::where('email', 'luis@example.com')->sole()->uid);
    }

    #[DataProvider('invalidCsvFiles')]
    public function test_invalid_csv_returns_validation_errors_without_partial_changes(string $csv): void
    {
        $participant = Participant::factory()->create(['name' => 'Nombre original', 'email' => 'ana@example.com']);
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));
        $file = UploadedFile::fake()->createWithContent('participantes.csv', $csv);

        $this->from('/panel/participantes')->post('/panel/participantes/importar', ['csv' => $file])
            ->assertRedirect('/panel/participantes')->assertSessionHasErrors('csv');

        $this->assertDatabaseCount('participants', 1);
        $this->assertDatabaseHas('participants', ['id' => $participant->id, 'name' => 'Nombre original']);
    }

    public static function invalidCsvFiles(): array
    {
        return [
            'empty file' => [''],
            'missing headers' => ["nombre,telefono\nAna,123\n"],
            'headers only' => ["nombre,email\n"],
            'invalid row after valid row' => ["nombre,email\nNombre actualizado,ana@example.com\nOtra persona,no-es-email\n"],
            'duplicate email' => ["nombre,email\nNombre actualizado,ana@example.com\nOtra persona,ANA@example.com\n"],
        ];
    }

    public function test_csv_export_includes_personal_links_and_escapes_spreadsheet_formulas(): void
    {
        $participant = Participant::factory()->create(['name' => '=SUM(1+1)', 'email' => 'ana@example.com']);
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $response = $this->get('/panel/participantes/exportar')->assertOk()->assertDownload('participantes.csv')->assertHeader('Cache-Control', 'no-store, private');

        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=SUM(1+1)", $csv);
        $this->assertStringContainsString('ana@example.com', $csv);
        $this->assertStringContainsString($participant->uid, $csv);
        $this->assertStringContainsString(route('formulario', ['uid' => $participant->uid]), $csv);
    }

    public function test_public_uid_lookup_returns_only_that_participants_name_and_email(): void
    {
        $participant = Participant::factory()->create(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);
        Participant::factory()->create(['name' => 'Otra persona', 'email' => 'other@example.com']);

        $this->postJson('/api/participants/resolve', ['uid' => $participant->uid])->assertOk()
            ->assertExactJson(['name' => 'Ana Pérez', 'email' => 'ana@example.com'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_an_unknown_uid_returns_404_without_any_participant_data(): void
    {
        Participant::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/participants/resolve', ['uid' => str_repeat('Z', 40)])->assertNotFound()
            ->assertExactJson(['message' => 'No encontramos los datos de este enlace.']);
    }

    public function test_a_malformed_uid_returns_422(): void
    {
        $this->postJson('/api/participants/resolve', ['uid' => '1'])->assertUnprocessable()->assertJsonValidationErrors('uid');
    }

    public function test_the_event_site_can_resolve_a_uid_with_cors(): void
    {
        $this->withHeaders([
            'Origin' => 'https://eventos.huesped.org.ar',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type',
        ])->options('/api/participants/resolve')->assertSuccessful()
            ->assertHeader('Access-Control-Allow-Origin', 'https://eventos.huesped.org.ar');
    }
}
