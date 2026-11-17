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

    public function test_moderator_can_create_a_participant_with_a_manually_assigned_id(): void
    {
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->post('/panel/participantes', ['name' => 'Ana Pérez', 'email' => 'ANA@example.com', 'uid' => '000123'])
            ->assertRedirect('/panel/participantes')->assertSessionHas('status');

        $participant = Participant::sole();
        $this->assertSame('000123', $participant->uid);
        $this->assertDatabaseHas('participants', ['name' => 'Ana Pérez', 'email' => 'ana@example.com']);
        $this->assertDatabaseCount('users', 1);
        $this->get('/panel/participantes')->assertOk()->assertSee('Usuarios cargados')->assertSee('000123')
            ->assertSee(route('formulario', ['uid' => '000123']));
        $this->get('/panel')->assertOk()->assertSee('Usuarios');
    }

    public function test_manual_creation_requires_an_id(): void
    {
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->post('/panel/participantes', ['name' => 'Ana', 'email' => 'ana@example.com'])->assertSessionHasErrors('uid');

        $this->assertDatabaseCount('participants', 0);
    }

    public function test_manual_creation_rejects_a_duplicate_id_without_overwriting_the_user(): void
    {
        $participant = Participant::factory()->create(['uid' => '123', 'name' => 'Nombre original']);
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->post('/panel/participantes', ['uid' => '123', 'name' => 'Otro usuario', 'email' => 'another@example.com'])->assertSessionHasErrors('uid');

        $this->assertDatabaseCount('participants', 1);
        $this->assertDatabaseHas('participants', ['id' => $participant->id, 'uid' => '123', 'name' => 'Nombre original']);
    }

    public function test_participant_names_are_escaped_in_the_management_page(): void
    {
        Participant::factory()->create(['name' => '<script>alert("name")</script>']);
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->get('/panel/participantes')->assertOk()
            ->assertSee('<script>alert("name")</script>')
            ->assertDontSee('<script>alert("name")</script>', false);
    }

    public function test_csv_import_updates_data_by_the_supplied_id_and_preserves_personal_links(): void
    {
        $participant = Participant::factory()->create(['uid' => '000123', 'name' => 'Nombre anterior', 'email' => 'old@example.com']);
        $uid = $participant->uid;
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));
        $file = UploadedFile::fake()->createWithContent('participantes.csv', "\xEF\xBB\xBFid;user;mail\n000123;Ana Pérez;ANA@example.com\n456;Luis Gómez;luis@example.com\n");

        $this->post('/panel/participantes/importar', ['csv' => $file])->assertRedirect('/panel/participantes')->assertSessionHas('status');

        $this->assertDatabaseCount('participants', 2);
        $this->assertDatabaseHas('participants', ['id' => $participant->id, 'uid' => $uid, 'name' => 'Ana Pérez', 'email' => 'ana@example.com']);
        $this->assertDatabaseHas('participants', ['uid' => '456', 'name' => 'Luis Gómez', 'email' => 'luis@example.com']);
        $this->assertNotSame($uid, Participant::where('email', 'luis@example.com')->sole()->uid);
    }

    public function test_csv_import_accepts_uid_name_and_email_headers(): void
    {
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));
        $file = UploadedFile::fake()->createWithContent('participantes.csv', "uid,name,email\nUSER_1-A,Ana,ana@example.com\n");

        $this->post('/panel/participantes/importar', ['csv' => $file])->assertRedirect('/panel/participantes')->assertSessionHas('status');

        $this->assertDatabaseHas('participants', ['uid' => 'USER_1-A', 'name' => 'Ana', 'email' => 'ana@example.com']);
    }

    public function test_a_conflicting_email_rolls_back_all_rows_in_the_import(): void
    {
        Participant::factory()->create(['uid' => '123', 'name' => 'Nombre original', 'email' => 'ana@example.com']);
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));
        $file = UploadedFile::fake()->createWithContent('participantes.csv', "id,user,mail\n456,Nombre nuevo,luis@example.com\n789,Otro nombre,ana@example.com\n");

        $this->post('/panel/participantes/importar', ['csv' => $file])->assertSessionHasErrors('csv');

        $this->assertDatabaseCount('participants', 1);
        $this->assertDatabaseHas('participants', ['uid' => '123', 'name' => 'Nombre original', 'email' => 'ana@example.com']);
    }

    #[DataProvider('invalidCsvFiles')]
    public function test_invalid_csv_returns_validation_errors_without_partial_changes(string $csv): void
    {
        $participant = Participant::factory()->create(['uid' => '123', 'name' => 'Nombre original', 'email' => 'ana@example.com']);
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
            'missing id header' => ["nombre,email\nAna,ana@example.com\n"],
            'headers only' => ["id,user,mail\n"],
            'invalid row after valid row' => ["id,user,mail\n123,Nombre actualizado,ana@example.com\n456,Otra persona,no-es-email\n"],
            'duplicate email' => ["id,user,mail\n123,Nombre actualizado,ana@example.com\n456,Otra persona,ANA@example.com\n"],
            'duplicate id' => ["id,user,mail\n123,Nombre actualizado,ana@example.com\n123,Otra persona,another@example.com\n"],
            'missing id' => ["id,user,mail\n,Ana,ana@example.com\n"],
            'invalid id' => ["id,user,mail\n<script>,Ana,ana@example.com\n"],
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
        $participant = Participant::factory()->create(['uid' => '000123', 'name' => 'Ana Pérez', 'email' => 'ana@example.com']);
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
        $this->postJson('/api/participants/resolve', ['uid' => 'id con espacios'])->assertUnprocessable()->assertJsonValidationErrors('uid');
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
