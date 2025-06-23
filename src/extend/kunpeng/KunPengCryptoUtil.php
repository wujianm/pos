<?php declare(strict_types=1);

namespace think\pos\extend\kunpeng;

use shali\phpmate\exception\CryptoException;

/**
 * Class KunPengCryptoUtil
 * 鲲鹏支付加密、签名、验签相关的工具类。
 * 此类封装了与鲲鹏支付接口交互时所需的AES、RSA加解密及签名验签的底层实现。
 *
 * @package think\pos\extend\kunpeng
 */
class KunPengCryptoUtil
{
    /** @var string OpenSSL算法标识符，用于SHA256。 */
    private const SIGN_ALGORITHM_OPENSSL = OPENSSL_ALGO_SHA256;
    /** @var string AES加密方法 (AES-128-ECB)。鲲鹏文档指定为AES/ECB/PKCS5Padding。 */
    private const AES_CIPHER_METHOD = 'AES-128-ECB';
    /** @var int AES密钥的字符长度，根据鲲鹏文档示例。 */
    private const AES_KEY_LENGTH_CHARS = 16;

    /**
     * 生成一个16个字符的随机AES密钥字符串。
     * @return string 生成的AES密钥。
     * @throws \Exception 如果random_int无法收集足够的随机性。
     */
    public static function generateAesKey(): string
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
     * AES ECB PKCS5Padding 加密。
     * @param string $data 待加密的数据 (UTF-8 JSON 字符串)。
     * @param string $key AES密钥 (16个字符)。
     * @return string Base64编码后的加密数据。
     * @throws CryptoException 如果加密失败。
     */
    public static function aesEncrypt(string $data, string $key): string
    {
        $iv = ''; // ECB模式不需要IV。
        $encrypted = openssl_encrypt($data, self::AES_CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new CryptoException('AES加密失败: ' . self::getOpenSSLError());
        }
        return base64_encode($encrypted);
    }

    /**
     * AES ECB PKCS5Padding 解密。
     * @param string $base64Data Base64编码的待解密数据。
     * @param string $key AES密钥 (16个字符)。
     * @return string 解密后的数据 (UTF-8 JSON 字符串)。
     * @throws CryptoException 如果解密失败。
     */
    public static function aesDecrypt(string $base64Data, string $key): string
    {
        $iv = ''; // ECB模式不需要IV。
        $encryptedData = base64_decode($base64Data);
        if ($encryptedData === false) {
            throw new CryptoException('AES解密失败: base64_decode返回false。');
        }
        $decrypted = openssl_decrypt($encryptedData, self::AES_CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new CryptoException('AES解密失败: ' . self::getOpenSSLError());
        }
        return $decrypted;
    }

    /**
     * RSA公钥加密 (PKCS1Padding)。
     * 用于加密AES密钥等敏感数据，使用接收方的公钥。
     *
     * @param string $data 待加密的数据。
     * @param string $publicKeyPem PEM格式的RSA公钥。
     * @return string Base64编码后的加密数据。
     * @throws CryptoException 如果公钥无效或加密失败。
     */
    public static function rsaEncryptWithPublicKey(string $data, string $publicKeyPem): string
    {
        $publicKeyResource = openssl_pkey_get_public(self::formatKey($publicKeyPem, 'PUBLIC'));
        if ($publicKeyResource === false) {
            throw new CryptoException("RSA公钥加密失败：无效的公钥。错误: " . self::getOpenSSLError());
        }
        $encrypted = '';
        if (!openssl_public_encrypt($data, $encrypted, $publicKeyResource, OPENSSL_PKCS1_PADDING)) {
            openssl_free_key($publicKeyResource);
            throw new CryptoException('RSA公钥加密操作失败: ' . self::getOpenSSLError());
        }
        openssl_free_key($publicKeyResource);
        return base64_encode($encrypted);
    }

    /**
     * RSA私钥解密 (PKCS1Padding)。
     * 用于解密由对应公钥加密的数据。
     *
     * @param string $base64Data Base64编码的待解密数据。
     * @param string $privateKeyPem PEM格式的RSA私钥。
     * @return string 解密后的数据。
     * @throws CryptoException 如果私钥无效或解密失败。
     */
    public static function rsaDecryptWithPrivateKey(string $base64Data, string $privateKeyPem): string
    {
        $privateKeyResource = openssl_pkey_get_private(self::formatKey($privateKeyPem, 'PRIVATE'));
        if ($privateKeyResource === false) {
            throw new CryptoException("RSA私钥解密失败：无效的私钥。错误: " . self::getOpenSSLError());
        }
        $encryptedData = base64_decode($base64Data);
        if ($encryptedData === false) {
            openssl_free_key($privateKeyResource);
            throw new CryptoException('RSA私钥解密失败: base64_decode待解密数据返回false。');
        }
        $decrypted = '';
        if (!openssl_private_decrypt($encryptedData, $decrypted, $privateKeyResource, OPENSSL_PKCS1_PADDING)) {
            openssl_free_key($privateKeyResource);
            throw new CryptoException('RSA私钥解密操作失败: ' . self::getOpenSSLError());
        }
        openssl_free_key($privateKeyResource);
        return $decrypted;
    }

    /**
     * 生成签名 (SHA256WithRSA)。
     * 使用己方私钥对数据进行签名。
     *
     * @param array $data 待签名的数据数组 (键值对)。构建签名串时将排除 'sign' 字段。
     * @param string $privateKeyPem PEM格式的己方RSA私钥。
     * @param array|null &$rawLog (引用传递) 用于记录中间日志的可选数组。会添加 'string_to_sign'。
     * @return string Base64编码的签名。
     * @throws CryptoException 如果私钥无效或签名失败。
     */
    public static function generateSignature(array $data, string $privateKeyPem, ?array &$rawLog = null): string
    {
        $stringToSign = self::buildSignString($data);
        if ($rawLog !== null) {
            $rawLog['string_to_sign'] = $stringToSign;
        }

        $privateKeyResource = openssl_pkey_get_private(self::formatKey($privateKeyPem, 'PRIVATE'));
        if ($privateKeyResource === false) {
            throw new CryptoException("签名生成失败：无效的私钥。错误: " . self::getOpenSSLError());
        }
        $signature = '';
        if (!openssl_sign($stringToSign, $signature, $privateKeyResource, self::SIGN_ALGORITHM_OPENSSL)) {
            openssl_free_key($privateKeyResource);
            throw new CryptoException('签名生成操作失败: ' . self::getOpenSSLError());
        }
        openssl_free_key($privateKeyResource);
        return base64_encode($signature);
    }

    /**
     * 验证签名 (SHA256WithRSA)。
     * 使用对方公钥验证接收到的数据的签名。
     *
     * @param array $data 待验证的数据数组 (键值对)。构建验签串时将排除 'sign' 字段。
     * @param string $signature Base64编码的签名字符串。
     * @param string $publicKeyPem PEM格式的对方RSA公钥。
     * @param array|null &$rawLog (引用传递) 用于记录中间日志的可选数组。会添加 'string_to_verify' 和 'verification_error_detail'。
     * @return bool 签名是否有效。
     * @throws CryptoException 如果公钥无效或验签过程中发生OpenSSL错误。
     */
    public static function verifySignature(array $data, string $signature, string $publicKeyPem, ?array &$rawLog = null): bool
    {
        $stringToVerify = self::buildSignString($data);
         if ($rawLog !== null) {
            $rawLog['string_to_verify'] = $stringToVerify;
        }

        $publicKeyResource = openssl_pkey_get_public(self::formatKey($publicKeyPem, 'PUBLIC'));
        if ($publicKeyResource === false) {
            throw new CryptoException("签名验证失败：无效的公钥。错误: " . self::getOpenSSLError());
        }
        $signatureBytes = base64_decode($signature);
        if ($signatureBytes === false) {
            if ($rawLog !== null) {
                 $rawLog['verification_error_detail'] = '签名验签失败：签名字符串不是有效的Base64编码。';
            }
            openssl_free_key($publicKeyResource);
            return false; // Signature not valid base64
        }
        $result = openssl_verify($stringToVerify, $signatureBytes, $publicKeyResource, self::SIGN_ALGORITHM_OPENSSL);
        openssl_free_key($publicKeyResource);

        if ($result === -1) {
            throw new CryptoException('签名验证过程中发生错误: ' . self::getOpenSSLError());
        }
        return $result === 1;
    }

    /**
     * 构建待签名/验签的字符串。
     * 规则：排除 'sign' 字段，按键名对剩余参数进行字典升序排序，然后以 "key=value" 格式用 "&" 连接。
     * 空字符串或null值的参数不参与拼接。
     *
     * @param array $data 包含待处理参数的数组。
     * @return string 构建好的待签名/验签字符串。
     */
    public static function buildSignString(array $data): string
    {
        unset($data['sign']);
        ksort($data);
        $parts = [];
        foreach ($data as $key => $value) {
            if ($value !== null && (string)$value !== '') {
                $parts[] = $key . '=' . $value;
            }
        }
        return implode('&', $parts);
    }

    /**
     * 格式化PEM密钥字符串，确保其包含标准的BEGIN/END标记和正确的换行。
     * @param string $key 原始密钥字符串（可能不含头尾标记或换行）。
     * @param string $type 密钥类型，'PUBLIC' 或 'PRIVATE'。对于私钥，如果未指定为 'RSA PRIVATE'，则默认为PKCS#8格式。
     * @return string 格式化后的PEM密钥字符串。
     */
    public static function formatKey(string $key, string $type): string
    {
        $type = strtoupper($type);
        $pemType = $type;

        if ($type === 'PRIVATE') {
            if (strpos($key, '-----BEGIN RSA PRIVATE KEY-----') !== false) {
                $pemType = 'RSA PRIVATE';
            } elseif (strpos($key, '-----BEGIN PRIVATE KEY-----') !== false) {
                $pemType = 'PRIVATE'; // PKCS#8
            } else {
                $pemType = 'PRIVATE'; // 默认为 PKCS#8 (鲲鹏文档示例私钥为此格式)
            }
        }

        $header = "-----BEGIN {$pemType} KEY-----";
        $footer = "-----END {$pemType} KEY-----";

        if (strpos($key, $header) === false) {
            $strippedKey = preg_replace('/-----(BEGIN|END) (RSA )?(PUBLIC|PRIVATE) KEY-----/', '', $key);
            $strippedKey = str_replace(["\r", "\n", " "], '', $strippedKey);
            return $header . "\n" . chunk_split($strippedKey, 64, "\n") . $footer;
        }
        return $key;
    }

    /**
     * 获取OpenSSL错误队列中的所有错误信息。
     * @return string 拼接后的错误信息，如果队列为空则返回 'Unknown OpenSSL error'。
     */
    private static function getOpenSSLError(): string
    {
        $errors = [];
        while ($msg = openssl_error_string()) {
            $errors[] = $msg;
        }
        return empty($errors) ? '未知的OpenSSL错误' : implode('; ', $errors);
    }

    /**
     * 准备鲲鹏支付API请求所需的完整参数包。
     * 包括业务数据AES加密、AES密钥RSA加密、生成时间戳和签名。
     *
     * @param array $bizData 要发送的业务数据数组。
     * @param string $appId 合作方AppID。
     * @param string $kunPengPlatformPublicKeyPem 鲲鹏平台的RSA公钥（PEM格式），用于加密AES密钥。
     * @param string $ourPrivateKeyPem 我方的RSA私钥（PEM格式），用于对整个请求包签名。
     * @param array &$rawLog (可选, 引用传递) 用于记录加密和签名过程中的中间数据，方便调试。
     *                       会包含: 'generated_aes_key', 'biz_data_json', 'string_to_sign_for_request'
     * @return array 准备好的请求参数数组，可直接用于POST请求的JSON体。
     * @throws CryptoException 如果加密或签名过程中发生错误。
     * @throws \InvalidArgumentException 如果业务数据JSON编码失败。
     * @throws \Exception AES密钥生成时可能抛出异常。
     */
    public static function prepareKunPengRequest(
        array $bizData,
        string $appId,
        string $kunPengPlatformPublicKeyPem,
        string $ourPrivateKeyPem,
        array &$rawLog = []
    ): array {
        $aesKey = self::generateAesKey();
        $rawLog['generated_aes_key'] = $aesKey;

        $bizDataJson = json_encode($bizData, JSON_UNESCAPED_UNICODE);
        if ($bizDataJson === false) {
            throw new \InvalidArgumentException('业务数据JSON编码失败: ' . json_last_error_msg());
        }
        $rawLog['biz_data_json'] = $bizDataJson;

        $encryptedData = self::aesEncrypt($bizDataJson, $aesKey);
        $encryptedAesKey = self::rsaEncryptWithPublicKey($aesKey, $kunPengPlatformPublicKeyPem);

        $requestParams = [
            'appId' => $appId,
            'encryptKey' => $encryptedAesKey,
            'data' => $encryptedData,
            'timestamp' => (string)(int)(microtime(true) * 1000),
        ];

        // generateSignature 内部会调用 buildSignString，并将结果存入 $rawLog['string_to_sign']
        // 为避免混淆，我们可以在这里记录一下调用 generateSignature 前的 $requestParams 状态
        // 或者让 generateSignature 返回签名串和待签名串，但这会改变其当前接口
        // 当前 generateSignature 会通过引用修改 $rawLog
        $signatureLog = []; // 用于捕获 generateSignature 内部的 string_to_sign
        $requestParams['sign'] = self::generateSignature($requestParams, $ourPrivateKeyPem, $signatureLog);
        if (isset($signatureLog['string_to_sign'])) {
             $rawLog['string_to_sign_for_request'] = $signatureLog['string_to_sign'];
        }

        return $requestParams;
    }

    /**
     * 处理鲲鹏支付API的响应数据。
     * 包括验证签名、RSA解密AES密钥、AES解密业务数据。
     *
     * @param array $responseData 从API接收到的响应数组 (已json_decode)。
     * @param string $ourPrivateKeyPem 我方的RSA私钥（PEM格式），用于解密AES密钥。
     * @param string $kunPengPlatformPublicKeyPem 鲲鹏平台的RSA公钥（PEM格式），用于验证响应签名。
     * @param array &$rawLog (可选, 引用传递) 用于记录解密和验签过程中的中间数据，方便调试。
     *                       会包含: 'string_to_verify_for_response', 'verification_error_detail' (如果验签失败),
     *                                'decrypted_aes_key_from_response', 'decrypted_biz_data_json_from_response'.
     * @return array|null 解密后的业务数据数组。如果验签失败、解密失败或必要字段缺失，则返回null或抛出异常。
     * @throws CryptoException 如果验签或解密过程中发生不可恢复的错误，或者必要字段缺失。
     */
    public static function processKunPengResponse(
        array $responseData,
        string $ourPrivateKeyPem,
        string $kunPengPlatformPublicKeyPem,
        array &$rawLog = []
    ): ?array {
        if (!isset($responseData['sign'], $responseData['encryptKey'], $responseData['data'])) {
            $errorMessage = 'API响应缺少必要字段 (sign, encryptKey, 或 data)。响应: ' . json_encode($responseData);
            $rawLog['processing_error'] = $errorMessage;
            throw new CryptoException($errorMessage);
        }

        $sign = $responseData['sign'];
        // verifySignature 内部会调用 buildSignString，并将结果存入 $rawLog['string_to_verify']
        if (!self::verifySignature($responseData, $sign, $kunPengPlatformPublicKeyPem, $rawLog)) {
            // verification_error_detail 已经在 verifySignature 中记录到 $rawLog
            $errorMessage = 'API响应签名验证失败。';
            if(isset($rawLog['string_to_verify'])){
                $errorMessage .= ' 待验证字符串: ' . $rawLog['string_to_verify'];
            }
             $rawLog['processing_error'] = $errorMessage;
            throw new CryptoException($errorMessage);
        }
        $rawLog['response_signature_verified'] = true;

        $encryptedAesKey = $responseData['encryptKey'];
        $encryptedData = $responseData['data'];

        $aesKey = self::rsaDecryptWithPrivateKey($encryptedAesKey, $ourPrivateKeyPem);
        $rawLog['decrypted_aes_key_from_response'] = $aesKey;

        $decryptedDataJson = self::aesDecrypt($encryptedData, $aesKey);
        $rawLog['decrypted_biz_data_json_from_response'] = $decryptedDataJson;

        $decodedData = json_decode($decryptedDataJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMessage = '无法解码解密后的JSON业务数据: ' . json_last_error_msg() . '. JSON: ' . $decryptedDataJson;
            $rawLog['processing_error'] = $errorMessage;
            throw new CryptoException($errorMessage);
        }
        return $decodedData;
    }

    /**
     * 处理鲲鹏支付平台推送的回调通知。
     * 包括解析JSON、验证签名、RSA解密AES密钥、AES解密业务数据。
     *
     * @param string $rawCallbackContent 从平台接收到的原始回调内容 (JSON字符串)。
     * @param string $ourPrivateKeyPem 我方的RSA私钥（PEM格式），用于解密AES密钥。
     * @param string $kunPengPlatformPublicKeyPem 鲲鹏平台的RSA公钥（PEM格式），用于验证回调签名。
     * @param array &$rawLog (可选, 引用传递) 用于记录解密和验签过程中的中间数据，方便调试。
     *                       结构与 processKunPengResponse 中的 $rawLog 类似。
     * @return array|null 解密后的回调业务数据数组，并会额外添加 '_serviceType' 和 '_appId' (如果存在) 键。
     *                    如果处理失败则返回null或抛出异常。
     * @throws CryptoException 如果JSON解析、验签或解密过程中发生不可恢复的错误，或者必要字段缺失。
     */
    public static function processKunPengCallback(
        string $rawCallbackContent,
        string $ourPrivateKeyPem,
        string $kunPengPlatformPublicKeyPem,
        array &$rawLog = []
    ): ?array {
        $rawLog['raw_callback_content'] = $rawCallbackContent;
        $callbackData = json_decode($rawCallbackContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMessage = '无法解码回调JSON内容: ' . json_last_error_msg() . '. 原始内容: ' . $rawCallbackContent;
            $rawLog['processing_error'] = $errorMessage;
            throw new CryptoException($errorMessage);
        }
        $rawLog['callback_data_array'] = $callbackData;

        if (!isset($callbackData['serviceType'])) { // serviceType 是回调特有的，比通用响应多一个检查
            $errorMessage = '回调数据缺少必要字段 serviceType。数据: ' . $rawCallbackContent;
            $rawLog['processing_error'] = $errorMessage;
            throw new CryptoException($errorMessage);
        }

        // 复用 processKunPengResponse 的核心逻辑来处理验签和解密
        $decryptedBizData = self::processKunPengResponse($callbackData, $ourPrivateKeyPem, $kunPengPlatformPublicKeyPem, $rawLog);

        if ($decryptedBizData !== null) {
            $decryptedBizData['_serviceType'] = $callbackData['serviceType'];
            if (isset($callbackData['appId'])) {
                $decryptedBizData['_appId'] = $callbackData['appId'];
            }
        }

        return $decryptedBizData;
    }
}
