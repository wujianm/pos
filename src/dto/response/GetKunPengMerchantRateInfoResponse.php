<?php declare(strict_types=1);

namespace think\pos\dto\response;

class GetKunPengMerchantRateInfoResponse extends PosProviderResponse
{
    /**
     * @var array 鲲鹏支付的商户费率详情列表
     *            每个元素是包含 payTypeViewCode, rateValue, cappingValue 等键的数组
     */
    private $rateDetails = [];

    /**
     * @return array
     */
    public function getRateDetails(): array
    {
        return $this->rateDetails;
    }

    /**
     * @param array $rateDetails
     */
    public function setRateDetails(array $rateDetails): void
    {
        $this->rateDetails = $rateDetails;
    }
}
