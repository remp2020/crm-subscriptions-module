<?php

namespace Crm\SubscriptionsModule\PaymentItem;

use Crm\PaymentsModule\PaymentItem\PaymentItemInterface;
use Crm\PaymentsModule\PaymentItem\PaymentItemTrait;
use Nette\Database\Table\IRow;

class SubscriptionTypePaymentItem implements PaymentItemInterface
{
    use PaymentItemTrait;

    const TYPE = 'subscription_type';

    private $subscriptionTypeId;

    public function __construct(
        int $subscriptionTypeId,
        string $name,
        float $price,
        int $vat,
        int $count = 1
    ) {
        $this->subscriptionTypeId = $subscriptionTypeId;
        $this->name = $name;
        $this->price = $price;
        $this->vat = $vat;
        $this->count = $count;
    }

    /**
     * @param IRow $subscriptionType
     * @param int $count
     * @return static[]
     */
    public static function fromSubscriptionType(IRow $subscriptionType, int $count = 1): array
    {
        $rows = [];
        foreach ($subscriptionType->related('subscription_type_items')->order('sorting') as $item) {
            $rows[] = static::fromSubscriptionTypeItem($item, $count);
        }
        return $rows;
    }

    /**
     * @param IRow $subscriptionTypeItem
     * @param int $count
     * @return static
     */
    public static function fromSubscriptionTypeItem(IRow $subscriptionTypeItem, int $count = 1)
    {
        return new SubscriptionTypePaymentItem(
            $subscriptionTypeItem->subscription_type_id,
            $subscriptionTypeItem->name,
            $subscriptionTypeItem->amount,
            $subscriptionTypeItem->vat,
            $count
        );
    }

    /**
     * @param IRow $paymentItem
     * @return SubscriptionTypePaymentItem
     * @throws \Exception Thrown if payment item isn't `subscription_type` payment item type.
     */
    public static function fromPaymentItem(IRow $paymentItem)
    {
        if ($paymentItem->type != self::TYPE) {
            throw new \Exception("Can not load SubscriptionTypePaymentItem from payment item of different type. Got [{$paymentItem->type}]");
        }
        return new SubscriptionTypePaymentItem(
            $paymentItem->subscription_type_id,
            $paymentItem->name,
            $paymentItem->amount,
            $paymentItem->vat,
            $paymentItem->count
        );
    }

    public function data(): array
    {
        return [
            'subscription_type_id' => $this->subscriptionTypeId,
        ];
    }

    public function forceName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function forceVat(int $vat): self
    {
        $this->vat = $vat;
        return $this;
    }

    public function forcePrice(float $price): self
    {
        $this->price = $price;
        return $this;
    }
}
