<?php declare(strict_types=1);

namespace think\pos\dto\request\callback;

use think\pos\dto\request\CallbackRequest;
use shali\phpmate\util\Money;
use shali\phpmate\core\date\LocalDateTime;

class KunPengDepositStopOrderCallbackRequest extends CallbackRequest
{
    private $merchantNo = '';
    private $merchantName = '';
    private $deviceNo = '';
    private $materialsType = ''; // 设备类型
    private $policyId = '';    // 政策编号
    private $orderNo = '';     // 止付订单号
    private $amount;           // 止付金额 (Money object)
    private /*string (SUCCESS | FAIL | CLOSED)*/ $status = ''; // 止付订单状态
    private $successTime;      // 交易成功时间 (LocalDateTime object)

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

    public function getDeviceNo(): string
    {
        return $this->deviceNo;
    }

    public function setDeviceNo(string $deviceNo): void
    {
        $this->deviceNo = $deviceNo;
    }

    public function getMaterialsType(): string
    {
        return $this->materialsType;
    }

    public function setMaterialsType(string $materialsType): void
    {
        $this->materialsType = $materialsType;
    }

    public function getPolicyId(): string
    {
        return $this->policyId;
    }

    public function setPolicyId(string $policyId): void
    {
        $this->policyId = $policyId;
    }

    public function getOrderNo(): string
    {
        return $this->orderNo;
    }

    public function setOrderNo(string $orderNo): void
    {
        $this->orderNo = $orderNo;
    }

    public function getAmount(): ?Money
    {
        return $this->amount;
    }

    public function setAmount(?Money $amount): void
    {
        $this->amount = $amount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getSuccessTime(): ?LocalDateTime
    {
        return $this->successTime;
    }

    public function setSuccessTime(?LocalDateTime $successTime): void
    {
        $this->successTime = $successTime;
    }
}
