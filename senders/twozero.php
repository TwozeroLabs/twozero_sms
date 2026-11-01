<?php
class twozero extends TwoZeroSms {

    public $countryCode;

    function __construct($message,$gsmnumber,$countryCode=""){
        $this->message = $this->utilmessage($message);
        $this->gsmnumber = $this->utilgsmnumber($gsmnumber);
        $this->countryCode = $countryCode;
    }

    /**
     * Build a unique idempotency key per send.
     *
     * @return string
     */
    protected function buildIdempotencyKey(){
        try {
            return 'whmcs-' . bin2hex(random_bytes(8));
        } catch (Exception $e) {
            return 'whmcs-' . uniqid('', true);
        }
    }

    /**
     * Build the JSON body for the send endpoint.
     *
     * sender_id is optional per API docs and defaults to the account
     * setting. It is omitted when empty: sending "" can trigger a
     * server-side 500 instead of falling back to the default.
     *
     * @param string $number   Destination number, already normalized.
     * @param string $message  Message text.
     * @param string $senderid Sender ID (may be empty).
     * @return array
     */
    protected function buildBody($number, $message, $senderid){
        $body = array(
            'to'      => $number,
            'message' => $message,
        );
        if (trim((string) $senderid) !== '') {
            $body['sender_id'] = trim((string) $senderid);
        }
        return $body;
    }

    /**
     * Extract the gateway message id from a decoded success response.
     *
     * Per API docs the id lives at data.message_id; older/alternate
     * shapes are accepted as fallbacks.
     *
     * @param mixed $decoded
     * @return string|null
     */
    protected function extractMsgId($decoded){
        if (!is_array($decoded)) {
            return null;
        }
        if (isset($decoded['data']['message_id'])) {
            return (string) $decoded['data']['message_id'];
        } elseif (isset($decoded['data']['id'])) {
            return (string) $decoded['data']['id'];
        } elseif (isset($decoded['id'])) {
            return (string) $decoded['id'];
        } elseif (isset($decoded['message_id'])) {
            return (string) $decoded['message_id'];
        }
        return null;
    }

    /**
     * Send SMS via the twozero.io v1 API.
     *
     * Endpoint: POST https://sms.twozero.io/api/v1/sms/send
     * Headers : X-API-Key, Idempotency-Key, Content-Type: application/json
     * Body    : {"to":"...","message":"..."} plus optional "sender_id"
     *
     * @return array|null  On success: array('log'=>array(), 'error'=>null, 'msgid'=>string|null).
     *                     On failure: array('log'=>array(), 'error'=>array(), 'msgid'=>null).
     *                     Returns null if the destination number is malformed.
     */
    public function send()
    {
        if ($this->gsmnumber == "numbererror") {
            $log[] = ("Number format error." . $this->gsmnumber);
            $error[] = ("Number format error." . $this->gsmnumber);
            return array(
                'log' => $log,
                'error' => $error,
                'msgid' => null,
            );
        }

        $params = $this->getParams();

        $url = "https://sms.twozero.io/api/v1/sms/send";

        $number = str_replace(array('+', '-', ' '), '', $this->gsmnumber);

        $apiKey   = isset($params->apiKey)   ? trim($params->apiKey)   : '';
        $senderid = isset($params->senderid) ? trim($params->senderid) : '';

        if ($apiKey === '') {
            return array(
                'log'   => array('twozero.io error: API key not configured.'),
                'error' => array('twozero.io error: API key not configured.'),
                'msgid' => null,
            );
        }

        $body = $this->buildBody($number, $this->message, $senderid);

        $headers = array(
            'X-API-Key: ' . $apiKey,
            'Idempotency-Key: ' . $this->buildIdempotencyKey(),
            'Content-Type: application/json',
            'accept: application/json',
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $curlResponse = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError   = curl_error($ch);
        curl_close($ch);

        $log   = array();
        $error = array();
        $msgid = null;

        $log[] = "twozero.io request: " . json_encode($body);
        $log[] = "twozero.io response: " . $curlResponse;
        $log[] = "twozero.io HTTP status: " . $httpCode;

        if ($curlResponse === false) {
            $error[] = "cURL error: " . $curlError;
            return array(
                'log' => $log,
                'error' => $error,
                'msgid' => null,
            );
        }

        $decoded = json_decode($curlResponse, true);

        if ($httpCode >= 200 && $httpCode < 300 && is_array($decoded)) {
            // Accept any 2xx as success. Capture an id if the gateway
            // returned one so it lands in the messages table.
            $msgid = $this->extractMsgId($decoded);
            return array(
                'log' => $log,
                'error' => null,
                'msgid' => $msgid,
            );
        }

        // Non-2xx or unparseable response: treat as failure.
        if (is_array($decoded) && isset($decoded['message'])) {
            $error[] = "twozero.io error: " . (is_array($decoded['message']) ? json_encode($decoded['message']) : $decoded['message']) . " (HTTP " . $httpCode . ")";
        } elseif (is_array($decoded) && isset($decoded['error'])) {
            $error[] = "twozero.io error: " . (is_array($decoded['error']) ? json_encode($decoded['error']) : $decoded['error']) . " (HTTP " . $httpCode . ")";
        } else {
            $error[] = "twozero.io error: HTTP " . $httpCode . " - " . $curlResponse;
        }
        if ($httpCode == 500) {
            $error[] = "twozero.io returned an internal Server Error. Check the balance panel for credits/KYC status (unverified accounts can only send to their own registered number), then retry. If it persists, report to twozero.io with the request time and destination number.";
        }
        return array(
            'log' => $log,
            'error' => $error,
            'msgid' => null,
        );
    }

    public function balance()
    {
        $params = $this->getParams();
        $apiKey = isset($params->apiKey) ? $params->apiKey : '';

        $now    = date('Y-m-d H:i:s');
        $result = array(
            'success'      => false,
            'credits'      => null,
            'kyc_status'   => null,
            'kyc_verified' => null,
            'error'        => null,
            'last_checked' => $now,
            'http_code'    => 0,
        );

        if (empty($apiKey)) {
            $result['error'] = 'API key not configured.';
            return $result;
        }

        $url     = 'https://sms.twozero.io/api/v1/account/balance';
        $headers = array(
            'X-API-Key: ' . $apiKey,
            'accept: application/json',
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $curlResponse = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        $result['http_code'] = $httpCode;

        if ($curlResponse === false) {
            $result['error'] = 'cURL error: ' . $curlError;
            return $result;
        }

        $decoded = json_decode($curlResponse, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($decoded['data']) && is_array($decoded['data'])) {
            $data                = $decoded['data'];
            $result['success']      = true;
            $result['credits']      = isset($data['credits'])      ? (int)    $data['credits']      : null;
            $result['kyc_status']   = isset($data['kyc_status'])   ? (string) $data['kyc_status']   : null;
            $result['kyc_verified'] = isset($data['kyc_verified']) ? (bool)   $data['kyc_verified'] : null;
            return $result;
        }

        // Non-2xx or unparseable: extract a readable snippet for the error message.
        $snippet = is_string($curlResponse) ? substr($curlResponse, 0, 200) : '';
        if (is_array($decoded) && isset($decoded['message'])) {
            $msg     = $decoded['message'];
            $snippet = is_array($msg) ? json_encode($msg) : (string) $msg;
        } elseif (is_array($decoded) && isset($decoded['error'])) {
            $err     = $decoded['error'];
            $snippet = is_array($err) ? json_encode($err) : (string) $err;
        }
        $result['error'] = 'HTTP ' . $httpCode . ': ' . $snippet;
        return $result;
    }

    public function report($msgid)
    {
        return null;
    }

    public function utilgsmnumber($number)
    {
        return $number;
    }

    public function utilmessage($message)
    {
        return $message;
    }
}

return array(
    'value' => 'twozero',
    'label' => 'twozero.io',
    'fields' => array(
        'apiKey',
        'senderid'
    )
);