<?php declare(strict_types=1);

namespace think\pos\provider\kunpeng;

use shali\phpmate\exception\CryptoException;
use think\pos\PosStrategy;
use think\pos\dto; // Alias for base DTO namespace

/**
 * Class KunPengPosStrategy
 *
 * Implements the POS strategy for KunPeng Payment services.
 * This class handles the specifics of KunPeng's API, including:
 * - Encryption and decryption of request/response data (AES + RSA).
 * - Signature generation and verification (SHA256WithRSA).
 * - Mapping of common POS operations to KunPeng's specific API endpoints and data formats.
 * - Processing of asynchronous callback notifications from KunPeng.
 *
 * Configuration for this provider is expected in `config/pos.php` under the 'kunpeng' key.
 * Required config keys:
 *  - `appId`: Partner ID provided by KunPeng.
 *  - `privateKey`: Your RSA private key in PEM format (for signing requests and decrypting data).
 *  - `kunPengPublicKey`: KunPeng's RSA public key in PEM format (for verifying their signature and encrypting data).
 *  - `gateway`: Production API gateway URL.
 *  - `testGateway`: Test API gateway URL.
 *  - `test`: Boolean, true to use testGateway, false for production.
 *
 * @package think\pos\provider\kunpeng
 */
class KunPengPosStrategy extends PosStrategy
{
    /** @var string OpenSSL algorithm identifier for SHA256. */
    private const SIGN_ALGORITHM_OPENSSL = OPENSSL_ALGO_SHA256;

    /** @var string AES cipher method (AES-128-ECB). KunPeng docs specify AES/ECB/PKCS5Padding. */
    private const AES_CIPHER_METHOD = 'AES-128-ECB';

    /** @var int Length of the AES key in characters, as per KunPeng documentation example. */
    private const AES_KEY_LENGTH_CHARS = 16;

    /**
     * Returns the provider name.
     * @return string
     */
    public static function providerName(): string
    {
        return '鲲鹏支付';
    }

    /**
     * Generates a random AES key (16 characters string).
     * @return string
     * @throws \Exception
     */
    private function generateAesKey(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $key = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < self::AES_KEY_LENGTH_CHARS; $i++) {
            $key .= $chars[random_int(0, $max)];
        }
        return $key;
    }

    /**
     * AES ECB PKCS5Padding encryption.
     * @param string $data Data to encrypt (UTF-8 JSON string).
     * @param string $key AES key (16 characters).
     * @return string Base64 encoded encrypted data.
     * @throws CryptoException
     */
    private function aesEncrypt(string $data, string $key): string
    {
        $iv = ''; // ECB mode does not use an IV.
        // PKCS5Padding is essentially PKCS7Padding for AES (16-byte block size).
        // openssl_encrypt handles PKCS7Padding by default when using a block cipher like AES in ECB mode.
        $encrypted = openssl_encrypt($data, self::AES_CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new CryptoException('AES encryption failed: ' . $this->getOpenSSLError());
        }
        return base64_encode($encrypted);
    }

    /**
     * AES ECB PKCS5Padding decryption.
     * @param string $base64Data Base64 encoded data to decrypt.
     * @param string $key AES key (16 characters).
     * @return string Decrypted data (UTF-8 JSON string).
     * @throws CryptoException
     */
    private function aesDecrypt(string $base64Data, string $key): string
    {
        $iv = ''; // ECB mode does not use an IV.
        $encryptedData = base64_decode($base64Data);
        if ($encryptedData === false) {
            throw new CryptoException('AES decryption failed: base64_decode returned false.');
        }
        // openssl_decrypt with OPENSSL_RAW_DATA should handle PKCS7 unpadding.
        $decrypted = openssl_decrypt($encryptedData, self::AES_CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new CryptoException('AES decryption failed: ' . $this->getOpenSSLError());
        }
        // Manual unpadding for PKCS7 (if necessary, though usually handled by OPENSSL_RAW_DATA)
        // $padding = ord($decrypted[strlen($decrypted) - 1]);
        // if ($padding > 0 && $padding <= 16) { // AES block size is 16
        //     $validPadding = true;
        //     for ($i = 0; $i < $padding; $i++) {
        //         if (ord($decrypted[strlen($decrypted) - 1 - $i]) !== $padding) {
        //             $validPadding = false;
        //             break;
        //         }
        //     }
        //     if ($validPadding) {
        //         $decrypted = substr($decrypted, 0, -$padding);
        //     }
        // }
        return $decrypted;
    }


    /**
     * RSA public key encryption (PKCS1Padding).
     * @param string $data Data to encrypt.
     * @param string $publicKeyPem PEM formatted RSA public key (Kunpeng platform public key).
     * @return string Base64 encoded encrypted data.
     * @throws CryptoException
     */
    private function rsaEncrypt(string $data, string $publicKeyPem): string
    {
        $publicKeyResource = openssl_pkey_get_public($this->formatKey($publicKeyPem, 'PUBLIC'));
        if ($publicKeyResource === false) {
            throw new CryptoException("Invalid public key provided for encryption. Error: " . $this->getOpenSSLError());
        }
        $encrypted = '';
        // RSA/ECB/PKCS1Padding in Java corresponds to OPENSSL_PKCS1_PADDING in PHP.
        if (!openssl_public_encrypt($data, $encrypted, $publicKeyResource, OPENSSL_PKCS1_PADDING)) {
            throw new CryptoException('RSA public key encryption failed: ' . $this->getOpenSSLError());
        }
        openssl_free_key($publicKeyResource);
        return base64_encode($encrypted);
    }

    /**
     * RSA private key decryption (PKCS1Padding).
     * @param string $base64Data Base64 encoded data to decrypt.
     * @param string $privateKeyPem PEM formatted RSA private key (our private key).
     * @return string Decrypted data.
     * @throws CryptoException
     */
    private function rsaDecrypt(string $base64Data, string $privateKeyPem): string
    {
        $privateKeyResource = openssl_pkey_get_private($this->formatKey($privateKeyPem, 'PRIVATE'));
        if ($privateKeyResource === false) {
            throw new CryptoException("Invalid private key provided for decryption. Error: " . $this->getOpenSSLError());
        }
        $encryptedData = base64_decode($base64Data);
        if ($encryptedData === false) {
            throw new CryptoException('RSA decryption failed: base64_decode returned false for encrypted data.');
        }
        $decrypted = '';
        if (!openssl_private_decrypt($encryptedData, $decrypted, $privateKeyResource, OPENSSL_PKCS1_PADDING)) {
            throw new CryptoException('RSA private key decryption failed: ' . $this->getOpenSSLError());
        }
        openssl_free_key($privateKeyResource);
        return $decrypted;
    }

    /**
     * Generates a signature (SHA256WithRSA).
     * @param array $data Data array to sign (key-value pairs).
     * @param string $privateKeyPem Our PEM formatted private key.
     * @return string Base64 encoded signature.
     * @throws CryptoException
     */
    private function generateSign(array $data, string $privateKeyPem): string
    {
        $stringToSign = $this->buildSignString($data);
        $this->rawRequest['string_to_sign_for_generation'] = $stringToSign; // Log for debugging

        $privateKeyResource = openssl_pkey_get_private($this->formatKey($privateKeyPem, 'PRIVATE'));
        if ($privateKeyResource === false) {
            throw new CryptoException("Invalid private key for signing. Error: " . $this->getOpenSSLError());
        }
        $signature = '';
        if (!openssl_sign($stringToSign, $signature, $privateKeyResource, self::SIGN_ALGORITHM_OPENSSL)) {
            throw new CryptoException('Signature generation failed: ' . $this->getOpenSSLError());
        }
        openssl_free_key($privateKeyResource);
        return base64_encode($signature);
    }

    /**
     * Verifies a signature (SHA256WithRSA).
     * @param array $data Data array to verify (key-value pairs, 'sign' field will be excluded).
     * @param string $signature Base64 encoded signature.
     * @param string $publicKeyPem Kunpeng platform's PEM formatted public key.
     * @return bool True if signature is valid, false otherwise.
     * @throws CryptoException If an error occurs during verification process.
     */
    private function verifySign(array $data, string $signature, string $publicKeyPem): bool
    {
        $stringToVerify = $this->buildSignString($data);
        $this->rawResponse['string_to_verify_for_verification'] = $stringToVerify; // Log for debugging

        $publicKeyResource = openssl_pkey_get_public($this->formatKey($publicKeyPem, 'PUBLIC'));
        if ($publicKeyResource === false) {
            throw new CryptoException("Invalid public key for verification. Error: " . $this->getOpenSSLError());
        }
        $signatureBytes = base64_decode($signature);
        if ($signatureBytes === false) {
            // This indicates the signature itself is not valid Base64.
            $this->rawResponse['verification_error_detail'] = 'Signature is not valid Base64.';
            return false;
        }
        $result = openssl_verify($stringToVerify, $signatureBytes, $publicKeyResource, self::SIGN_ALGORITHM_OPENSSL);
        openssl_free_key($publicKeyResource);

        if ($result === -1) {
            throw new CryptoException('Error during signature verification: ' . $this->getOpenSSLError());
        }
        return $result === 1;
    }

    /**
     * Builds the string to be signed/verified.
     * Rule: Exclude 'sign' field, sort alphabetically by key, concatenate as "key=value&key=value".
     * Empty or null values are excluded from concatenation.
     * @param array $data
     * @return string
     */
    private function buildSignString(array $data): string
    {
        unset($data['sign']);
        ksort($data);
        $parts = [];
        foreach ($data as $key => $value) {
            if ($value !== null && (string)$value !== '') { // Ensure value is not null and not an empty string
                $parts[] = $key . '=' . $value;
            }
        }
        return implode('&', $parts);
    }

    /**
     * Formats a PEM key string, ensuring it has BEGIN/END markers and correct newlines.
     * @param string $key Raw key string.
     * @param string $type 'PUBLIC' or 'PRIVATE'. For private keys, it assumes PKCS#8 if not specified as 'RSA PRIVATE'.
     * @return string Properly formatted PEM key string.
     */
    private function formatKey(string $key, string $type): string
    {
        $type = strtoupper($type);
        $pemType = $type; // Default PEM type matches $type

        if ($type === 'PRIVATE') {
            // Check if it's already PKCS#1 (RSA PRIVATE KEY) or PKCS#8 (PRIVATE KEY)
            if (strpos($key, '-----BEGIN RSA PRIVATE KEY-----') !== false) {
                $pemType = 'RSA PRIVATE';
            } elseif (strpos($key, '-----BEGIN PRIVATE KEY-----') !== false) {
                $pemType = 'PRIVATE'; // PKCS#8
            } else {
                 // Default to PKCS#8 for "PRIVATE" if no header found. Kunpeng docs show PKCS#8 for their example private key.
                $pemType = 'PRIVATE';
            }
        } elseif ($type === 'PUBLIC' && strpos($key, '-----BEGIN PUBLIC KEY-----') !== false) {
             // Already formatted
        }


        $header = "-----BEGIN {$pemType} KEY-----";
        $footer = "-----END {$pemType} KEY-----";

        if (strpos($key, $header) === false) {
            // Remove existing headers/footers and newlines/spaces, then reformat.
            $strippedKey = preg_replace('/-----(BEGIN|END) (RSA )?(PUBLIC|PRIVATE) KEY-----/', '', $key);
            $strippedKey = str_replace(["\r", "\n", " "], '', $strippedKey);
            return $header . "\n" . chunk_split($strippedKey, 64, "\n") . $footer;
        }
        // If already contains header, assume it's correctly formatted or trust as is.
        return $key;
    }

    private function getOpenSSLError(): string
    {
        $errors = [];
        while ($msg = openssl_error_string()) {
            $errors[] = $msg;
        }
        return empty($errors) ? 'Unknown OpenSSL error' : implode('; ', $errors);
    }

    /**
     * Prepares request data, including encryption and signing.
     * @param array $bizData Business data (key-value pairs).
     * @return array The final request array to be sent to Kunpeng.
     * @throws CryptoException|\Exception
     */
    protected function prepareRequestData(array $bizData): array
    {
        $aesKey = $this->generateAesKey();
        $this->rawRequest['generated_aes_key'] = $aesKey; // Log for debugging

        $bizDataJson = json_encode($bizData, JSON_UNESCAPED_UNICODE);
        if ($bizDataJson === false) {
            throw new \InvalidArgumentException('Failed to JSON encode business data: ' . json_last_error_msg());
        }
        $this->rawRequest['biz_data_json'] = $bizDataJson; // Log for debugging

        $encryptedData = $this->aesEncrypt($bizDataJson, $aesKey);
        $encryptedAesKey = $this->rsaEncrypt($aesKey, $this->config['kunPengPublicKey']);

        $requestParams = [
            'appId' => $this->config['appId'],
            'encryptKey' => $encryptedAesKey,
            'data' => $encryptedData,
            'timestamp' => (string)(int)(microtime(true) * 1000),
        ];

        $requestParams['sign'] = $this->generateSign($requestParams, $this->config['privateKey']);
        $this->rawRequest['final_request_params'] = $requestParams; // Log final params before sending

        return $requestParams;
    }

    /**
     * Processes response data, including signature verification and decryption.
     * @param array $responseData Kunpeng's raw array response data.
     * @return array|null Decrypted business data array, or null on failure.
     * @throws CryptoException
     */
    protected function processResponseData(array $responseData): ?array
    {
        $this->rawResponse['body_array'] = $responseData; // Log received array

        if (!isset($responseData['sign'], $responseData['encryptKey'], $responseData['data'])) {
            $errorMessage = 'Response missing required fields (sign, encryptKey, or data). Response: ' . json_encode($responseData);
            $this->rawResponse['error_detail'] = $errorMessage;
            throw new CryptoException($errorMessage);
        }

        $sign = $responseData['sign'];
        $dataToVerify = $responseData;
        // buildSignString will unset 'sign' from a copy of $dataToVerify or from $dataToVerify itself if passed by reference (depends on PHP version array copy-on-write)
        // To be safe, let's pass a copy to buildSignString if it modifies, or ensure it doesn't.
        // Current buildSignString unsets from the array passed to it.

        if (!$this->verifySign($dataToVerify, $sign, $this->config['kunPengPublicKey'])) {
            $errorMessage = 'Response signature verification failed. String to verify: ' . $this->buildSignString($dataToVerify) . '. Signature: ' . $sign;
            $this->rawResponse['error_detail'] = $errorMessage;
            throw new CryptoException($errorMessage);
        }
        $this->rawResponse['signature_verified'] = true;


        $encryptedAesKey = $responseData['encryptKey'];
        $encryptedData = $responseData['data'];

        $aesKey = $this->rsaDecrypt($encryptedAesKey, $this->config['privateKey']);
        $this->rawResponse['decrypted_aes_key'] = $aesKey; // Log for debugging

        $decryptedDataJson = $this->aesDecrypt($encryptedData, $aesKey);
        $this->rawResponse['decrypted_body_json'] = $decryptedDataJson; // Log decrypted JSON

        $decodedData = json_decode($decryptedDataJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMessage = 'Failed to decode decrypted JSON data: ' . json_last_error_msg() . '. JSON: ' . $decryptedDataJson;
            $this->rawResponse['error_detail'] = $errorMessage;
            throw new CryptoException($errorMessage);
        }
        return $decodedData;
    }

     /**
     * Processes callback notification data.
     * @param string $rawCallbackContent Raw POST callback content (JSON string).
     * @return array|null Decrypted business data array, or null on failure.
     * @throws CryptoException
     */
    protected function processCallbackData(string $rawCallbackContent): ?array
    {
        $this->rawResponse['body'] = $rawCallbackContent; // Store raw callback content

        $callbackData = json_decode($rawCallbackContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMessage = 'Failed to decode callback JSON: ' . json_last_error_msg() . '. Raw content: ' . $rawCallbackContent;
            $this->rawResponse['error_detail'] = $errorMessage;
            throw new CryptoException($errorMessage);
        }
        $this->rawResponse['callback_data_array'] = $callbackData;


        if (!isset($callbackData['sign'], $callbackData['encryptKey'], $callbackData['data'], $callbackData['serviceType'])) {
            $errorMessage = 'Callback missing required fields (sign, encryptKey, data, or serviceType). Callback data: ' . $rawCallbackContent;
            $this->rawResponse['error_detail'] = $errorMessage;
            throw new CryptoException($errorMessage);
        }

        $sign = $callbackData['sign'];
        $dataToVerify = $callbackData;

        if (!$this->verifySign($dataToVerify, $sign, $this->config['kunPengPublicKey'])) {
             $errorMessage = 'Callback signature verification failed. String to verify: ' . $this->buildSignString($dataToVerify) . '. Signature: ' . $sign;
             $this->rawResponse['error_detail'] = $errorMessage;
             throw new CryptoException($errorMessage);
        }
        $this->rawResponse['callback_signature_verified'] = true;

        $encryptedAesKey = $callbackData['encryptKey'];
        $encryptedData = $callbackData['data'];

        $aesKey = $this->rsaDecrypt($encryptedAesKey, $this->config['privateKey']);
        $this->rawResponse['decrypted_aes_key_callback'] = $aesKey; // Log for debugging

        $decryptedDataJson = $this->aesDecrypt($encryptedData, $aesKey);
        $this->rawResponse['decrypted_body_json_callback'] = $decryptedDataJson; // Log decrypted JSON

        $decodedData = json_decode($decryptedDataJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMessage = 'Failed to decode decrypted callback JSON data: ' . json_last_error_msg() . '. JSON: ' . $decryptedDataJson;
            $this->rawResponse['error_detail'] = $errorMessage;
            throw new CryptoException($errorMessage);
        }

        $decodedData['_serviceType'] = $callbackData['serviceType'];
        if (isset($callbackData['appId'])) {
             $decodedData['_appId'] = $callbackData['appId'];
        }
        return $decodedData;
    }

    /**
     * 商户修改费率
     * @param MerchantRequestDto $dto
     * @return PosProviderResponse
     * @throws CryptoException
     * @throws \Exception
     * @see https://gateway/modify/rate (参考文档中的接口地址)
     */
    function setMerchantRate(dto\request\MerchantRequestDto $dto): dto\response\PosProviderResponse
    {
        $this->rawRequest = []; // Reset raw request log

        // 从 DTO 中获取鲲鹏支付所需的费率信息
        // 约定: 费率信息通过 $dto->getOptions()['kunpeng_rates'] 传入
        // kunpeng_rates应该是一个数组，每个元素包含:
        // 'payTypeViewCode' => 'WECHAT',
        // 'rateValue' => '0.38', // 接口文档要求 "0.3" 代表千3，即0.3%。这里需要确认单位。
        //                          文档："百分比类型 例：0.003(千3)传0.3" -> 这句话似乎有歧义或笔误
        //                          通常 "0.3" 表示 0.3%。如果 "0.003(千3)传0.3"，意味着传入的值是实际百分比值的100倍？
        //                          假设文档意图是：如果费率是0.3%，则传入 "0.3"。
        // 'cappingValue' => '20' // 单位：元
        $kunpengRates = $dto->getOptions()['kunpeng_rates'] ?? null;

        if (empty($dto->getDeviceSn())) {
            return dto\response\PosProviderResponse::fail('物料编号 (deviceSn) 不能为空');
        }
        if (empty($kunpengRates) || !is_array($kunpengRates)) {
            return dto\response\PosProviderResponse::fail('鲲鹏支付的费率信息 (options.kunpeng_rates) 未提供或格式不正确');
        }

        $ratePayload = [];
        foreach ($kunpengRates as $rateItem) {
            if (empty($rateItem['payTypeViewCode']) || !isset($rateItem['rateValue'])) {
                return dto\response\PosProviderResponse::fail('费率项缺少 payTypeViewCode 或 rateValue');
            }
            $item = [
                'payTypeViewCode' => (string)$rateItem['payTypeViewCode'],
                'rateValue' => (string)$rateItem['rateValue'], // 按文档是字符串
            ];
            if (isset($rateItem['cappingValue'])) {
                $item['cappingValue'] = (string)$rateItem['cappingValue']; // 按文档是字符串
            }
            $ratePayload[] = $item;
        }

        $bizData = [
            'materialsNo' => $dto->getDeviceSn(), // 文档中是 materialsNo，对应我们的 deviceSn
            'rate' => $ratePayload,
        ];

        // 构造请求 URL
        $gateway = $this->isTestMode() ? $this->config['testGateway'] : $this->config['gateway'];
        // 接口地址：/modify/rate, 文档中请求地址是 /gateway/customer/api/customer, POST /modify/rate
        // 这里的路径需要根据 "鲲pay商户修改和终端类接口文档.2.0.md" Page 6 & 7 确认
        // Page 6: 商户类接口 /gateway/customer/api/customer
        // Page 7: 接口地址：/modify/rate (这是相对路径)
        // 所以完整路径应该是 ${gateway}/gateway/customer/api/customer/modify/rate ? 还是 /gateway/customer/api/modify/rate ?
        // 通常是 base_url + specific_path. 假设是 /gateway/customer/api/customer + /modify/rate
        // 但文档中 "2.2.20 商户修改费率 接口地址：/modify/rate" 更像是直接拼在域名后的路径。
        // 参考 "1.4 平台请求地址 测试环境地址： https://kpla-test.globebill.com"
        // "接⼝类型 地址 ... 商户类接⼝ /gateway/customer/api/customer"
        // "2.2.20 商户修改费率 接⼝地址：/modify/rate"
        // 这看起来 `/modify/rate` 是独立的路径，或者需要和 `/gateway/customer/api/customer` 结合
        // 如果是后者，HttpClient 需要能处理 base_path。
        // 假设文档中 /modify/rate 就是完整路径后缀
        // 修正：根据文档结构，“商户类接口 /gateway/customer/api/customer” 应该是这些接口的统一前缀。
        // 所以请求路径应该是 testGateway + /gateway/customer/api/customer + /modify/rate (如果 customer 是占位符的话)
        // 或者 testGateway + /gateway/customer/api/modify/rate
        // 仔细看文档 Page 6 "设备类接口 /gateway/admin//api/materials Api" (有个双斜杠)
        // "商户类接⼝ /gateway/customer/api/customer"
        // "2.2.20 商户修改费率 接⼝地址：/modify/rate"
        // 最可能的情况是: ${gateway}/gateway/customer/api/customer (作为POST请求的URL)，然后在POST的body中指定实际操作的API名，或者通过某个参数。
        // 但鲲鹏文档中，请求参数里没有指明子路径的字段。
        // 另一个可能是 ${gateway} + /modify/rate (如果文档中的 "接口地址" 是指相对于根网关的路径)
        // 查阅其他类似文档，通常有一个base path，然后是具体的API endpoint。
        // **重要**: 鲲鹏支付的 "接口地址：/modify/rate" 是指相对于 `/gateway/customer/api/customer` 的路径还是相对于根网关的路径？
        // 鉴于文档在 "2.2 商户类接⼝" 下列出 "/gateway/customer/api/customer"，然后在 "2.2.20 ... 接⼝地址：/modify/rate"
        // 看起来 `/modify/rate` 不是独立的。
        // 先假设是 ${gateway_base}/modify/rate，而 ${gateway_base} = ${testGateway}/gateway/customer/api/customer
        // 不，文档Page 6 表格中 "商户类接口" 对应的 "地址" 是 "/gateway/customer/api/customer"
        // 而 "2.2.20 商户修改费率 接口地址：/modify/rate" 是该具体接口的路径。
        // 这意味着请求的URL应该是 $gateway . '/modify/rate'。 (如果gateway本身已经是包含 /gateway/customer/api/customer 的完整路径)
        // 如果 $this->config['gateway'] 是 'https://kpla-test.globebill.com'
        // 则 URL = 'https://kpla-test.globebill.com' + '/modify/rate' -> 感觉不对
        // 更有可能是 'https://kpla-test.globebill.com' + '/gateway/customer/api/customer' (作为 POST URL)
        // 然后在请求体中通过某个参数指定操作类型，或者 /modify/rate 是这个基础URL下的子路径。
        // 鲲鹏文档的结构不是很清晰。
        // “1.2 通用接口参数” 暗示所有请求都发送到一个通用网关，通过特定参数或路径区分业务。
        // 重新审视文档Page 6的表格:
        //   接⼝类型           地址
        //   商户类接⼝        /gateway/customer/api/customer
        //   代付类接⼝        /gateway/profit/api/payment
        //   ...
        // 这表示，所有“商户类接口”都打到 `/gateway/customer/api/customer` 这个路径。
        // 然后具体的接口如 “商户修改费率 /modify/rate” 是如何区分的？
        // 难道是请求体中有一个字段指定，比如 `method: "/modify/rate"`？ 但请求参数中没有这样的字段。
        // 或者是 HTTP PathInfo? e.g. POST /gateway/customer/api/customer/modify/rate
        // 这个是最常见的方式。我将采用这种方式。
        $apiUrl = rtrim($gateway, '/') . '/gateway/customer/api/customer/modify/rate';
        $this->rawRequest['url'] = $apiUrl;


        $requestData = $this->prepareRequestData($bizData);
        $this->rawRequest['params'] = $requestData; // Log prepared request params

        try {
            $httpClient = new \shali\phpmate\http\HttpClient();
            $responseJson = $httpClient->post($apiUrl, json_encode($requestData), ['Content-Type' => 'application/json;charset=UTF-8']);
            $this->rawResponse = array_merge($this->rawResponse, $httpClient->getRawResponse()); // Merge http client raw log
            $this->rawResponse['body_raw_http_response'] = $responseJson; // Log raw http response body
        } catch (\Exception $e) {
            $this->rawResponse['exception'] = $e->getMessage();
            return dto\response\PosProviderResponse::fail(static::providerName() . '请求失败: ' . $e->getMessage());
        }

        $responseData = json_decode($responseJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->rawResponse['error_detail'] = 'JSON decode response failed: ' . json_last_error_msg();
            return dto\response\PosProviderResponse::fail(static::providerName() . '响应解析失败: ' . json_last_error_msg());
        }
        $this->rawResponse['body_decoded_array'] = $responseData; // Log decoded array

        // 检查code是否为"00" (成功)
        if (!isset($responseData['code']) || $responseData['code'] !== '00') {
            $msg = $responseData['msg'] ?? '未知错误';
            $this->rawResponse['error_detail'] = 'Provider error: ' . $msg . ' (Code: ' . ($responseData['code'] ?? 'N/A') . ')';
            return dto\response\PosProviderResponse::fail(static::providerName() . '操作失败: ' . $msg);
        }

        // 如果code为00，则data和encryptKey应该存在并被解密
        try {
            $decryptedBizData = $this->processResponseData($responseData);
            if ($decryptedBizData === null) {
                 // processResponseData 内部已经记录了错误到 $this->rawResponse['error']
                return dto\response\PosProviderResponse::fail(static::providerName() . '响应处理失败: ' . ($this->rawResponse['error_detail'] ?? '解密或验签失败'));
            }
            $this->rawResponse['decrypted_biz_data'] = $decryptedBizData; // Log decrypted business data

            // 根据文档，成功响应的 data 解密后内容：
            // { "status": "审核结果 (TRUE/FALSE/etc)", "reason": "审核原因" }
            if (isset($decryptedBizData['status']) && $decryptedBizData['status'] === 'TRUE') {
                return dto\response\PosProviderResponse::success();
            } else {
                $failMsg = $decryptedBizData['reason'] ?? '费率修改状态未知或失败';
                if (isset($decryptedBizData['status'])) {
                    $failMsg .= ' (Status: ' . $decryptedBizData['status'] . ')';
                }
                 $this->rawResponse['error_detail'] = 'Rate modification failed by provider: ' . $failMsg;
                return dto\response\PosProviderResponse::fail(static::providerName() . ': ' . $failMsg);
            }
        } catch (CryptoException $e) {
            $this->rawResponse['crypto_exception'] = $e->getMessage();
            return dto\response\PosProviderResponse::fail(static::providerName() . '响应处理加密异常: ' . $e->getMessage());
        }
    }


    /**
     * 商户费率查询
     * Fetches merchant rate information from KunPeng.
     * The agentNo required by KunPeng is assumed to be the appId from the configuration.
     *
     * @param dto\request\MerchantRequestDto $dto DTO containing merchantNo.
     * @return dto\response\GetKunPengMerchantRateInfoResponse Response DTO containing the list of rate details if successful,
     *                                                       or error information if failed.
     * @throws CryptoException If cryptographic operations fail.
     * @throws \Exception For other general exceptions during processing.
     */
    public function getMerchantRateInfo(dto\request\MerchantRequestDto $dto): dto\response\GetKunPengMerchantRateInfoResponse
    {
        $this->rawRequest = []; // Reset raw request log
        $response = new dto\response\GetKunPengMerchantRateInfoResponse(); // Use the new DTO

        if (empty($dto->getMerchantNo())) {
            $response->setSuccess(false);
            $response->setErrorMsg('商户号 (merchantNo) 不能为空');
            return $response;
        }

        $agentNo = $this->config['appId'] ?? null;
        if (empty($agentNo)) {
            $response->setSuccess(false);
            $response->setErrorMsg('代理商编号 (appId in config) 未配置');
            return $response;
        }

        $bizData = [
            'customerNo' => $dto->getMerchantNo(),
            'agentNo' => $agentNo,
        ];

        $gateway = $this->isTestMode() ? $this->config['testGateway'] : $this->config['gateway'];
        $apiUrl = rtrim($gateway, '/') . '/gateway/customer/api/customer/query/rate';
        $this->rawRequest['url'] = $apiUrl;

        $requestData = $this->prepareRequestData($bizData);
        $this->rawRequest['params'] = $requestData;

        try {
            $httpClient = new \shali\phpmate\http\HttpClient();
            $responseJson = $httpClient->post($apiUrl, json_encode($requestData), ['Content-Type' => 'application/json;charset=UTF-8']);
            $this->rawResponse = array_merge($this->rawResponse, $httpClient->getRawResponse());
            $this->rawResponse['body_raw_http_response'] = $responseJson;
        } catch (\Exception $e) {
            $this->rawResponse['exception'] = $e->getMessage();
            $response->setSuccess(false);
            $response->setErrorMsg(static::providerName() . '请求失败: ' . $e->getMessage());
            return $response;
        }

        $responseData = json_decode($responseJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->rawResponse['error_detail'] = 'JSON decode response failed: ' . json_last_error_msg();
            $response->setSuccess(false);
            $response->setErrorMsg(static::providerName() . '响应解析失败: ' . json_last_error_msg());
            return $response;
        }
        $this->rawResponse['body_decoded_array'] = $responseData;

        if (!isset($responseData['code']) || $responseData['code'] !== '00') {
            $msg = $responseData['msg'] ?? '未知错误';
            $this->rawResponse['error_detail'] = 'Provider error: ' . $msg . ' (Code: ' . ($responseData['code'] ?? 'N/A') . ')';
            $response->setSuccess(false);
            $response->setErrorMsg(static::providerName() . '操作失败: ' . $msg);
            return $response;
        }

        try {
            $decryptedBizData = $this->processResponseData($responseData);
            if ($decryptedBizData === null) {
                $response->setSuccess(false);
                $response->setErrorMsg(static::providerName() . '响应处理失败: ' . ($this->rawResponse['error_detail'] ?? '解密或验签失败'));
                return $response;
            }
            $this->rawResponse['decrypted_biz_data'] = $decryptedBizData;

            $response->setSuccess(true);
            $response->setRateDetails($decryptedBizData); // Set the rate details using the new DTO's method
            return $response;

        } catch (CryptoException $e) {
            $this->rawResponse['crypto_exception'] = $e->getMessage();
            $response->setSuccess(false);
            $response->setErrorMsg(static::providerName() . '响应处理加密异常: ' . $e->getMessage());
            return $response;
        }
    }

    /**
     * 商户绑定终端
     * @param dto\request\MerchantRequestDto $merchantRequestDto
     * @param dto\request\PosRequestDto $posRequestDto
     * @return dto\response\PosProviderResponse
     * @throws CryptoException
     * @throws \Exception
     */
    function bindPos(dto\request\MerchantRequestDto $merchantRequestDto, dto\request\PosRequestDto $posRequestDto): dto\response\PosProviderResponse
    {
        return $this->commonMaterialsOperate($merchantRequestDto->getMerchantNo(), $posRequestDto->getDeviceSn(), 'BIND');
    }

    /**
     * 商户解绑终端
     * @param dto\request\MerchantRequestDto $merchantRequestDto
     * @param dto\request\PosRequestDto $posRequestDto
     * @return dto\response\PosProviderResponse
     * @throws CryptoException
     * @throws \Exception
     */
    function unbindPos(dto\request\MerchantRequestDto $merchantRequestDto, dto\request\PosRequestDto $posRequestDto): dto\response\PosProviderResponse
    {
        return $this->commonMaterialsOperate($merchantRequestDto->getMerchantNo(), $posRequestDto->getDeviceSn(), 'UN_BIND');
    }

    /**
     * 通用终端操作 (绑定/解绑)
     * @param string $merchantNo 商户号
     * @param string $deviceSn 设备SN
     * @param string $operationType 操作类型 ('BIND' or 'UN_BIND')
     * @return dto\response\PosProviderResponse
     * @throws CryptoException
     * @throws \Exception
     */
    private function commonMaterialsOperate(string $merchantNo, string $deviceSn, string $operationType): dto\response\PosProviderResponse
    {
        $this->rawRequest = [];

        if (empty($merchantNo)) {
            return dto\response\PosProviderResponse::fail('商户号 (merchantNo) 不能为空');
        }
        if (empty($deviceSn)) {
            return dto\response\PosProviderResponse::fail('设备SN (deviceSn) 不能为空');
        }

        $agentNo = $this->config['appId'] ?? null;
        if (empty($agentNo)) {
            return dto\response\PosProviderResponse::fail('代理商编号 (appId in config) 未配置');
        }

        $bizData = [
            'customerNo' => $merchantNo,
            'agentNo' => $agentNo,
            'materialsNo' => $deviceSn,
            'materialsOperate' => $operationType,
            // 'oldMaterialsNo' => $oldDeviceSn, // 如果需要支持换绑，从options传入
        ];

        $gateway = $this->isTestMode() ? $this->config['testGateway'] : $this->config['gateway'];
        // URL: /gateway/admin/api/materialsApi/materialsOperate  (注意文档中 /gateway/admin//api/materials Api 有个双斜杠，应该是 /gateway/admin/api/materialsApi)
        // 修正：文档 Page 6 "设备类接口 /gateway/admin//api/materials Api" -> 假设是 /gateway/admin/api/materialsApi
        // 然后 Page 11 "2.3.2终端绑定解绑 接⼝地址：/materialsOperate"
        // 所以完整路径是 $gateway . /gateway/admin/api/materialsApi/materialsOperate
        $apiUrl = rtrim($gateway, '/') . '/gateway/admin/api/materialsApi/materialsOperate';
        $this->rawRequest['url'] = $apiUrl;

        $requestData = $this->prepareRequestData($bizData);
        $this->rawRequest['params'] = $requestData;

        try {
            $httpClient = new \shali\phpmate\http\HttpClient();
            $responseJson = $httpClient->post($apiUrl, json_encode($requestData), ['Content-Type' => 'application/json;charset=UTF-8']);
            $this->rawResponse = array_merge($this->rawResponse, $httpClient->getRawResponse());
            $this->rawResponse['body_raw_http_response'] = $responseJson;
        } catch (\Exception $e) {
            $this->rawResponse['exception'] = $e->getMessage();
            return dto\response\PosProviderResponse::fail(static::providerName() . '请求失败: ' . $e->getMessage());
        }

        $responseData = json_decode($responseJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->rawResponse['error_detail'] = 'JSON decode response failed: ' . json_last_error_msg();
            return dto\response\PosProviderResponse::fail(static::providerName() . '响应解析失败: ' . json_last_error_msg());
        }
        $this->rawResponse['body_decoded_array'] = $responseData;

        if (!isset($responseData['code']) || $responseData['code'] !== '00') {
            $msg = $responseData['msg'] ?? '未知错误';
            $this->rawResponse['error_detail'] = 'Provider error: ' . $msg . ' (Code: ' . ($responseData['code'] ?? 'N/A') . ')';
            return dto\response\PosProviderResponse::fail(static::providerName() . '操作失败: ' . $msg);
        }

        // 对于绑定/解绑操作，如果code='00'，我们通常认为操作成功。
        // 文档没有明确说明成功时data的内容，所以这里不解密data。
        // 如果需要根据data内容判断，则需要解密：
        // try {
        //     $decryptedBizData = $this->processResponseData($responseData);
        //     $this->rawResponse['decrypted_biz_data'] = $decryptedBizData;
        //     // 根据 $decryptedBizData 中的具体状态判断是否真的成功
        // } catch (CryptoException $e) { ... }

        return dto\response\PosProviderResponse::success();
    }

    /**
     * 终端变更政策
     * @param dto\request\PosRequestDto $dto Input DTO. Specific params for KunPeng should be in $dto->getOptions()['kunpeng_policy_params']
     *                                       'kunpeng_policy_params' => [
     *                                           'migrateType' => 'ORDER' | 'NO_ORDER',
     *                                           'materialsNoList' => ['SN1', 'SN2'], // or ['START_SN', 'END_SN'] if ORDER
     *                                           'policyId' => 123
     *                                       ]
     * @return dto\response\PosProviderResponse
     * @throws CryptoException
     * @throws \Exception
     */
    public function updatePosPolicy(dto\request\PosRequestDto $dto): dto\response\PosProviderResponse
    {
        $this->rawRequest = [];

        $policyParams = $dto->getOptions()['kunpeng_policy_params'] ?? null;

        if (empty($policyParams) || !is_array($policyParams)) {
            return dto\response\PosProviderResponse::fail('鲲鹏支付的终端变更政策参数 (options.kunpeng_policy_params) 未提供或格式不正确');
        }

        $requiredKeys = ['migrateType', 'materialsNoList', 'policyId'];
        foreach ($requiredKeys as $key) {
            if (!isset($policyParams[$key])) {
                return dto\response\PosProviderResponse::fail("终端变更政策参数缺少必需字段: {$key}");
            }
        }

        $agentNo = $this->config['appId'] ?? null;
        if (empty($agentNo)) {
            return dto\response\PosProviderResponse::fail('代理商编号 (appId in config) 未配置');
        }

        $bizData = [
            'migrateType' => $policyParams['migrateType'],
            'agentNo' => $agentNo,
            'materialsNoList' => $policyParams['materialsNoList'], // This should be an array
            'policyId' => $policyParams['policyId'],
        ];

        $gateway = $this->isTestMode() ? $this->config['testGateway'] : $this->config['gateway'];
        // URL: /gateway/admin/api/materialsApi/updateMaterialsPolicy
        $apiUrl = rtrim($gateway, '/') . '/gateway/admin/api/materialsApi/updateMaterialsPolicy';
        $this->rawRequest['url'] = $apiUrl;

        $requestData = $this->prepareRequestData($bizData);
        $this->rawRequest['params'] = $requestData;

        try {
            $httpClient = new \shali\phpmate\http\HttpClient();
            $responseJson = $httpClient->post($apiUrl, json_encode($requestData), ['Content-Type' => 'application/json;charset=UTF-8']);
            $this->rawResponse = array_merge($this->rawResponse, $httpClient->getRawResponse());
            $this->rawResponse['body_raw_http_response'] = $responseJson;
        } catch (\Exception $e) {
            $this->rawResponse['exception'] = $e->getMessage();
            return dto\response\PosProviderResponse::fail(static::providerName() . '请求失败: ' . $e->getMessage());
        }

        $responseData = json_decode($responseJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->rawResponse['error_detail'] = 'JSON decode response failed: ' . json_last_error_msg();
            return dto\response\PosProviderResponse::fail(static::providerName() . '响应解析失败: ' . json_last_error_msg());
        }
        $this->rawResponse['body_decoded_array'] = $responseData;

        if (!isset($responseData['code']) || $responseData['code'] !== '00') {
            $msg = $responseData['msg'] ?? '未知错误';
            $this->rawResponse['error_detail'] = 'Provider error: ' . $msg . ' (Code: ' . ($responseData['code'] ?? 'N/A') . ')';
            return dto\response\PosProviderResponse::fail(static::providerName() . '操作失败: ' . $msg);
        }

        // Similar to bind/unbind, assuming code='00' means success as data content for success is not specified.
        return dto\response\PosProviderResponse::success();
    }

    /**
     * pos 平台回调，统一处理
     * @param string $content post body content
     * @return dto\request\CallbackRequest
     * @throws CryptoException
     * @throws \Exception
     */
    function handleCallback(string $content): dto\request\CallbackRequest
    {
        $this->rawRequest = []; // Reset for callback context
        $this->rawResponse = ['body' => $content]; // Store raw callback content for logging

        try {
            $decryptedData = $this->processCallbackData($content);
        } catch (CryptoException $e) {
            $this->rawResponse['callback_processing_error'] = 'CryptoException: ' . $e->getMessage();
            // For callbacks, if decryption or signature check fails, we usually return a failure DTO.
            // The caller (e.g., a controller) would then decide what HTTP response to send.
            // It might still need to send "OK" to stop Kunpeng from resending, but log the error.
            $errorResponse = new dto\request\CallbackRequest();
            $errorResponse->setSuccess(false);
            $errorResponse->setErrorCode('CALLBACK_ERROR');
            $errorResponse->setErrorMsg(static::providerName() . '回调处理失败: ' . $e->getMessage());
            $errorResponse->setRawData($content);
            return $errorResponse;
        }

        if ($decryptedData === null || !isset($decryptedData['_serviceType'])) {
            $this->rawResponse['callback_processing_error'] = 'Failed to process callback or missing serviceType.';
            $errorResponse = new dto\request\CallbackRequest();
            $errorResponse->setSuccess(false);
            $errorResponse->setErrorCode('CALLBACK_ERROR');
            $errorResponse->setErrorMsg(static::providerName() . '回调数据处理失败或缺少serviceType');
            $errorResponse->setRawData($content);
            return $errorResponse;
        }

        $serviceType = $decryptedData['_serviceType'];
        $this->rawResponse['service_type'] = $serviceType;
        $this->rawResponse['decrypted_callback_data'] = $decryptedData;


        switch ($serviceType) {
            case 'PAY_ORDER': // 交易订单
                return $this->handlePayOrderCallback($decryptedData, $content);
            case 'ACTIVATION': // 激活达标
                return $this->handleActivationCallback($decryptedData, $content);
            case 'CUSTOMER_REGISTER': // 商户进件
                return $this->handleCustomerRegisterCallback($decryptedData, $content);
            case 'SIM_STOP_ORDER': // 流量卡止付订单
                 return $this->handleSimStopOrderCallback($decryptedData, $content);
            case 'MATERIALS_NOTACTIVATION_NOTIFY': // 未伪激活
                 return $this->handleMaterialsNotActivationNotifyCallback($decryptedData, $content);
            case 'DEPOSIT_STOP_ORDER': // 押金止付订单
                 return $this->handleDepositStopOrderCallback($decryptedData, $content);
            // case 'TERMINAL_BIND_UNBIND': // 假设的serviceType，如果文档后续明确
            //     return $this->handleTerminalBindUnbindCallback($decryptedData, $content);
            default:
                $this->rawResponse['unknown_service_type'] = $serviceType;
                // For unknown service types, we can return a generic success DTO but log it,
                // or a failure DTO if strict handling is required.
                // Returning a generic success DTO to acknowledge receipt but marking it internally.
                $unknownResponse = new dto\request\CallbackRequest();
                $unknownResponse->setSuccess(true); // Acknowledge to stop resends
                $unknownResponse->setErrorCode('UNKNOWN_SERVICE_TYPE');
                $unknownResponse->setErrorMsg(static::providerName() . ' 未知回调服务类型: ' . $serviceType);
                $unknownResponse->setRawData($content);
                $unknownResponse->setOptions(['original_data' => $decryptedData]); // pass through data
                return $unknownResponse;
        }
    }

    /**
     * 处理交易订单回调 (PAY_ORDER)
     * @param array $data 解密后的回调数据
     * @param string $rawContent 原始回调内容
     * @return dto\request\callback\PosTransCallbackRequest
     */
    private function handlePayOrderCallback(array $data, string $rawContent): dto\request\callback\PosTransCallbackRequest
    {
        $callbackRequest = new dto\request\callback\PosTransCallbackRequest();
        $callbackRequest->setRawData($rawContent);
        $callbackRequest->setOptions(['kunpeng_decrypted_data' => $data]); // Store full decrypted data

        try {
            $callbackRequest->setMerchantNo($data['merchantNo'] ?? '');
            $callbackRequest->setMerchantName($data['merchantName'] ?? '');
            $callbackRequest->setDeviceSn($data['deviceNo'] ?? ''); // 鲲鹏是 deviceNo
            $callbackRequest->setTransNo($data['orderNo'] ?? '');   // 鲲鹏是 orderNo

            if (isset($data['amount'])) {
                $callbackRequest->setAmount(\shali\phpmate\util\Money::valueOfYuan($data['amount']));
            }
            if (isset($data['fee'])) {
                $callbackRequest->setFee(\shali\phpmate\util\Money::valueOfYuan($data['fee']));
            }
            // 鲲鹏 specific: fixedValue (笔数费/秒到费)
            if (isset($data['fixedValue'])) {
                 // PosTransCallbackRequest doesn't have a direct field for fixedValue.
                 // Could be part of fee, or a new field, or in options.
                 // Let's assume it's part of the total fee or a component of withdrawFee if applicable.
                 // Or, if it's separate, it might need to be handled differently.
                 // For now, let's add it to options if not directly mappable.
                 $callbackRequest->addOption('kunpeng_fixedValue', \shali\phpmate\util\Money::valueOfYuan($data['fixedValue']));
            }

            if (isset($data['feeRate'])) { // 鲲鹏是 feeRate (不带百分号)
                // Rate expects decimal, e.g., 0.006 for 0.6%. If kunpeng sends "0.6" for 0.6%
                $rateValue = floatval($data['feeRate']) / 100;
                $callbackRequest->setRate(\shali\phpmate\util\Rate::valueOf($rateValue));
            }
             // Kunpeng specific: baseRate, agentRaisePriceRate
            if (isset($data['baseRate'])) {
                $callbackRequest->addOption('kunpeng_baseRate', \shali\phpmate\util\Rate::valueOf(floatval($data['baseRate']) / 100));
            }
            if (isset($data['agentRaisePriceRate'])) {
                 $callbackRequest->addOption('kunpeng_agentRaisePriceRate', \shali\phpmate\util\Rate::valueOf(floatval($data['agentRaisePriceRate']) / 100));
            }


            if (isset($data['successTime'])) { // yyyyMMddHHmmss
                $callbackRequest->setSuccessDateTime(\shali\phpmate\core\date\LocalDateTime::parse($data['successTime'], 'yyyyMMddHHmmss'));
            }

            // Mapping Kunpeng payTypeCode to internal PaymentType and TransOrderType
            // This is a simplified mapping and might need to be more robust
            $payTypeCode = $data['payTypeCode'] ?? '';
            $orderType = \think\pos\constant\TransOrderType::GENERAL; // Default
            $paymentType = \think\pos\constant\PaymentType::UNKNOWN;

            // Example mapping (needs to be comprehensive based on Kunpeng's payTypeCode list)
            if (strpos($payTypeCode, 'WECHAT') !== false) $paymentType = \think\pos\constant\PaymentType::WEIXIN;
            elseif (strpos($payTypeCode, 'ALIPAY') !== false) $paymentType = \think\pos\constant\PaymentType::ALIPAY;
            elseif (strpos($payTypeCode, 'UNIONPAY') !== false || strpos($payTypeCode, 'POS_') !== false) {
                $paymentType = \think\pos\constant\PaymentType::BANK_CARD;
                if (strpos($payTypeCode, '_CC') !== false) {
                    // Credit card
                } elseif (strpos($payTypeCode, '_DC') !== false) {
                    // Debit card
                }
            }
             elseif (strpos($payTypeCode, 'JD_PLI') !== false) $paymentType = \think\pos\constant\PaymentType::JD_BAITIAO;


            $callbackRequest->setOrderType($orderType);
            $callbackRequest->setPaymentType($paymentType);
            $callbackRequest->setStatus(\think\pos\constant\TransOrderStatus::SUCCESS); // Assuming PAY_ORDER notification means success
            $callbackRequest->setSuccess(true);

        } catch (\Exception $e) {
            $callbackRequest->setSuccess(false);
            $callbackRequest->setErrorCode('DTO_MAPPING_ERROR');
            $callbackRequest->setErrorMsg('交易订单回调数据映射失败: ' . $e->getMessage());
        }
        return $callbackRequest;
    }

    /**
     * 处理激活达标回调 (ACTIVATION)
     * @param array $data 解密后的回调数据
     * @param string $rawContent 原始回调内容
     * @return dto\request\callback\PosActivateCallbackRequest
     */
    private function handleActivationCallback(array $data, string $rawContent): dto\request\callback\PosActivateCallbackRequest
    {
        $callbackRequest = new dto\request\callback\PosActivateCallbackRequest();
        $callbackRequest->setRawData($rawContent);
        $callbackRequest->setOptions(['kunpeng_decrypted_data' => $data]);

        try {
            $callbackRequest->setMerchantNo($data['merchantNo'] ?? '');
            $callbackRequest->setMerchantName($data['merchantName'] ?? '');
            $callbackRequest->setDeviceSn($data['deviceNo'] ?? ''); // 鲲鹏是 deviceNo

            if (isset($data['activationTime'])) { // yyyyMMddHHmmss
                $callbackRequest->setActivateDateTime(\shali\phpmate\core\date\LocalDateTime::parse($data['activationTime'], 'yyyyMMddHHmmss'));
            }

            // Kunpeng activationType: FINISHED, FINISHED_OVER, UN_ACTIVATION_ACHIEVE
            // Map to internal PosStatus if possible, or store in options.
            // For simplicity, if we receive an ACTIVATION callback, we assume it's activated.
            $callbackRequest->setStatus(\think\pos\constant\PosStatus::ACTIVATED);
            $callbackRequest->addOption('kunpeng_activationType', $data['activationType'] ?? '');
            $callbackRequest->addOption('kunpeng_terNo', $data['terNo'] ?? '');
            $callbackRequest->addOption('kunpeng_materialsType', $data['materialsType'] ?? '');

            $callbackRequest->setSuccess(true);
        } catch (\Exception $e) {
            $callbackRequest->setSuccess(false);
            $callbackRequest->setErrorCode('DTO_MAPPING_ERROR');
            $callbackRequest->setErrorMsg('激活达标回调数据映射失败: ' . $e->getMessage());
        }
        return $callbackRequest;
    }

    /**
     * 处理商户进件回调 (CUSTOMER_REGISTER)
     * @param array $data 解密后的回调数据
     * @param string $rawContent 原始回调内容
     * @return dto\request\callback\MerchantRegisterCallbackRequest
     */
    private function handleCustomerRegisterCallback(array $data, string $rawContent): dto\request\callback\MerchantRegisterCallbackRequest
    {
        $callbackRequest = new dto\request\callback\MerchantRegisterCallbackRequest();
        $callbackRequest->setRawData($rawContent);
        $callbackRequest->setOptions(['kunpeng_decrypted_data' => $data]);

        try {
            $callbackRequest->setMerchantNo($data['merchantNo'] ?? '');
            $callbackRequest->setMerchantName($data['merchantName'] ?? ''); // 鲲鹏明文商户名
            $callbackRequest->setPhone($data['phoneNo'] ?? '');     // 鲲鹏是脱敏手机号
            // MerchantRegisterCallbackRequest uses MerchantTrait which has more fields.
            // Map what's available from Kunpeng.
            // $callbackRequest->setShortName(); // Kunpeng doesn't provide shortName directly
            // $callbackRequest->setDeviceSn($data['deviceNo'] ?? ''); // deviceNo is present in Kunpeng CUSTOMER_REGISTER
            // $callbackRequest->set... other fields from MerchantTrait

            // Kunpeng specific fields:
            $callbackRequest->addOption('kunpeng_idCard', $data['idCard'] ?? ''); // 脱敏身份证号
            $callbackRequest->addOption('kunpeng_legalName', $data['legalName'] ?? ''); // 脱敏法人姓名
            $callbackRequest->addOption('kunpeng_deviceNo', $data['deviceNo'] ?? '');
            $callbackRequest->addOption('kunpeng_customerRole', $data['customerRole'] ?? ''); // MAIN or SON

            if (isset($data['createTime'])) { // yyyyMMddHHmmss
                // MerchantRegisterCallbackRequest doesn't have createTime, store in options
                $callbackRequest->addOption('kunpeng_createTime', \shali\phpmate\core\date\LocalDateTime::parse($data['createTime'], 'yyyyMMddHHmmss'));
            }
            // Assuming CUSTOMER_REGISTER means merchant is now active/registered.
            $callbackRequest->setStatus(\think\pos\constant\MerchantStatus::ACTIVE);
            $callbackRequest->setSuccess(true);

        } catch (\Exception $e) {
            $callbackRequest->setSuccess(false);
            $callbackRequest->setErrorCode('DTO_MAPPING_ERROR');
            $callbackRequest->setErrorMsg('商户进件回调数据映射失败: ' . $e->getMessage());
        }
        return $callbackRequest;
    }

    /**
     * 处理流量卡止付回调 (SERVICE_TYPE: SIM_STOP_ORDER)
     * 使用从回调中获取的数据填充 KunPengSimStopOrderCallbackRequest DTO。
     *
     * @param array $data 解密后的回调业务数据。
     * @param string $rawContent 原始回调内容字符串，用于日志记录或参考。
     * @return dto\request\callback\KunPengSimStopOrderCallbackRequest 填充完毕的回调请求DTO。
     */
    private function handleSimStopOrderCallback(array $data, string $rawContent): dto\request\callback\KunPengSimStopOrderCallbackRequest
    {
        $callbackRequest = new dto\request\callback\KunPengSimStopOrderCallbackRequest();
        $callbackRequest->setRawData($rawContent);
        $callbackRequest->setOptions(['kunpeng_decrypted_data' => $data]); // Keep original decrypted data if needed

        try {
            $callbackRequest->setMerchantNo($data['merchantNo'] ?? '');
            $callbackRequest->setMerchantName($data['merchantName'] ?? '');
            $callbackRequest->setDeviceNo($data['deviceNo'] ?? '');
            $callbackRequest->setMaterialsType($data['materialsType'] ?? '');
            $callbackRequest->setPolicyId($data['policyId'] ?? '');
            $callbackRequest->setOrderNo($data['orderNo'] ?? '');
            if (isset($data['amount'])) {
                $callbackRequest->setAmount(\shali\phpmate\util\Money::valueOfYuan($data['amount']));
            }
            $callbackRequest->setNum(isset($data['num']) ? intval($data['num']) : 0);
            if (isset($data['successTime'])) {
                $callbackRequest->setSuccessTime(\shali\phpmate\core\date\LocalDateTime::parse($data['successTime'], 'yyyyMMddHHmmss'));
            }
            $callbackRequest->setSuccess(true);
            $callbackRequest->setProviderMessage("流量卡止付通知处理完成");
        } catch (\Exception $e) {
            $callbackRequest->setSuccess(false);
            $callbackRequest->setErrorCode('DTO_MAPPING_ERROR');
            $callbackRequest->setErrorMsg('流量卡止付回调数据映射失败: ' . $e->getMessage());
        }
        return $callbackRequest;
    }

    /**
     * 处理未伪激活通知 (SERVICE_TYPE: MATERIALS_NOTACTIVATION_NOTIFY)
     * 使用从回调中获取的数据填充 KunPengNotActivationCallbackRequest DTO。
     *
     * @param array $data 解密后的回调业务数据。
     * @param string $rawContent 原始回调内容字符串，用于日志记录或参考。
     * @return dto\request\callback\KunPengNotActivationCallbackRequest 填充完毕的回调请求DTO。
     */
    private function handleMaterialsNotActivationNotifyCallback(array $data, string $rawContent): dto\request\callback\KunPengNotActivationCallbackRequest
    {
        $callbackRequest = new dto\request\callback\KunPengNotActivationCallbackRequest();
        $callbackRequest->setRawData($rawContent);
        $callbackRequest->setOptions(['kunpeng_decrypted_data' => $data]);

        try {
            $callbackRequest->setMerchantNo($data['merchantNo'] ?? '');
            $callbackRequest->setMerchantName($data['merchantName'] ?? '');
            $callbackRequest->setDeviceNo($data['deviceNo'] ?? '');
            $callbackRequest->setStatus($data['status'] ?? ''); // NOT_ACTIVATION or PSEUDO_ACTIVATION
            if (isset($data['activationTime'])) { // yyyyMMddHHmmss
                $callbackRequest->setActivationTime(\shali\phpmate\core\date\LocalDateTime::parse($data['activationTime'], 'yyyyMMddHHmmss'));
            }
            $callbackRequest->setSuccess(true);
            $callbackRequest->setProviderMessage("未伪激活通知处理完成");
        } catch (\Exception $e) {
            $callbackRequest->setSuccess(false);
            $callbackRequest->setErrorCode('DTO_MAPPING_ERROR');
            $callbackRequest->setErrorMsg('未伪激活回调数据映射失败: ' . $e->getMessage());
        }
        return $callbackRequest;
    }

    /**
     * 处理押金止付回调 (SERVICE_TYPE: DEPOSIT_STOP_ORDER)
     * 使用从回调中获取的数据填充 KunPengDepositStopOrderCallbackRequest DTO。
     *
     * @param array $data 解密后的回调业务数据。
     * @param string $rawContent 原始回调内容字符串，用于日志记录或参考。
     * @return dto\request\callback\KunPengDepositStopOrderCallbackRequest 填充完毕的回调请求DTO。
     */
    private function handleDepositStopOrderCallback(array $data, string $rawContent): dto\request\callback\KunPengDepositStopOrderCallbackRequest
    {
        $callbackRequest = new dto\request\callback\KunPengDepositStopOrderCallbackRequest();
        $callbackRequest->setRawData($rawContent);
        $callbackRequest->setOptions(['kunpeng_decrypted_data' => $data]);

        try {
            $callbackRequest->setMerchantNo($data['merchantNo'] ?? '');
            $callbackRequest->setMerchantName($data['merchantName'] ?? '');
            $callbackRequest->setDeviceNo($data['deviceNo'] ?? '');
            $callbackRequest->setMaterialsType($data['materialsType'] ?? '');
            $callbackRequest->setPolicyId($data['policyId'] ?? '');
            $callbackRequest->setOrderNo($data['orderNo'] ?? '');
            if (isset($data['amount'])) {
                $callbackRequest->setAmount(\shali\phpmate\util\Money::valueOfYuan($data['amount']));
            }
            $callbackRequest->setStatus($data['status'] ?? ''); // SUCCESS, FAIL, CLOSED
            if (isset($data['successTime'])) {
                $callbackRequest->setSuccessTime(\shali\phpmate\core\date\LocalDateTime::parse($data['successTime'], 'yyyyMMddHHmmss'));
            }
            $callbackRequest->setSuccess(true);
            $callbackRequest->setProviderMessage("押金止付通知处理完成");
        } catch (\Exception $e) {
            $callbackRequest->setSuccess(false);
            $callbackRequest->setErrorCode('DTO_MAPPING_ERROR');
            $callbackRequest->setErrorMsg('押金止付回调数据映射失败: ' . $e->getMessage());
        }
        return $callbackRequest;
    }
}
