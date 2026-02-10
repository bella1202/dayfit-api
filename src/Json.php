<?php
class Json {
    public static function body() {
        return json_decode(file_get_contents('php://input'), true);
    }

    public static function ok($data = []) {
        header('Content-Type: application/json');
        echo json_encode(['success'=>true,'data'=>$data]);
        exit;
    }

    public static function error($code, $msg) {
        http_response_code($code);
        echo json_encode(['success'=>false,'message'=>$msg]);
        exit;
    }
}
