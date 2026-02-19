<?php

class AuthService {
    private function issueToken(int $userId): string {
        $token = base64_encode(random_bytes(40));
        // $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24); // 1일
        $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30); // 30일
    
        Db::$pdo->prepare("
            INSERT INTO access_tokens (user_id, token, expires_at)
            VALUES (:user_id, :token, :expires_at)
        ")->execute([
            ':user_id' => $userId,
            ':token'   => $token,
            ':expires_at' => $expiresAt
        ]);

        Db::$pdo->prepare("
            INSERT INTO users_history (user_id, ip, device)
            VALUES (:user_id, :ip, :device)
        ")->execute([
            ':user_id' => $userId,
            ':ip'   => $_SERVER['REMOTE_ADDR'],
            ':device' => $_SERVER['HTTP_USER_AGENT']
        ]);
    
        return $token;
    }    

    public function checkEmail() {
        $b = Json::body();

        $stmt = Db::$pdo->prepare("
            SELECT id
            FROM users
            WHERE email = :email
        ");
        $stmt->execute([
            ':email' => $b['email']
        ]);

        Json::ok([
            'exists' => $stmt->fetch() ? true : false
        ]);
    }

    public function requestEmailVerify() {
        $b = Json::body();
        $code = random_int(100000, 999999);

        $stmt = Db::$pdo->prepare("
            INSERT INTO email_verifications (
                email,
                code,
                expires_at
            ) VALUES (
                :email,
                :code,
                DATE_ADD(NOW(), INTERVAL 10 MINUTE)
            )
        ");
        $stmt->execute([
            ':email' => $b['email'],
            ':code'  => $code
        ]);

        // TODO: 실제 메일 발송
        Json::ok([
            'code' => $code
        ]);
    }

    public function verifyEmail() {
        $b = Json::body();

        $stmt = Db::$pdo->prepare("
            SELECT id
            FROM email_verifications
            WHERE email = :email
              AND code = :code
              AND expires_at > NOW()
              AND verified_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':email' => $b['email'],
            ':code'  => $b['code']
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            Json::error(400, 'INVALID_CODE');
        }

        Db::$pdo->prepare("
            UPDATE email_verifications
            SET verified_at = NOW()
            WHERE id = :id
        ")->execute([
            ':id' => $row['id']
        ]);

        Json::ok();
    }

    public function signup() {
        $b = Json::body();
    
        $hash = password_hash($b['password'], PASSWORD_DEFAULT);
    
        Db::$pdo->prepare("
            INSERT INTO users (
                email,
                password_hash,
                nickname,
                birthday,
                phone,
                provider,
                email_verified
            ) VALUES (
                :email,
                :password_hash,
                :nickname,
                :birthday,
                :phone,
                'email',
                1
            )
        ")->execute([
            ':email'          => $b['email'],
            ':password_hash'  => $hash,
            ':nickname'       => $b['nickname'],
            ':birthday'       => $b['birthday'],
            ':phone'          => $b['phone']
        ]);
    
        $userId = (int)Db::$pdo->lastInsertId();
    
        $token = $this->issueToken($userId);
    
        Json::ok([
            'token' => $token,
            'user' => [
                'id' => (int)$userId,
                'email' => $b['email'],
                'nickname' => $b['nickname'],
                'birthday' => $b['birthday'],
                'phone' => $b['phone']
            ]
        ]);
    }
    

    public function login() {
        $b = Json::body();
    
        $stmt = Db::$pdo->prepare("
            SELECT *
            FROM users
            WHERE email = :email
              AND provider = 'email'
            LIMIT 1
        ");
        $stmt->execute([
            ':email' => $b['email']
        ]);
    
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if (
            !$user ||
            !password_verify($b['password'], $user['password_hash'])
        ) {
            Json::error(401, 'INVALID_LOGIN', '이메일 또는 비밀번호가 올바르지 않습니다.');
        }
    
        // 이메일 인증 체크
        if ((int)$user['email_verified'] !== 1) {
            Json::error(403, 'EMAIL_NOT_VERIFIED', '이메일 인증이 필요합니다.');
        }
    
        $token = $this->issueToken((int)$user['id']);
    
        Json::ok([
            'token' => $token
        ]);
    }
    

    public function appleLogin() {
        $b = Json::body();
        $sub = $b['apple_sub'];

        $stmt = Db::$pdo->prepare("
            SELECT id
            FROM users
            WHERE apple_sub = :apple_sub
              AND provider = 'apple'
            LIMIT 1
        ");
        $stmt->execute([
            ':apple_sub' => $sub
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Db::$pdo->prepare("
                INSERT INTO users (
                    apple_sub,
                    provider
                ) VALUES (
                    :apple_sub,
                    'apple'
                )
            ")->execute([
                ':apple_sub' => $sub
            ]);
            $userId = Db::$pdo->lastInsertId();
        } else {
            $userId = $user['id'];
        }

        $token = base64_encode(random_bytes(40));

        Db::$pdo->prepare("
            INSERT INTO access_tokens (
                user_id,
                token
            ) VALUES (
                :user_id,
                :token
            )
        ")->execute([
            ':user_id' => $userId,
            ':token'   => $token
        ]);

        Json::ok([
            'token' => $token
        ]);
    }
}
