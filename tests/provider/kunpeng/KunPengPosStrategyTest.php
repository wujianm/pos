<?php declare(strict_types=1);

namespace tests\think\pos\provider\kunpeng;

use PHPUnit\Framework\TestCase;
use think\pos\provider\kunpeng\KunPengPosStrategy;
use think\pos\dto\request\MerchantRequestDto;
use think\pos\dto\request\PosRequestDto;
use think\pos\dto\response\GetKunPengMerchantRateInfoResponse;
use think\pos\dto\request\callback\KunPengSimStopOrderCallbackRequest;
use think\pos\dto\request\callback\KunPengNotActivationCallbackRequest;
use think\pos\dto\request\callback\KunPengDepositStopOrderCallbackRequest;
use think\pos\dto\request\callback\PosTransCallbackRequest;
use think\pos\dto\request\callback\PosActivateCallbackRequest;
use think\pos\dto\request\callback\MerchantRegisterCallbackRequest;
use think\pos\exception\ProviderGatewayException;
use shali\phpmate\exception\CryptoException;

class KunPengPosStrategyTest extends TestCase
{
    private $config;
    private $strategy;

    // Generated RSA key pair for testing (2048 bits)
    // 我方私钥 (for signing requests, decrypting AES key encrypted by Kunpeng Public Key - though for test we use mock Kunpeng public key)
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

    // 我方公钥 (for Kunpeng to verify our signature, encrypt AES key to us - though for test we use mock Kunpeng private key to encrypt)
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

    // 模拟鲲鹏平台的私钥 (for signing responses/callbacks to us, decrypting AES key encrypted by My Public Key)
    private const KUNPENG_PRIVATE_KEY_PEM = <<<EOT
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

    // 模拟鲲鹏平台的公钥 (for us to verify their signature, encrypt AES key to them)
    private const KUNPENG_PUBLIC_KEY_PEM = <<<EOT
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAtyPMXIbB0oSTtZEAEbkD
8g63pXyYy59XWzO8y2yX8DkL7e6XxGfXJ6mZ7rX8sT9qX3T5nL3rV/sDxJ/n9g8B
7S3kZ/P9e7X6vW+wS8Z7L5oY9sX3U8vQ5xL2bX1eJ7rF9c7E8Q2gX3S5r5vN8x/z
BF0gH6P8bV7P2fW4X7S9v9vL6qX3R8nN9XnU2pX9vT6a/7N0dYx/tM8gC8oM6eK
1SL5s8vX9eY4fN8zR9v7Y8vQ7rX6sN3xW1uV9pD9sN2xZAgMBAAE=
-----END PUBLIC KEY-----
EOT;


    protected function setUp(): void
    {
        parent::setUp();
        $this->config = [
            'test' => true,
            'appId' => 'TEST_APP_ID',
            'privateKey' => self::MY_PRIVATE_KEY_PEM, // 我方私钥
            'kunPengPublicKey' => self::KUNPENG_PUBLIC_KEY_PEM, // 鲲鹏公钥
            'gateway' => 'https://fake.kunpeng.com/api',
            'testGateway' => 'https://fake-test.kunpeng.com/api',
        ];
        $this->strategy = new KunPengPosStrategy($this->config);

        // Mock HttpClient to avoid actual HTTP calls
        // This is a simple mock, a more robust solution might use a library like Mockery
        $this->httpClientMock = $this->getMockBuilder(\shali\phpmate\http\HttpClient::class)
                                     ->onlyMethods(['post', 'getRawResponse'])
                                     ->getMock();

        // Reflection to set the httpClient property if it's private/protected in KunPengPosStrategy
        // Or, modify KunPengPosStrategy to allow httpClient injection for testing.
        // For now, assuming direct calls will be made and we test data prep/processing.
    }

    // Helper to access private/protected methods for testing
    private function callProtectedMethod($object, string $methodName, array $parameters = [])
    {
        $className = get_class($object);
        $reflection = new \ReflectionClass($className);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    public function testGenerateAesKey()
    {
        $key = $this->callProtectedMethod($this->strategy, 'generateAesKey');
        $this->assertEquals(16, strlen($key));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{16}$/', $key);
    }

    public function testAesEncryptionDecryption()
    {
        $aesKey = $this->callProtectedMethod($this->strategy, 'generateAesKey');
        $data = "This is a secret message for AES!";

        $encrypted = $this->callProtectedMethod($this->strategy, 'aesEncrypt', [$data, $aesKey]);
        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals($data, $encrypted);

        $decrypted = $this->callProtectedMethod($this->strategy, 'aesDecrypt', [$encrypted, $aesKey]);
        $this->assertEquals($data, $decrypted);
    }

    public function testRsaEncryptionDecryption()
    {
        // Test RSA encryption with Kunpeng's Public Key and decryption with My Private Key (typical for AES key decryption)
        $dataToEncrypt = "secret_aes_key_example";
        $encryptedWithKunpengPublic = $this->callProtectedMethod($this->strategy, 'rsaEncrypt', [$dataToEncrypt, self::KUNPENG_PUBLIC_KEY_PEM]);
        $this->assertNotEmpty($encryptedWithKunpengPublic);

        // This would be done by Kunpeng with My Public Key, so we simulate it:
        // $encryptedWithMyPublic = $this->callProtectedMethod($this->strategy, 'rsaEncrypt', [$dataToEncrypt, self::MY_PUBLIC_KEY_PEM]);
        // $decryptedWithKunpengPrivate = $this->callProtectedMethod($this->strategy, 'rsaDecrypt', [$encryptedWithMyPublic, self::KUNPENG_PRIVATE_KEY_PEM]);
        // $this->assertEquals($dataToEncrypt, $decryptedWithKunpengPrivate);

        // Test decrypting something encrypted with Kunpeng's public key (which is what we do for their response's AES key)
        // This requires us to have Kunpeng's *private* key to encrypt for this test scenario, which we simulated as KUNPENG_PRIVATE_KEY_PEM
        // So, encrypt with KUNPENG_PRIVATE_KEY (simulating Kunpeng encrypting for us using their private key - this is not standard for RSA public key crypto)
        // More accurately: Kunpeng encrypts AES key with *Our Public Key*. We decrypt with *Our Private Key*.
        // Let's test the path where we decrypt AES key from Kunpeng:
        // 1. Kunpeng generates AES key.
        // 2. Kunpeng encrypts AES key with *Our Public Key* (MY_PUBLIC_KEY_PEM).
        // 3. We receive it and decrypt with *Our Private Key* (MY_PRIVATE_KEY_PEM).

        $aesKeyByKunpeng = "TestAesKey123456"; // 16 chars
        // Simulate Kunpeng encrypting this AES key with MY_PUBLIC_KEY_PEM
        // For this, we need an RSA encrypt function that takes a public key.
        // $kunpengEncryptedAesKey = RsaUtil::publicEncrypt($aesKeyByKunpeng, self::MY_PUBLIC_KEY_PEM); // Assuming RsaUtil is accessible and works
        // Let's use the strategy's own rsaEncrypt but with MY_PUBLIC_KEY_PEM
        $tempStrategyForMyKeys = new KunPengPosStrategy([
            'privateKey' => self::MY_PRIVATE_KEY_PEM,
            'kunPengPublicKey' => self::MY_PUBLIC_KEY_PEM // Temporarily use my public as "kunpeng's" for this specific test
        ]);
        $kunpengEncryptedAesKey = $this->callProtectedMethod($tempStrategyForMyKeys, 'rsaEncrypt', [$aesKeyByKunpeng, self::MY_PUBLIC_KEY_PEM]);


        $decryptedAesKey = $this->callProtectedMethod($this->strategy, 'rsaDecrypt', [$kunpengEncryptedAesKey, self::MY_PRIVATE_KEY_PEM]);
        $this->assertEquals($aesKeyByKunpeng, $decryptedAesKey);
    }

    public function testBuildSignString()
    {
        $data = [
            'timestamp' => '1694505252824',
            'appId' => '91272436',
            'serviceType' => 'PAY_ORDER',
            'data' => 'someencrypteddata',
            'encryptKey' => 'anotherencryptedkey',
            'sign' => 'shouldberemoved',
            'z_param' => 'last',
            'a_param' => 'first',
            'empty_param' => '',
            'null_param' => null,
        ];
        $expected = "a_param=first&appId=91272436&data=someencrypteddata&encryptKey=anotherencryptedkey&serviceType=PAY_ORDER&timestamp=1694505252824&z_param=last";
        $actual = $this->callProtectedMethod($this->strategy, 'buildSignString', [$data]);
        $this->assertEquals($expected, $actual);
    }

    public function testSignatureGenerationAndVerification()
    {
        $data = [
            'appId' => $this->config['appId'],
            'timestamp' => (string)(int)(microtime(true) * 1000),
            'data' => 'somedata',
            'encryptKey' => 'somekey'
        ];

        // Sign with My Private Key
        $signature = $this->callProtectedMethod($this->strategy, 'generateSign', [$data, self::MY_PRIVATE_KEY_PEM]);
        $this->assertNotEmpty($signature);

        // Verify with My Public Key
        $isValid = $this->callProtectedMethod($this->strategy, 'verifySign', [$data, $signature, self::MY_PUBLIC_KEY_PEM]);
        $this->assertTrue($isValid);

        // Verify with wrong key (Kunpeng's Public Key) - should fail
        $isInvalid = $this->callProtectedMethod($this->strategy, 'verifySign', [$data, $signature, self::KUNPENG_PUBLIC_KEY_PEM]);
        $this->assertFalse($isInvalid);

        // Tamper data - should fail verification
        $tamperedData = $data;
        $tamperedData['data'] = 'tampereddata';
        $isValidTampered = $this->callProtectedMethod($this->strategy, 'verifySign', [$tamperedData, $signature, self::MY_PUBLIC_KEY_PEM]);
        $this->assertFalse($isValidTampered);
    }

    public function testPrepareRequestData()
    {
        $bizData = ['testParam' => 'testValue', 'amount' => 100];
        $preparedData = $this->callProtectedMethod($this->strategy, 'prepareRequestData', [$bizData]);

        $this->assertArrayHasKey('appId', $preparedData);
        $this->assertEquals($this->config['appId'], $preparedData['appId']);
        $this->assertArrayHasKey('encryptKey', $preparedData);
        $this->assertArrayHasKey('data', $preparedData);
        $this->assertArrayHasKey('timestamp', $preparedData);
        $this->assertArrayHasKey('sign', $preparedData);

        // Further checks: try to decrypt and verify
        // 1. Decrypt AES key using KUNPENG_PRIVATE_KEY_PEM (as if we are Kunpeng)
        $rawAesKey = $this->callProtectedMethod($this->strategy, 'rsaDecrypt', [$preparedData['encryptKey'], self::KUNPENG_PRIVATE_KEY_PEM]);
        $this->assertNotEmpty($rawAesKey);
        $this->assertEquals(16, strlen($rawAesKey));

        // 2. Decrypt data using this AES key
        $decryptedBizJson = $this->callProtectedMethod($this->strategy, 'aesDecrypt', [$preparedData['data'], $rawAesKey]);
        $decryptedBizData = json_decode($decryptedBizJson, true);
        $this->assertEquals($bizData, $decryptedBizData);

        // 3. Verify signature using MY_PUBLIC_KEY_PEM (as if we are Kunpeng verifying our request)
        $dataToVerify = $preparedData; // buildSignString will remove 'sign'
        $isValid = $this->callProtectedMethod($this->strategy, 'verifySign', [$dataToVerify, $preparedData['sign'], self::MY_PUBLIC_KEY_PEM]);
        $this->assertTrue($isValid, "Signature verification failed for prepared data.");
    }

    public function testProcessResponseData_Success()
    {
        // Simulate a successful response from Kunpeng
        $bizResponseData = ['status' => 'TRUE', 'message' => 'Success'];
        $bizResponseJson = json_encode($bizResponseData);

        // 1. Kunpeng generates an AES key
        $aesKey = $this->callProtectedMethod($this->strategy, 'generateAesKey');

        // 2. Kunpeng encrypts bizResponseJson with this AES key
        $encryptedData = $this->callProtectedMethod($this->strategy, 'aesEncrypt', [$bizResponseJson, $aesKey]);

        // 3. Kunpeng encrypts this AES key with My Public Key (MY_PUBLIC_KEY_PEM)
        //    For this, we need a temporary strategy instance or a direct RSA call
        $tempStrategy = new KunPengPosStrategy(['privateKey'=>self::KUNPENG_PRIVATE_KEY_PEM, 'kunPengPublicKey' => self::MY_PUBLIC_KEY_PEM]);
        $encryptedAesKey = $this->callProtectedMethod($tempStrategy, 'rsaEncrypt', [$aesKey, self::MY_PUBLIC_KEY_PEM]);

        // 4. Kunpeng signs the response package
        $responsePackage = [
            'appId' => 'KUNPENG_APPID_RESP', // Kunpeng might use their own appId in response, or ours. Assuming generic for now.
            'code' => '00',
            'msg' => '处理成功',
            'timestamp' => (string)(int)(microtime(true) * 1000),
            'encryptKey' => $encryptedAesKey,
            'data' => $encryptedData,
        ];
        $signature = $this->callProtectedMethod($tempStrategy, 'generateSign', [$responsePackage, self::KUNPENG_PRIVATE_KEY_PEM]); // Signed by Kunpeng's Private Key
        $responsePackage['sign'] = $signature;

        // Now, process this simulated response with our strategy
        $processedData = $this->callProtectedMethod($this->strategy, 'processResponseData', [$responsePackage]);
        $this->assertEquals($bizResponseData, $processedData);
    }

    public function testProcessResponseData_SignatureFailure()
    {
        $this->expectException(CryptoException::class);
        $this->expectExceptionMessageMatches('/Response signature verification failed/');

        $responsePackage = [
            'appId' => 'KUNPENG_APPID_RESP',
            'code' => '00',
            'msg' => '处理成功',
            'timestamp' => (string)(int)(microtime(true) * 1000),
            'encryptKey' => 'dummyEncryptedAesKey',
            'data' => 'dummyEncryptedData',
            'sign' => 'invalidsignature', // Invalid signature
        ];
        $this->callProtectedMethod($this->strategy, 'processResponseData', [$responsePackage]);
    }

    public function testHandleCallback_PayOrder()
    {
        // Simulate PAY_ORDER callback data
        $serviceType = 'PAY_ORDER';
        $bizCallbackData = [
            'orderNo' => 'ORD12345',
            'merchantNo' => 'MCHT001',
            'merchantName' => 'Test Merchant',
            'deviceNo' => 'SN007',
            'successTime' => '20230101120000',
            'amount' => '100.50',
            'fee' => '0.60',
            'fixedValue' => '0.10',
            'feeRate' => '0.55', // 0.55%
            'baseRate' => '0.50',
            'agentRaisePriceRate' => '0.05',
            'payTypeCode' => 'POS_CC',
        ];
        $rawCallbackContent = $this->prepareEncryptedCallbackContent($serviceType, $bizCallbackData);

        $callbackRequest = $this->strategy->handleCallback($rawCallbackContent);

        $this->assertInstanceOf(PosTransCallbackRequest::class, $callbackRequest);
        $this->assertTrue($callbackRequest->isSuccess());
        $this->assertEquals('ORD12345', $callbackRequest->getTransNo());
        $this->assertEquals('MCHT001', $callbackRequest->getMerchantNo());
        $this->assertEquals('100.50', $callbackRequest->getAmount()->toString()); // Money object to string
        $this->assertEquals('0.60', $callbackRequest->getFee()->toString());
        $this->assertEquals(0.0055, $callbackRequest->getRate()->getValue()); // 0.55 / 100
        $this->assertEquals('0.10', $callbackRequest->getOptions()['kunpeng_fixedValue']->toString());
    }

    // Helper to create an encrypted and signed callback content string for testing handleCallback
    private function prepareEncryptedCallbackContent(string $serviceType, array $bizData): string
    {
        $bizJson = json_encode($bizData);
        // 1. Generate AES key
        $aesKey = $this->callProtectedMethod($this->strategy, 'generateAesKey');
        // 2. Encrypt bizJson with AES key
        $encryptedData = $this->callProtectedMethod($this->strategy, 'aesEncrypt', [$bizJson, $aesKey]);
        // 3. Encrypt AES key with My Public Key (MY_PUBLIC_KEY_PEM) - simulating Kunpeng sending to us
        $tempStrategy = new KunPengPosStrategy(['privateKey' => 'dummy', 'kunPengPublicKey' => self::MY_PUBLIC_KEY_PEM]);
        $encryptedAesKey = $this->callProtectedMethod($tempStrategy, 'rsaEncrypt', [$aesKey, self::MY_PUBLIC_KEY_PEM]);

        // 4. Construct full callback package and sign with Kunpeng's Private Key
        $callbackPackage = [
            'appId' => 'KUNPENG_CALLBACK_APPID', // Could be our appId or Kunpeng's
            'serviceType' => $serviceType,
            'timestamp' => (string)(int)(microtime(true) * 1000),
            'encryptKey' => $encryptedAesKey,
            'data' => $encryptedData,
        ];

        $kunpengSigningStrategy = new KunPengPosStrategy(['privateKey' => self::KUNPENG_PRIVATE_KEY_PEM, 'kunPengPublicKey' => 'dummy']);
        $signature = $this->callProtectedMethod($kunpengSigningStrategy, 'generateSign', [$callbackPackage, self::KUNPENG_PRIVATE_KEY_PEM]);
        $callbackPackage['sign'] = $signature;

        return json_encode($callbackPackage);
    }

    // TODO: Add more tests for other callback types (ACTIVATION, CUSTOMER_REGISTER, etc.)
    // TODO: Add tests for specific interface methods (setMerchantRate, getMerchantRateInfo, etc.) by mocking HttpClient responses.
}
