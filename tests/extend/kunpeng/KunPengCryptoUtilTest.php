<?php declare(strict_types=1);

namespace tests\think\pos\extend\kunpeng;

use PHPUnit\Framework\TestCase;
use think\pos\extend\kunpeng\KunPengCryptoUtil;
use shali\phpmate\exception\CryptoException;

class KunPengCryptoUtilTest extends TestCase
{
    // Copied from KunPengPosStrategyTest - these keys are for testing cryptographic round trips.
    // In a real scenario, "MY" keys are ours, "KUNPENG" keys are provided by Kunpeng.
    private const MY_PRIVATE_KEY_PEM = <<<EOT
-----BEGIN RSA PRIVATE KEY-----
MIIEpQIBAAKCAQEA0R+xJ0vPmgDrRT8OzvSMBm9zThYNaHAdGgaS470ampyVbL6s
hJNaB0y3lgPdsFSUevtfvXLAs6EvPz9rYSkzGshe9UcHf6ixCePlc2M6dYyiq9ZM
xJWWs2PpxESStpZpS+xJGnopVacIHQwCScd7q3R0jXzILAeT9Syp0otP8SqhJq8Y
t2vVdCrxCsvlAGHgeDb5av0N2G3jY0hj9GWJdpvoCHD71x98FxQJggP2A7wEM3oS
uf6Q5tT705i97zT2jVzV2WnmkZ/A1jYHx7SnHM9K7nIpwvZo1vskNcL2PFgYvB6J
qnMqEAjYgA3x55q/GWM2T0OFY34KM1X0JQxN9QIDAQABAoIBAQCx86ZfTCuVp0nK
DE8k9sN8hLgC8fhE89e8g1AUgPqYyVySUyUxFyLd2g8pUGteY58f0xYBYY9P0S6u
y9sJ9SjE1B4v7T0N/ZkK9wzXvj6c4f2fV6Z9w8XnQ5vX9vS2gX2V5r/0A6A2nL3j
c4q9qZ/v9wAgD/2d5f8zY6nB9a4a3vQ1zL2bX1dJ7rF9c7E8Q2gX3S5r5vN8x/zB
F0gH6P8bV7P2fW4X7S9v9vL6qX3R8nN9XnU2pX9vT6a/7N0dYx/tM8gC8oM6eK1S
L5s8vX9eY4fN8zR9v7Y8vQ7rX6sN3xW1uV9pD9sN2xZAgMBAAECggEAPLTqRzEy
tK6+JjcjN2ZQHz7rXwD8+4xZc3YF9P9vN9L3rZ6PzN8vO7M9b8dY2xX7Y8vQ6rX
6sN3xW1uV9pD9sN2xZAgMBAAECggEAPLTqRzEytK6+JjcjN2ZQHz7rXwD8+4xZ
c3YF9P9vN9L3rZ6PzN8vO7M9b8dY2xX7Y8vQ6rX6sN3xW1uV9pD9sN2xZAgMBAA
ECggEAPLTqRzEytK6+JjcjN2ZQHz7rXwD8+4xZc3YF9P9vN9L3rZ6PzN8vO7M9
b8dY2xX7Y8vQ6rX6sN3xW1uV9pD9sN2xZAgMBAAECggEAPLTqRzEytK6+JjcjN2
ZQHz7rXwD8+4xZc3YF9P9vN9L3rZ6PzN8vO7M9b8dY2xX7Y8vQ6rX6sN3xW1uV
9pD9sN2xZAgMBAAECggEAPLTqRzEytK6+JjcjN2ZQHz7rXwD8+4xZc3YF9P9vN
9L3rZ6PzN8vO7M9b8dY2xX7Y8vQ6rX6sN3xW1uV9pD9sN2xZAgMBAAE=
-----END RSA PRIVATE KEY-----
EOT;

    private const MY_PUBLIC_KEY_PEM = <<<EOT
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA0R+xJ0vPmgDrRT8OzvSM
Bm9zThYNaHAdGgaS470ampyVbL6shJNaB0y3lgPdsFSUevtfvXLAs6EvPz9rYSkz
Gshe9UcHf6ixCePlc2M6dYyiq9ZMxJWWs2PpxESStpZpS+xJGnopVacIHQwCScd7
q3R0jXzILAeT9Syp0otP8SqhJq8Yt2vVdCrxCsvlAGHgeDb5av0N2G3jY0hj9GWJ
dpvoCHD71x98FxQJggP2A7wEM3oSuf6Q5tT705i97zT2jVzV2WnmkZ/A1jYHx7Sn
HM9K7nIpwvZo1vskNcL2PFgYvB6JqnMqEAjYgA3x55q/GWM2T0OFY34KM1X0JQxN
9QIDAQAB
-----END PUBLIC KEY-----
EOT;

    private const KUNPENG_PLATFORM_PRIVATE_KEY_PEM = <<<EOT
-----BEGIN RSA PRIVATE KEY-----
MIIEowIBAAKCAQEAtyPMXIbB0oSTtZEAEbkD8g63pXyYy59XWzO8y2yX8DkL7e6X
xGfXJ6mZ7rX8sT9qX3T5nL3rV/sDxJ/n9g8B7S3kZ/P9e7X6vW+wS8Z7L5oY9sX
3U8vQ5xL2bX1eJ7rF9c7E8Q2gX3S5r5vN8x/zBF0gH6P8bV7P2fW4X7S9v9vL6q
X3R8nN9XnU2pX9vT6a/7N0dYx/tM8gC8oM6eK1SL5s8vX9eY4fN8zR9v7Y8vQ7r
X6sN3xW1uV9pD9sN2xZAgMBAAECggEATz1kF7T6zG8L0fX/P5eS7R8sP9vN9L3r
Z6PzN8vO7M9b8dY2xX7Y8vQ6rX6sN3xW1uV9pD9sN2xZAgMBAAECggEAPLTqRzEy
tK6+JjcjN2ZQHz7rXwD8+4xZc3YF9P9vN9L3rZ6PzN8vO7M9b8dY2xX7Y8vQ6rX
6sN3xW1uV9pD9sN2xZAgMBAAECggEAPLTqRzEytK6+JjcjN2ZQHz7rXwD8+4xZ
c3YF9P9vN9L3rZ6PzN8vO7M9b8dY2xX7Y8vQ6rX6sN3xW1uV9pD9sN2xZAgMBAA
ECggEAPLTqRzEytK6+JjcjN2ZQHz7rXwD8+4xZc3YF9P9vN9L3rZ6PzN8vO7M9
b8dY2xX7Y8vQ6rX6sN3xW1uV9pD9sN2xZAgMBAAECggEAPLTqRzEytK6+JjcjN2
ZQHz7rXwD8+4xZc3YF9P9vN9L3rZ6PzN8vO7M9b8dY2xX7Y8vQ6rX6sN3xW1uV
9pD9sN2xZAgMBAAE=
-----END RSA PRIVATE KEY-----
EOT;

    // This is the public key corresponding to KUNPENG_PLATFORM_PRIVATE_KEY_PEM
    private const KUNPENG_PLATFORM_PUBLIC_KEY_PEM = <<<EOT
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAtyPMXIbB0oSTtZEAEbkD
8g63pXyYy59XWzO8y2yX8DkL7e6XxGfXJ6mZ7rX8sT9qX3T5nL3rV/sDxJ/n9g8B
7S3kZ/P9e7X6vW+wS8Z7L5oY9sX3U8vQ5xL2bX1eJ7rF9c7E8Q2gX3S5r5vN8x/z
BF0gH6P8bV7P2fW4X7S9v9vL6qX3R8nN9XnU2pX9vT6a/7N0dYx/tM8gC8oM6eK
1SL5s8vX9eY4fN8zR9v7Y8vQ7rX6sN3xW1uV9pD9sN2xZAgMBAAE=
-----END PUBLIC KEY-----
EOT;


    public function testGenerateAesKey()
    {
        $key = KunPengCryptoUtil::generateAesKey();
        $this->assertEquals(16, strlen($key));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{16}$/', $key);
    }

    public function testAesEncryptionDecryption()
    {
        $aesKey = KunPengCryptoUtil::generateAesKey();
        $data = "这是一个AES加密的秘密信息!";

        $encrypted = KunPengCryptoUtil::aesEncrypt($data, $aesKey);
        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals($data, $encrypted);

        $decrypted = KunPengCryptoUtil::aesDecrypt($encrypted, $aesKey);
        $this->assertEquals($data, $decrypted);
    }

    public function testRsaEncryptionDecryption()
    {
        // Test RSA encryption with Kunpeng's Public Key and decryption with My Private Key
        // This simulates Kunpeng encrypting something for us (e.g. an AES key in a response, though they use Our Public Key for that)
        // Or us encrypting an AES key for Kunpeng (using their Public Key)
        $dataToEncrypt = "secret_aes_key_example_for_kunpeng";
        $encrypted = KunPengCryptoUtil::rsaEncryptWithPublicKey($dataToEncrypt, self::KUNPENG_PLATFORM_PUBLIC_KEY_PEM);
        $this->assertNotEmpty($encrypted);

        // Kunpeng would decrypt this with KUNPENG_PLATFORM_PRIVATE_KEY_PEM
        $decryptedByKunpeng = KunPengCryptoUtil::rsaDecryptWithPrivateKey($encrypted, self::KUNPENG_PLATFORM_PRIVATE_KEY_PEM);
        $this->assertEquals($dataToEncrypt, $decryptedByKunpeng);

        // Test RSA encryption with My Public Key and decryption with Kunpeng's Private Key
        // This simulates us encrypting something that only Kunpeng can decrypt with their private key. (Not a typical flow for this SDK)
        // More relevant: Kunpeng encrypts AES key with Our Public Key. We decrypt with Our Private Key.
        $aesKeyByKunpeng = "TestAesKey123456"; // 16 chars
        $encryptedAesKeyForUs = KunPengCryptoUtil::rsaEncryptWithPublicKey($aesKeyByKunpeng, self::MY_PUBLIC_KEY_PEM); // Kunpeng uses My Public Key
        $decryptedAesKeyByUs = KunPengCryptoUtil::rsaDecryptWithPrivateKey($encryptedAesKeyForUs, self::MY_PRIVATE_KEY_PEM); // We use My Private Key
        $this->assertEquals($aesKeyByKunpeng, $decryptedAesKeyByUs);
    }

    public function testBuildSignString()
    {
        $data = [
            'timestamp' => '1694505252824',
            'appId' => '91272436',
            'serviceType' => 'PAY_ORDER',
            'data' => 'someencrypteddata',
            'encryptKey' => 'anotherencryptedkey',
            'sign' => 'shouldberemoved', // This will be removed by buildSignString
            'z_param' => 'last',
            'a_param' => 'first',
            'empty_param' => '',    // Should be excluded
            'null_param' => null,   // Should be excluded
        ];
        // Expected: sorted by key, sign removed, empty/null params removed, key=value joined by &
        $expected = "a_param=first&appId=91272436&data=someencrypteddata&encryptKey=anotherencryptedkey&serviceType=PAY_ORDER&timestamp=1694505252824&z_param=last";
        $actual = KunPengCryptoUtil::buildSignString($data);
        $this->assertEquals($expected, $actual);
    }

    public function testSignatureGenerationAndVerification()
    {
        $data = [
            'appId' => 'TEST_APP_ID_SIG',
            'timestamp' => (string)(int)(microtime(true) * 1000),
            'data' => 'signdata',
            'encryptKey' => 'signkey'
        ];

        // Sign with My Private Key
        $signature = KunPengCryptoUtil::generateSignature($data, self::MY_PRIVATE_KEY_PEM);
        $this->assertNotEmpty($signature);

        // Verify with My Public Key (should be valid)
        $isValid = KunPengCryptoUtil::verifySignature($data, $signature, self::MY_PUBLIC_KEY_PEM);
        $this->assertTrue($isValid, "Signature verification failed with correct key.");

        // Verify with wrong public key (Kunpeng's Public Key) - should fail
        $isInvalidWithWrongKey = KunPengCryptoUtil::verifySignature($data, $signature, self::KUNPENG_PLATFORM_PUBLIC_KEY_PEM);
        $this->assertFalse($isInvalidWithWrongKey, "Signature verification succeeded with incorrect key.");

        // Tamper data - should fail verification
        $tamperedData = $data;
        $tamperedData['data'] = 'tamperedsigndata';
        $isValidTampered = KunPengCryptoUtil::verifySignature($tamperedData, $signature, self::MY_PUBLIC_KEY_PEM);
        $this->assertFalse($isValidTampered, "Signature verification succeeded with tampered data.");
    }
}
