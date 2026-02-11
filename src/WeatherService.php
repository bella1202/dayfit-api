<?php

class WeatherService {
    public function current() {
        $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
        $lon = isset($_GET['lon']) ? (float)$_GET['lon'] : null;

        if ($lat === null || $lon === null) {
            Json::error(400, 'BAD_REQUEST', 'lat, lon are required');
        }

        // 대충 유효 범위 체크
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            Json::error(400, 'BAD_REQUEST', 'invalid lat/lon');
        }

        $apiKey = Env::get('OPENWEATHER_API_KEY', '');
        if (!$apiKey) {
            Json::error(500, 'CONFIG', 'OPENWEATHER_API_KEY is missing');
        }

        $url = 'https://api.openweathermap.org/data/2.5/weather'
            . '?lat=' . rawurlencode((string)$lat)
            . '&lon=' . rawurlencode((string)$lon)
            . '&appid=' . rawurlencode($apiKey)
            . '&units=metric'
            . '&lang=kr';

        $data = $this->httpGetJson($url);

        // OpenWeather 응답에서 앱에 필요한 것만 가공해서 내려줌
        $temp = isset($data['main']['temp']) ? (float)$data['main']['temp'] : null;
        $feels = isset($data['main']['feels_like']) ? (float)$data['main']['feels_like'] : null;

        $weatherId = isset($data['weather'][0]['id']) ? (int)$data['weather'][0]['id'] : null;
        $desc = isset($data['weather'][0]['description']) ? (string)$data['weather'][0]['description'] : '';
        $wind = isset($data['wind']['speed']) ? (float)$data['wind']['speed'] : null;

        Json::ok([
            'temp' => $temp !== null ? (int)round($temp) : null,
            'feels' => $feels !== null ? (int)round($feels) : null,
            'weather_id' => $weatherId,
            'condition' => $desc,
            'wind' => $wind,
        ]);
    }

    private function httpGetJson(string $url): array {
        // cURL 사용(타임아웃/에러처리 안정적)
        if (!function_exists('curl_init')) {
            // curl 없으면 file_get_contents fallback
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 5,
                    'header' => "Accept: application/json\r\n",
                ]
            ]);

            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) {
                Json::error(502, 'UPSTREAM', 'OpenWeather request failed');
            }

            $json = json_decode($raw, true);
            if (!is_array($json)) {
                Json::error(502, 'UPSTREAM', 'OpenWeather invalid json');
            }

            // OpenWeather 에러 포맷 대비
            if (isset($json['cod']) && (int)$json['cod'] >= 400) {
                Json::error(502, 'UPSTREAM', 'OpenWeather error');
            }

            return $json;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno || $raw === false) {
            Json::error(502, 'UPSTREAM', 'OpenWeather request failed');
        }
        if ($http < 200 || $http >= 300) {
            Json::error(502, 'UPSTREAM', 'OpenWeather http error');
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            Json::error(502, 'UPSTREAM', 'OpenWeather invalid json');
        }

        // OpenWeather가 200이어도 cod/message로 에러 줄 때 대비
        if (isset($json['cod']) && is_numeric($json['cod']) && (int)$json['cod'] >= 400) {
            Json::error(502, 'UPSTREAM', 'OpenWeather error');
        }

        Json::ok($json);
    }
}
