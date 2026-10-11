<?php

use App\Models\UserModel;
use Tests\Support\OrbitFeatureTestCase;

/**
 * Skenario 29: pengaturan gedung + preferensi tema.
 *
 * @internal
 */
final class SettingsPreferenceTest extends OrbitFeatureTestCase
{
    public function testSettingsValidationPersistenceAndThemePreference(): void
    {
        $settings = service('settings');
        $original  = $settings->vacantDurationMinutes();
        $user      = (new UserModel())->find(1);
        $origTheme = $user['theme'];

        try {
            // Validasi: 0 di luar rentang 1-1440.
            $bad = $this->admin()->put('api/settings', ['vacant_duration_minutes' => 0]);

            $bad->assertStatus(422);
            $this->assertNotEmpty($this->json($bad)['errors']['vacant_duration_minutes']);
            $this->assertSame($original, $settings->vacantDurationMinutes(), 'Nilai tak boleh berubah saat invalid.');

            // Persistensi + audit.
            $mark = $this->logIdMax();
            $ok   = $this->admin()->put('api/settings', ['vacant_duration_minutes' => 7]);

            $ok->assertStatus(200);
            $this->assertSame(7, $settings->vacantDurationMinutes());
            $this->assertNotNull($this->latestLog('setting.update', $mark));

            // Preferensi tema (semua peran boleh).
            $theme = $this->admin()->put('api/preferences', ['theme' => 'dark']);

            $theme->assertStatus(200);
            $this->assertSame('dark', $this->json($theme)['data']['theme']);
            $this->assertSame('dark', (new UserModel())->find(1)['theme']);

            $badTheme = $this->admin()->put('api/preferences', ['theme' => 'neon']);

            $badTheme->assertStatus(422);
            $this->assertNotEmpty($this->json($badTheme)['errors']['theme']);
        } finally {
            $settings->set('vacant_duration_minutes', (string) $original);
            (new UserModel())->update(1, ['theme' => $origTheme]);
        }
    }
}
