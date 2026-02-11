<?php

class LocationService
{
    // 최근 목록 최대 개수
    private const MAX_RECENTS = 10;

    // Authorization: Bearer <token> 에서 user_id 추출
    private function requireUserId(): int
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/Bearer\s+(.+)/', $auth, $m)) {
            Json::error(401, 'UNAUTHORIZED', 'missing token');
        }

        $token = trim($m[1]);

        $stmt = Db::$pdo->prepare("
            SELECT user_id
            FROM access_tokens
            WHERE token = :token
              AND expires_at > NOW()
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Json::error(401, 'UNAUTHORIZED', 'invalid token');
        }

        return (int)$row['user_id'];
    }

    private function placeKey(string $title, string $subtitle, float $lat, float $lon): string
    {
        // “실질적으로 같은 위치”를 하나로 묶기 위해 소수점 5자리 고정
        $latS = number_format($lat, 5, '.', '');
        $lonS = number_format($lon, 5, '.', '');
        return sha1($latS . '|' . $lonS . '|' . $title . '|' . $subtitle);
    }

    private function validateLatLon(float $lat, float $lon): void
    {
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            Json::error(400, 'BAD_REQUEST', 'invalid lat/lon');
        }
    }

    // GET /v1/location/primary
    public function getPrimary(): void
    {
        $uid = $this->requireUserId();

        $stmt = Db::$pdo->prepare("
            SELECT id, title, subtitle, lat, lon, updated_at
            FROM user_locations
            WHERE user_id = :uid AND is_primary = 1
            ORDER BY updated_at DESC
            LIMIT 1
        ");
        $stmt->execute([':uid' => $uid]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        Json::ok([
            'item' => $r ? [
                'id' => (int)$r['id'],
                'title' => (string)$r['title'],
                'subtitle' => (string)$r['subtitle'],
                'lat' => (float)$r['lat'],
                'lon' => (float)$r['lon'],
                'updated_at' => $r['updated_at'],
            ] : null
        ]);
    }

    // GET /v1/location/recents
    public function getRecents(): void
    {
        $uid = $this->requireUserId();

        $stmt = Db::$pdo->prepare("
            SELECT id, title, subtitle, lat, lon, is_primary, updated_at
            FROM user_locations
            WHERE user_id = :uid
            ORDER BY updated_at DESC
            LIMIT " . self::MAX_RECENTS . "
        ");
        $stmt->execute([':uid' => $uid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(function ($r) {
            return [
                'id' => (int)$r['id'],
                'title' => (string)$r['title'],
                'subtitle' => (string)$r['subtitle'],
                'lat' => (float)$r['lat'],
                'lon' => (float)$r['lon'],
                'is_primary' => ((int)$r['is_primary'] === 1),
                'updated_at' => $r['updated_at'],
            ];
        }, $rows);

        Json::ok(['items' => $items]);
    }

    // PUT /v1/location/primary
    // body: { title, subtitle, lat, lon }
    public function putPrimary(): void
    {
        $uid = $this->requireUserId();
        $b = Json::body();

        $title = trim((string)($b['title'] ?? ''));
        $subtitle = trim((string)($b['subtitle'] ?? ''));
        $lat = isset($b['lat']) ? (float)$b['lat'] : null;
        $lon = isset($b['lon']) ? (float)$b['lon'] : null;

        if ($title === '' || $subtitle === '' || $lat === null || $lon === null) {
            Json::error(400, 'BAD_REQUEST', 'title, subtitle, lat, lon are required');
        }
        $this->validateLatLon($lat, $lon);

        $pkey = $this->placeKey($title, $subtitle, $lat, $lon);

        Db::$pdo->beginTransaction();
        try {
            // 기존 대표 해제
            Db::$pdo->prepare("
                UPDATE user_locations
                SET is_primary = 0
                WHERE user_id = :uid AND is_primary = 1
            ")->execute([':uid' => $uid]);

            // 해당 위치 upsert + 대표로 설정 + updated_at 갱신
            Db::$pdo->prepare("
                INSERT INTO user_locations (
                    user_id, title, subtitle, lat, lon, place_key, is_primary, created_at, updated_at
                ) VALUES (
                    :uid, :title, :subtitle, :lat, :lon, :pkey, 1, NOW(), NOW()
                )
                ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    subtitle = VALUES(subtitle),
                    lat = VALUES(lat),
                    lon = VALUES(lon),
                    is_primary = 1,
                    updated_at = NOW()
            ")->execute([
                ':uid' => $uid,
                ':title' => $title,
                ':subtitle' => $subtitle,
                ':lat' => $lat,
                ':lon' => $lon,
                ':pkey' => $pkey,
            ]);

            // MAX_RECENTS 유지 (대표 포함)
            Db::$pdo->prepare("
                DELETE FROM user_locations
                WHERE user_id = :uid
                  AND id NOT IN (
                    SELECT id FROM (
                      SELECT id
                      FROM user_locations
                      WHERE user_id = :uid2
                      ORDER BY updated_at DESC
                      LIMIT " . self::MAX_RECENTS . "
                    ) t
                  )
            ")->execute([':uid' => $uid, ':uid2' => $uid]);

            Db::$pdo->commit();
        } catch (Throwable $e) {
            Db::$pdo->rollBack();
            throw $e;
        }

        Json::ok();
    }

    // DELETE /v1/location/recents
    // 정책: 대표(is_primary=1)는 유지, 나머지만 삭제
    public function clearRecents(): void
    {
        $uid = $this->requireUserId();

        Db::$pdo->prepare("
            DELETE FROM user_locations
            WHERE user_id = :uid AND is_primary = 0
        ")->execute([':uid' => $uid]);

        Json::ok();
    }

    // DELETE /v1/location/item?id=
    public function deleteItem(): void
    {
        $uid = $this->requireUserId();
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($id <= 0) {
            Json::error(400, 'BAD_REQUEST', 'id is required');
        }

        Db::$pdo->prepare("
            DELETE FROM user_locations
            WHERE id = :id AND user_id = :uid
        ")->execute([
            ':id' => $id,
            ':uid' => $uid
        ]);

        Json::ok();
    }

    public function deletePrimary() {
        $uid = $this->requireUserId(); 
    
        Db::$pdo->prepare("
            UPDATE user_locations
            SET is_primary = 0
            WHERE user_id = :uid
              AND is_primary = 1
        ")->execute([
            ':uid' => $uid
        ]);
    
        Json::ok();
    }
}
