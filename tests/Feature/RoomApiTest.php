<?php

use App\Models\RoomModel;
use Tests\Support\OrbitFeatureTestCase;

/**
 * Skenario 6-12: API ruangan (daftar, buat, ubah, arsip, okupansi, mode).
 *
 * @internal
 */
final class RoomApiTest extends OrbitFeatureTestCase
{
    public function testListRoomsReturnsSeededRooms(): void
    {
        $result = $this->admin()->get('api/rooms');

        $result->assertStatus(200);
        $body = $this->json($result);
        $this->assertTrue($body['success']);
        $this->assertIsArray($body['data']['rooms']);
        $this->assertGreaterThanOrEqual(9, count($body['data']['rooms']));
    }

    public function testCreateRoomPersistsRowAndWritesAuditLog(): void
    {
        $mark = $this->logIdMax();
        $name = 'Ruang Uji ' . uniqid();

        $result = $this->admin()->post('api/rooms', [
            'name'        => $name,
            'floor_id'    => $this->floorId(),
            'room_type'   => 'closed',
            'description' => 'Dibuat oleh pengujian.',
        ]);

        $result->assertStatus(201);
        $body = $this->json($result);
        $this->assertSame($name, $body['data']['room']['name']);

        $room = (new RoomModel())->where('name', $name)->first();
        $this->assertNotNull($room, 'Ruangan tidak tersimpan.');
        $this->assertSame('Dibuat oleh pengujian.', $room['description']);

        $log = $this->latestLog('room.create', $mark);
        $this->assertNotNull($log, 'Log room.create tidak ditulis.');
        $this->assertSame((int) $room['id'], (int) $log['target_id']);
        $this->assertSame(1, (int) $log['user_id']);
    }

    public function testDuplicateRoomNameOnSameFloorRejected(): void
    {
        $name = 'Duplikat ' . uniqid();

        $this->admin()
            ->post('api/rooms', ['name' => $name, 'floor_id' => $this->floorId()])
            ->assertStatus(201);

        $dup = $this->admin()->post('api/rooms', ['name' => $name, 'floor_id' => $this->floorId()]);

        $dup->assertStatus(422);
        $body = $this->json($dup);
        $this->assertFalse($body['success']);
        $this->assertNotEmpty($body['errors']['name']);
        $this->assertSame(1, (new RoomModel())->where('name', $name)->countAllResults());
    }

    public function testPartialUpdateKeepsOtherFields(): void
    {
        $room = $this->makeRoom('Parsial ' . uniqid(), [
            'description' => 'Asli',
            'room_type'   => 'open_area',
        ]);

        $result = $this->admin()->put('api/rooms/' . $room['id'], ['description' => 'Diperbarui']);

        $result->assertStatus(200);

        $fresh = (new RoomModel())->find($room['id']);
        $this->assertSame('Diperbarui', $fresh['description']);
        $this->assertSame($room['name'], $fresh['name'], 'Nama berubah walau tidak dikirim.');
        $this->assertSame('open_area', $fresh['room_type']);
        $this->assertSame((int) $room['floor_id'], (int) $fresh['floor_id']);
    }

    public function testArchiveRoomSetsTimestampAndHidesFromList(): void
    {
        $mark = $this->logIdMax();
        $room = $this->makeRoom('Arsip ' . uniqid());

        $this->admin()->post('api/rooms/' . $room['id'] . '/archive', ['archive' => true])->assertStatus(200);

        $fresh = (new RoomModel())->find($room['id']);
        $this->assertNotNull($fresh['archived_at']);
        $this->assertSame(0, (int) $fresh['is_active']);

        $list = $this->json($this->admin()->get('api/rooms'));
        $ids  = array_map('intval', array_column($list['data']['rooms'], 'id'));
        $this->assertNotContains((int) $room['id'], $ids);

        $this->assertNotNull($this->latestLog('room.archive', $mark));
    }

    public function testOccupancyVacantSetsUtcVacantSinceAndOccupiedClears(): void
    {
        $room = $this->makeRoom('Okupansi ' . uniqid());
        $this->assertNull($room['vacant_since']);

        $vacant = $this->admin()->post('api/rooms/' . $room['id'] . '/occupancy', ['occupancy' => 'vacant']);

        $vacant->assertStatus(200);
        $vacantBody = $this->json($vacant);
        $this->assertSame('vacant', $vacantBody['data']['room']['occupancy']);

        $since = (string) $vacantBody['data']['room']['vacant_since'];
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since);
        $this->assertLessThan(10, abs(time() - strtotime($since . ' UTC')), 'vacant_since bukan waktu UTC terkini.');

        $invalid = $this->admin()->post('api/rooms/' . $room['id'] . '/occupancy', ['occupancy' => 'maybe']);
        $invalid->assertStatus(422);

        $occupied = $this->admin()->post('api/rooms/' . $room['id'] . '/occupancy', ['occupancy' => 'occupied']);

        $occupied->assertStatus(200);
        $occupiedBody = $this->json($occupied);
        $this->assertSame('occupied', $occupiedBody['data']['room']['occupancy']);
        $this->assertNull($occupiedBody['data']['room']['vacant_since']);
    }

    public function testSetModePersistsAndWritesAuditLog(): void
    {
        $mark = $this->logIdMax();
        $room = $this->makeRoom('Mode ' . uniqid());
        $this->assertSame('active', $room['mode']);

        $result = $this->admin()->post('api/rooms/' . $room['id'] . '/mode', ['mode' => 'standby']);

        $result->assertStatus(200);
        $fresh = (new RoomModel())->find($room['id']);
        $this->assertSame('standby', $fresh['mode']);

        $log = $this->latestLog('room.mode', $mark);
        $this->assertNotNull($log, 'Log room.mode tidak ditulis.');
        $details = json_decode((string) $log['details_json'], true);
        $this->assertSame('standby', $details['sesudah']);

        $bad = $this->admin()->post('api/rooms/' . $room['id'] . '/mode', ['mode' => 'nonsense']);
        $bad->assertStatus(422);
        $this->assertSame('standby', (new RoomModel())->find($room['id'])['mode'], 'Mode berubah oleh nilai invalid.');
    }
}
