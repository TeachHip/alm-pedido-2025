<?php
/**
 * PayGold Client
 * Wraps Redsys's PayGold REST API to request a "2 fases" payment link that
 * a member can pay from (Ds_Merchant_TransactionType = "15"). The signing
 * algorithm and field names here are taken directly from Redsys's own
 * official PHP reference library (see _mats/redsys-example/redsys-lib,
 * provided by the user -- not reconstructed from documentation prose,
 * which turned out to disagree with itself across sources).
 *
 * Signature: HMAC_SHA512_V2 -- derive a per-order AES-128-CBC key from the
 * merchant secret key (first 16 chars, zero-padded, used as raw key bytes
 * -- NOT base64-decoded), encrypt the order number with it (CBC, zero IV),
 * base64-encode that -> use it as the HMAC-SHA512 key over the
 * base64url-encoded request JSON, base64url-encode the result. The same
 * function (with the response's own order number) verifies that a
 * response genuinely came from Redsys before it's trusted.
 */

class PayGoldClient {
    const ENDPOINT_TEST = 'https://sis-t.redsys.es:25443/sis/rest/trataPeticionREST';
    const ENDPOINT_PROD = 'https://sis.redsys.es/sis/rest/trataPeticionREST';
    const CURRENCY_EUR = '978';
    const TRANSACTION_TYPE_PAYGOLD = '15';

    private $merchantCode;
    private $terminal;
    private $secretKey;
    private $endpoint;

    public function __construct($merchantCode, $terminal, $secretKey, $environment = 'TEST') {
        $this->merchantCode = $merchantCode;
        $this->terminal = $terminal;
        $this->secretKey = $secretKey;
        $this->endpoint = ($environment === 'PROD') ? self::ENDPOINT_PROD : self::ENDPOINT_TEST;
    }

    /**
     * Load includes/config/api-keys-DB.php (if present) and build a client
     * from its constants, picking the secret key matching PAYGOLD_ENVIRONMENT
     * (Redsys issues a different signing key per environment, same merchant
     * code/terminal for both). Returns null if the file is missing or the
     * required constants aren't set/filled in -- callers should fall back
     * to mock behavior in that case, same as before this was shared.
     */
    public static function fromConfig() {
        $apiKeysFile = __DIR__ . '/../config/api-keys-DB.php';
        if (file_exists($apiKeysFile)) {
            require_once $apiKeysFile;
        }

        $environment = (defined('PAYGOLD_ENVIRONMENT') && PAYGOLD_ENVIRONMENT) ? PAYGOLD_ENVIRONMENT : 'TEST';
        $secretConstant = $environment === 'PROD' ? 'PAYGOLD_SECRET_KEY_PROD' : 'PAYGOLD_SECRET_KEY_TEST';

        $configured = defined('PAYGOLD_MERCHANT_CODE') && PAYGOLD_MERCHANT_CODE !== ''
            && defined('PAYGOLD_TERMINAL') && PAYGOLD_TERMINAL !== ''
            && defined($secretConstant) && constant($secretConstant) !== '';

        if (!$configured) {
            return null;
        }

        return new self(PAYGOLD_MERCHANT_CODE, PAYGOLD_TERMINAL, constant($secretConstant), $environment);
    }

    /**
     * Redsys requires Ds_Merchant_Order to be 4-12 characters, the first 4
     * of which must be digits. Not reusable as our own ticket_number
     * (format "#ALM-YYYY-MM-####" doesn't qualify) -- generate a fresh one
     * per payment request and store it (see invoices.paygold_reference).
     */
    public static function generateOrderReference($invoiceId) {
        $numericPrefix = str_pad((string) ($invoiceId % 10000), 4, '0', STR_PAD_LEFT);
        $suffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        return $numericPrefix . $suffix;
    }

    /**
     * Request a PayGold payment link.
     * $order: from generateOrderReference(). $amount: in euros (converted
     * to cents internally, Redsys's required unit). $notificationUrl:
     * where Redsys POSTs the async payment-confirmation callback (server-to-
     * server, see paygold-notify.php -- the authoritative signal). $expiryDate
     * (optional): 'aaaa-mm-dd-HH.MM.ss.sss', defaults to Redsys's own 24h
     * window. $urlOk/$urlKo (optional): where Redsys redirects the customer's
     * own browser after a completed/failed payment -- separate from
     * $notificationUrl, and not guaranteed to fire in any particular order
     * relative to it (the browser redirect can arrive before the webhook has
     * been processed). Returns ['success' => true, 'payment_url', 'raw'] or
     * ['success' => false, 'error'].
     */
    public function requestPaymentLink($order, $amount, $notificationUrl, $expiryDate = null, $urlOk = null, $urlKo = null) {
        $params = [
            'DS_MERCHANT_MERCHANTCODE' => $this->merchantCode,
            'DS_MERCHANT_TERMINAL' => (string) $this->terminal,
            'DS_MERCHANT_TRANSACTIONTYPE' => self::TRANSACTION_TYPE_PAYGOLD,
            'DS_MERCHANT_ORDER' => $order,
            'DS_MERCHANT_AMOUNT' => (string) (int) round($amount * 100),
            'DS_MERCHANT_CURRENCY' => self::CURRENCY_EUR,
            'DS_MERCHANT_MERCHANTURL' => $notificationUrl,
        ];
        if ($expiryDate) {
            $params['DS_MERCHANT_P2F_EXPIRYDATE'] = $expiryDate;
        }
        if ($urlOk) {
            $params['DS_MERCHANT_URLOK'] = $urlOk;
        }
        if ($urlKo) {
            $params['DS_MERCHANT_URLKO'] = $urlKo;
        }

        $merchantParameters = $this->base64UrlEncodeSafe(json_encode($params));
        $signature = $this->computeSignature($merchantParameters, $order);

        $body = [
            'Ds_MerchantParameters' => $merchantParameters,
            'Ds_Signature' => $signature,
            'Ds_SignatureVersion' => 'HMAC_SHA512_V2',
        ];

        $response = $this->post($body);
        return $this->parseResponse($response, $order);
    }

    /**
     * Verify and decode an incoming payment-confirmation notification --
     * Redsys POSTs this to whatever URL was set as Ds_Merchant_MerchantURL
     * when the link was requested, the moment the customer completes (or
     * fails) payment. This is the authoritative signal; a browser reaching
     * a "thank you" page proves nothing on its own (could be interrupted,
     * closed, spoofed). $receivedParams: the incoming request's
     * Ds_MerchantParameters/Ds_Signature, however the caller extracted them
     * (JSON body or form POST -- Redsys's own reference notification
     * example merges both). Returns ['success' => true, 'order',
     * 'approved' => bool, 'raw'] or ['success' => false, 'error'].
     */
    public function verifyNotification($receivedParams) {
        if (!isset($receivedParams['Ds_MerchantParameters'], $receivedParams['Ds_Signature'])) {
            return ['success' => false, 'error' => 'Notificación sin los campos esperados'];
        }

        $decodedJson = $this->base64UrlDecodeSafe($receivedParams['Ds_MerchantParameters']);
        $decoded = json_decode($decodedJson, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'error' => 'No se pudo decodificar la notificación'];
        }

        // Case-insensitive lookup, same reasoning as parseResponse().
        $decodedUpper = [];
        foreach ($decoded as $key => $value) {
            $decodedUpper[strtoupper($key)] = $value;
        }

        $order = $decodedUpper['DS_ORDER'] ?? null;
        if (!$order) {
            return ['success' => false, 'error' => 'Notificación sin número de pedido'];
        }

        $expectedSignature = $this->computeSignature($receivedParams['Ds_MerchantParameters'], $order);
        if (!hash_equals($expectedSignature, $receivedParams['Ds_Signature'])) {
            return ['success' => false, 'error' => 'Firma de la notificación no válida'];
        }

        // Ds_Response 0-99 = authorized, per Redsys's own response-code
        // tables (_mats/redsys-example/.../codResponseDescription) -- "0"
        // is literally "Transacción autorizada para pagos y preautorizaciones".
        // Anything else (or missing) is a decline/error, not a payment.
        $responseCode = $decodedUpper['DS_RESPONSE'] ?? null;
        $approved = $responseCode !== null && ctype_digit((string) $responseCode) && (int) $responseCode < 100;

        return [
            'success' => true,
            'order' => $order,
            'approved' => $approved,
            'raw' => $decoded,
        ];
    }

    private function post($body) {
        $json = json_encode($body);
        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json),
            ],
            CURLOPT_TIMEOUT => 15,
        ]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return ['raw' => $raw, 'http_code' => $httpCode, 'curl_error' => $curlError];
    }

    private function parseResponse($response, $sentOrder) {
        if ($response['curl_error']) {
            return ['success' => false, 'error' => 'Error de conexión con PayGold: ' . $response['curl_error']];
        }

        $data = json_decode($response['raw'], true);
        if (!is_array($data)) {
            return ['success' => false, 'error' => 'Respuesta no válida de PayGold (HTTP ' . $response['http_code'] . ')', 'raw' => $response['raw']];
        }

        if (isset($data['errorCode'])) {
            return ['success' => false, 'error' => 'Error PayGold: ' . $data['errorCode']];
        }

        if (!isset($data['Ds_MerchantParameters'], $data['Ds_Signature'])) {
            return ['success' => false, 'error' => 'Respuesta de PayGold sin los campos esperados', 'raw' => $response['raw']];
        }

        $decodedJson = $this->base64UrlDecodeSafe($data['Ds_MerchantParameters']);
        $decoded = json_decode($decodedJson, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'error' => 'No se pudo decodificar la respuesta de PayGold'];
        }

        // Case-insensitive lookup -- Redsys's own reference library does
        // the same, since observed casing varies (e.g. Ds_Order vs DS_ORDER).
        $decodedUpper = [];
        foreach ($decoded as $key => $value) {
            $decodedUpper[strtoupper($key)] = $value;
        }

        $responseOrder = $decodedUpper['DS_ORDER'] ?? $sentOrder;

        // Verify the response is genuinely from Redsys and unmodified
        // before trusting the payment URL it contains.
        $expectedSignature = $this->computeSignature($data['Ds_MerchantParameters'], $responseOrder);
        if (!hash_equals($expectedSignature, $data['Ds_Signature'])) {
            return ['success' => false, 'error' => 'La firma de la respuesta de PayGold no es válida'];
        }

        $paymentUrl = $decodedUpper['DS_URLPAGO2FASES'] ?? null;
        if (!$paymentUrl) {
            $errorDesc = $decodedUpper['DS_RESPONSE'] ?? $decodedUpper['DS_ERRORCODE'] ?? null;
            return ['success' => false, 'error' => 'PayGold no devolvió un enlace de pago' . ($errorDesc ? " (Ds_Response: {$errorDesc})" : ''), 'raw' => $decoded];
        }

        return ['success' => true, 'payment_url' => $paymentUrl, 'raw' => $decoded];
    }

    private function computeSignature($merchantParametersB64, $order) {
        $orderKey = $this->deriveOrderKey($order);
        $hmac = hash_hmac('sha512', $merchantParametersB64, $orderKey, true);
        return $this->base64UrlEncodeSafe($hmac);
    }

    /**
     * AES-128-CBC-encrypt the order number with a key derived from the
     * merchant secret (first 16 chars, zero-padded -- used as raw key
     * bytes, not base64-decoded), zero IV. Base64 (not URL-safe) encode
     * the result, per Redsys's own reference implementation.
     */
    private function deriveOrderKey($order) {
        $fixedKey = str_pad(substr($this->secretKey, 0, 16), 16, '0');
        $encrypted = openssl_encrypt($order, 'aes-128-cbc', $fixedKey, OPENSSL_RAW_DATA, str_repeat("\0", 16));
        return base64_encode($encrypted);
    }

    private function base64UrlEncodeSafe($data) {
        return str_replace('=', '', strtr(base64_encode($data), '+/', '-_'));
    }

    private function base64UrlDecodeSafe($data) {
        $data = strtr($data, '-_', '+/');
        $pad = strlen($data) % 4;
        if ($pad > 0) {
            $data .= str_repeat('=', 4 - $pad);
        }
        return base64_decode($data);
    }
}
