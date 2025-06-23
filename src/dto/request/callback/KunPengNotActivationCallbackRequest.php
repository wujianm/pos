<?php declare(strict_types=1);

namespace think\pos\dto\request\callback;

use think\pos\dto\request\CallbackRequest;
use shali\phpmate\core\date\LocalDateTime;

class KunPengNotActivationCallbackRequest extends CallbackRequest
{
    private $merchantNo = '';
    private $merchantName = '';
    private /*string (NOT_ACTIVATION | PSEUDO_ACTIVATION)*/ $status = '';
    private $deviceNo = '';
    private $activationTime; // 未伪激活时间 (LocalDateTime object)

    public function getMerchantNo(): string
    {
        return $this->merchantNo;
    }

    public function setMerchantNo(string $merchantNo): void
    {
        $this->merchantNo = $merchantNo;
    }

    public function getMerchantName(): string
    {
        return $this->merchantName;
    }

    public function setMerchantName(string $merchantName): void
    {
        $this->merchantName = $merchantName;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getDeviceNo(): string
    {
        return $this->deviceNo;
    }

    public function setDeviceNo(string $deviceNo): void
    {
        $this->deviceNo = $deviceNo;
    }

    public function getActivationTime(): ?LocalDateTime
    {
        return $this->activationTime;
    }

    public function setActivationTime(?LocalDateTime $activationTime): void
    {
        $this->activationTime = $activationTime;
    }
}
