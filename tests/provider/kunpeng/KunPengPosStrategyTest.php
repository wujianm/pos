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

    // Generated RSA key pair for testing (2048 bits) - These are needed for KunPengPosStrategy instance
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

    // 我方公钥
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

    // 模拟鲲鹏平台的私钥
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

    // 模拟鲲鹏平台的公钥
    private const KUNPENG_PLATFORM_PUBLIC_KEY_PEM = <<<EOT
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
            'privateKey' => self::MY_PRIVATE_KEY_PEM,
            'kunPengPublicKey' => self::KUNPENG_PLATFORM_PUBLIC_KEY_PEM,
            'gateway' => 'https://fake.kunpeng.com/api',
            'testGateway' => 'https://fake-test.kunpeng.com/api',
        ];
        $this->strategy = new KunPengPosStrategy($this->config);

        // Mock HttpClient to avoid actual HTTP calls
        // This is a simple mock, a more robust solution might use a library like Mockery
        // $this->httpClientMock = $this->getMockBuilder(\shali\phpmate\http\HttpClient::class)
        //                              ->onlyMethods(['post', 'getRawResponse'])
        //                              ->getMock();
        // For KunPengPosStrategy, actual http client is instantiated inside methods,
        // so mocking it here directly doesn't affect the strategy unless we inject it.
        // The tests for prepareRequestData/processResponseData will test the crypto logic
        // which is now in KunPengCryptoUtil, so KunPengPosStrategyTest focuses more on
        // the interaction and DTO mapping.
    }

    // Helper to access protected methods (prepareRequestData, processResponseData, processCallbackData)
    // Note: This is generally discouraged for unit testing private/protected methods directly.
    // It's better to test them via public interface.
    // However, for these specific protected methods that encapsulate core logic, it can be useful.
    // For private crypto methods, they are now in KunPengCryptoUtil and tested there.
    private function callStrategyProtectedMethod(string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(KunPengPosStrategy::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($this->strategy, $parameters);
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
