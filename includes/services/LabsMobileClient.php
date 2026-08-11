<?php
/**
 * LabsMobile SMS API client (http/POST JSON, the API version LabsMobile
 * itself recommends). Endpoint/auth/payload shape verified against
 * https://www.labsmobile.com/en/sms-api/api-versions/http-rest-post-json
 * and https://apidocs.labsmobile.com/ on 2026-08-11 -- re-check if sending
 * ever starts failing, in case LabsMobile changes their API.
 *
 * Thin wrapper: takes a phone + message, returns a plain result array. No
 * pricing/membership/invoice logic belongs here -- callers hand in an
 * already-composed message (see AI/plans v10).
 */
class LabsMobileClient {
    private $username;
    private $token;
    private $endpoint = 'https://api.labsmobile.com/json/send';

    public function __construct($username, $token) {
        $this->username = $username;
        $this->token = $token;
    }

    /**
     * Send an SMS. $toPhone accepts MemberRepository::normalizePhone()'s
     * '+34612345678' form (the leading + is stripped -- LabsMobile expects
     * a bare digits-only msisdn). $senderAlias is the tpoa value: numeric
     * (max 16 digits) until LabsMobile approves the "ALMERCAU" alphanumeric
     * alias (max 11 chars), then alphanumeric -- callers should pass
     * settings.sms_sender_alias, not a hardcoded value.
     *
     * Returns ['success' => bool, 'message_id' => ?string, 'error' => ?string].
     */
    public function sendSms($toPhone, $message, $senderAlias) {
        $msisdn = ltrim((string) $toPhone, '+');

        $payload = json_encode([
            'message' => $message,
            'tpoa' => $senderAlias,
            'recipient' => [['msisdn' => $msisdn]],
        ]);

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Cache-Control: no-cache',
                'Authorization: Basic ' . base64_encode($this->username . ':' . $this->token),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            return ['success' => false, 'error' => 'cURL error: ' . $curlError];
        }

        $data = json_decode($responseBody, true);
        if (!is_array($data)) {
            return ['success' => false, 'error' => 'Invalid response from LabsMobile: ' . $responseBody];
        }

        if (isset($data['code']) && $data['code'] === '0') {
            return ['success' => true, 'message_id' => $data['subid'] ?? null];
        }

        return [
            'success' => false,
            'error' => $data['message'] ?? 'Unknown LabsMobile error',
            'code' => $data['code'] ?? null,
        ];
    }
}
