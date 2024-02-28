<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained from
 * Adobe.
 */
declare(strict_types=1);

namespace Magento\QuoteGraphQl\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\SalesRule\Api\CouponRepositoryInterface;
use Magento\SalesRule\Api\Data\CouponInterface;
use Magento\SalesRule\Api\Data\RuleDiscountInterface;

class GetDiscounts implements ResetAfterRequestInterface
{
    private array $couponsByCode = [];

    /**
     * @param CouponRepositoryInterface $couponRepository
     * @param SearchCriteriaBuilder $criteriaBuilder
     */
    public function __construct(
        private readonly CouponRepositoryInterface $couponRepository,
        private readonly SearchCriteriaBuilder $criteriaBuilder
    ) {
    }

    /**
     * Get Discount Values
     *
     * @param Quote $quote
     * @param RuleDiscountInterface[]|null $discounts
     * @return array|null
     * @throws LocalizedException
     */
    public function execute(Quote $quote, array $discounts): ?array
    {
        if (empty($discounts)) {
            return null;
        }

        $discountValues = [];
        $coupon = $this->getCoupon($quote);
        foreach ($discounts as $value) {
            $discountData = $value->getDiscountData();
            $discountValues[] = [
                'label' => $value->getRuleLabel() ?: __('Discount'),
                'applied_to' => $discountData->getAppliedTo(),
                'amount' => [
                    'value' => $discountData->getAmount(),
                    'currency' => $quote->getQuoteCurrencyCode()
                ],
                'coupon' => $this->getFormattedCoupon($coupon, (int) $value->getRuleID())
            ];
        }

        return $discountValues;
    }

    /**
     * Get formatted coupon for the rule id
     *
     * @param CouponInterface|null $coupon
     * @param int $ruleId
     * @return array|null
     */
    private function getFormattedCoupon(?CouponInterface $coupon, int $ruleId): ?array
    {
        if ($coupon && $coupon->getRuleId() && $coupon->getRuleId() == $ruleId) {
            return ['code' => $coupon->getCode()];
        }
        return null;
    }

    /**
     * Retrieve coupon data object
     *
     * @param CartInterface $quote
     * @return CouponInterface|null
     * @throws LocalizedException
     */
    private function getCoupon(CartInterface $quote): ?CouponInterface
    {
        $couponCode = $quote->getCouponCode();
        if (!$couponCode) {
            return null;
        }
        if (isset($this->couponsByCode[$couponCode])) {
            return $this->couponsByCode[$couponCode];
        }
        $couponModels = $this->couponRepository->getList(
            $this->criteriaBuilder->addFilter('code', $couponCode)->create()
        )->getItems();
        if (empty($couponModels)) {
            return null;
        }
        $this->couponsByCode[$couponCode] = reset($couponModels);
        return $this->couponsByCode[$couponCode];
    }

    /**
     * @inheritdoc
     */
    public function _resetState(): void
    {
        $this->couponsByCode = [];
    }
}
